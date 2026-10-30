<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\DbInterface;
use App\Database\OracleDb;

final class OracleMail
{
    private const MAIL_PACKAGE = 'XX_RJD_DISLOCATION_NEW_PKG';

    public function __construct(private DbInterface $db, private array $config)
    {
    }

    public function sendSystem(array $recipients, string $subject, string $text, array $files): void
    {
        $prefix = rtrim(trim((string) ($this->config['system_mail_subject'] ?? '')), '. ');
        $subject = ltrim(trim($subject), '. ');
        if ($prefix !== '') {
            $subject = $prefix . ($subject !== '' ? '. ' . $subject : '');
        }
        $this->send($recipients, $subject, $text, $files);
    }

    public function send(array $recipients, string $subject, string $text, array $files): void
    {
        if (!$this->db instanceof OracleDb) {
            throw new \RuntimeException('Отправка писем доступна только при подключении к Oracle');
        }
        if (!$recipients) {
            throw new \RuntimeException('Не указаны получатели письма');
        }
        $sender = trim((string) ($this->config['report_mail_sender'] ?? ''));
        if (!filter_var($sender, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Некорректный report_mail_sender в конфигурации');
        }
        $package = self::MAIL_PACKAGE;

        foreach ($recipients as $recipient) {
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Некорректный e-mail получателя: ' . $recipient);
            }
        }

        $connection = $this->db->connection();
        $this->execute($connection, "BEGIN {$package}.mail_start; END;");

        $blobs = [];
        foreach ($files as $file) {
            if (!is_file($file) || !is_readable($file)) {
                throw new \RuntimeException('Не удалось прочитать вложение: ' . $file);
            }
            $contents = file_get_contents($file);
            if ($contents === false) {
                throw new \RuntimeException('Не удалось прочитать вложение: ' . $file);
            }

            $blob = oci_new_descriptor($connection, OCI_D_LOB);
            if (!$blob || !$blob->writeTemporary($contents, OCI_TEMP_BLOB)) {
                throw new \RuntimeException('Не удалось подготовить вложение: ' . basename($file));
            }
            $fileName = mb_substr(basename($file), 0, 240);
            $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            $statement = oci_parse(
                $connection,
                "BEGIN {$package}.mail_add_file(
                    p_file => :file_data,
                    p_file_name => :file_name,
                    p_mime_type => :file_mime
                ); END;"
            );
            if (!$statement) {
                $blob->free();
                $this->oracleError($connection, 'Не удалось подготовить добавление вложения');
            }
            oci_bind_by_name($statement, ':file_data', $blob, -1, SQLT_BLOB);
            oci_bind_by_name($statement, ':file_name', $fileName, 240);
            oci_bind_by_name($statement, ':file_mime', $mime, 240);
            $this->runStatement($statement, 'Не удалось добавить вложение ' . $fileName);
            oci_free_statement($statement);
            // Пакет хранит указатель на временный BLOB, поэтому освобождаем его только после отправки.
            $blobs[] = $blob;
        }

        foreach ($recipients as $recipient) {
            $body = oci_new_descriptor($connection, OCI_D_LOB);
            if (!$body || !$body->writeTemporary($text, OCI_TEMP_CLOB)) {
                throw new \RuntimeException('Не удалось подготовить текст письма');
            }
            $statement = oci_parse(
                $connection,
                "BEGIN {$package}.mail_send(
                    p_sender => :sender,
                    p_recipient => :recipient,
                    p_subject => :subject,
                    p_text => :body
                ); END;"
            );
            if (!$statement) {
                $body->free();
                $this->oracleError($connection, 'Не удалось подготовить вызов send_mail');
            }
            $mailSubject = mb_substr($subject, 0, 250);
            oci_bind_by_name($statement, ':sender', $sender, 320);
            oci_bind_by_name($statement, ':recipient', $recipient, 320);
            oci_bind_by_name($statement, ':subject', $mailSubject, 250);
            oci_bind_by_name($statement, ':body', $body, -1, SQLT_CLOB);
            $this->runStatement($statement, 'Не удалось отправить письмо получателю ' . $recipient);
            oci_free_statement($statement);
            $body->free();
        }

        foreach ($blobs as $blob) {
            $blob->free();
        }
    }

    private function execute($connection, string $sql): void
    {
        $statement = oci_parse($connection, $sql);
        if (!$statement) {
            $this->oracleError($connection, 'Не удалось подготовить вызов Oracle-пакета');
        }
        $this->runStatement($statement, 'Не удалось инициализировать список вложений');
        oci_free_statement($statement);
    }

    private function runStatement($statement, string $message): void
    {
        if (!@oci_execute($statement, OCI_NO_AUTO_COMMIT)) {
            $error = oci_error($statement);
            throw new \RuntimeException($message . ': ' . trim((string) ($error['message'] ?? 'ошибка Oracle')));
        }
    }

    private function oracleError($connection, string $message): never
    {
        $error = oci_error($connection);
        throw new \RuntimeException($message . ': ' . trim((string) ($error['message'] ?? 'ошибка Oracle')));
    }
}
