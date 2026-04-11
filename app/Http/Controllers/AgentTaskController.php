<?php

namespace App\Http\Controllers;

use Firebase\JWT\JWT;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

class AgentTaskController extends Controller
{
    private const POSTGREST_URL = 'http://postgrest:3000';

    private const ALLOWED_CREATE_FIELDS = [
        'title',
        'description',
        'priority',
        'category_id',
        'due_date',
        'task_column',
        'position',
        'reminder_at',
        'parent_id',
    ];

    private const ALLOWED_UPDATE_FIELDS = [
        'title',
        'description',
        'priority',
        'category_id',
        'due_date',
        'task_column',
        'position',
        'reminder_at',
        'parent_id',
    ];

    public function store(Request $request): JsonResponse
    {
        $jwt = $this->validateJwt($request);
        if ($jwt instanceof JsonResponse) {
            return $jwt;
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|string|in:low,medium,high',
            'category_id' => 'nullable|uuid',
            'due_date' => 'nullable|date',
            'task_column' => 'nullable|string|in:new,in_progress,review,on_hold,done',
            'position' => 'nullable|integer|min:0',
            'reminder_at' => 'nullable|date',
            'parent_id' => 'nullable|uuid',
        ]);

        $data = $this->filterFields($validated, self::ALLOWED_CREATE_FIELDS);

        // Set defaults
        $data['priority'] = $data['priority'] ?? 'medium';
        $data['task_column'] = $data['task_column'] ?? 'new';

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$jwt}",
            'Prefer' => 'return=representation',
        ])->post(self::POSTGREST_URL . '/tasks', $data);

        if (! $response->successful()) {
            return response()->json([
                'error' => 'Failed to create task',
                'details' => $response->json(),
            ], $response->status());
        }

        $tasks = $response->json();
        return response()->json($tasks[0] ?? $tasks, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $jwt = $this->validateJwt($request);
        if ($jwt instanceof JsonResponse) {
            return $jwt;
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|string|in:low,medium,high',
            'category_id' => 'nullable|uuid',
            'due_date' => 'nullable|date',
            'task_column' => 'nullable|string|in:new,in_progress,review,on_hold,done',
            'position' => 'nullable|integer|min:0',
            'reminder_at' => 'nullable|date',
            'parent_id' => 'nullable|uuid',
        ]);

        $data = $this->filterFields($validated, self::ALLOWED_UPDATE_FIELDS);

        if (empty($data)) {
            return response()->json(['error' => 'No valid fields to update'], 422);
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$jwt}",
            'Prefer' => 'return=representation',
        ])->patch(self::POSTGREST_URL . "/tasks?id=eq.{$id}", $data);

        if (! $response->successful()) {
            return response()->json([
                'error' => 'Failed to update task',
                'details' => $response->json(),
            ], $response->status());
        }

        $tasks = $response->json();
        return response()->json($tasks[0] ?? $tasks, 200);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $jwt = $this->validateJwt($request);
        if ($jwt instanceof JsonResponse) {
            return $jwt;
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$jwt}",
        ])->delete(self::POSTGREST_URL . "/tasks?id=eq.{$id}");

        if (! $response->successful()) {
            return response()->json([
                'error' => 'Failed to delete task',
                'details' => $response->json(),
            ], $response->status());
        }

        return response()->json(['deleted' => true], 200);
    }

    private function validateJwt(Request $request): JsonResponse|string
    {
        $authHeader = $request->header('Authorization');
        if (! $authHeader || ! str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['error' => 'Missing or invalid Authorization header'], 401);
        }

        $token = substr($authHeader, 7);

        try {
            $secret = config('app.jwt_secret');
            $decoded = JWT::decode($token, $secret);

            if (($decoded->role ?? '') !== 'agent') {
                return response()->json(['error' => 'Invalid role in token'], 403);
            }

            return $token;
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid or expired token: ' . $e->getMessage()], 401);
        }
    }

    private function filterFields(array $data, array $allowed): array
    {
        return array_intersect_key($data, array_flip($allowed));
    }
}