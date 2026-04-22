<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$req = \Illuminate\Http\Request::create('/api/v1/auth/login', 'POST', ['email' => 'superadmin@erp.test', 'password' => 'Password@123']);
$resp = $kernel->handle($req);
echo $resp->getContent();
