<?php

/** @var \Laravel\Lumen\Routing\Router $router */

// Docs — solo se registran en local, testing o development.
$env = (string) env('APP_ENV', 'production');

if (in_array($env, ['local', 'testing', 'development'], true)) {
    $router->get('/docs/openapi.json', 'DocsController@openApiDownload');
    $router->get('/docs/openapi.inline.json', 'DocsController@openApiInline');
    $router->get('/docs/routes', 'DocsController@listRoutes');
}

$router->get('/', function () use ($router) {
    return $router->app->version();
});

// Atomic Operations (POST /operations) — DEBE ir antes que POST /{resource}
$router->post('/operations', 'AtomicController@atomicOperations');

// GET — GetController
$router->get('/{resource}', 'GetController@getCollection');
$router->get('/{resource}/{id}', 'GetController@getObject');
$router->get('/{resource}/{id}/relationships/{related}', 'GetController@getSmallRelatedResource');
$router->get('/{resource}/{id}/{related}', 'GetController@getFullRelatedResource');

// OPTIONS — OptionsController (descubrimiento)
$router->options('/{resource}', 'OptionsController@optionsCollection');
$router->options('/{resource}/{id}', 'OptionsController@optionsResource');
$router->options('/{resource}/{id}/relationships/{related}', 'OptionsController@optionsRelationship');
$router->options('/{resource}/{id}/{related}', 'OptionsController@optionsRelated');

// POST — PostController
$router->post('/{resource}', 'PostController@createResource');
$router->post('/{resource}/{id}/relationships/{related}', 'PostController@addToRelationship');

// PATCH — PatchController
$router->patch('/{resource}/{id}', 'PatchController@updateResource');
$router->patch('/{resource}/{id}/relationships/{related}', 'PatchController@updateRelationship');

// PUT — PutController
$router->put('/{resource}/{id}', 'PutController@replaceResource');

// DELETE — DeleteController
$router->delete('/{resource}/{id}', 'DeleteController@deleteResource');
$router->delete('/{resource}/{id}/relationships/{related}', 'DeleteController@removeFromRelationship');