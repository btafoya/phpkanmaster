<?php

namespace App\Http\Controllers;

use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WebhookController extends Controller
{
    public function __construct(
        private readonly WebhookService $webhookService
    ) {}

    public function handle(Request $request, string $source): JsonResponse
    {
        // Validate source is alphanumeric (prevent injection)
        if (! preg_match('/^[a-z0-9_]+$/i', $source)) {
            Log::warning('Invalid webhook source format', ['source' => $source]);
            return response()->json(['error' => 'Invalid source format'], 422);
        }

        // Check IP allowlist
        if (! $this->isIpAllowed($request)) {
            Log::warning('Webhook request rejected - IP not in allowlist', [
                'source' => $source,
                'client_ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // Validate JSON payload
        $validator = Validator::make($request->all(), [
            'event_type' => 'required|string',
            'issue' => 'required|array',
            'issue.id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 400);
        }

        try {
            $payload = $request->json()->all();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid JSON payload'], 400);
        }

        $result = $this->webhookService->handleWebhook($source, $payload);

        if (! ($result['success'] ?? false)) {
            $code = $result['code'] ?? 500;
            return response()->json(['error' => $result['error'] ?? 'Unknown error'], $code);
        }

        return response()->json([
            'success' => true,
            'task_id' => $result['task_id'] ?? null,
            'skipped' => $result['skipped'] ?? false,
            'has_conflict' => $result['has_conflict'] ?? false,
        ], 200);
    }

    private function isIpAllowed(Request $request): bool
    {
        $allowedIps = config('app.webhook_allowed_ips', '');
        if (empty($allowedIps)) {
            // No allowlist configured - allow all (not recommended but backward compatible)
            return true;
        }

        $clientIp = $request->ip();
        if (! $clientIp) {
            return false;
        }

        $allowedList = array_map('trim', explode(',', $allowedIps));

        foreach ($allowedList as $allowed) {
            if ($this->ipMatches($clientIp, $allowed)) {
                return true;
            }
        }

        return false;
    }

    private function ipMatches(string $ip, string $rule): bool
    {
        // Check if it's a CIDR notation
        if (str_contains($rule, '/')) {
            return $this->ipInCidr($ip, $rule);
        }

        // Exact match
        return $ip === $rule;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);

        if (! is_numeric($mask) || $mask < 0 || $mask > 32) {
            return false;
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $maskBits = -1 << (32 - (int) $mask);
        $maskedIp = $ipLong & $maskBits;
        $maskedSubnet = $subnetLong & $maskBits;

        return $maskedIp === $maskedSubnet;
    }
}