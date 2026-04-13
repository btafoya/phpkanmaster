<?php

namespace App\Services;

use App\Models\IssueMapping;
use App\Models\Task;
use App\Models\WebhookStatusMapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebhookService
{
    private const POSTGREST_URL = 'http://postgrest:3000';
    private const BUSINESS_CATEGORY_NAME = 'Business';

    public function handleWebhook(string $source, array $payload): array
    {
        $eventType = $payload['event_type'] ?? null;
        $issue = $payload['issue'] ?? [];

        Log::info('Webhook received', [
            'source' => $source,
            'event_type' => $eventType,
            'external_id' => $issue['id'] ?? null,
        ]);

        if (! $eventType) {
            return ['success' => false, 'error' => 'Missing event_type', 'code' => 422];
        }

        return match ($eventType) {
            'issue.created' => $this->handleIssueCreated($source, $issue),
            'issue.updated' => $this->handleIssueUpdated($source, $issue),
            'issue.note_added' => $this->handleIssueNoteAdded($source, $issue),
            default => $this->handleUnknownEvent($eventType),
        };
    }

    private function handleIssueCreated(string $source, array $issue): array
    {
        $externalId = $issue['id'] ?? null;
        if (! $externalId) {
            return ['success' => false, 'error' => 'Missing issue.id', 'code' => 422];
        }

        // Check idempotency - skip if already exists
        $existing = IssueMapping::where('source', $source)
            ->where('external_id', $externalId)
            ->first();

        if ($existing) {
            Log::info('Issue already mapped, skipping issue.created', [
                'source' => $source,
                'external_id' => $externalId,
                'task_id' => $existing->task_id,
            ]);
            return ['success' => true, 'skipped' => true, 'task_id' => $existing->task_id];
        }

        // Build task data
        $taskData = $this->buildTaskData($source, $issue);
        $additionalFieldsText = $this->formatAdditionalFields($issue);

        if ($additionalFieldsText) {
            $taskData['description'] = ($taskData['description'] ?? '') . "\n\n" . $additionalFieldsText;
        }

        // Assign Business category to all webhook tasks
        $businessCategoryId = $this->getBusinessCategoryId();
        if ($businessCategoryId) {
            $taskData['category_id'] = $businessCategoryId;
        }

        // Create task via PostgREST
        $taskId = $this->createTask($taskData);
        if (! $taskId) {
            return ['success' => false, 'error' => 'Failed to create task', 'code' => 500];
        }

        // Create mapping
        IssueMapping::create([
            'id' => Str::uuid()->toString(),
            'external_id' => $externalId,
            'task_id' => $taskId,
            'source' => $source,
            'project_id' => $issue['project']['id'] ?? null,
            'last_synced_at' => now(),
        ]);

        Log::info('Issue created', [
            'source' => $source,
            'external_id' => $externalId,
            'task_id' => $taskId,
        ]);

        return ['success' => true, 'task_id' => $taskId];
    }

    private function handleIssueUpdated(string $source, array $issue): array
    {
        $externalId = $issue['id'] ?? null;
        if (! $externalId) {
            return ['success' => false, 'error' => 'Missing issue.id', 'code' => 422];
        }

        $mapping = IssueMapping::where('source', $source)
            ->where('external_id', $externalId)
            ->first();

        if (! $mapping) {
            Log::warning('No mapping found for issue.updated', [
                'source' => $source,
                'external_id' => $externalId,
            ]);
            return ['success' => false, 'error' => 'Issue not found', 'code' => 404];
        }

        $taskId = $mapping->task_id;

        // Sync notes if present
        if (! empty($issue['notes']) && is_array($issue['notes'])) {
            $this->syncNotes($taskId, $issue['notes'], $source);
        }

        // Build update data
        $taskData = $this->buildTaskData($source, $issue);
        $additionalFieldsText = $this->formatAdditionalFields($issue);

        if ($additionalFieldsText) {
            $currentDescription = $this->getCurrentTaskDescription($taskId);
            $taskData['description'] = ($currentDescription ?? '') . "\n\n" . $additionalFieldsText;
        }

        // Ensure Business category is assigned on update
        if (empty($taskData['category_id'])) {
            $businessCategoryId = $this->getBusinessCategoryId();
            if ($businessCategoryId) {
                $taskData['category_id'] = $businessCategoryId;
            }
        }

        // Check for conflicts (local modification since last sync)
        $hasConflict = $this->detectConflict($mapping, $issue);
        if ($hasConflict) {
            $conflictNote = $this->formatConflictNote($mapping, $issue);
            $this->appendNote($taskId, $conflictNote);
        } elseif (! empty($taskData)) {
            // Update task via PostgREST
            $this->updateTask($taskId, $taskData);
        }

        // Update last synced timestamp
        $mapping->last_synced_at = now();
        $mapping->save();

        Log::info('Issue updated', [
            'source' => $source,
            'external_id' => $externalId,
            'task_id' => $taskId,
            'has_conflict' => $hasConflict,
        ]);

        return ['success' => true, 'task_id' => $taskId, 'has_conflict' => $hasConflict];
    }

    private function handleIssueNoteAdded(string $source, array $issue): array
    {
        $externalId = $issue['id'] ?? null;
        if (! $externalId) {
            return ['success' => false, 'error' => 'Missing issue.id', 'code' => 422];
        }

        $mapping = IssueMapping::where('source', $source)
            ->where('external_id', $externalId)
            ->first();

        if (! $mapping) {
            Log::warning('No mapping found for issue.note_added', [
                'source' => $source,
                'external_id' => $externalId,
            ]);
            return ['success' => false, 'error' => 'Issue not found', 'code' => 404];
        }

        $taskId = $mapping->task_id;
        $notes = $issue['notes'] ?? [];

        if (empty($notes) || ! is_array($notes)) {
            return ['success' => true, 'task_id' => $taskId, 'skipped' => true];
        }

        // Only sync the single new note (first one in array for note_added event)
        $note = $notes[0];
        $noteId = $note['id'] ?? null;

        if ($noteId) {
            // Check idempotency - skip if note already exists
            $existingNote = $this->noteExists($taskId, $noteId, $source);
            if ($existingNote) {
                Log::info('Note already exists, skipping', [
                    'task_id' => $taskId,
                    'external_note_id' => $noteId,
                ]);
                return ['success' => true, 'task_id' => $taskId, 'skipped' => true];
            }
        }

        $noteContent = $this->formatNote($note);
        $this->appendNote($taskId, $noteContent, $noteId, $source);

        Log::info('Note added', [
            'source' => $source,
            'external_id' => $externalId,
            'task_id' => $taskId,
            'external_note_id' => $noteId,
        ]);

        return ['success' => true, 'task_id' => $taskId];
    }

    private function handleUnknownEvent(string $eventType): array
    {
        Log::warning('Unknown webhook event type', ['event_type' => $eventType]);
        return ['success' => false, 'error' => "Unknown event type: {$eventType}", 'code' => 422];
    }

    private function buildTaskData(string $source, array $issue): array
    {
        $data = [];

        if (isset($issue['summary'])) {
            $data['title'] = $issue['summary'];
        }

        if (isset($issue['description'])) {
            $data['description'] = $issue['description'];
        }

        if (isset($issue['priority'])) {
            $data['priority'] = $this->mapPriority($issue['priority']);
        }

        if (isset($issue['status'])) {
            $kanbanColumn = $this->mapStatusToColumn($source, $issue['status']);
            $data['task_column'] = $kanbanColumn;
        }

        return $data;
    }

    private function mapPriority(int|string $priority): string
    {
        // Map external priority IDs to phpKanMaster priorities:
        // 10=None → low, 20=Low → low, 30=Normal → medium,
        // 40=High → high, 50=Urgent → high, 60=Immediate → high
        $p = (int) $priority;

        if ($p >= 40) {
            return 'high';
        }

        if ($p >= 30) {
            return 'medium';
        }

        return 'low';
    }

    private function mapStatusToColumn(string $source, int $externalStatus): string
    {
        $mapping = WebhookStatusMapping::where('source', $source)
            ->where('external_status', $externalStatus)
            ->first();

        if ($mapping) {
            return $mapping->kanban_column;
        }

        Log::warning('No status mapping found, defaulting to new', [
            'source' => $source,
            'external_status' => $externalStatus,
        ]);

        return 'new';
    }

    private function formatAdditionalFields(array $issue): string
    {
        $fields = [];

        if (! empty($issue['reporter'])) {
            $reporter = $issue['reporter'];
            $fields[] = "Reporter: {$reporter['realname']} ({$reporter['username']})";
        }

        if (! empty($issue['handler'])) {
            $handler = $issue['handler'];
            $fields[] = "Handler: {$handler['realname']} ({$handler['username']})";
        }

        if (! empty($issue['project'])) {
            $fields[] = "Project: {$issue['project']['name']}";
        }

        if (! empty($issue['category'])) {
            $fields[] = "Category: {$issue['category']['name']}";
        }

        if (! empty($issue['tags']) && is_array($issue['tags'])) {
            $fields[] = "Tags: " . implode(', ', $issue['tags']);
        }

        if (isset($issue['severity'])) {
            $fields[] = "Severity: {$issue['severity']}";
        }

        if (! empty($issue['steps_to_reproduce'])) {
            $fields[] = "Steps to Reproduce:\n{$issue['steps_to_reproduce']}";
        }

        if (! empty($issue['additional_information'])) {
            $fields[] = "Additional Information:\n{$issue['additional_information']}";
        }

        // Append any other fields not explicitly handled
        $explicitFields = ['summary', 'description', 'status', 'priority', 'severity',
            'reporter', 'handler', 'project', 'category', 'tags', 'steps_to_reproduce',
            'additional_information', 'id', 'created_at', 'updated_at', 'notes'];

        $otherFields = array_diff_key($issue, array_flip($explicitFields));
        if (! empty($otherFields)) {
            $fields[] = "--- Additional Data ---\n" . json_encode($otherFields, JSON_PRETTY_PRINT);
        }

        if (empty($fields)) {
            return '';
        }

        return "--- Additional Fields ---\n" . implode("\n", $fields);
    }

    private function formatNote(array $note): string
    {
        $author = $note['author'] ?? [];
        $authorName = $author['realname'] ?? ($author['username'] ?? 'Unknown');
        $authorUsername = $author['username'] ?? '';
        $timestamp = $note['created_at'] ?? now()->toIso8601String();
        $text = $note['text'] ?? '';

        $header = "--- Note from {$authorName}";
        if ($authorUsername) {
            $header .= " ({$authorUsername})";
        }
        $header .= " on {$timestamp} ---";

        return "{$header}\n{$text}";
    }

    private function formatConflictNote(IssueMapping $mapping, array $issue): string
    {
        $timestamp = now()->format('c');
        $lastSync = $mapping->last_synced_at?->format('c') ?? 'never';

        $lines = [
            "--- Sync Conflict Detected on {$timestamp} ---",
            "Local task was modified after last sync ({$lastSync}).",
            "External changes:",
            "",
        ];

        // Compare key fields
        $currentTask = $this->getTask($mapping->task_id);
        if ($currentTask) {
            $externalStatus = $issue['status'] ?? null;
            if ($externalStatus !== null) {
                $externalColumn = $this->mapStatusToColumn($mapping->source, $externalStatus);
                $localColumn = $currentTask['task_column'] ?? 'unknown';
                if ($externalColumn !== $localColumn) {
                    $lines[] = "Field: status";
                    $lines[] = "  External: {$externalStatus} ({$externalColumn})";
                    $lines[] = "  Local was: {$localColumn}";
                    $lines[] = "";
                }
            }

            $externalHandler = $issue['handler']['username'] ?? null;
            $localHandler = $currentTask['handler_username'] ?? null;
            if ($externalHandler && $externalHandler !== $localHandler) {
                $lines[] = "Field: handler";
                $lines[] = "  External: {$issue['handler']['realname']} ({$externalHandler})";
                $lines[] = "  Local was: {$localHandler}";
                $lines[] = "";
            }
        }

        $lines[] = "--- End Sync Conflict ---";

        return implode("\n", $lines);
    }

    private function detectConflict(IssueMapping $mapping, array $issue): bool
    {
        if (! $mapping->last_synced_at) {
            return false;
        }

        $issueUpdatedAt = $issue['updated_at'] ?? null;
        if (! $issueUpdatedAt) {
            return false;
        }

        // Get current task to check if it was modified locally
        $currentTask = $this->getTask($mapping->task_id);
        if (! $currentTask) {
            return false;
        }

        $taskUpdatedAt = $currentTask['updated_at'] ?? null;
        if (! $taskUpdatedAt) {
            return false;
        }

        // If task was updated after our last sync, there's a conflict
        $taskTime = strtotime($taskUpdatedAt);
        $syncTime = $mapping->last_synced_at->getTimestamp();
        $issueTime = strtotime($issueUpdatedAt);

        // Conflict if task was modified locally after last sync but before the external update
        return $taskTime > $syncTime && $taskTime < $issueTime;
    }

    private function syncNotes(string $taskId, array $notes, string $source): void
    {
        foreach ($notes as $note) {
            $noteId = $note['id'] ?? null;

            // Skip if note already exists
            if ($noteId && $this->noteExists($taskId, $noteId, $source)) {
                continue;
            }

            $noteContent = $this->formatNote($note);
            $this->appendNote($taskId, $noteContent, $noteId, $source);
        }
    }

    private function noteExists(string $taskId, int|string $externalNoteId, string $source): bool
    {
        // Check task_notes table for existing note with this external ID
        // We store external note IDs in a metadata format in the note content
        // Look for pattern: --- Note from ... (external_id: XXX) ---
        $task = $this->getTask($taskId);
        if (! $task) {
            return false;
        }

        // Query task_notes directly
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('app.jwt_secret'),
        ])->get(self::POSTGREST_URL . '/task_notes?task_id=eq.' . $taskId);

        if (! $response->successful()) {
            return false;
        }

        $existingNotes = $response->json();
        $pattern = "/\(external_id: {$externalNoteId}\)/";

        foreach ($existingNotes as $existingNote) {
            if (preg_match($pattern, $existingNote['content'] ?? '')) {
                return true;
            }
        }

        return false;
    }

    private function appendNote(string $taskId, string $content, int|string|null $externalNoteId = null, string $source = ''): void
    {
        $noteContent = $content;
        if ($externalNoteId !== null) {
            $noteContent .= " (external_id: {$externalNoteId})";
        }

        Http::withHeaders([
            'Authorization' => 'Bearer ' . config('app.jwt_secret'),
            'Prefer' => 'return=representation',
        ])->post(self::POSTGREST_URL . '/task_notes', [
            'task_id' => $taskId,
            'content' => $noteContent,
        ]);
    }

    /**
     * Get or create the Business category via PostgREST.
     * All webhook-created tasks are assigned to this category.
     */
    private function getBusinessCategoryId(): ?string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('app.jwt_secret'),
        ])->get(self::POSTGREST_URL . '/categories?name=eq.' . urlencode(self::BUSINESS_CATEGORY_NAME));

        if (! $response->successful()) {
            Log::error('Failed to query Business category', ['response' => $response->body()]);
            return null;
        }

        $categories = $response->json();
        if (! empty($categories[0]['id'])) {
            return $categories[0]['id'];
        }

        // Create the Business category if it doesn't exist
        $createResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('app.jwt_secret'),
            'Prefer' => 'return=representation',
        ])->post(self::POSTGREST_URL . '/categories', [
            'name' => self::BUSINESS_CATEGORY_NAME,
            'color' => '#6c757d',
        ]);

        if (! $createResponse->successful()) {
            Log::error('Failed to create Business category', ['response' => $createResponse->body()]);
            return null;
        }

        $created = $createResponse->json();
        return $created[0]['id'] ?? $created['id'] ?? null;
    }

    private function createTask(array $data): ?string
    {
        // Ensure position is set
        if (! isset($data['position'])) {
            $maxPosition = $this->getMaxPosition($data['task_column'] ?? 'new');
            $data['position'] = $maxPosition + 1;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('app.jwt_secret'),
            'Prefer' => 'return=representation',
        ])->post(self::POSTGREST_URL . '/tasks', $data);

        if (! $response->successful()) {
            Log::error('Failed to create task via PostgREST', [
                'data' => $data,
                'response' => $response->json(),
            ]);
            return null;
        }

        $tasks = $response->json();
        return $tasks[0]['id'] ?? $tasks['id'] ?? null;
    }

    private function updateTask(string $taskId, array $data): bool
    {
        if (empty($data)) {
            return true;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('app.jwt_secret'),
            'Prefer' => 'return=representation',
        ])->patch(self::POSTGREST_URL . "/tasks?id=eq.{$taskId}", $data);

        if (! $response->successful()) {
            Log::error('Failed to update task via PostgREST', [
                'task_id' => $taskId,
                'data' => $data,
                'response' => $response->json(),
            ]);
            return false;
        }

        return true;
    }

    private function getTask(string $taskId): ?array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('app.jwt_secret'),
        ])->get(self::POSTGREST_URL . "/tasks?id=eq.{$taskId}");

        if (! $response->successful()) {
            return null;
        }

        $tasks = $response->json();
        return $tasks[0] ?? null;
    }

    private function getCurrentTaskDescription(string $taskId): ?string
    {
        $task = $this->getTask($taskId);
        return $task['description'] ?? null;
    }

    private function getMaxPosition(string $column): int
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('app.jwt_secret'),
        ])->get(self::POSTGREST_URL . "/tasks?task_column=eq.{$column}&order=position.desc&limit=1");

        if (! $response->successful()) {
            return 0;
        }

        $tasks = $response->json();
        return $tasks[0]['position'] ?? 0;
    }
}