<?php

namespace App\Http\Controllers;

use Firebase\JWT\JWT;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgentTokenController extends Controller
{
    public function token(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        /** @var SessionGuard $guard */
        $guard = Auth::guard('single');
        if (! $guard->attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $secret = config('app.jwt_secret');
        $now = time();

        $payload = [
            'iss' => config('app.url'),
            'sub' => config('auth.credentials.username'),
            'role' => 'agent',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $token = JWT::encode($payload, $secret, 'HS256');

        return response()->json([
            'token' => $token,
            'expires_in' => 3600,
        ]);
    }
}
