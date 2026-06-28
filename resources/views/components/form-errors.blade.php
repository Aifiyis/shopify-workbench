@if ($errors->any())
    <div class="admin-alert admin-alert-error" role="alert">
        <strong>提交内容有误，请检查以下信息：</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
