@extends('layouts.app')

@section('content')
    @php
        $editing = $craft->exists;
        $routeParameters = $returnTarget ? ['return_to' => $returnTarget] : [];
        $action = $editing
            ? route('processing-crafts.update', array_merge([$craft], $routeParameters))
            : route('processing-crafts.store', $routeParameters);
        $indexUrl = route('processing-crafts.index', $routeParameters);
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-4">
        <h1 class="m-0">{{ $editing ? '编辑工艺' : '新增工艺' }}</h1>
        <a class="button button-secondary no-underline" href="{{ $indexUrl }}">返回列表</a>
    </div>

    <form method="POST" action="{{ $action }}" class="mt-5 max-w-xl space-y-5">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div>
            <label for="name" class="mb-1 block font-semibold">工艺名称</label>
            <input id="name" name="name" required maxlength="255" value="{{ old('name', $craft->name) }}">
        </div>

        <div>
            <label for="parent_id" class="mb-1 block font-semibold">上级工艺</label>
            <select id="parent_id" name="parent_id" data-searchable-select data-option-type="craft" data-placeholder="无上级工艺">
                <option value="">无上级工艺</option>
                @foreach ($parents as $parent)
                    <option value="{{ $parent->id }}"
                            data-depth="{{ substr_count($parent->path, '-') }}"
                            data-path="{{ $parent->path }}"
                            @if ((string) old('parent_id', $craft->parent_id) === (string) $parent->id) selected @endif>
                        {{ $parent->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="flex gap-2 border-t border-gray-200 pt-4">
            <button type="submit" class="button bg-green-700 text-white hover:bg-green-800">{{ $editing ? '保存修改' : '创建工艺' }}</button>
            <a class="button button-secondary no-underline" href="{{ $indexUrl }}">取消</a>
        </div>
    </form>
@endsection
