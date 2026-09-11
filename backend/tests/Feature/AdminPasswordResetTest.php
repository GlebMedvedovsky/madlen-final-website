<?php

namespace Tests\Feature;

use App\Filament\Auth\RequestPasswordReset;
use App\Filament\Auth\ResetPassword;
use App\Models\User;
use App\Notifications\AdminResetPassword;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = 'Synthetic-old-password-73!';
    private const NEW = 'Synthetic-new-password-94!';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('p', 32)), 'app.locale' => 'de']);
        app()->setLocale('de');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
    }

    private function user(): User
    {
        return User::factory()->create(['email' => 'reset@example.test', 'password' => self::OLD]);
    }

    private function requestLink(string $email)
    {
        return Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $email])->call('request')
            ->assertHasNoFormErrors()
            ->assertNotified(RequestPasswordReset::MESSAGE)
            ->assertSet('data.email', null);
    }

    private function reset(string $email, string $token, string $password = self::NEW, ?string $confirmation = null)
    {
        return Livewire::test(ResetPassword::class, ['email' => $email, 'token' => $token])
            ->fillForm(['password' => $password, 'passwordConfirmation' => $confirmation ?? $password])
            ->call('resetPassword');
    }

    public function test_request_has_identical_public_result_for_known_unknown_and_broker_throttled_email(): void
    {
        Notification::fake();
        $user = $this->user();
        $this->requestLink($user->email);
        $this->requestLink('unknown@example.test');
        Notification::assertSentToTimes($user, AdminResetPassword::class, 1);
        $this->assertDatabaseCount('password_reset_tokens', 1);
        // Isolate the account throttle from the independent IP throttle.
        RateLimiter::clear('livewire-rate-limiter:'.sha1(RequestPasswordReset::class.'|request|127.0.0.1'));
        $this->requestLink($user->email);
        Notification::assertSentToTimes($user, AdminResetPassword::class, 1);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_email_is_german_https_signed_time_limited_and_not_controlled_by_host_or_app_url(): void
    {
        $user = $this->user();
        $token = Password::broker('users')->createToken($user);
        config(['app.url' => 'http://hostile.example']);
        URL::forceRootUrl('http://hostile.example');
        $notification = new AdminResetPassword($token);
        $mail = $notification->toMail($user);
        $this->assertSame('de', $notification->locale);
        $this->assertSame('https', parse_url($mail->actionUrl, PHP_URL_SCHEME));
        $this->assertSame('admin.madebymadlen.de', parse_url($mail->actionUrl, PHP_URL_HOST));
        parse_str(parse_url($mail->actionUrl, PHP_URL_QUERY), $query);
        $this->assertSame(3600, (int) $query['expires'] - now()->timestamp);
        $this->assertTrue(URL::hasValidSignature(\Illuminate\Http\Request::create($mail->actionUrl)));
        $this->assertSame('Madlen · Passwort zurücksetzen', $mail->subject);
        $html = (string) $mail->render();
        $this->assertStringContainsString('60 Minuten', $html);
        $this->assertStringContainsString('Falls die Schaltfläche', $html);
        $this->assertStringNotContainsString('All rights reserved', $html);
        $this->assertStringNotContainsString('If you', $html);
        $this->assertNotSame($token, DB::table('password_reset_tokens')->value('token'));
        $this->assertSame(60, config('auth.passwords.users.expire'));
        $this->assertSame(60, config('auth.passwords.users.throttle'));
        // Native signed GET rejects tampering/expiration, without consuming a token.
        URL::forceRootUrl(null);
        $this->get($mail->actionUrl)->assertOk();
        $this->get(str_replace('reset%40', 'other%40', $mail->actionUrl))->assertForbidden();
        $this->travel(61)->minutes();
        $this->get($mail->actionUrl)->assertForbidden();
    }

    public function test_success_revokes_old_password_remember_cookie_and_one_time_token(): void
    {
        $user = $this->user();
        $oldRemember = $user->getRememberToken();
        $token = Password::broker('users')->createToken($user);
        $this->reset($user->email, $token)->assertHasNoFormErrors()
            ->assertNotified(__('passwords.reset'))->assertRedirect(Filament::getLoginUrl());
        $this->assertTrue(Hash::check(self::NEW, $user->fresh()->password));
        $this->assertFalse(Hash::check(self::OLD, $user->fresh()->password));
        $this->assertNotSame($oldRemember, $user->fresh()->getRememberToken());
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertGuest();
        $this->reset($user->email, $token)->assertNotified(__('passwords.token'))->assertNoRedirect();
        $this->assertTrue(Hash::check(self::NEW, $user->fresh()->password));
    }

    public function test_invalid_expired_wrong_user_and_weak_or_unconfirmed_password_do_not_change_account(): void
    {
        $user = $this->user();
        $token = Password::broker('users')->createToken($user);
        $this->reset($user->email, 'invalid')->assertNotified(__('passwords.token'));
        $this->reset('unknown@example.test', $token)->assertNotified(__('passwords.user'));
        $this->travel(61)->seconds();
        $this->reset($user->email, $token, 'short')->assertHasFormErrors(['password'])
            ->assertSee('mindestens 12 Zeichen')->assertDontSee('mindestens 8 Zeichen');
        $this->reset($user->email, $token, self::NEW, 'Not-matching-36!')->assertHasFormErrors(['password']);
        $this->travel(61)->seconds();
        $this->reset($user->email, $token, 'longbutonlylowercase')->assertHasFormErrors(['password']);
        $this->travel(61)->minutes();
        $this->reset($user->email, $token)->assertNotified(__('passwords.token'));
        $this->assertTrue(Hash::check(self::OLD, $user->fresh()->password));
    }

    public function test_mail_failure_is_safe_generic_throttled_and_retryable_without_logging_secrets(): void
    {
        $user = $this->user();
        Log::spy();
        Notification::shouldReceive('sendNow')->once()->andThrow(new \RuntimeException('synthetic-sensitive-transport-detail'));
        $this->requestLink($user->email);
        Log::shouldHaveReceived('warning')->once()->with('Admin password reset email delivery failed; inspect mail configuration privately.');
        $this->assertTrue(Hash::check(self::OLD, $user->fresh()->password));
        $this->assertDatabaseCount('password_reset_tokens', 1);
        $this->travel(61)->seconds();
        Notification::fake();
        $this->requestLink($user->email);
        Notification::assertSentTo($user, AdminResetPassword::class);
    }

    public function test_request_ip_limit_and_disabled_registration_and_login_protection(): void
    {
        Notification::fake();
        $this->requestLink('unknown@example.test');
        $this->requestLink('unknown@example.test');
        Livewire::test(RequestPasswordReset::class)->fillForm(['email' => 'unknown@example.test'])
            ->call('request')->assertNotified('Zu viele Versuche.');
        $this->assertFalse(Filament::hasRegistration());
        $this->get('/admin/register')->assertNotFound();
        $this->get('/admin/login')->assertOk()->assertSee('Passwort vergessen?');
        $this->assertContains(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class, Filament::getCurrentPanel()->getMiddleware());
        Livewire::test(Login::class)->fillForm(['email' => 'unknown@example.test', 'password' => self::OLD])
            ->call('authenticate')->assertHasFormErrors(['email']);
    }

    public function test_long_utf8_password_and_reset_rate_limit_are_enforced_without_changing_password(): void
    {
        $user = $this->user();
        $token = Password::broker('users')->createToken($user);
        $this->reset($user->email, $token, str_repeat('Ä', 36).'a1!')
            ->assertHasFormErrors(['password']);
        $this->reset($user->email, 'invalid')->assertNotified(__('passwords.token'));
        $this->reset($user->email, $token)->assertNotified('Zu viele Versuche.')->assertNoRedirect();
        $this->assertTrue(Hash::check(self::OLD, $user->fresh()->password));
    }

    public function test_log_mailer_cannot_leak_reset_link_and_bad_email_does_not_issue_token(): void
    {
        Notification::fake();
        Log::spy();
        $user = $this->user();
        Livewire::test(RequestPasswordReset::class)->fillForm(['email' => 'not-an-email'])
            ->call('request')->assertHasFormErrors(['email']);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        config(['mail.default' => 'log']);
        $this->requestLink($user->email);
        Notification::assertNothingSent();
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_reset_cannot_reuse_the_previous_password_or_reveal_it_with_an_invalid_token(): void
    {
        $user = $this->user();
        $token = Password::broker('users')->createToken($user);
        $this->reset($user->email, 'invalid', self::OLD)->assertNotified(__('passwords.token'));
        $this->reset($user->email, $token, self::OLD)
            ->assertHasFormErrors(['password'])->assertSee('ein anderes Passwort');
        $this->assertTrue(Password::broker('users')->tokenExists($user, $token));
        $this->assertTrue(Hash::check(self::OLD, $user->fresh()->password));
    }

    public function test_old_sessions_cannot_access_dashboard_preview_media_or_csrf_recovery(): void
    {
        $user = $this->user();
        $oldHash = Auth::guard('web')->hashPasswordForCookie($user->password);
        $token = Password::broker('users')->createToken($user);
        $this->reset($user->email, $token)->assertNotified(__('passwords.reset'));
        foreach (['/admin', '/admin/session/csrf-token', '/admin/preview/not-a-token', '/admin/media/999'] as $path) {
            $this->actingAs($user->fresh())->withSession(['password_hash_web' => $oldHash]);
            $this->getJson($path)->assertUnauthorized();
            $this->assertGuest();
        }
    }

    public function test_unstamped_legacy_session_is_not_blessed_after_reset_but_real_login_is_stamped(): void
    {
        $user = $this->user();
        $this->actingAs($user);
        session()->forget('password_hash_web');
        $this->getJson('/admin/session/csrf-token')->assertUnauthorized();
        Auth::forgetGuards();
        Livewire::test(Login::class)->fillForm(['email' => $user->email, 'password' => self::OLD])
            ->call('authenticate')->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(session()->has('password_hash_web'));
        $this->get('/admin/session/csrf-token')->assertOk();
    }
}
