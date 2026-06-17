<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Services\RBAC\RBACService;

$user = User::first();
$rbac = app(RBACService::class);
$menus = $rbac->getUserMenus($user, 1);

echo json_encode($menus, JSON_PRETTY_PRINT);
