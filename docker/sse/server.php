<?php

/**
 * phpKanMaster SSE Server
 *
 * Connects to PostgreSQL, LISTENs on notification channels,
 * and streams Server-Sent Events to connected browsers.
 *
 * Channels: task_changes, category_changes, file_changes, note_changes
 * Events:   tasks-changed, categories-changed, files-changed, notes-changed
 */

// ── Configuration ────────────────────────────────────────────────────────────

$pgDsn      = getenv('SSE_DATABASE_URL') ?: 'pgsql:host=db;port=5432;dbname=kanban';
$pgUser     = getenv('SSE_DB_USER')      ?: 'kanban';
$pgPassword = getenv('SSE_DB_PASSWORD')  ?: getenv('DB_PASSWORD') ?: 'kanban_secret';
$port       = (int)(getenv('SSE_PORT') ?: 8080);
$heartbeat  = (int)(getenv('SSE_HEARTBEAT') ?: 30); // seconds

$channels = [
    'task_changes'     => 'tasks-changed',
    'category_changes' => 'categories-changed',
    'file_changes'     => 'files-changed',
    'note_changes'     => 'notes-changed',
];

// ── Trigger bootstrap ────────────────────────────────────────────────────────

if (!\function_exists('ensureTriggers')) {
    function ensureTriggers(PDO $pdo): void
    {
        $triggers = [
            ['table' => 'tasks',       'channel' => 'task_changes'],
            ['table' => 'categories',  'channel' => 'category_changes'],
            ['table' => 'task_files',  'channel' => 'file_changes'],
            ['table' => 'task_notes',  'channel' => 'note_changes'],
        ];

        foreach ($triggers as $t) {
            $table   = $t['table'];
            $channel = $t['channel'];
            $fn      = "notify_{$channel}";
            $trg     = "trg_{$channel}";

            $pdo->exec(<<<SQL
                CREATE OR REPLACE FUNCTION {$fn}() RETURNS trigger AS \$\$
                BEGIN
                    PERFORM pg_notify(
                        '{$channel}',
                        json_build_object(
                            'op', TG_OP,
                            'table', TG_TABLE_NAME
                        )::text
                    );
                    RETURN NEW;
                END;
                \$\$ LANGUAGE plpgsql
            SQL);

            $exists = $pdo->query(
                "SELECT 1 FROM information_schema.triggers WHERE event_object_table = '{$table}' AND trigger_name = '{$trg}'"
            )->fetch();

            if (!$exists) {
                $pdo->exec("CREATE TRIGGER {$trg} AFTER INSERT OR UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION {$fn}()");
            }
        }
    }
}

// ── PostgreSQL connection with LISTEN ────────────────────────────────────────

if (!\function_exists('connectPg')) {
    function connectPg(): PDO
    {
        global $pgDsn, $pgUser, $pgPassword;
        $pdo = new PDO($pgDsn, $pgUser, $pgPassword, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('LISTEN task_changes');
        $pdo->exec('LISTEN category_changes');
        $pdo->exec('LISTEN file_changes');
        $pdo->exec('LISTEN note_changes');
        return $pdo;
    }
}

// ── HTTP server ──────────────────────────────────────────────────────────────

require_once __DIR__ . '/vendor/autoload.php';

use React\EventLoop\Loop;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Stream\ThroughStream;

// Shared state: map of ThroughStream => true
$clients    = [];
$notifyConn = null;

// Connect and bootstrap
$connectDb = function() use (&$notifyConn) {
    try {
        $pdo = connectPg();
        ensureTriggers($pdo);
        echo "PostgreSQL connected, triggers ensured\n";

        // Separate connection for non-blocking notification polling
        $notifyConn = connectPg();
        echo "Notification listener connected\n";
    } catch (\Throwable $e) {
        echo "PostgreSQL connection failed: {$e->getMessage()}\n";
        Loop::addTimer(5, $connectDb);
    }
};

$connectDb();

// Poll PostgreSQL notifications (non-blocking)
$pollNotifications = function() use (&$notifyConn, $channels, &$clients) {
    if (!$notifyConn) return;

    try {
        while ($result = $notifyConn->pgsqlGetNotify(PDO::FETCH_ASSOC, 0)) {
            $pgChannel = $result['message'] ?? '';
            $eventName  = $channels[$pgChannel] ?? null;
            if (!$eventName) continue;

            $payload = $result['payload'] ?? '{}';
            $data    = "event: {$eventName}\ndata: {$payload}\n\n";

            foreach ($clients as $stream) {
                $stream->write($data);
            }
        }
    } catch (\Throwable $e) {
        echo "Notification poll error: {$e->getMessage()}\n";
    }
};

Loop::addPeriodicTimer(0.5, $pollNotifications);

// Heartbeat: keep connections alive through proxies
Loop::addPeriodicTimer($heartbeat, function() use (&$clients) {
    $data = ": heartbeat " . time() . "\n\n";
    foreach ($clients as $stream) {
        $stream->write($data);
    }
});

// HTTP handler
$handler = function(\Psr\Http\Message\ServerRequestInterface $request) use (&$clients) {
    $path = $request->getUri()->getPath();

    if ($path === '/health') {
        return new Response(200, ['Content-Type' => 'text/plain'], 'ok');
    }

    if ($path === '/sse') {
        $stream = new ThroughStream();

        // Send retry directive for EventSource auto-reconnect
        $stream->write("retry: 5000\n\n");

        $clients[] = $stream;

        // Clean up on close
        $stream->on('close', function() use ($stream, &$clients) {
            $clients = array_values(array_filter($clients, fn($s) => $s !== $stream));
            echo "Client disconnected (" . count($clients) . " connected)\n";
        });

        echo "Client connected (" . count($clients) . " connected)\n";

        return new Response(
            200,
            [
                'Content-Type'      => 'text/event-stream',
                'Cache-Control'     => 'no-cache',
                'Connection'        => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ],
            $stream
        );
    }

    return new Response(404, ['Content-Type' => 'text/plain'], 'Not Found');
};

$server = new HttpServer($handler);
$socket = new React\Socket\SocketServer("0.0.0.0:{$port}");
$server->listen($socket);

echo "SSE server listening on port {$port}\n";