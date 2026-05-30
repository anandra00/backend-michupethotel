<?php

use App\Http\Controllers\Api\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = User::first();
if (!$user) {
    echo "No users found in database.\n";
    exit;
}

echo "Logging in as: " . $user->email . "\n";
Auth::login($user);

$request = Request::create('/api/notifications/test', 'POST');
$request->setUserResolver(function () use ($user) {
    return $user;
});

$controller = new NotificationController();
try {
    $response = $controller->testNotification($request);
    echo "Response: " . $response->getContent() . "\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
