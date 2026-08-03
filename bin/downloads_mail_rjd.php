<?php
/**
 * fetch_excel.php
 * Скачивает последний подходящий Excel-файл за каждый день за последние 2 недели
 * из IMAP-почты (без расширения php-imap, через SSL-сокет).
 * Читает только письма с флагом \Flagged, которые ранее были обработаны
 * Java-программой. Состояние писем не изменяет.
 *
 * Настройки: bin/settings.json
 * Запуск:    php downloads_mail_rjd.php
 * В браузере: php -S 127.0.0.1:8090 -t bin
 *             http://127.0.0.1:8090/downloads_mail_rjd.php
 */

define('EXCEL_EXTS', ['xlsx', 'xls', 'xlsm', 'xltx', 'xltm']);
define('OUTPUT_DIR', __DIR__ . '/');
define('DAYS_BACK', 14);

// ═══════════════════════════════════════════════════════════════════════════════
//  IMAP-клиент на чистых сокетах (не требует php-imap)
// ═══════════════════════════════════════════════════════════════════════════════

class RawIMAP
{
    private $socket = null;
    private int $tag = 0;

    public function connect(string $host, int $port, string $encryption = 'ssl', int $timeout = 30): bool
    {
        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]
        ]);
        $scheme = strtolower($encryption) === 'none' ? 'tcp' : 'ssl';
        $this->socket = @stream_socket_client(
            "{$scheme}://{$host}:{$port}",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $ctx
        );
        if (!$this->socket) {
            echo "[ERROR] Соединение не установлено: {$errstr} ({$errno})\n";
            return false;
        }
        stream_set_timeout($this->socket, $timeout);
        $greeting = $this->readLine();
        if ($greeting === '') {
            $meta = stream_get_meta_data($this->socket);
            $state = !empty($meta['timed_out']) ? 'тайм-аут ожидания'
                : (!empty($meta['eof']) ? 'сервер закрыл соединение' : 'пустой ответ');
            echo "[ERROR] Сервер не прислал приветствие IMAP ({$state}).\n";
            fclose($this->socket);
            $this->socket = null;
            return false;
        }
        echo "[INFO] Ответ сервера: {$greeting}\n";
        if (!preg_match('/^\*\s+(OK|PREAUTH)\b/i', $greeting)) {
            echo "[ERROR] На указанном порту получен не IMAP-ответ.\n";
            fclose($this->socket);
            $this->socket = null;
            return false;
        }
        return true;
    }

    public function login(string $user, string $pass): bool
    {
        $capabilities = $this->capabilities();
        echo "[INFO] IMAP-возможности: "
            . ($capabilities ? implode(' ', $capabilities) : 'не получены') . "\n";

        // JavaMail предпочитает SASL-механизмы, объявленные сервером.
        if (in_array('AUTH=PLAIN', $capabilities, true)) {
            $lines = $this->authenticatePlain($user, $pass);
            if ($this->isOK($lines)) {
                return true;
            }
            $this->printAuthFailure('AUTHENTICATE PLAIN', $lines);
            return false;
        }

        $lines = $this->cmd('LOGIN ' . $this->q($user) . ' ' . $this->q($pass));
        if ($this->isOK($lines)) {
            return true;
        }
        $this->printAuthFailure('LOGIN', $lines);
        return false;
    }

    private function capabilities(): array
    {
        $result = [];
        foreach ($this->cmd('CAPABILITY') as $line) {
            if (preg_match('/^\* CAPABILITY\s+(.+)$/i', $line, $m)) {
                $result = preg_split('/\s+/', strtoupper(trim($m[1]))) ?: [];
                break;
            }
        }
        return $result;
    }

    private function authenticatePlain(string $user, string $pass): array
    {
        $tag = $this->nextTag();
        fwrite($this->socket, "{$tag} AUTHENTICATE PLAIN\r\n");
        $lines = [];
        while (!feof($this->socket)) {
            $line = $this->readLine();
            $lines[] = $line;
            if (str_starts_with($line, '+')) {
                fwrite($this->socket, base64_encode("\0{$user}\0{$pass}") . "\r\n");
                continue;
            }
            if (str_starts_with($line, $tag . ' ')) {
                break;
            }
        }
        return $lines;
    }

    private function printAuthFailure(string $mechanism, array $lines): void
    {
        foreach ($lines as $line) {
            if (preg_match('/^T\d{5}\s+(NO|BAD)\s+(.+)$/i', $line, $m)) {
                echo "[ERROR] {$mechanism}: {$m[1]} {$m[2]}\n";
                return;
            }
        }
        $safeLines = array_values(array_filter($lines, static fn($line) => trim($line) !== ''));
        if ($safeLines) {
            echo "[ERROR] {$mechanism}: ответ сервера: " . implode(' | ', $safeLines) . "\n";
            return;
        }
        $meta = is_resource($this->socket) ? stream_get_meta_data($this->socket) : [];
        $state = !empty($meta['timed_out']) ? 'тайм-аут'
            : (!empty($meta['eof']) ? 'соединение закрыто сервером' : 'пустой ответ');
        echo "[ERROR] {$mechanism}: сервер не вернул описание ошибки ({$state}).\n";
    }

    public function select(string $mailbox): bool
    {
        return $this->isOK($this->cmd('SELECT ' . $this->q($mailbox)));
    }

    /** Возвращает номера сообщений по критерию поиска */
    public function search(string $criteria): array
    {
        foreach ($this->cmd("SEARCH $criteria") as $line) {
            if (preg_match('/^\* SEARCH\s+(.+)$/i', $line, $m)) {
                return array_map('intval', preg_split('/\s+/', trim($m[1])));
            }
        }
        return [];
    }

    /** Unix-timestamp внутренней даты сообщения */
    public function internalDate(int $num): int
    {
        foreach ($this->cmd("FETCH {$num} INTERNALDATE") as $line) {
            if (preg_match('/INTERNALDATE\s+"([^"]+)"/i', $line, $m)) {
                return strtotime($m[1]) ?: 0;
            }
        }
        return 0;
    }

    /** Загружает полное RFC-822 сообщение */
    public function fetchRaw(int $num): string
    {
        $tag = $this->nextTag();
        fwrite($this->socket, "{$tag} FETCH {$num} BODY.PEEK[]\r\n");

        $body = '';
        $needBytes = -1;

        while (!feof($this->socket)) {
            if ($needBytes > 0) {
                $chunk = fread($this->socket, min($needBytes, 65536));
                if ($chunk === false)
                    break;
                $body .= $chunk;
                $needBytes -= strlen($chunk);
            } elseif ($needBytes === 0) {
                // дочитываем до закрывающей строки тега
                $line = $this->readLine();
                if (str_starts_with($line, $tag . ' '))
                    break;
            } else {
                $line = $this->readLine();
                if (preg_match('/\{(\d+)\}$/', $line, $m)) {
                    $needBytes = (int) $m[1];
                } elseif (str_starts_with($line, $tag . ' ')) {
                    break;
                }
            }
        }
        return $body;
    }

    public function logout(): void
    {
        if ($this->socket) {
            try {
                $this->cmd('LOGOUT');
            } catch (\Throwable $e) {
            }
            fclose($this->socket);
            $this->socket = null;
        }
    }

    // ── внутренние ────────────────────────────────────────────────────────────

    private function cmd(string $command): array
    {
        $tag = $this->nextTag();
        fwrite($this->socket, "{$tag} {$command}\r\n");
        $lines = [];
        while (!feof($this->socket)) {
            $line = $this->readLine();
            $lines[] = $line;
            if (str_starts_with($line, $tag . ' '))
                break;
        }
        return $lines;
    }

    private function readLine(): string
    {
        $line = fgets($this->socket, 8192);
        return $line !== false ? rtrim($line, "\r\n") : '';
    }

    private function nextTag(): string
    {
        return 'T' . str_pad(++$this->tag, 5, '0', STR_PAD_LEFT);
    }

    private function isOK(array $lines): bool
    {
        foreach ($lines as $l) {
            if (preg_match('/^T\d{5} OK\b/i', $l))
                return true;
        }
        return false;
    }

    private function q(string $s): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $s) . '"';
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
//  Microsoft Exchange Web Services
// ═══════════════════════════════════════════════════════════════════════════════

class ExchangeEws
{
    private string $url;
    private string $username;
    private string $password;

    public function __construct(array $cfg)
    {
        $this->url = trim((string) ($cfg['ews_url'] ?? ''));
        $domain = trim((string) ($cfg['domain'] ?? ''));
        $user = trim((string) ($cfg['username'] ?? ''));
        $this->username = $domain !== '' ? $domain . '\\' . $user : $user;
        $this->password = (string) ($cfg['password'] ?? '');
    }

    public function findProcessedMessages(int $daysBack, string $sender, string $subjectPart): array
    {
        $from = gmdate('Y-m-d\TH:i:s\Z', strtotime("-{$daysBack} days"));
        $xml = $this->soap(
            '<m:FindItem Traversal="Shallow">'
            . '<m:ItemShape><t:BaseShape>IdOnly</t:BaseShape><t:AdditionalProperties>'
            . '<t:FieldURI FieldURI="item:Subject"/>'
            . '<t:FieldURI FieldURI="item:DateTimeReceived"/>'
            . '<t:FieldURI FieldURI="message:From"/>'
            . '<t:FieldURI FieldURI="item:HasAttachments"/>'
            . '<t:ExtendedFieldURI PropertyTag="0x1090" PropertyType="Integer"/>'
            . '</t:AdditionalProperties></m:ItemShape>'
            . '<m:IndexedPageItemView MaxEntriesReturned="1000" Offset="0" BasePoint="Beginning"/>'
            . '<m:Restriction><t:IsGreaterThanOrEqualTo>'
            . '<t:FieldURI FieldURI="item:DateTimeReceived"/>'
            . '<t:FieldURIOrConstant><t:Constant Value="' . htmlspecialchars($from, ENT_XML1) . '"/>'
            . '</t:FieldURIOrConstant></t:IsGreaterThanOrEqualTo></m:Restriction>'
            . '<m:SortOrder><t:FieldOrder Order="Descending">'
            . '<t:FieldURI FieldURI="item:DateTimeReceived"/>'
            . '</t:FieldOrder></m:SortOrder>'
            . '<m:ParentFolderIds><t:DistinguishedFolderId Id="inbox"/></m:ParentFolderIds>'
            . '</m:FindItem>'
        );

        $xp = new DOMXPath($xml);
        $messages = [];
        foreach ($xp->query('//*[local-name()="Message"]') as $node) {
            $idNode = $xp->query('.//*[local-name()="ItemId"]', $node)->item(0);
            $subject = trim((string) $xp->evaluate('string(.//*[local-name()="Subject"])', $node));
            $received = trim((string) $xp->evaluate('string(.//*[local-name()="DateTimeReceived"])', $node));
            $email = trim((string) $xp->evaluate(
                'string(.//*[local-name()="From"]//*[local-name()="EmailAddress"])',
                $node
            ));
            $flagStatus = trim((string) $xp->evaluate(
                'string(.//*[local-name()="ExtendedProperty"]/*[local-name()="Value"])',
                $node
            ));
            $hasAttachments = strtolower(trim((string) $xp->evaluate(
                'string(.//*[local-name()="HasAttachments"])',
                $node
            ))) === 'true';

            if (!$idNode instanceof DOMElement
                || mb_strtolower($email, 'UTF-8') !== mb_strtolower($sender, 'UTF-8')
                || !str_contains(mb_strtolower($subject, 'UTF-8'), mb_strtolower($subjectPart, 'UTF-8'))
                || $flagStatus !== '2'
                || !$hasAttachments) {
                continue;
            }
            $messages[] = [
                'id' => $idNode->getAttribute('Id'),
                'change_key' => $idNode->getAttribute('ChangeKey'),
                'subject' => $subject,
                'ts' => strtotime($received) ?: 0,
            ];
        }
        return $messages;
    }

    public function firstXlsxAttachment(array $message): ?array
    {
        $item = '<t:ItemId Id="' . htmlspecialchars($message['id'], ENT_XML1) . '"'
            . ' ChangeKey="' . htmlspecialchars($message['change_key'], ENT_XML1) . '"/>';
        $xml = $this->soap(
            '<m:GetItem><m:ItemShape><t:BaseShape>IdOnly</t:BaseShape>'
            . '<t:IncludeMimeContent>false</t:IncludeMimeContent>'
            . '<t:AdditionalProperties><t:FieldURI FieldURI="item:Attachments"/>'
            . '</t:AdditionalProperties></m:ItemShape><m:ItemIds>'
            . $item . '</m:ItemIds></m:GetItem>'
        );
        $xp = new DOMXPath($xml);
        foreach ($xp->query('//*[local-name()="FileAttachment"]') as $attachment) {
            $name = trim((string) $xp->evaluate('string(./*[local-name()="Name"])', $attachment));
            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'xlsx') {
                continue;
            }
            $idNode = $xp->query('./*[local-name()="AttachmentId"]', $attachment)->item(0);
            if (!$idNode instanceof DOMElement) {
                continue;
            }
            $attachmentId = htmlspecialchars($idNode->getAttribute('Id'), ENT_XML1);
            $attachmentXml = $this->soap(
                '<m:GetAttachment><m:AttachmentShape/>'
                . '<m:AttachmentIds><t:AttachmentId Id="' . $attachmentId . '"/>'
                . '</m:AttachmentIds></m:GetAttachment>'
            );
            $attachmentXp = new DOMXPath($attachmentXml);
            $content = trim((string) $attachmentXp->evaluate(
                'string(//*[local-name()="FileAttachment"]/*[local-name()="Content"])'
            ));
            $data = base64_decode($content, true);
            if ($data !== false) {
                return ['name' => $name, 'data' => $data];
            }
        }
        return null;
    }

    private function soap(string $body): DOMDocument
    {
        if ($this->url === '') {
            throw new RuntimeException('Не указан ews_url');
        }
        $request = '<?xml version="1.0" encoding="utf-8"?>'
            . '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"'
            . ' xmlns:m="http://schemas.microsoft.com/exchange/services/2006/messages"'
            . ' xmlns:t="http://schemas.microsoft.com/exchange/services/2006/types">'
            . '<s:Header><t:RequestServerVersion Version="Exchange2010_SP2"/></s:Header>'
            . '<s:Body>' . $body . '</s:Body></s:Envelope>';

        $curl = curl_init($this->url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $request,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=utf-8'],
            CURLOPT_HTTPAUTH => CURLAUTH_NTLM,
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT => 120,
        ]);
        $response = curl_exec($curl);
        $error = curl_error($curl);
        $errorNumber = curl_errno($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($status >= 400) {
            $hint = match ($status) {
                401 => 'не приняты домен, имя пользователя или пароль',
                403 => 'учётной записи запрещён доступ к EWS',
                404 => 'адрес EWS не найден',
                default => 'ошибка HTTP',
            };
            throw new RuntimeException("Exchange вернул HTTP {$status}: {$hint}");
        }
        if ($response === false) {
            throw new RuntimeException(
                'Exchange: ошибка cURL ' . $errorNumber . ($error ? " — {$error}" : '')
            );
        }
        if ($response === '') {
            throw new RuntimeException("Exchange вернул пустой ответ (HTTP {$status})");
        }

        $document = new DOMDocument();
        if (!@$document->loadXML($response)) {
            throw new RuntimeException('Exchange вернул некорректный XML');
        }
        $xp = new DOMXPath($document);
        $responseCode = trim((string) $xp->evaluate('string(//*[local-name()="ResponseCode"][1])'));
        if ($responseCode !== '' && $responseCode !== 'NoError') {
            $message = trim((string) $xp->evaluate('string(//*[local-name()="MessageText"][1])'));
            throw new RuntimeException("Exchange: {$responseCode}" . ($message ? " — {$message}" : ''));
        }
        return $document;
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
//  Разбор MIME-сообщения
// ═══════════════════════════════════════════════════════════════════════════════

function mime_headers(string $raw): array
{
    $headers = [];
    // Раскрываем folded headers
    $raw = preg_replace("/\r\n([ \t])/", '$1', $raw);
    $raw = preg_replace("/\n([ \t])/", '$1', $raw);
    foreach (preg_split("/\r?\n/", $raw) as $line) {
        $pos = strpos($line, ':');
        if ($pos === false)
            continue;
        $key = strtolower(trim(substr($line, 0, $pos)));
        $val = trim(substr($line, $pos + 1));
        $headers[$key] = $val;
    }
    return $headers;
}

function mime_decode_word(string $encoded): string
{
    // =?charset?B/Q?text?=
    return preg_replace_callback(
        '/=\?([^?]+)\?([BbQq])\?([^?]+)\?=/',
        function ($m) {
            $charset = $m[1];
            $text = strtoupper($m[2]) === 'B'
                ? base64_decode($m[3])
                : quoted_printable_decode(str_replace('_', ' ', $m[3]));
            return mb_convert_encoding($text, 'UTF-8', $charset);
        },
        $encoded
    );
}

/**
 * Рекурсивно обходит MIME-части, возвращает список Excel-вложений:
 * [['name' => string, 'data' => string], ...]
 */
function mime_excel_attachments(string $raw): array
{
    // Разделяем заголовки и тело
    foreach (["\r\n\r\n", "\n\n"] as $sep) {
        $pos = strpos($raw, $sep);
        if ($pos !== false) {
            $hdr = substr($raw, 0, $pos);
            $body = substr($raw, $pos + strlen($sep));
            break;
        }
    }
    if (!isset($hdr))
        return [];

    $headers = mime_headers($hdr);
    $ct = $headers['content-type'] ?? 'text/plain';

    // Multipart?
    if (preg_match('/^multipart\//i', $ct)) {
        if (!preg_match('/boundary="?([^";\s]+)"?/i', $ct, $bm))
            return [];
        $boundary = $bm[1];
        $results = [];
        // Разбиваем по границе
        $parts = preg_split('/(?:\r?\n)?--' . preg_quote($boundary, '/') . '(?:--)?(?:\r\n|\n|$)/m', $body);
        foreach (array_slice($parts, 1) as $part) {  // первый элемент — преамбула
            $results = array_merge($results, mime_excel_attachments(ltrim($part, "\r\n")));
        }
        return $results;
    }

    // Одиночная часть — проверяем: это вложение?
    $disp = $headers['content-disposition'] ?? '';
    $name = '';

    // Имя из Content-Disposition
    if (preg_match('/filename\*?=(?:"([^"]+)"|([^;\s]+))/i', $disp, $nm)) {
        $name = mime_decode_word($nm[1] ?: $nm[2]);
    }
    // Имя из Content-Type (name=...)
    if (!$name && preg_match('/name="?([^";]+)"?/i', $ct, $nm)) {
        $name = mime_decode_word($nm[1]);
    }

    if (!$name)
        return [];

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, EXCEL_EXTS, true))
        return [];

    // Декодирование содержимого
    $encoding = strtolower($headers['content-transfer-encoding'] ?? '');
    $data = $body;
    if ($encoding === 'base64') {
        $data = base64_decode(preg_replace('/\s+/', '', $body));
    } elseif ($encoding === 'quoted-printable') {
        $data = quoted_printable_decode($body);
    }

    return [['name' => trim($name), 'data' => $data]];
}

// ═══════════════════════════════════════════════════════════════════════════════
//  settings.json
// ═══════════════════════════════════════════════════════════════════════════════

function load_settings(): array
{
    // Сначала ищем рядом со скриптом
    $local = __DIR__ . '/settings.json';
    if (file_exists($local)) {
        echo "[INFO] Настройки: $local\n";
        return json_decode(file_get_contents($local), true) ?: [];
    }
    foreach (['APPDATA', 'LOCALAPPDATA', 'HOME'] as $env) {
        $base = getenv($env);
        if (!$base)
            continue;
        $path = 'settings.json';
        if (file_exists($path)) {
            echo "[INFO] Настройки: $path\n";
            return json_decode(file_get_contents($path), true) ?: [];
        }
    }
    echo "[WARN] settings.json не найден.\n";
    return [];
}

function get_imap_cfg(array $s, string $tab): array
{
    $def = [
        'enabled' => false,
        'server' => '',
        'port' => 993,
        'encryption' => 'ssl',
        'username' => '',
        'password' => '',
        'mailbox' => 'INBOX',
        'sender_filter' => '',
        'subject_filter' => '',
        'subject_equals' => '',
        'attachment_name_contains' => '',
        'attachment_name_equals' => '',
    ];
    $key = $tab === 'approach' ? 'imap_approach' : 'imap_departure';
    return array_merge($def, $s['imap'] ?? [], $s[$key] ?? []);
}

/**
 * Для ручной загрузки используется одна папка «Входящие».
 * Данные подключения и фильтры берём из единственной секции imap.
 */
function get_rjd_inbox_cfg(array $settings): array
{
    $cfg = get_imap_cfg($settings, 'approach');
    $cfg['enabled'] = true;
    $cfg['mailbox'] = 'INBOX';
    $cfg['sender_filter'] = 'cargolk@gvc.rzd.ru';
    $cfg['subject_filter'] = 'Отчёт слежения Дислокация ТУ';
    $cfg['subject_equals'] = '';
    $cfg['attachment_name_contains'] = '.xlsx';
    $cfg['attachment_name_equals'] = '';
    return $cfg;
}

// ═══════════════════════════════════════════════════════════════════════════════
//  Сохранение файла
// ═══════════════════════════════════════════════════════════════════════════════

function safe_name(string $n): string
{
    return preg_replace('/[^\w.\-]/u', '_', $n) ?: 'file.xlsx';
}

function save_file(string $tab, string $day, string $orig_name, string $data): ?string
{
    $dest = OUTPUT_DIR . "{$tab}_{$day}_" . safe_name($orig_name);
    $existed = file_exists($dest);
    $written = file_put_contents($dest, $data);
    if ($written === false || $written !== strlen($data)) {
        echo "  [ERROR] Не удалось полностью записать: " . basename($dest) . "\n";
        return null;
    }
    echo $existed ? "  [UPDATE] " : "  [OK]   ";
    echo basename($dest) . " (" . round(strlen($data) / 1024) . " KB)\n";
    return $dest;
}

/** Импортирует файл тем же кодом, который обслуживает форму /import. */
function import_saved_file(string $path): array
{
    static $controller = null;
    if ($controller === null) {
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        $configFile = dirname(__DIR__) . '/src/Config.php';
        if (!is_file($autoload) || !is_file($configFile)) {
            throw new RuntimeException('Не найдены файлы приложения для импорта в БД');
        }
        require_once $autoload;
        $config = require $configFile;
        $db = \App\Database\DbFactory::create($config);
        $organization = $db->fetchOne(
            'SELECT id FROM xx_rjd_organizations WHERE code = :code AND is_active = 1',
            ['code' => 'MTF']
        );
        if (!$organization) {
            throw new RuntimeException('Организация MTF не найдена или отключена');
        }
        $organizations = new \App\Services\OrganizationService(
            $db,
            (int) $organization['id']
        );
        $controller = new \App\Controllers\ImportController($db, $config, $organizations);
    }
    return $controller->importLocalFile($path);
}

function raw_header(string $raw, string $name): string
{
    $headerEnd = strpos($raw, "\r\n\r\n");
    if ($headerEnd === false) {
        $headerEnd = strpos($raw, "\n\n");
    }
    $headers = mime_headers($headerEnd === false ? $raw : substr($raw, 0, $headerEnd));
    return mime_decode_word($headers[strtolower($name)] ?? '');
}

function address_matches(string $from, string $filter): bool
{
    if ($filter === '') {
        return true;
    }
    if (preg_match('/<([^>]+)>/', $from, $m)) {
        $from = $m[1];
    }
    return strtolower(trim($from, " \t\n\r\0\x0B\"'")) === strtolower($filter);
}

// ═══════════════════════════════════════════════════════════════════════════════
//  Загрузка из IMAP
// ═══════════════════════════════════════════════════════════════════════════════

function fetch_from_imap(array $cfg, string $tab): void
{
    $server = trim($cfg['server'] ?? '');
    $port = (int) ($cfg['port'] ?? 993);
    $encryption = strtolower(trim((string) ($cfg['encryption'] ?? 'ssl')));
    $username = trim($cfg['username'] ?? '');
    $password = $cfg['password'] ?? '';
    $mailbox = trim($cfg['mailbox'] ?? '') ?: 'INBOX';

    if (!$server || !$username || !$password) {
        echo "[SKIP] Не заполнены server/username/password.\n";
        return;
    }

    $lower = static fn(string $value): string => mb_strtolower(trim($value), 'UTF-8');
    $sender_filter = $lower((string) ($cfg['sender_filter'] ?? ''));
    $subject_filter = $lower((string) ($cfg['subject_filter'] ?? ''));
    $subject_equals = $lower((string) ($cfg['subject_equals'] ?? ''));
    $attachment_name_contains = $lower((string) ($cfg['attachment_name_contains'] ?? ''));
    $attachment_name_equals = $lower((string) ($cfg['attachment_name_equals'] ?? ''));

    echo "[INFO] Подключаюсь: {$server}:{$port} / {$mailbox} / {$encryption}\n";

    $imap = new RawIMAP();
    if (!$imap->connect($server, $port, $encryption))
        return;
    if (!$imap->login($username, $password)) {
        echo "[ERROR] Ошибка авторизации.\n";
        $imap->logout();
        return;
    }
    if (!$imap->select($mailbox)) {
        echo "[ERROR] Не удалось открыть папку «{$mailbox}».\n";
        $imap->logout();
        return;
    }

    $since = date('d-M-Y', strtotime('-' . DAYS_BACK . ' days'));
    $nums = $imap->search("FLAGGED SINCE \"{$since}\"");
    echo "[INFO] Обработанных писем за " . DAYS_BACK . " дней: " . count($nums) . "\n";

    if (!$nums) {
        $imap->logout();
        return;
    }

    // Сначала полностью проверяем письмо и вложение. Только после этого
    // группируем по дню, иначе более позднее постороннее письмо скроет нужное.
    $by_day = [];
    foreach ($nums as $num) {
        $ts = $imap->internalDate($num);
        if ($ts <= 0) {
            continue;
        }
        $raw = $imap->fetchRaw($num);
        $from = raw_header($raw, 'from');
        $subject = $lower(raw_header($raw, 'subject'));
        if (!address_matches($from, $sender_filter)) {
            continue;
        }
        if ($subject_equals && $subject !== $subject_equals) {
            continue;
        }
        if ($subject_filter && !str_contains($subject, $subject_filter)) {
            continue;
        }

        $selectedAttachment = null;
        foreach (mime_excel_attachments($raw) as $attachment) {
            $attachmentNameLower = $lower($attachment['name']);
            if ($attachment_name_equals && $attachmentNameLower !== $attachment_name_equals) {
                continue;
            }
            if ($attachment_name_contains && !str_contains($attachmentNameLower, $attachment_name_contains)) {
                continue;
            }
            $selectedAttachment = $attachment;
            break;
        }
        if ($selectedAttachment === null) {
            continue;
        }

        $day = date('Y-m-d', $ts);
        $candidate = [
            'num' => $num,
            'ts' => $ts,
            'attachment' => $selectedAttachment,
        ];
        if (!isset($by_day[$day])) {
            $by_day[$day] = ['latest' => $candidate];
        } else {
            if ($ts > $by_day[$day]['latest']['ts']
                || ($ts === $by_day[$day]['latest']['ts']
                    && $num > $by_day[$day]['latest']['num'])) {
                $by_day[$day]['latest'] = $candidate;
            }
        }
    }
    ksort($by_day);
    echo "[INFO] Дней с подходящими файлами: " . count($by_day) . "\n\n";

    foreach ($by_day as $day => $group) {
        echo "  $day  ";
        $latest = $group['latest'];
        $attachment = $latest['attachment'];
        $savedPath = save_file($tab, $day, $attachment['name'], $attachment['data']);
        if ($savedPath === null) {
            continue;
        }
        try {
            $result = import_saved_file($savedPath);
            echo "         Импорт: {$result['rows']} строк; {$result['type']}; {$result['report_dt']}\n";
        } catch (\Throwable $e) {
            echo "         [ERROR] Импорт не выполнен: {$e->getMessage()}\n";
            echo "         Состояние писем не изменено; импорт можно запустить повторно.\n";
            continue;
        }
        echo "         Выбранное обработанное письмо: #{$latest['num']}\n";
    }

    $imap->logout();
}

function fetch_from_exchange(array $cfg, string $tab): void
{
    $sender = trim((string) ($cfg['sender_filter'] ?? ''));
    $subject = trim((string) ($cfg['subject_filter'] ?? ''));
    echo "[INFO] Подключаюсь к Exchange: " . ($cfg['ews_url'] ?? '') . "\n";
    echo "[INFO] Папка: Входящие; домен: " . ($cfg['domain'] ?? '') . "\n";

    try {
        $exchange = new ExchangeEws($cfg);
        $messages = $exchange->findProcessedMessages(DAYS_BACK, $sender, $subject);
        echo "[INFO] Подходящих обработанных писем: " . count($messages) . "\n";

        $byDay = [];
        foreach ($messages as $message) {
            if ($message['ts'] <= 0) {
                continue;
            }
            $day = date('Y-m-d', $message['ts']);
            if (!isset($byDay[$day])) {
                $byDay[$day] = $message;
            }
        }
        ksort($byDay);
        echo "[INFO] Дней с письмами: " . count($byDay) . "\n\n";

        foreach ($byDay as $day => $message) {
            echo "  {$day}  ";
            $attachment = $exchange->firstXlsxAttachment($message);
            if ($attachment === null) {
                echo "[SKIP] В последнем письме нет XLSX-вложения.\n";
                continue;
            }
            $savedPath = save_file($tab, $day, $attachment['name'], $attachment['data']);
            if ($savedPath === null) {
                continue;
            }
            try {
                $result = import_saved_file($savedPath);
                echo "         Импорт: {$result['rows']} строк; {$result['type']}; {$result['report_dt']}\n";
                echo "         Выбранное обработанное письмо: {$message['subject']}\n";
            } catch (\Throwable $e) {
                echo "         [ERROR] Импорт не выполнен: {$e->getMessage()}\n";
            }
        }
    } catch (\Throwable $e) {
        echo "[ERROR] {$e->getMessage()}\n";
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
//  Загрузка из локальной папки
// ═══════════════════════════════════════════════════════════════════════════════

function fetch_from_folder(string $tab): void
{
    $candidates = [__DIR__ . "/$tab", dirname(__DIR__) . "/$tab"];
    $folder = null;
    foreach ($candidates as $c) {
        if (is_dir($c)) {
            $folder = $c;
            break;
        }
    }
    if (!$folder) {
        echo "[SKIP] Папка '$tab' не найдена.\n";
        return;
    }

    echo "[INFO] Читаю папку: $folder\n";
    $files = [];
    foreach (EXCEL_EXTS as $ext) {
        foreach (glob("$folder/*.$ext") ?: [] as $f) {
            $files[] = ['path' => $f, 'mtime' => filemtime($f)];
        }
    }
    if (!$files) {
        echo "[SKIP] Нет Excel-файлов.\n";
        return;
    }

    $by_day = [];
    $cutoff = strtotime('-' . DAYS_BACK . ' days');
    foreach ($files as $f) {
        if ($f['mtime'] < $cutoff)
            continue;
        $day = date('Y-m-d', $f['mtime']);
        if (!isset($by_day[$day]) || $f['mtime'] > $by_day[$day]['mtime']) {
            $by_day[$day] = $f;
        }
    }
    ksort($by_day);
    echo "[INFO] Дней с файлами: " . count($by_day) . "\n\n";

    foreach ($by_day as $day => $f) {
        echo "  $day  ";
        $dest = OUTPUT_DIR . "{$tab}_{$day}_" . safe_name(basename($f['path']));
        if (file_exists($dest)) {
            echo "[SKIP] Уже есть: " . basename($dest) . "\n";
            continue;
        }
        copy($f['path'], $dest);
        echo "[OK]   " . basename($dest) . "\n";
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
//  Точка входа
// ═══════════════════════════════════════════════════════════════════════════════

function run_download(): void
{
    echo "=== downloads_mail_rjd.php (" . DAYS_BACK . " дней) ===\n";
    echo "Сохранение в: " . OUTPUT_DIR . "\n\n";

    $settings = load_settings();
    echo "━━━ Входящие / отчёты РЖД ━━━\n";
    $cfg = get_rjd_inbox_cfg($settings);
    if (strtolower((string) ($cfg['protocol'] ?? 'imap')) === 'exchange') {
        fetch_from_exchange($cfg, 'rjd');
    } else {
        fetch_from_imap($cfg, 'rjd');
    }
    echo "\n";
    echo "=== Готово ===\n";
}

if (PHP_SAPI === 'cli') {
    run_download();
    exit;
}

$output = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_start();
    try {
        run_download();
    } catch (\Throwable $e) {
        echo "[ERROR] " . $e->getMessage() . "\n";
    }
    $output = (string) ob_get_clean();
}
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Последние файлы РЖД за день</title>
  <style>
    body{margin:0;background:#f3f5f7;color:#20252b;font:15px/1.5 Arial,sans-serif}
    main{max-width:900px;margin:40px auto;padding:0 20px}
    section{background:#fff;border:1px solid #dfe3e8;border-radius:10px;padding:26px;box-shadow:0 3px 14px #0000000d}
    h1{margin:0 0 10px;font-size:24px} p{color:#606a75}
    button{border:0;border-radius:6px;background:#d71920;color:#fff;padding:11px 18px;font-weight:700;cursor:pointer}
    pre{margin-top:22px;padding:18px;overflow:auto;background:#15191e;color:#dce3ea;border-radius:7px;white-space:pre-wrap}
  </style>
</head>
<body><main><section>
  <h1>Загрузка последних файлов РЖД</h1>
  <p>Проверяется папка «Входящие»: уже обработанные письма от cargolk@gvc.rzd.ru, тема которых содержит «Отчёт слежения Дислокация ТУ». За каждый из последних <?= DAYS_BACK ?> дней выбирается только самый поздний XLSX. Состояние писем не изменяется.</p>
  <form method="post"><button type="submit">Проверить почту и загрузить</button></form>
  <?php if ($output !== ''): ?><pre><?= htmlspecialchars($output, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre><?php endif; ?>
</section></main></body>
</html>
