<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::capture();
$app->instance('request', $request);

$kernel->bootstrap();

$user = App\Models\User::first();
if (!$user) {
    die("No users found\n");
}
$org = App\Models\Organization::first();
if (!$org) {
    die("No orgs found\n");
}

$testRequest = Illuminate\Http\Request::create('/api/v1/roles', 'POST', [
    'name' => 'Super Admin',
    'slug' => 'super-admin-2',
    'description' => 'This is a role for the super admin.',
    'is_inheritable' => true,
    'level' => 0
]);
$testRequest->headers->set('Accept', 'application/json');
$testRequest->headers->set('X-ORG-ID', $org->uuid);

$app->instance('request', $testRequest);
$testRequest->setUserResolver(function () use ($user) {
    return $user;
});

$response = $kernel->handle($testRequest);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Content: " . $response->getContent() . "\n";
