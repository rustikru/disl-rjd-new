<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
use App\Logging\ErrorLogger;
use App\Services\Authorization;
use App\Services\AuditLog;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DataScopesController
{
    public function __construct(private DbInterface $db, private array $config) {}

    public function page(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        $user = $_SESSION['user'] ?? [];
        if (!Authorization::isAdmin($user) || (!Authorization::isSuper($user) && !in_array('data-scopes', $user['allowed_pages'] ?? [], true))) {
            return $response->withStatus(403);
        }
        $basePath = $this->config['base_path'] ?? '';
        $appName = $this->config['app_name'];
        $id = (int) ($args['id'] ?? 0);
        $isForm = isset($args['id']) || str_ends_with($request->getUri()->getPath(), '/new');
        $query = $request->getQueryParams();
        $flashOk = $query['ok'] ?? null;
        $flashErr = $query['err'] ?? null;
        $csrf = $_SESSION['csrf_token'] ?? '';
        $scope = ['id' => 0, 'name' => '', 'description' => '', 'is_active' => 1, 'start_date' => '', 'end_date' => ''];
        $rules = [];
        try {
            if ($request->getMethod() === 'POST') {
                $body = (array) $request->getParsedBody();
                if ($csrf === '' || !hash_equals($csrf, (string) ($body['csrf_token'] ?? ''))) {
                    return $response->withStatus(403);
                }
                $id = (int) ($body['id'] ?? 0);
                if ($id > 0 && !$this->db->fetchOne('SELECT id FROM xx_rjd_data_scopes WHERE id = :id', ['id' => $id])) {
                    throw new \InvalidArgumentException('Область не найдена.');
                }
                $isDelete = str_ends_with($request->getUri()->getPath(), '/delete');
                if (!$isDelete) {
                    $name = trim((string) ($body['name'] ?? ''));
                    $description = trim((string) ($body['description'] ?? ''));
                    if ($name === '' || mb_strlen($name) > 120 || mb_strlen($description) > 500) {
                        throw new \InvalidArgumentException('Укажите название до 120 символов и описание до 500 символов.');
                    }
                    $dates = [];
                    foreach (['start_date', 'end_date'] as $key) {
                        $value = trim((string) ($body[$key] ?? ''));
                        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                        if ($value !== '' && (!$date || $date->format('Y-m-d') !== $value)) {
                            throw new \InvalidArgumentException('Некорректная дата.');
                        }
                        $dates[$key] = $value ?: null;
                    }
                    if ($dates['start_date'] && $dates['end_date'] && $dates['end_date'] < $dates['start_date']) {
                        throw new \InvalidArgumentException('Окончание периода раньше начала.');
                    }
                    if ($this->db->fetchOne('SELECT id FROM xx_rjd_data_scopes WHERE LOWER(name) = LOWER(:name) AND id <> :id', ['name' => $name, 'id' => $id])) {
                        throw new \InvalidArgumentException('Область с таким названием уже существует.');
                    }
                    $rules = (array) ($body['rules'] ?? []);
                    if (!$rules || count($rules) > 100) {
                        throw new \InvalidArgumentException('Добавьте от 1 до 100 правил.');
                    }
                    foreach ($rules as &$rule) {
                        if (!is_array($rule) || !in_array($rule['effect'] ?? '', ['ALLOW', 'DENY'], true)) {
                            throw new \InvalidArgumentException('Некорректное действие правила.');
                        }
                        $rule['organization_id'] = (int) ($rule['organization_id'] ?? 0);
                        if (!$this->db->fetchOne('SELECT id FROM xx_rjd_organizations WHERE id = :id', ['id' => $rule['organization_id']])) {
                            throw new \InvalidArgumentException('Выберите организацию для каждого правила.');
                        }
                        foreach (['cargo_code' => 50, 'station_code' => 20] as $key => $length) {
                            $rule[$key] = trim((string) ($rule[$key] ?? ''));
                            if ($rule[$key] !== '' && (!ctype_digit($rule[$key]) || strlen($rule[$key]) > $length)) {
                                throw new \InvalidArgumentException('Коды груза, станции и номер вагона должны содержать цифры.');
                            }
                        }
                    }
                    unset($rule);
                }
                $this->db->beginTransaction();
                try {
                    if ($isDelete) {
                        if ($id <= 0) throw new \InvalidArgumentException('Область не найдена.');
                        $this->db->execute('DELETE FROM xx_rjd_data_scopes WHERE id = :id', ['id' => $id]);
                    } else {
                        if ($id === 0) {
                            $row = $this->db->fetchOne('SELECT xx_rjd_data_scopes_seq.NEXTVAL AS id FROM dual');
                            $id = (int) $row['id'];
                            $this->db->execute('INSERT INTO xx_rjd_data_scopes (id, name) VALUES (:id, :name)', ['id' => $id, 'name' => $name]);
                        }
                        $this->db->execute("UPDATE xx_rjd_data_scopes SET name = :name, description = :description, is_active = :active, start_date = TO_DATE(:start_date, 'YYYY-MM-DD'), end_date = TO_DATE(:end_date, 'YYYY-MM-DD') WHERE id = :id", ['id' => $id, 'name' => $name, 'description' => $description ?: null, 'active' => ($body['is_active'] ?? '') === '1' ? 1 : 0] + $dates);
                        $this->db->execute('DELETE FROM xx_rjd_data_scope_rules WHERE scope_id = :id', ['id' => $id]);
                        foreach ($rules as $rule) {
                            $this->db->execute('INSERT INTO xx_rjd_data_scope_rules (scope_id, organization_id, effect, cargo_code, station_code) VALUES (:scope_id, :organization_id, :effect, :cargo_code, :station_code)', ['scope_id' => $id, 'organization_id' => $rule['organization_id'], 'effect' => $rule['effect'], 'cargo_code' => $rule['cargo_code'] ?: null, 'station_code' => $rule['station_code'] ?: null]);
                        }
                    }
                    (new AuditLog($this->db))->write($isDelete ? 'DATA_SCOPE_DELETE' : 'DATA_SCOPE_SAVE', 'DATA_SCOPE', $id, $isDelete ? 'Удалена область данных' : 'Сохранена область данных');
                    $this->db->commit();
                } catch (\Throwable $error) {
                    $this->db->rollback();
                    throw $error;
                }
                return $response->withHeader('Location', $basePath . '/admin/data-scopes?ok=' . urlencode('Изменения сохранены'))->withStatus(303);
            }
            $scopes = $this->db->fetchAll('SELECT s.*, (SELECT COUNT(*) FROM xx_rjd_data_scope_rules r WHERE r.scope_id = s.id) AS rule_count, (SELECT COUNT(*) FROM xx_rjd_user_data_scopes u WHERE u.scope_id = s.id) AS user_count FROM xx_rjd_data_scopes s ORDER BY s.name');
            $organizations = $this->db->fetchAll('SELECT id, name, short_name FROM xx_rjd_organizations ORDER BY name');
            $cargoes = $isForm ? $this->db->fetchAll("SELECT DISTINCT REGEXP_SUBSTR(cargo_name, '[0-9]+\\)$') raw_code, cargo_name FROM xx_dislocation_rjd WHERE cargo_name IS NOT NULL ORDER BY cargo_name") : [];
            $stations = $isForm ? $this->db->fetchAll('SELECT esr_code, station_name FROM xx_rjd_stations ORDER BY station_name') : [];
            if ($id > 0) {
                $scope = $this->db->fetchOne("SELECT id, name, description, is_active, TO_CHAR(start_date, 'YYYY-MM-DD') start_date, TO_CHAR(end_date, 'YYYY-MM-DD') end_date FROM xx_rjd_data_scopes WHERE id = :id", ['id' => $id]);
                if (!$scope) return $response->withStatus(404);
                $rules = $this->db->fetchAll('SELECT organization_id, effect, cargo_code, station_code, car_number FROM xx_rjd_data_scope_rules WHERE scope_id = :id ORDER BY id', ['id' => $id]);
            }
        } catch (\InvalidArgumentException $error) {
            return $response->withHeader('Location', $basePath . '/admin/data-scopes?err=' . urlencode($error->getMessage()))->withStatus(303);
        } catch (\Throwable $error) {
            $code = ErrorLogger::logThrowable($error, ['operation' => 'data_scopes', 'scope_id' => $id], $request);
            $flashErr = 'Не удалось обработать области данных. Код ошибки: ' . $code;
            $scopes = [];
            $organizations = [];
            $isForm = false;
        }
        ob_start();
        include dirname(__DIR__, 2) . '/templates/admin/data-scopes.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
