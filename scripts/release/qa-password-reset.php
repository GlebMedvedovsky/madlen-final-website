<?php
// Local-only, disposable browser fixture. No production routes/configuration.
$app = require __DIR__.'/qa-bootstrap.php';
$root = '/tmp/madlen-release-qa';
$app->usePublicPath($root.'/public');
if (PHP_SAPI === 'cli') {
    switch ($argv[1] ?? '') {
        case 'reset-fixture':
            $user = App\Models\User::where('email', 'qa@example.test')->where('name', 'Release QA')->sole();
            $user->forceFill(['password' => 'Local-QA-only-928!', 'remember_token' => null])->save();
            Illuminate\Support\Facades\DB::table('password_reset_tokens')->delete();
            Illuminate\Support\Facades\Cache::flush();
            if (is_file($root.'/fail-mail')) unlink($root.'/fail-mail');
            break;
        case 'assets':
            Illuminate\Support\Facades\File::copyDirectory('/workspace/backend/public', $root.'/public');
            Illuminate\Support\Facades\Artisan::call('filament:assets');
            echo "Disposable Filament assets prepared.\n";
            break;
        case 'mail':
            echo file_get_contents($root.'/reset-mail.json');
            break;
        case 'clear-limits':
            Illuminate\Support\Facades\Cache::flush(); // disposable test cache ONLY
            break;
        case 'expire':
            Illuminate\Support\Facades\DB::table('password_reset_tokens')->update(['created_at' => now()->subMinutes(61)]);
            break;
        case 'failure-on':
            touch($root.'/fail-mail');
            break;
        case 'failure-off':
            if (is_file($root.'/fail-mail')) unlink($root.'/fail-mail');
            break;
        case 'allow-next-mail':
            Illuminate\Support\Facades\Cache::flush();
            Illuminate\Support\Facades\DB::table('password_reset_tokens')->update(['created_at' => now()->subSeconds(61)]);
            break;
        case 'state':
            echo json_encode([
                'users' => App\Models\User::count(),
                'resetTokens' => Illuminate\Support\Facades\DB::table('password_reset_tokens')->count(),
                'mailCount' => is_file($root.'/mail-count') ? (int) file_get_contents($root.'/mail-count') : 0,
                'previews' => App\Models\PreviewBuild::count(),
                'publications' => App\Models\ProductionPublication::count(),
            ]);
            break;
        default: throw new RuntimeException('Unknown disposable password QA command');
    }
    exit;
}

// Playwright's local proxy models the canonical HTTPS origin without changing
// DNS or contacting the real admin host. Backend gets a local HTTP connection.
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = '443';
$_SERVER['HTTP_HOST'] = 'admin.madebymadlen.de';
config(['app.url' => 'https://admin.madebymadlen.de', 'session.secure' => true]);
Illuminate\Support\Facades\Event::listen(Illuminate\Notifications\Events\NotificationSending::class, function ($event) use ($root): void {
    if ($event->notification instanceof App\Notifications\AdminResetPassword && is_file($root.'/fail-mail')) {
        throw new RuntimeException('Synthetic SMTP failure');
    }
});
Illuminate\Support\Facades\Event::listen(Illuminate\Notifications\Events\NotificationSent::class, function ($event) use ($root): void {
    if (! $event->notification instanceof App\Notifications\AdminResetPassword) return;
    $mail = $event->notification->toMail($event->notifiable);
    file_put_contents($root.'/reset-mail.json', json_encode(['url' => $mail->actionUrl]));
    chmod($root.'/reset-mail.json', 0600);
    $count = is_file($root.'/mail-count') ? (int) file_get_contents($root.'/mail-count') : 0;
    file_put_contents($root.'/mail-count', (string) ($count + 1));
});
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file($root.'/public'.$path) && ! str_ends_with($path, '.php')) return false;
$app->handleRequest(Illuminate\Http\Request::capture());
