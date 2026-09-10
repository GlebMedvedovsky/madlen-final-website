<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ContactInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $trimmed = [];
        foreach ([
            'language', 'name', 'email', 'phone', 'request_type', 'preferred_date',
            'location', 'message', 'privacy', 'website', 'form_started_at',
        ] as $key) {
            $value = $this->input($key);
            $trimmed[$key] = is_string($value) ? trim($value) : $value;
        }

        $this->merge($trimmed);
    }

    public function rules(): array
    {
        $language = $this->input('language') === 'en' ? 'en' : 'de';

        return [
            'language' => ['required', Rule::in(['de', 'en'])],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'phone' => ['nullable', 'string', 'max:60'],
            'request_type' => ['required', Rule::in(config("contact.request_types.{$language}", []))],
            'preferred_date' => ['required', 'string', 'max:160'],
            'location' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'privacy' => ['accepted'],
            'website' => ['nullable', 'string', 'max:0'],
            'form_started_at' => ['required', 'integer'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $startedAt = filter_var($this->input('form_started_at'), FILTER_VALIDATE_INT);
                if ($startedAt === false) {
                    return;
                }

                $elapsed = time() - $startedAt;
                $minimum = (int) config('contact.form_time.minimum_seconds', 3);
                $maximum = (int) config('contact.form_time.maximum_seconds', 7200);
                if ($elapsed < $minimum || $elapsed > $maximum) {
                    $validator->errors()->add('form_started_at', $this->languageMessage(
                        'Bitte laden Sie das Formular neu und versuchen Sie es noch einmal.',
                        'Please reload the form and try again.',
                    ));
                }
            },
        ];
    }

    public function inquiry(): array
    {
        return $this->safe()->only([
            'language', 'name', 'email', 'phone', 'request_type',
            'preferred_date', 'location', 'message',
        ]);
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'ok' => false,
            'message' => $this->languageMessage(
                'Bitte prüfen Sie die markierten Angaben und versuchen Sie es erneut.',
                'Please check the form details and try again.',
            ),
            'errors' => $validator->errors(),
        ], 422));
    }

    private function languageMessage(string $de, string $en): string
    {
        return $this->input('language') === 'en' ? $en : $de;
    }
}
