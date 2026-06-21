<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
use App\Services\DislocationExcelExporter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ScheduleController
{
    public function __construct(
        private DbInterface $db,
        private array $config
    ) {}

    /** GET /admin/schedules/list — список расписаний (JSON). */
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rows = $this->db->fetchAll(
            "SELECT id, name, report_type, frequency, run_hour, run_minute,
                    day_of_week, output_dir, is_active,
                    TO_CHAR(last_run_at, 'DD.MM.YYYY HH24:MI') AS last_run_at,
                    TO_CHAR(created_at,  'DD.MM.YYYY HH24:MI') AS created_at
               FROM xx_rjd_report_schedules
              ORDER BY id"
        );
        return $this->json($response, ['schedules' => $rows]);
    }

    /** POST /admin/schedules/save — создать или обновить расписание. */
    public function save(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);

        $id         = isset($body['id']) && $body['id'] !== '' ? (int) $body['id'] : null;
        $name       = trim($body['name'] ?? '');
        $frequency  = $body['frequency'] ?? 'daily';
        $runHour    = (int) ($body['run_hour'] ?? 8);
        $runMinute  = (int) ($body['run_minute'] ?? 0);
        $dayOfWeek  = $frequency === 'weekly' ? (int) ($body['day_of_week'] ?? 1) : null;
        $outputDir  = trim($body['output_dir'] ?? ($this->defaultOutputDir()));
        $isActive   = isset($body['is_active']) ? 1 : 0;

        if ($name === '') {
            return $this->json($response, ['ok' => false, 'error' => 'Название обязательно'], 422);
        }
        if (!in_array($frequency, ['daily', 'hourly', 'weekly'], true)) {
            return $this->json($response, ['ok' => false, 'error' => 'Неверная частота'], 422);
        }

        $userId = (int) ($_SESSION['user']['id'] ?? 0);

        if ($id === null) {
            $this->db->execute(
                "INSERT INTO xx_rjd_report_schedules
                    (name, report_type, frequency, run_hour, run_minute,
                     day_of_week, output_dir, is_active, created_by)
                 VALUES
                    (:name, 'dislocation_summary', :freq, :hour, :min,
                     :dow, :dir, :active, :uid)",
                [
                    'name'   => $name,
                    'freq'   => $frequency,
                    'hour'   => $runHour,
                    'min'    => $runMinute,
                    'dow'    => $dayOfWeek,
                    'dir'    => $outputDir,
                    'active' => $isActive,
                    'uid'    => $userId,
                ]
            );
        } else {
            $this->db->execute(
                "UPDATE xx_rjd_report_schedules
                    SET name = :name, frequency = :freq, run_hour = :hour,
                        run_minute = :min, day_of_week = :dow,
                        output_dir = :dir, is_active = :active
                  WHERE id = :id",
                [
                    'name'   => $name,
                    'freq'   => $frequency,
                    'hour'   => $runHour,
                    'min'    => $runMinute,
                    'dow'    => $dayOfWeek,
                    'dir'    => $outputDir,
                    'active' => $isActive,
                    'id'     => $id,
                ]
            );
        }

        return $this->json($response, ['ok' => true]);
    }

    /** POST /admin/schedules/toggle — переключить is_active. */
    public function toggle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $id   = (int) ($body['id'] ?? 0);

        if ($id <= 0) {
            return $this->json($response, ['ok' => false, 'error' => 'Неверный id'], 422);
        }

        $this->db->execute(
            "UPDATE xx_rjd_report_schedules
                SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END
              WHERE id = :id",
            ['id' => $id]
        );
        return $this->json($response, ['ok' => true]);
    }

    /** POST /admin/schedules/delete — удалить расписание. */
    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $id   = (int) ($body['id'] ?? 0);

        if ($id <= 0) {
            return $this->json($response, ['ok' => false, 'error' => 'Неверный id'], 422);
        }

        $this->db->execute(
            "DELETE FROM xx_rjd_report_schedules WHERE id = :id",
            ['id' => $id]
        );
        return $this->json($response, ['ok' => true]);
    }

    /** POST /admin/schedules/run — запустить вручную. */
    public function run(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body      = (array) ($request->getParsedBody() ?? []);
        $id        = (int) ($body['id'] ?? 0);

        $schedule = $id > 0
            ? $this->db->fetchOne(
                "SELECT * FROM xx_rjd_report_schedules WHERE id = :id",
                ['id' => $id]
              )
            : null;

        $outputDir = $schedule['output_dir'] ?? $this->defaultOutputDir();

        try {
            $exporter = new DislocationExcelExporter($this->db);
            $filePath = $exporter->export($outputDir);

            if ($id > 0) {
                $this->db->execute(
                    "UPDATE xx_rjd_report_schedules SET last_run_at = SYSDATE WHERE id = :id",
                    ['id' => $id]
                );
            }

            return $this->json($response, [
                'ok'   => true,
                'file' => basename($filePath),
                'path' => $filePath,
            ]);
        } catch (\Throwable $e) {
            return $this->json($response, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------------------

    private function defaultOutputDir(): string
    {
        return __DIR__ . '/../../storage/reports';
    }

    private function json(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
