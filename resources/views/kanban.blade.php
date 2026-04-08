<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>phpKanMaster</title>

    {{-- Bootstrap 5.3 --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    {{-- Custom CSS --}}
    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body>
    {{-- Navbar --}}
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">phpKanMaster</span>
            <div class="d-flex">
                <a href="/logout" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    {{-- Main Board --}}
    <main class="container-fluid mt-3">
        <div id="board" class="row flex-nowrap overflow-auto">
            {{-- Columns rendered by App.Board --}}
        </div>
    </main>

    {{-- jQuery 4.0 --}}
    <script src="https://code.jquery.com/jquery-4.0.0.min.js"></script>

    {{-- Bootstrap JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    {{-- App JS --}}
    <script>
        window.POSTGREST_URL = '{{ env('PGRST_BASE_URL', '/api') }}';
    </script>
    <script src="/assets/js/app.js"></script>
</body>
</html>
