<?php


define('LARAVEL_START', microtime(true));

// 1. Composer autoloader
require __DIR__ . '/../../vendor/autoload.php';

// 2. Capture the HTTP request FIRST
$request = \Illuminate\Http\Request::capture();

// 3. Boot the application
$app = require_once __DIR__ . '/../../bootstrap/app.php';

// 4. Bind the request into the container
$app->instance('request', $request);

// 5. Run the standard bootstrapper chain
$app->bootstrapWith([
    \Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,
    \Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
    \Illuminate\Foundation\Bootstrap\HandleExceptions::class,
    \Illuminate\Foundation\Bootstrap\RegisterFacades::class,
    \Illuminate\Foundation\Bootstrap\RegisterProviders::class,
    \Illuminate\Foundation\Bootstrap\BootProviders::class,
]);

/** @var \App\Http\Controllers\Api\AdmsPushController $controller */
$controller = $app->make(\App\Http\Controllers\Api\V1\AdmsPushController::class);

/** @var \Illuminate\Http\Response $response */
$response = $controller->getrequest($request);

// Emit the reply the device expects
http_response_code($response->getStatusCode());

foreach ($response->headers->allPreserveCase() as $name => $values) {
    foreach ($values as $value) {
        header("{$name}: {$value}", false);
    }
}

echo $response->getContent();
