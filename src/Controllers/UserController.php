<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
use App\Services\DirectoryAccess;
use App\Services\Organizations;
use App\Services\PasswordReset;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class UserController
{
    private PasswordReset $passwordReset;
    private Organizations $organizations;

    public function __construct(private DbInterface $db, private array $config = [])
    {
        $this->passwordReset = new PasswordReset($db, $config);
        $this->organizations = new Organizations($db);
    }

    /** GET /profile */
    public function profile(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $_SESSION['user'] ?? [];
        $id = (int) ($user['id'] ?? 0);
        if (!$id) {
            return $this->redirect($response, '/login');
        }

        $profile = $this->db->fetchOne(
            'SELECT id, username, display_name, email, is_active FROM xx_rjd_users WHERE id = :id',
            ['id' => $id]
        );
        if (!$profile) {
            $response->getBody()->write('Пользователь не найден');
            return $response->withStatus(404);
        }

        $roles = $this->db->fetchAll(
            "SELECT r.code, r.name, r.description
               FROM xx_rjd_roles r
               JOIN xx_rjd_user_roles ur ON ur.role_id = r.id
              WHERE ur.user_id = :id AND r.code <> 'SUPER'
              ORDER BY r.name",
            ['id' => $id]
        );
        $pages = $this->db->fetchAll(
            'SELECT DISTINCT p.page FROM xx_rjd_role_pages p JOIN xx_rjd_user_roles ur ON ur.role_id = p.role_id WHERE ur.user_id = :id',
            ['id' => $id]
        );
        $allowed = DirectoryAccess::expandPages(array_column($pages, 'page'));
        $isSuper = \App\Services\Authorization::isSuper($user);
        $isAdmin = $isSuper || \App\Services\Authorization::isAdmin($user) || in_array(\App\Services\Authorization::ADMIN, array_column($roles, 'code'), true);
        $administrativeAccess = $isSuper
            ? array_keys(\App\Services\PageRegistry::labels())
            : \App\Services\PageRegistry::adminPermissions();
        $access = $isAdmin
            ? array_values(array_intersect_key(\App\Services\PageRegistry::labels(), array_flip($administrativeAccess)))
            : array_values(array_intersect_key(\App\Services\PageRegistry::labels(), array_flip($allowed)));
        $organizations = $this->organizations->organizations();
        $organizationId = $this->organizations->id();
        $basePath = $this->config['base_path'] ?? '';
        $appName = $this->config['app_name'] ?? 'Дислокация';

        ob_start();
        include __DIR__ . '/../../templates/profile.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function forgotPassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (empty($this->config['password_reset_enabled'])) {
            return $this->redirect($response, '/login');
        }

        return $this->render($response, 'forgot-password.php', [
            'message' => $_SESSION['password_reset_message'] ?? null,
            'error' => $_SESSION['password_reset_error'] ?? null,
        ]);
    }

    public function sendPasswordReset(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (empty($this->config['password_reset_enabled'])) {
            return $this->redirect($response, '/login');
        }
        $body = (array) $request->getParsedBody();
        if (!$this->validCsrf((string) ($body['csrf_token'] ?? ''))) {
            $_SESSION['password_reset_error'] = 'Ошибка запроса. Попробуйте снова.';
            return $this->redirect($response, '/forgot-password');
        }

        $username = trim((string) ($body['username'] ?? ''));
        if ($username === '') {
            $_SESSION['password_reset_error'] = 'Введите логин';
            return $this->redirect($response, '/forgot-password');
        }

        try {
            $uri = $request->getUri();
            $baseUrl = $uri->getScheme() . '://' . $uri->getAuthority() . ($this->config['base_path'] ?? '');
            $server = $request->getServerParams();
            $this->passwordReset->request($username, $baseUrl, (string) ($server['REMOTE_ADDR'] ?? ''));
            $_SESSION['password_reset_message'] = 'Письмо отправлено на почту. Следуйте инструкции по восстановлению пароля.';
        } catch (\Throwable $error) {
            $_SESSION['password_reset_error'] = 'Не удалось подготовить восстановление пароля. Обратитесь к администратору.';
        }

        return $this->redirect($response, '/forgot-password');
    }

    public function resetPasswordForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $token = trim((string) ($request->getQueryParams()['token'] ?? ''));
        try {
            $valid = $this->passwordReset->isTokenValid($token);
        } catch (\Throwable $error) {
            $valid = false;
        }

        return $this->render($response, 'reset-password.php', [
            'token' => $token,
            'tokenValid' => $valid,
            'error' => $_SESSION['password_reset_error'] ?? null,
        ]);
    }

    public function resetPassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $token = trim((string) ($body['token'] ?? ''));
        $resetUrl = '/reset-password?token=' . rawurlencode($token);

        if (!$this->validCsrf((string) ($body['csrf_token'] ?? ''))) {
            $_SESSION['password_reset_error'] = 'Ошибка запроса. Попробуйте снова.';
            return $this->redirect($response, $resetUrl);
        }

        $password = (string) ($body['password'] ?? '');
        $confirmation = (string) ($body['password_confirmation'] ?? '');
        if (mb_strlen($password) < 8) {
            $_SESSION['password_reset_error'] = 'Пароль должен содержать не менее 8 символов';
            return $this->redirect($response, $resetUrl);
        }
        if (!hash_equals($password, $confirmation)) {
            $_SESSION['password_reset_error'] = 'Пароли не совпадают';
            return $this->redirect($response, $resetUrl);
        }

        try {
            if (!$this->passwordReset->reset($token, $password)) {
                $_SESSION['password_reset_error'] = 'Ссылка недействительна или срок её действия истёк';
                return $this->redirect($response, $resetUrl);
            }
        } catch (\Throwable $error) {
            $_SESSION['password_reset_error'] = 'Не удалось изменить пароль. Обратитесь к администратору.';
            return $this->redirect($response, $resetUrl);
        }

        $_SESSION['login_notice'] = 'Пароль изменён. Теперь можно войти в систему.';
        return $this->redirect($response, '/login');
    }

    public function selectOrganization(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $csrf = (string) ($body['csrf_token'] ?? '');
        if ($csrf === '' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrf)) {
            return $response->withStatus(403);
        }

        $organizationId = (int) ($body['organization_id'] ?? 0);
        if ($organizationId <= 0 || !$this->organizations->set($organizationId)) {
            return $response->withStatus(403);
        }

        $returnUrl = (string) ($body['return_url'] ?? '/');
        if (!$this->isLocalUrl($returnUrl)) {
            $returnUrl = ($this->config['base_path'] ?? '') . '/';
        }

        return $response->withHeader('Location', $returnUrl)->withStatus(302);
    }

    private function render(ResponseInterface $response, string $template, array $variables): ResponseInterface
    {
        $appName = $this->config['app_name'] ?? '';
        $basePath = $this->config['base_path'] ?? '';
        extract($variables, EXTR_SKIP);
        unset($_SESSION['password_reset_message'], $_SESSION['password_reset_error']);
        ob_start();
        require __DIR__ . '/../../templates/' . $template;
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function validCsrf(string $csrf): bool
    {
        return $csrf !== '' && hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrf);
    }

    private function redirect(ResponseInterface $response, string $path): ResponseInterface
    {
        return $response->withHeader('Location', ($this->config['base_path'] ?? '') . $path)->withStatus(302);
    }

    private function isLocalUrl(string $url): bool
    {
        if ($url === '' || str_starts_with($url, '//')) {
            return false;
        }
        $parts = parse_url($url);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
            return false;
        }
        return str_starts_with($parts['path'] ?? '', '/');
    }
}
