<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;

$user = App\Models\User::first();
$org = App\Models\Organization::first();

// Make sure user is super admin to avoid permission denied
$user->is_super_admin = true;
$user->save();

// Authenticate user
auth('api')->setUser($user);

// Setup Request
$request = Request::create('/api/v1/roles', 'POST', [], [], [], [
    'HTTP_ACCEPT' => 'application/json',
    'HTTP_X-ORG-ID' => $org->uuid
], json_encode([
    'name' => 'Super Admin',
    'slug' => 'super-admin',
    'description' => 'This role is for the super admin account.',
    'is_inheritable' => true,
    'level' => 0
]));
$request->headers->set('Content-Type', 'application/json');

// Handle request
$response = $app->handle($request);

echo "STATUS CODE: " . $response->getStatusCode() . "\n";
echo "RESPONSE: " . $response->getContent() . "\n";
