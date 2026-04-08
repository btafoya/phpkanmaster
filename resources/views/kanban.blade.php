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
        <div id="kanban-board" class="d-flex gap-3 overflow-x-auto py-4">
            <div class="kanban-column" data-column="new">
                <div class="column-header d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">New</h5>
                    <span class="badge bg-secondary task-count">0</span>
                </div>
                <div class="task-list" id="list-new"></div>
            </div>
            <div class="kanban-column" data-column="in_progress">
                <div class="column-header d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">In Progress</h5>
                    <span class="badge bg-secondary task-count">0</span>
                </div>
                <div class="task-list" id="list-in_progress"></div>
            </div>
            <div class="kanban-column" data-column="review">
                <div class="column-header d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Review</h5>
                    <span class="badge bg-secondary task-count">0</span>
                </div>
                <div class="task-list" id="list-review"></div>
            </div>
            <div class="kanban-column" data-column="on_hold">
                <div class="column-header d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">On Hold</h5>
                    <span class="badge bg-secondary task-count">0</span>
                </div>
                <div class="task-list" id="list-on_hold"></div>
            </div>
            <div class="kanban-column" data-column="done">
                <div class="column-header d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Done</h5>
                    <span class="badge bg-secondary task-count">0</span>
                </div>
                <div class="task-list" id="list-done"></div>
            </div>
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
