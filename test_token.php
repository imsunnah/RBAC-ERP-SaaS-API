<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$user = App\Models\User::first();
$org = App\Models\Organization::first();
$token = $user->createToken('test')->plainTextToken;
echo "TOKEN: " . $token . "\n";
echo "ORG: " . $org->uuid . "\n";
