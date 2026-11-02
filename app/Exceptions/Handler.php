<?php

namespace App\Exceptions;

use App\Http\Controllers\ErrorController;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    // Excepciones que no se reportan (logging).
    protected $dontReport = [
        AuthorizationException::class,
        AuthenticationException::class,
        ValidationException::class,
        HttpExceptionInterface::class,
        ModelNotFoundException::class,
    ];

    // Reporta o registra una excepción (delegando en el padre).
    public function report(Throwable $e)
    {
        parent::report($e);
    }

    // Renderiza una excepción como documento JSON:API.
    public function render($request, Throwable $e)
    {
        if ($request instanceof Request) {
            return $this->renderJsonApi($request, $e);
        }

        return parent::render($request, $e);
    }

    // Convierte la excepción a un documento JSON:API.
    protected function renderJsonApi(Request $request, Throwable $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return $this->renderValidationException($request, $e);
        }

        [$status, $title, $detail, $extra] = $this->classify($e);

        return $this->errorController()->renderError($request, $status, $title, $detail, $extra);
    }

    // Renderiza una ValidationException como multi-error con source.pointer por campo.
    protected function renderValidationException(Request $request, ValidationException $e): JsonResponse
    {
        $errors = [];

        foreach ($e->errors() as $field => $messages) {
            foreach ((array) $messages as $message) {
                $errors[] = [
                    'status' => 422,
                    'detail' => $message,
                    'source' => ['pointer' => '/data/attributes/' . $field],
                    'code' => 'invalid_attribute',
                ];
            }
        }

        if ($errors === []) { return $this->errorController()->renderError($request, 422, 'Unprocessable Entity', 'Los datos no son válidos.'); }

        return $this->errorController()->renderManyErrors($request, $errors);
    }

    // Devuelve una instancia concreta de ErrorController (clase anónima).
    protected function errorController(): ErrorController
    {
        static $instance = null;

        return $instance ??= new class extends ErrorController {};
    }

    // Clasifica la excepción en [status, title, detail, extra].
    protected function classify(Throwable $e): array
    {
        if ($e instanceof NotFoundHttpException) {
            return [404, 'Not Found', 'La ruta solicitada no existe.', []];
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            $allowed = $e->getHeaders()['Allow'] ?? null;

            $extra = [];

            if ($allowed !== null) {
                $extra['source'] = ['header' => 'Allow'];
                $extra['meta'] = ['allow' => $allowed];
            }

            return [405, 'Method Not Allowed', 'El método HTTP no está permitido para esta ruta.', $extra];
        }

        if ($e instanceof ModelNotFoundException) {
            return [404, 'Not Found', 'El recurso solicitado no existe.', []];
        }

        if ($e instanceof AuthenticationException) {
            return [401, 'Unauthorized', 'Se requiere autenticación.', []];
        }

        if ($e instanceof AuthorizationException) {
            return [403, 'Forbidden', 'No tienes permiso para acceder a este recurso.', []];
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $message = $e->getMessage() ?: $this->defaultHttpMessage($status);

            return [$status, $this->httpTitle($status), $message, []];
        }

        $detail = $this->debugEnabled() ? $e->getMessage() : 'Se produjo un error interno.';
        $extra = $this->debugEnabled() ? ['meta' => ['exception' => get_class($e)]] : [];

        return [500, 'Internal Server Error', $detail, $extra];
    }

    // Título por defecto para status HTTP.
    protected function httpTitle(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            409 => 'Conflict',
            412 => 'Precondition Failed',
            413 => 'Payload Too Large',
            415 => 'Unsupported Media Type',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            default => 'Error',
        };
    }

    // Mensaje por defecto para status HTTP.
    protected function defaultHttpMessage(int $status): string
    {
        return match ($status) {
            400 => 'La petición no es válida.',
            401 => 'Se requiere autenticación.',
            403 => 'No tienes permiso para acceder a este recurso.',
            404 => 'La ruta solicitada no existe.',
            405 => 'El método HTTP no está permitido para esta ruta.',
            406 => 'El servidor no puede generar una representación aceptable.',
            409 => 'La petición entra en conflicto con el estado actual.',
            412 => 'Una precondición de la petición falló.',
            413 => 'El cuerpo de la petición es demasiado grande.',
            415 => 'El Content-Type no está soportado.',
            422 => 'Los datos de la petición no son válidos.',
            429 => 'Demasiadas peticiones.',
            default => 'Se produjo un error.',
        };
    }

    // Indica si APP_DEBUG está activo.
    protected function debugEnabled(): bool
    {
        return filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
    }
}