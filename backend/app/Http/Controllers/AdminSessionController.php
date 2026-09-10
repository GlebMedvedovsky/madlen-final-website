<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSessionController extends Controller
{
    public function csrfToken(Request $request): JsonResponse
    {
        return response()
            ->json(['csrfToken' => $request->session()->token()])
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }
}
