<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Lumen\Routing\Controller as BaseController;

// Variables .env opcionales (todas con default en el código, ninguna obligatoria).
//   JSONAPI_VERSION                        → versión de la spec (default 1.1)
//   JSONAPI_API_VERSION                    → versión de la API (default 1.0)
//   JSONAPI_TIMESTAMP_FORMAT               → formato DateTime::format (default 'c')
//   JSONAPI_REQUEST_ID_HEADER              → header del request id (default X-Request-Id)
//   JSONAPI_CONTENT_TYPE                   → Content-Type JSON:API (default application/vnd.api+json)
//   JSONAPI_LIBRARY_NAME                   → nombre de la librería (default lumen-jsonapi)
//   JSONAPI_LIBRARY_VERSION                → versión de la librería (default 1.0.0)
//   JSONAPI_USE_LINK_OBJECTS               → links como {href,title,meta} (default true)
//   JSONAPI_DESCRIBEDBY_URL                → links.describedby global (default vacío)
//   JSONAPI_RESOURCE_DESCRIBEDBY_URL       → describedby por recurso, {type} {id} (default vacío)
//   JSONAPI_RELATIONSHIP_DESCRIBEDBY_URL   → describedby por relación, {type} {id} {related} (default vacío)
//   JSONAPI_ERROR_TYPE_URL                 → error.links.type, {code} (default vacío)
//   JSONAPI_ERROR_ABOUT_URL                → error.links.about, {id} (default vacío)
//   JSONAPI_RESOURCE_RELATED_URL           → resource.links.related, {type} {id} (default vacío)
//   JSONAPI_DEVELOPER                      → JSON con los datos del desarrollador (default hardcodeado)
// Las variables APP_NAME, APP_ENV, APP_DEBUG, APP_TIMEZONE y APP_URL se leen del .env estándar.

abstract class ErrorController extends BaseController
{
    // Perfil de paginación por cursor (JSON:API 1.1).
    public const CURSOR_PROFILE = 'https://jsonapi.org/profiles/ethanresnick/cursor-pagination';

    // Extensión Atomic Operations (JSON:API 1.1).
    public const ATOMIC_EXT = 'https://jsonapi.org/ext/atomic';

    // Formato estricto de @-Member: @namespace:member.
    public const CUSTOM_MEMBER_PATTERN = '/^@[A-Za-z][A-Za-z0-9_-]*:[A-Za-z][A-Za-z0-9_-]*$/';

    // Versión de la especificación JSON:API.
    protected string $jsonApiVersion = '1.1';

    // Versión de tu API (meta.api_version).
    protected string $apiVersion = '1.0';

    // Nombre de la app (meta.implementation).
    protected string $implementation = '';

    // Entorno (meta.environment).
    protected string $environment = '';

    // Zona horaria (meta.timestamp, meta.timezone).
    protected string $timezone = 'UTC';

    // Datos del desarrollador (meta.developer).
    protected array $developer = [];

    // Formato del timestamp.
    protected string $timestampFormat = 'c';

    // Header del request id.
    protected string $requestIdHeader = 'X-Request-Id';

    // Content-Type obligatorio de JSON:API.
    protected string $contentType = 'application/vnd.api+json';

    // Flags de json_encode().
    protected int $jsonEncodeFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    // Nombre y versión de la librería implementadora.
    protected string $libraryName = 'lumen-jsonapi';
    protected string $libraryVersion = '1.0.0';

    // Si true, los links se emiten como link objects.
    protected bool $useLinkObjects = true;

    // URLs de describedby / error links.
    protected string $describedByUrl = '';
    protected string $resourceDescribedByUrl = '';
    protected string $relationshipDescribedByUrl = '';
    protected string $errorTypeUrl = '';
    protected string $errorAboutUrl = '';
    protected string $resourceRelatedUrl = '';

    // Members custom (@-Members) a incluir siempre en la respuesta.
    protected array $customMembers = [];

    // Namespaces @-Member reconocidos por esta implementación.
    protected array $knownMemberNamespaces = [];

    // @-Members reconocidos recibidos en la petición actual.
    protected array $pendingKnownMembers = [];

    // Indica si se exponen detalles internos de los errores (APP_DEBUG).
    protected bool $debug = false;

    // Caché de metadatos del runtime.
    protected ?array $runtimeMetaCache = null;

    // Errores de validación acumulados en la petición actual.
    protected array $pendingValidationErrors = [];

    // Perfiles y extensiones solicitados por el cliente en Accept.
    protected array $clientProfiles = [];
    protected array $clientExtensions = [];

    // Perfiles y extensiones aplicados a este documento.
    protected array $appliedProfiles = [];
    protected array $appliedExtensions = [];

    public function __construct()
    {
        $this->jsonApiVersion = (string) env('JSONAPI_VERSION', '1.1');
        $this->apiVersion = (string) env('JSONAPI_API_VERSION', '1.0');
        $this->implementation = (string) env('APP_NAME', 'lumen-jsonapi');
        $this->environment = (string) env('APP_ENV', 'production');
        $this->timezone = (string) env('APP_TIMEZONE', 'UTC');
        $this->debug = filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
        $this->timestampFormat = (string) env('JSONAPI_TIMESTAMP_FORMAT', 'c');
        $this->requestIdHeader = (string) env('JSONAPI_REQUEST_ID_HEADER', 'X-Request-Id');
        $this->contentType = (string) env('JSONAPI_CONTENT_TYPE', 'application/vnd.api+json');
        $this->libraryName = (string) env('JSONAPI_LIBRARY_NAME', 'lumen-jsonapi');
        $this->libraryVersion = (string) env('JSONAPI_LIBRARY_VERSION', '1.0.0');
        $this->useLinkObjects = filter_var(env('JSONAPI_USE_LINK_OBJECTS', true), FILTER_VALIDATE_BOOLEAN);
        $this->describedByUrl = rtrim((string) env('JSONAPI_DESCRIBEDBY_URL', ''), '/');
        $this->resourceDescribedByUrl = (string) env('JSONAPI_RESOURCE_DESCRIBEDBY_URL', '');
        $this->relationshipDescribedByUrl = (string) env('JSONAPI_RELATIONSHIP_DESCRIBEDBY_URL', '');
        $this->errorTypeUrl = (string) env('JSONAPI_ERROR_TYPE_URL', '');
        $this->errorAboutUrl = (string) env('JSONAPI_ERROR_ABOUT_URL', '');
        $this->resourceRelatedUrl = (string) env('JSONAPI_RESOURCE_RELATED_URL', '');
        $this->developer = $this->decodeJsonEnv('JSONAPI_DEVELOPER', [
            'name' => 'Maicol Almarales Tamayo',
            'email' => 'maicol.almarales@uic.cu',
            'country' => 'Cuba',
            'workplace' => 'Centro de Desarrollo de Aplicaciones y Servicios Informáticos',
            'institution' => 'Centro Principal de Automatización',
            'ministry' => 'Ministerio de las Fuerzas Armadas Revolucionarias',
        ]);
    }

    // Decodifica una variable .env con formato JSON o devuelve el default.
    protected function decodeJsonEnv(string $key, mixed $default = []): mixed
    {
        $raw = env($key);

        if (! is_string($raw) || trim($raw) === '') { return $default; }

        $decoded = json_decode($raw, true);

        return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : $default;
    }

    // Extensiones soportadas por esta implementación.
    protected function supportedExtensions(): array
    {
        return [self::ATOMIC_EXT];
    }

    // Perfiles soportados por esta implementación.
    protected function supportedProfiles(): array
    {
        return [self::CURSOR_PROFILE];
    }

    // Añade un error de validación al acumulador (sin lanzar).
    protected function addValidationError(string $pointer, string $detail, int $status = 400, ?string $code = null): void
    {
        $this->pendingValidationErrors[] = ['source' => ['pointer' => $pointer], 'status' => $status, 'code' => $code, 'detail' => $detail];
    }

    // Comprueba si hay errores de validación acumulados.
    protected function hasValidationErrors(): bool
    {
        return $this->pendingValidationErrors !== [];
    }

    // Añade un error de validación y devuelve la excepción para hacer throw inmediato.
    protected function validationError(string $pointer, string $detail, int $status = 400, ?string $code = null): \InvalidArgumentException
    {
        $this->addValidationError($pointer, $detail, $status, $code);

        return new \InvalidArgumentException($detail);
    }

    // Valida el formato de los @-Members de un payload (estricto).
    protected function validateCustomMemberNames(array $payload, string $basePointer = ''): void
    {
        foreach ($payload as $key => $value) {
            if (! is_string($key) || ! str_starts_with($key, '@')) { continue; }

            if (! preg_match(self::CUSTOM_MEMBER_PATTERN, $key)) {
                $this->addValidationError($basePointer . '/' . $key, "El miembro '{$key}' no cumple el formato '@namespace:member'.", 400, 'invalid_member_name');
            }
        }

        if ($this->hasValidationErrors()) { throw new \InvalidArgumentException('Uno o más @-Members no son válidos.'); }
    }

    // Extrae los @-Members reconocidos (namespace conocido) del payload.
    protected function extractKnownCustomMembers(array $payload): array
    {
        $known = [];

        foreach ($payload as $key => $value) {
            if (! is_string($key) || ! str_starts_with($key, '@')) { continue; }

            if (preg_match(self::CUSTOM_MEMBER_PATTERN, $key)) {
                $namespace = substr($key, 1, (int) strpos($key, ':') - 1);

                if (in_array($namespace, $this->knownMemberNamespaces, true)) { $known[$key] = $value; }
            }
        }

        return $known;
    }

    // Registra los @-Members conocidos recibidos para propagarlos en la respuesta.
    protected function recordKnownMembers(array $payload): void
    {
        $this->pendingKnownMembers = array_merge($this->pendingKnownMembers, $this->extractKnownCustomMembers($payload));
    }

    // Detecta claves duplicadas en un objeto JSON (rechazo estricto).
    protected function assertNoDuplicateJsonKeys(string $json): void
    {
        $len = strlen($json);
        $i = 0;
        $stack = [];
        $expectKey = false;

        while ($i < $len) {
            $c = $json[$i];

            if (ctype_space($c)) { $i++; continue; }

            if ($c === '{') { $stack[] = ['type' => 'obj', 'keys' => []]; $expectKey = true; $i++; continue; }
            if ($c === '[') { $stack[] = ['type' => 'arr', 'keys' => []]; $expectKey = false; $i++; continue; }
            if ($c === '}' || $c === ']') { array_pop($stack); $expectKey = false; $i++; continue; }
            if ($c === ',') { $expectKey = ! empty($stack) && end($stack)['type'] === 'obj'; $i++; continue; }
            if ($c === ':') { $expectKey = false; $i++; continue; }

            if ($c === '"') {
                $i++;
                $buf = '';

                while ($i < $len) {
                    if ($json[$i] === '\\') { $buf .= $json[$i] . ($json[$i + 1] ?? ''); $i += 2; continue; }
                    if ($json[$i] === '"') { break; }
                    $buf .= $json[$i];
                    $i++;
                }
                $i++;

                if ($expectKey && ! empty($stack) && end($stack)['type'] === 'obj') {
                    $top = &$stack[count($stack) - 1];

                    if (isset($top['keys'][$buf])) {
                        throw $this->validationError('/', "Miembro JSON duplicado: '{$buf}'.", 400, 'duplicate_json_member');
                    }

                    $top['keys'][$buf] = true;
                }

                continue;
            }

            $i++;
        }
    }

    // Valor de link: string simple o link object.
    protected function linkValue(?string $href, array $meta = [], ?string $title = null): array|string|null
    {
        if ($href === null) { return null; }
        if (! $this->useLinkObjects) { return $href; }

        $link = ['href' => $href];

        if ($title !== null) { $link['title'] = $title; }
        if ($meta !== []) { $link['meta'] = $meta; }

        return $link;
    }

    // Añade `links.describedby` (si está configurado).
    protected function documentLinks(array $links = []): array
    {
        if ($this->describedByUrl !== '' && ! isset($links['describedby'])) {
            $links['describedby'] = $this->linkValue($this->describedByUrl, [], 'API documentation (OpenAPI / JSON Schema)');
        }

        return $links;
    }

    // Mergea los @-Members configurados + los conocidos recibidos (sin pisar los existentes).
    protected function mergeCustomMembers(array $payload): array
    {
        foreach ($this->customMembers as $key => $value) {
            if (str_starts_with($key, '@') && ! isset($payload[$key])) { $payload[$key] = $value; }
        }

        foreach ($this->pendingKnownMembers as $key => $value) {
            if (! isset($payload[$key])) { $payload[$key] = $value; }
        }

        return $payload;
    }

    // Negocia el Accept header (media type + ext/profile); 406 si no es aceptable.
    protected function validateAcceptHeader(Request $request): ?JsonResponse
    {
        $this->clientProfiles = [];
        $this->clientExtensions = [];

        $accept = $request->header('Accept', '*/*');

        if ($accept === null || trim($accept) === '' || trim($accept) === '*/*') { return null; }

        $acceptable = false;
        $requestedProfiles = [];
        $requestedExtensions = [];

        foreach (array_map('trim', explode(',', $accept)) as $instance) {
            if ($instance === '') { continue; }

            [$mediaType, $params] = $this->parseMediaType($instance);

            if ($mediaType === '*/*') { $acceptable = true; continue; }
            if ($mediaType !== 'application/vnd.api+json') { continue; }

            $parsed = $this->parseMediaParams($params);

            if ($parsed === null) { continue; }

            $exts = $parsed['ext'] ?? [];
            $extOk = true;

            foreach ($exts as $ext) {
                if (! in_array($ext, $this->supportedExtensions(), true)) { $extOk = false; break; }
            }

            if (! $extOk) { continue; }

            $acceptable = true;
            $requestedExtensions = array_merge($requestedExtensions, $exts);
            $requestedProfiles = array_merge($requestedProfiles, $parsed['profile'] ?? []);
        }

        if (! $acceptable) {
            return $this->errorResponse($request, 406, 'Not Acceptable', 'El servidor no puede generar una representación aceptable según el header Accept.');
        }

        $this->clientExtensions = array_values(array_intersect($requestedExtensions, $this->supportedExtensions()));
        $this->clientProfiles = array_values(array_intersect($requestedProfiles, $this->supportedProfiles()));

        return null;
    }

    // Separa un media range en media type y lista de parámetros.
    protected function parseMediaType(string $instance): array
    {
        $parts = explode(';', $instance);

        return [strtolower(trim($parts[0])), array_slice($parts, 1)];
    }

    // Parsea ext/profile (q ignorado); null si hay parámetros no reconocidos.
    protected function parseMediaParams(array $params): ?array
    {
        $result = [];

        foreach ($params as $param) {
            $param = trim($param);

            if ($param === '') { continue; }

            $eq = strpos($param, '=');

            if ($eq === false) { return null; }

            $name = strtolower(trim(substr($param, 0, $eq)));
            $value = trim(substr($param, $eq + 1));

            if ($name === 'q') { continue; }

            if ($name !== 'ext' && $name !== 'profile') { return null; }

            $value = trim($value, '"');
            $uris = preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            $result[$name] = array_merge($result[$name] ?? [], $uris);
        }

        return $result;
    }

    // Content-Type con ext/profile aplicados.
    protected function responseContentType(): string
    {
        $params = [];

        if ($this->appliedExtensions !== []) {
            $params[] = 'ext="' . implode(' ', array_values(array_unique($this->appliedExtensions))) . '"';
        }

        if ($this->appliedProfiles !== []) {
            $params[] = 'profile="' . implode(' ', array_values(array_unique($this->appliedProfiles))) . '"';
        }

        return $params === [] ? $this->contentType : $this->contentType . '; ' . implode('; ', $params);
    }

    // Valida el Content-Type (peticiones con cuerpo).
    protected function validateContentType(Request $request): ?JsonResponse
    {
        $contentType = $request->header('Content-Type', '');

        if (! str_contains($contentType, 'application/vnd.api+json')) {
            return $this->errorResponse($request, 415, 'Unsupported Media Type', 'El Content-Type debe ser application/vnd.api+json.', ['source' => ['header' => 'Content-Type']]);
        }

        return null;
    }

    // Envuelve un callback con el manejo de errores común.
    protected function guard(Request $request, callable $fn)
    {
        $this->pendingValidationErrors = [];
        $this->pendingKnownMembers = [];

        try { return $fn(); }
        catch (ModelNotFoundException $e) { return $this->errorResponse($request, 404, 'Not Found', 'El recurso solicitado no existe.'); }
        catch (QueryException $e) { return $this->handleQueryException($request, $e); }
        catch (\InvalidArgumentException $e) { return $this->handleInvalidArgumentException($request, $e); }
        catch (\Throwable $e) { return $this->internalError($request, $e); }
    }

    // Envuelve un callback en una transacción.
    protected function transaction(callable $fn): mixed
    {
        return DB::transaction($fn);
    }

    // Resuelve un InvalidArgumentException usando los errores acumulados (multi-error).
    protected function handleInvalidArgumentException(Request $request, \InvalidArgumentException $e): JsonResponse
    {
        $errors = $this->pendingValidationErrors;
        $this->pendingValidationErrors = [];

        if ($errors === []) { return $this->errorResponse($request, 400, 'Bad Request', $e->getMessage()); }

        if (count($errors) === 1) {
            $err = $errors[0];
            $extra = [];

            if (! empty($err['source'])) { $extra['source'] = $err['source']; }
            if (! empty($err['code'])) { $extra['code'] = $err['code']; }

            return $this->errorResponse($request, $err['status'] ?? 400, 'Bad Request', $err['detail'] ?? $e->getMessage(), $extra);
        }

        return $this->errorResponseMany($request, $errors);
    }

    // Mapea QueryException (SQLSTATE) a 4xx.
    protected function handleQueryException(Request $request, QueryException $e): JsonResponse
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = $e->errorInfo[1] ?? null;

        [$status, $code, $title, $detail] = match (true) {
            $sqlState === '23505' || ($sqlState === '23000' && $driverCode === 1062) => [409, 'conflict', 'Conflict', 'Ya existe un recurso con esos datos.'],
            $sqlState === '23503' || ($sqlState === '23000' && $driverCode === 1452) => [422, 'foreign_key_violation', 'Unprocessable Entity', 'Una referencia a otro recurso no existe.'],
            $sqlState === '23502' || ($sqlState === '23000' && $driverCode === 1048) => [422, 'not_null_violation', 'Unprocessable Entity', 'Un campo obligatorio está vacío.'],
            $sqlState === '23514' => [422, 'check_violation', 'Unprocessable Entity', 'Los datos no cumplen una restricción de la base de datos.'],
            $sqlState === '42703' || $sqlState === '42S22' => [400, 'undefined_column', 'Bad Request', 'La columna especificada no existe.'],
            default => [422, 'unprocessable_entity', 'Unprocessable Entity', 'Los datos no cumplen las restricciones de la base de datos.'],
        };

        $extra = ['code' => $code];

        if ($this->debug) { $extra['meta'] = ['exception' => get_class($e), 'sqlstate' => $sqlState, 'driver_code' => $driverCode]; }

        return $this->errorResponse($request, $status, $title, $detail, $extra);
    }

    // Código machine-readable por status HTTP.
    protected function autoErrorCode(int $status): string
    {
        return match ($status) {
            400 => 'bad_request',
            401 => 'unauthorized',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            406 => 'not_acceptable',
            409 => 'conflict',
            415 => 'unsupported_media_type',
            422 => 'unprocessable_entity',
            429 => 'too_many_requests',
            500 => 'internal_server_error',
            default => 'error',
        };
    }

    // Source por defecto de un error según su status HTTP.
    protected function autoErrorSource(int $status): ?array
    {
        return match ($status) {
            406 => ['header' => 'Accept'],
            415 => ['header' => 'Content-Type'],
            default => null,
        };
    }

    // Construye un objeto de error JSON:API con `links.type` y `links.about`.
    protected function buildError(Request $request, int $status, string $title, ?string $detail = null, array $extra = []): array
    {
        $code = $extra['code'] ?? $this->autoErrorCode($status);
        $source = array_key_exists('source', $extra) ? $extra['source'] : $this->autoErrorSource($status);
        $errorId = $extra['id'] ?? (string) Str::uuid();

        $error = [
            'id' => $errorId,
            'status' => (string) $status,
            'code' => $code,
            'title' => $title,
            'meta' => array_merge(['request_id' => $this->requestId($request), 'timestamp' => $this->now()], $extra['meta'] ?? []),
        ];

        if ($detail !== null && $detail !== '') { $error['detail'] = $detail; }
        if ($source !== null) { $error['source'] = $source; }

        $links = $extra['links'] ?? [];

        if ($this->errorTypeUrl !== '' && ! isset($links['type'])) {
            $links['type'] = $this->linkValue(str_replace('{code}', (string) $code, $this->errorTypeUrl), [], 'Error type');
        }

        if ($this->errorAboutUrl !== '' && ! isset($links['about'])) {
            $links['about'] = $this->linkValue(str_replace('{id}', $errorId, $this->errorAboutUrl), [], 'About this error');
        }

        if ($links !== []) { $error['links'] = $links; }

        return $error;
    }

    // Documento JSON:API de error único (con describedby + custom members).
    protected function errorResponse(Request $request, int $status, string $title, ?string $detail = null, array $extra = [])
    {
        $errors = [$this->buildError($request, $status, $title, $detail, $extra)];

        $payload = [
            'jsonapi' => $this->jsonApiObject(),
            'errors' => $errors,
            'meta' => array_merge($this->requestMeta($request), ['errors' => ['count' => count($errors)]]),
        ];

        $links = $this->documentLinks([]);

        if ($links !== []) { $payload['links'] = $links; }

        $payload = $this->mergeCustomMembers($payload);

        return response()->json($payload, $status, ['Content-Type' => $this->responseContentType(), 'Vary' => 'Accept'], $this->jsonEncodeFlags);
    }

    // Documento JSON:API con múltiples errores.
    protected function errorResponseMany(Request $request, array $errors): JsonResponse
    {
        $built = [];

        foreach ($errors as $err) {
            $extra = [];

            if (! empty($err['source'])) { $extra['source'] = $err['source']; }
            if (! empty($err['code'])) { $extra['code'] = $err['code']; }
            if (! empty($err['meta'])) { $extra['meta'] = $err['meta']; }
            if (! empty($err['links'])) { $extra['links'] = $err['links']; }

            $built[] = $this->buildError(
                $request,
                (int) ($err['status'] ?? 400),
                $err['title'] ?? 'Bad Request',
                $err['detail'] ?? null,
                $extra
            );
        }

        $status = (int) ($errors[0]['status'] ?? 400);

        $payload = [
            'jsonapi' => $this->jsonApiObject(),
            'errors' => $built,
            'meta' => array_merge($this->requestMeta($request), ['errors' => ['count' => count($built)]]),
        ];

        $links = $this->documentLinks([]);

        if ($links !== []) { $payload['links'] = $links; }

        $payload = $this->mergeCustomMembers($payload);

        return response()->json($payload, $status, ['Content-Type' => $this->responseContentType(), 'Vary' => 'Accept'], $this->jsonEncodeFlags);
    }

    // Error 500 ocultando el detalle fuera de debug.
    protected function internalError(Request $request, \Throwable $e)
    {
        $detail = $this->debug ? $e->getMessage() : 'Se produjo un error interno.';
        $extra = $this->debug ? ['meta' => ['exception' => get_class($e)]] : [];

        return $this->errorResponse($request, 500, 'Internal Server Error', $detail, $extra);
    }

    // Genera o reutiliza un id único por petición.
    protected function requestId(Request $request): string
    {
        if (! $request->attributes->has('_request_id')) { $request->attributes->set('_request_id', $request->headers->get($this->requestIdHeader) ?: (string) Str::uuid()); }

        return (string) $request->attributes->get('_request_id');
    }

    // Timestamp actual con la zona horaria configurada.
    protected function now(): string
    {
        return (new \DateTime('now', new \DateTimeZone($this->timezone)))->format($this->timestampFormat);
    }

    // Metadatos seguros del runtime.
    protected function runtimeMeta(Request $request): array
    {
        if ($this->runtimeMetaCache !== null) { return $this->runtimeMetaCache; }

        return $this->runtimeMetaCache = [
            'php_version' => PHP_VERSION,
            'php_sapi' => function_exists('php_sapi_name') ? (php_sapi_name() ?: 'unknown') : 'unknown',
            'web_server' => $request->server->get('SERVER_SOFTWARE') ?: 'unknown',
        ];
    }

    // Metadatos comunes de la petición.
    protected function requestMeta(Request $request): array
    {
        return array_merge($this->runtimeMeta($request), [
            'request_id' => $this->requestId($request),
            'timestamp' => $this->now(),
            'implementation' => $this->implementation,
            'environment' => $this->environment,
            'api_version' => $this->apiVersion,
            'timezone' => $this->timezone,
            'developer' => $this->developer,
        ]);
    }

    // Objeto jsonapi con version, ext/profile aplicados y meta.
    protected function jsonApiObject(): array
    {
        $jsonApi = ['version' => $this->jsonApiVersion];

        if ($this->appliedExtensions !== []) { $jsonApi['ext'] = array_values(array_unique($this->appliedExtensions)); }
        if ($this->appliedProfiles !== []) { $jsonApi['profile'] = array_values(array_unique($this->appliedProfiles)); }

        $jsonApi['meta'] = [
            'implementation' => $this->implementation,
            'library' => ['name' => $this->libraryName, 'version' => $this->libraryVersion],
        ];

        return $jsonApi;
    }

    // Construye una respuesta de error JSON:API (público, para uso desde el Exception Handler).
    public function renderError(Request $request, int $status, string $title, ?string $detail = null, array $extra = []): JsonResponse
    {
        return $this->errorResponse($request, $status, $title, $detail, $extra);
    }

    // Construye una respuesta de multi-error JSON:API (público, para uso desde el Exception Handler).
    public function renderManyErrors(Request $request, array $errors): JsonResponse
    {
        return $this->errorResponseMany($request, $errors);
    }
}