<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactInquiryRequest;
use App\Mail\ContactInquiryMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ContactInquiryController extends Controller
{
    public function options(): Response
    {
        return response()->noContent();
    }

    public function store(ContactInquiryRequest $request): JsonResponse
    {
        $language = $request->input('language') === 'en' ? 'en' : 'de';
        if (! $this->isReady()) {
            return $this->unavailable($language);
        }

        try {
            Mail::mailer((string) config('contact.mailer'))
                ->to((string) config('contact.recipient'))
                ->send(new ContactInquiryMail($request->inquiry()));
        } catch (Throwable $exception) {
            Log::warning('Kontaktformular-Mailtransport fehlgeschlagen.', [
                'exception_class' => get_debug_type($exception),
            ]);

            return $this->unavailable($language);
        }

        return response()->json([
            'ok' => true,
            'message' => $language === 'en'
                ? 'Thank you. Your inquiry has been sent.'
                : 'Vielen Dank. Ihre Anfrage wurde gesendet.',
        ]);
    }

    private function isReady(): bool
    {
        if (! config('contact.enabled')) {
            return false;
        }

        $mailer = (string) config('contact.mailer');
        if (app()->isProduction() && $mailer !== 'smtp') {
            return false;
        }

        return filter_var(config('contact.from.address'), FILTER_VALIDATE_EMAIL) !== false
            && filter_var(config('contact.recipient'), FILTER_VALIDATE_EMAIL) !== false;
    }

    private function unavailable(string $language): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => $language === 'en'
                ? 'The message could not be sent right now. Please email Madlen directly.'
                : 'Die Nachricht konnte gerade nicht gesendet werden. Bitte schreiben Sie Madlen direkt per E-Mail.',
        ], 503, ['Retry-After' => '60']);
    }
}
