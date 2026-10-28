<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\PasswordReset;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PasswordResetController
{
    public function __construct(private PasswordReset $passwordReset, private array $config)
    {
    }

    public function showRequest(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (empty($this->config['password_reset_enabled'])) {
            return $this->redirect($response, '/login');
        }

        return $this->render($response, 'forgot-password.php', [
            'message' => $_SESSION['password_reset_message'] ?? null,
            'error' => $_SESSION['password_reset_error'] ?? null,
        ]);
    }

    public function request(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
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

    public function showReset(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $token = trim((string) ($request->getQueryParams()['token'] ?? ''));
        $valid = false;
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

    public function reset(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
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
}

