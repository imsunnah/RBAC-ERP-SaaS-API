<?php

/**
 * ZKTeco iClock push receiver — public entry point.
 */

define('LARAVEL_START', microtime(true));

// 1. Composer autoloader
$logData = date('Y-m-d H:i:s') . " - " . ($_SERVER['REMOTE_ADDR'] ?? 'CLI') . " - " . ($_SERVER['REQUEST_METHOD'] ?? 'GET') . " - " . ($_SERVER['REQUEST_URI'] ?? '/') . "\n";
file_put_contents(__DIR__ . '/hit_log.txt', $logData, FILE_APPEND);

require __DIR__ . '/../../vendor/autoload.php';

// 2. Capture the HTTP request
$request = \Illuminate\Http\Request::capture();

// 3. Boot the application
$app = require_once __DIR__ . '/../../bootstrap/app.php';

// 4. Bind request
$app->instance('request', $request);

// 5. Bootstrap
$app->bootstrapWith([
    \Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,
    \Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
    \Illuminate\Foundation\Bootstrap\HandleExceptions::class,
    \Illuminate\Foundation\Bootstrap\RegisterFacades::class,
    \Illuminate\Foundation\Bootstrap\RegisterProviders::class,
    \Illuminate\Foundation\Bootstrap\BootProviders::class,
]);

// 6. Detect path
$pathInfo = $_SERVER['PATH_INFO'] ?? $_SERVER['ORIG_PATH_INFO'] ?? '';
$isGetrequest = str_contains($pathInfo, 'getrequest');

/** @var \App\Http\Controllers\Api\V1\AdmsPushController $controller */
$controller = $app->make(\App\Http\Controllers\Api\V1\AdmsPushController::class);

/** @var \Illuminate\Http\Response $response */
$response = $isGetrequest
    ? $controller->getrequest($request)
    : $controller->handle($request);

// 7. Emit Response
http_response_code($response->getStatusCode());

foreach ($response->headers->allPreserveCase() as $name => $values) {
    foreach ($values as $value) {
        header("{$name}: {$value}", false);
    }
}

echo $response->getContent();
