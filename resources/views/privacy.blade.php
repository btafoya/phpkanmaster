<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy — phpKanMaster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #212529; color: #dee2e6; }
        a { color: #0d6efd; }
        h1, h2, h3 { color: #fff; }
        hr { border-color: #495057; }
        code { background: #343a40; padding: 0.125rem 0.375rem; border-radius: 0.25rem; color: #adb5bd; }
    </style>
</head>
<body>
<div class="container py-5" style="max-width: 720px;">
    <h1 class="mb-4">Privacy Policy</h1>

    <p class="text-muted">Last updated: April 2026</p>

    <h2>Data Storage</h2>
    <p>phpKanMaster is a single-user personal productivity tool. All your task data — including task titles, descriptions, categories, due dates, and file attachments — is stored exclusively on your own server. No data is transmitted to any third party except when optional push notification channels (Pushover, Twilio, or RocketChat) are configured by you.</p>

    <h2>No Tracking or Analytics</h2>
    <p>This application does not use any analytics, telemetry, or tracking services. There are no cookies used for tracking purposes. No usage data is collected or aggregated.</p>

    <h2>Notification Channels</h2>
    <p>If you enable push notification channels (Pushover, Twilio, or RocketChat), reminder data (task title, due date) may be transmitted to those third-party services to deliver notifications. This is entirely optional and configured by you in your environment variables.</p>

    <h2>Local Operation</h2>
    <p>phpKanMaster is designed to run on a private server or local machine. It has no external API calls to phpKanMaster servers or any associated services.</p>

    <h2>Security</h2>
    <p>You are responsible for securing your own server and instance. Use HTTPS in production (Caddy handles this automatically via Let's Encrypt), keep your <code>APP_KEY</code> secret, and ensure your <code>.env</code> file is not publicly accessible.</p>

    <h2>Contact</h2>
    <p>Since this is a personal single-user tool with no external services, there is no formal support contact. For issues, refer to the project's documentation.</p>

    <hr class="my-4">
    <a href="/" class="btn btn-outline-primary">Back to Board</a>
</div>
</body>
</html>