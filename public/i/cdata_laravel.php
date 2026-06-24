<?php

/**
 * ZKTeco iClock push receiver — public entry point.
 *
 * Developer Info:
 *  Name   : AH ANik
 *  Email  : lanik615@gmail.com
 *  Mobile : +8801863795007
 *
 * Device URL:  http://192.168.100.97/pharmik/public/iclock/cdata.php
 *
 * Bootstraps Laravel properly (binds Request before service providers
 * boot, so RoutingServiceProvider resolves correctly), then delegates
 * to AdmsPushController@handle. All config, DB, and attendance logic
 * live in the controller — nothing is duplicated here.
 *
 * Device configuration:
 *   Server address : 192.168.100.20
 *   Server path    : /pharmik/public/iclock
 *   Port           : 4370
 */

define('LARAVEL_START', microtime(true));

// 1. Composer autoloader
require __DIR__ . '/../../vendor/autoload.php';

// 2. Capture the HTTP request FIRST — it must be bound into the
//    container before RoutingServiceProvider boots.
$request = \Illuminate\Http\Request::capture();

// 3. Boot the application
$app = require_once __DIR__ . '/../../bootstrap/app.php';

// 4. Bind the request into the container so the RoutingServiceProvider
//    (and UrlGenerator) can resolve it during bootstrapping.
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

// 6. Detect which endpoint the device is calling via PATH_INFO
//    cdata.php/iclock/getrequest → getrequest() → just returns "OK"
//    cdata.php/iclock/cdata      → handle()     → full handshake / ATTLOG
file_put_contents(__DIR__ . '/../../storage/logs/raw_hit.log', "[" . date('Y-m-d H:i:s') . "] Hit: " . $_SERVER['REQUEST_URI'] . "\n", FILE_APPEND);

$pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : (isset($_SERVER['ORIG_PATH_INFO']) ? $_SERVER['ORIG_PATH_INFO'] : '');
$isGetrequest = (strpos($pathInfo, 'getrequest') !== false);

/** @var \App\Http\Controllers\Api\AdmsPushController $controller */
$controller = $app->make(\App\Http\Controllers\Api\V1\AdmsPushController::class);

/** @var \Illuminate\Http\Response $response */
$response = $isGetrequest
    ? $controller->getrequest($request)
    : $controller->handle($request);

// 7. Emit the reply the device expects
http_response_code($response->getStatusCode());

foreach ($response->headers->allPreserveCase() as $name => $values) {
    foreach ($values as $value) {
        header("{$name}: {$value}", false);
    }
}

echo $response->getContent();
