<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureContactOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = rtrim((string) $request->headers->get('Origin'), '/');
        $allowedOrigins = config('contact.allowed_origins', []);
        $requiresOrigin = (bool) config('contact.require_origin', true);

        if (($origin === '' && $requiresOrigin) || ($origin !== '' && ! in_array($origin, $allowedOrigins, true))) {
            return new JsonResponse([
                'ok' => false,
                'message' => $this->message($request, 'origin'),
            ], Response::HTTP_FORBIDDEN);
        }

        $response = $next($request);

        if ($origin !== '') {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Accept, Content-Type');
            $response->headers->set('Access-Control-Max-Age', '600');
            $response->headers->set('Vary', 'Origin');
        }

        return $response;
    }

    private function message(Request $request, string $type): string
    {
        $language = $request->input('language') === 'en' ? 'en' : 'de';

        return match ([$language, $type]) {
            ['en', 'origin'] => 'This form cannot be submitted from this website address.',
            default => 'Dieses Formular kann von dieser Website-Adresse nicht gesendet werden.',
        };
    }
}
