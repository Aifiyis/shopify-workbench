@if (session('success'))
    <div class="admin-alert admin-alert-success" role="status">
        {{ session('success') }}
    </div>
@endif

@if (session('status'))
    <div class="admin-alert admin-alert-status" role="status">
        {{ session('status') }}
    </div>
@endif

@if (session('error'))
    <div class="admin-alert admin-alert-error" role="alert">
        {{ session('error') }}
    </div>
@endif
