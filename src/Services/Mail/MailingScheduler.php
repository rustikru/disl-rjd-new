<?php
declare(strict_types=1);

namespace App\Services\Mail;

use App\Services\Mail\MailingRun;
use App\Services\Mail\MailingService;

use App\Database\DbInterface;
use App\Logging\ErrorLogger;
use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

final class MailingScheduler
{
    public function __construct(private DbInterface $db, private array $config)
    {
    }

    public function runDue(?DateTimeInterface $dateTime = null): array
    {
        $dateTime ??= new DateTimeImmutable();
        $service = new MailingService($this->db);
        $mailings = $service->dueMailings($dateTime);
        $result = [
            'checked_at' => $dateTime->format(DATE_ATOM),
            'found' => count($mailings),
            'started' => 0,
            'success' => 0,
            'errors' => 0,
            'skipped' => 0,
            'mailings' => [],
        ];

        foreach ($mailings as $mailing) {
            $mailingId = (int) $mailing['id'];
            $runId = $service->claimScheduledRun($mailingId, $dateTime);
            if ($runId === 0) {
                $result['skipped']++;
                continue;
            }

            $result['started']++;
            try {
                $run = (new MailingRun($this->db, $this->config))->run($mailing);
                $message = (string) ($run['message'] ?? 'Файлы сформированы');
                $service->recordRun($mailingId, 'success', $run['directory'], $message);
                $service->finishRun($runId, 'success', $run['directory'], $message);
                $result['success']++;
                $result['mailings'][] = ['id' => $mailingId, 'status' => 'success', 'message' => $message];
            } catch (Throwable $error) {
                $errorId = ErrorLogger::logThrowable($error, [
                    'module' => self::class,
                    'function' => 'runDue',
                    'params' => ['mailing_id' => $mailingId],
                ]);
                $message = 'Ошибка выполнения. Код: ' . $errorId;
                try {
                    $service->recordRun($mailingId, 'error', null, $error->getMessage());
                    if ($runId > 0) {
                        $service->finishRun($runId, 'error', null, $error->getMessage());
                    }
                } catch (Throwable $recordError) {
                    ErrorLogger::logThrowable($recordError, [
                        'module' => self::class,
                        'function' => 'recordRunError',
                        'params' => ['mailing_id' => $mailingId, 'run_id' => $runId],
                    ]);
                }
                $result['errors']++;
                $result['mailings'][] = ['id' => $mailingId, 'status' => 'error', 'message' => $message];
            }
        }

        return $result;
    }
}
