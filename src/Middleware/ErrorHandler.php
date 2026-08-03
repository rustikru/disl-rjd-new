<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Logging\ErrorLogger;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;
use Throwable;

class ErrorHandler
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private array $config
    ) {}

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ResponseInterface {
        $status = $this->httpStatus($exception);
        $isApi = $this->isApiRequest($request);
        $errorId = ErrorLogger::logThrowable($exception, [
            'module' => self::class,
            'function' => '__invoke',
        ], $request);

        $response = $this->responseFactory->createResponse($status);

        return $isApi
            ? $this->jsonResponse($response, $exception, $status, $displayErrorDetails, $errorId)
            : $this->htmlResponse($response, $exception, $status, $displayErrorDetails, $errorId);
    }

    private function httpStatus(Throwable $e): int
    {
        if ($e instanceof HttpException) {
            return $e->getCode();
        }
        return 500;
    }

    private function isApiRequest(ServerRequestInterface $request): bool
    {
        $basePath = rtrim($this->config['base_path'] ?? '', '/');
        $path     = $request->getUri()->getPath();

        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }

        return str_starts_with($path, '/api/');
    }

    private function jsonResponse(
        ResponseInterface $response,
        Throwable $exception,
        int $status,
        bool $details,
        string $errorId
    ): ResponseInterface {
        $body = [
            'status'  => 'error',
            'message' => $status >= 500
                ? 'Внутренняя ошибка сервера. Код ошибки: ' . $errorId
                : $exception->getMessage(),
            'error_id' => $errorId,
        ];

        if ($details) {
            $body['exception'] = $exception->getMessage();
            $body['file']      = $exception->getFile() . ':' . $exception->getLine();
            $body['trace']     = explode("\n", $exception->getTraceAsString());
        }

        $response->getBody()->write(
            json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    private function htmlResponse(
        ResponseInterface $response,
        Throwable $exception,
        int $status,
        bool $details,
        string $errorId
    ): ResponseInterface {
        $tpl = __DIR__ . '/../../templates/' . $status . '.php';
        if (!file_exists($tpl)) {
            $tpl = __DIR__ . '/../../templates/500.php';
        }

        $basePath    = $this->config['base_path'] ?? '';
        $appName     = $this->config['app_name']  ?? '';
        $user        = $_SESSION['user'] ?? [];
        $headerSub   = '';
        $headerRight = '';
        $errorCode   = $status;
        $errorMessage = $details ? htmlspecialchars($exception->getMessage()) : '';
        $errorTrace   = $details ? htmlspecialchars($exception->getTraceAsString()) : '';

        ob_start();
        if (file_exists($tpl)) {
            include $tpl;
        } else {
            echo "<h1>$status — Ошибка сервера</h1>";
            if ($errorMessage) {
                echo "<pre style='color:red'>$errorMessage\n\n$errorTrace</pre>";
            }
        }
        $html = ob_get_clean();

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
