<?php

namespace Tests\Feature;

use App\Mail\ContactInquiryMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class ContactInquiryTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGIN = 'https://madebymadlen.de';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'contact.enabled' => true,
            'contact.require_origin' => true,
            'contact.allowed_origins' => [self::ORIGIN, 'https://admin.madebymadlen.de'],
            'contact.mailer' => 'array',
            'contact.from.address' => 'hi@madebymadlen.de',
            'contact.from.name' => 'Made by Madlen',
            'contact.recipient' => 'contact@madlenmedvedovskyy.de',
            'contact.rate_limit.per_minute' => 3,
            'contact.rate_limit.per_hour' => 10,
            'contact.form_time.minimum_seconds' => 3,
            'contact.form_time.maximum_seconds' => 7200,
        ]);
    }

    public function test_valid_inquiry_uses_fixed_addresses_and_visitor_reply_to(): void
    {
        Mail::fake();

        $payload = $this->validPayload() + [
            'from' => 'attacker@example.net',
            'to' => 'attacker@example.net',
            'subject' => 'Injected subject',
        ];

        $this->withHeader('Origin', self::ORIGIN)
            ->postJson('/api/contact', $payload)
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', self::ORIGIN)
            ->assertJson(['ok' => true]);

        Mail::assertSent(ContactInquiryMail::class, function (ContactInquiryMail $mail): bool {
            return $mail->hasFrom('hi@madebymadlen.de', 'Made by Madlen')
                && $mail->hasTo('contact@madlenmedvedovskyy.de')
                && $mail->hasReplyTo('visitor@example.com', 'Erika Beispiel')
                && $mail->hasSubject('Neue Anfrage über madebymadlen.de')
                && $mail->inquiry['message'] === '<script>alert("x")</script> Sichere Nachricht.';
        });
    }

    public function test_validation_bot_checks_and_disallowed_origins_do_not_send_mail(): void
    {
        Mail::fake();

        $this->withHeader('Origin', self::ORIGIN)
            ->postJson('/api/contact', array_replace($this->validPayload(), [
                'email' => 'not-an-email',
                'privacy' => '0',
            ]))
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->withHeader('Origin', self::ORIGIN)
            ->postJson('/api/contact', array_replace($this->validPayload(), ['website' => 'spam.example']))
            ->assertStatus(422);

        $this->withHeader('Origin', 'https://evil.example')
            ->postJson('/api/contact', $this->validPayload())
            ->assertForbidden()
            ->assertJson(['ok' => false]);

        Mail::assertNothingSent();
    }

    public function test_preflight_and_rate_limit_are_scoped_and_localized(): void
    {
        Mail::fake();

        $this->withHeaders([
            'Origin' => self::ORIGIN,
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/contact')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', self::ORIGIN)
            ->assertHeaderMissing('Access-Control-Allow-Credentials');

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
                ->withHeader('Origin', self::ORIGIN)
                ->postJson('/api/contact', $this->validPayload())
                ->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->withHeader('Origin', self::ORIGIN)
            ->postJson('/api/contact', $this->validPayload())
            ->assertStatus(429)
            ->assertJson([
                'ok' => false,
                'message' => 'Zu viele Versuche. Bitte warten Sie, bevor Sie es erneut versuchen.',
            ]);
    }

    public function test_mail_failure_returns_error_without_transport_details_or_false_success(): void
    {
        Mail::shouldReceive('mailer')
            ->once()
            ->with('array')
            ->andThrow(new RuntimeException('private SMTP diagnostic'));

        $response = $this->withHeader('Origin', self::ORIGIN)
            ->postJson('/api/contact', $this->validPayload('en'))
            ->assertStatus(503)
            ->assertHeader('Retry-After', '60')
            ->assertJson([
                'ok' => false,
                'message' => 'The message could not be sent right now. Please email Madlen directly.',
            ]);

        $this->assertStringNotContainsString('private SMTP diagnostic', $response->getContent());
    }

    public function test_disabled_contact_flow_never_falls_back_to_log_mail(): void
    {
        Mail::fake();
        config()->set('contact.enabled', false);

        $this->withHeader('Origin', self::ORIGIN)
            ->postJson('/api/contact', $this->validPayload())
            ->assertStatus(503)
            ->assertJson(['ok' => false]);

        Mail::assertNothingSent();
    }

    private function validPayload(string $language = 'de'): array
    {
        return [
            'language' => $language,
            'name' => 'Erika Beispiel',
            'email' => 'visitor@example.com',
            'phone' => '+49 711 123456',
            'request_type' => $language === 'en' ? 'Wedding' : 'Hochzeit',
            'preferred_date' => '05.–07.09.2027',
            'location' => 'Stuttgart',
            'message' => '<script>alert("x")</script> Sichere Nachricht.',
            'privacy' => '1',
            'website' => '',
            'form_started_at' => time() - 10,
        ];
    }
}
