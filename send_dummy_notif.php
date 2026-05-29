<?php

use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$user = User::where('email', 'anandradandi00@gmail.com')->first();
if ($user) {
    $user->notify(new AppNotification(
        'Testing Real-Time Notif! 🚀',
        'Halo '.$user->name.', ini adalah contoh notifikasi uji coba yang langsung masuk ke akun kamu tanpa harus refresh!',
        'success',
        '/dashboard/history'
    ));
    echo 'Sent to '.$user->name.' (ID: '.$user->id.")\n";
} else {
    echo "User not found\n";
}
