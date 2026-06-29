@props([
    'paginator',
    'pageName' => 'page',
    'perPageName' => 'per_page',
    'ariaLabel' => '分页',
])

@if ($paginator->total() > 0)
    @php
        $perPageQuery = request()->except([$pageName, $perPageName]);
        $jumpQuery = request()->except([$pageName]);
    @endphp
    <nav class="business-pagination" aria-label="{{ $ariaLabel }}">
        <span class="business-pagination-summary">共 {{ $paginator->total() }} 条</span>

        <form method="GET" action="{{ url()->current() }}" class="business-pagination-form">
            @foreach ($perPageQuery as $name => $value)
                @if (!is_array($value))
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endif
            @endforeach
            <label for="{{ $perPageName }}">每页</label>
            <select id="{{ $perPageName }}" name="{{ $perPageName }}" onchange="this.form.submit()">
                @foreach (\App\Support\PerPageOptions::ALLOWED as $option)
                    <option value="{{ $option }}" @if ($paginator->perPage() === $option) selected @endif>
                        {{ $option }} 条
                    </option>
                @endforeach
            </select>
        </form>

        <div class="business-pagination-pages">
            @if ($paginator->onFirstPage())
                <span class="text-gray-400">上一页</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}">上一页</a>
            @endif
            <span>第 {{ $paginator->currentPage() }} 页，共 {{ $paginator->lastPage() }} 页</span>
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}">下一页</a>
            @else
                <span class="text-gray-400">下一页</span>
            @endif
        </div>

        <form method="GET" action="{{ url()->current() }}" class="business-pagination-form">
            @foreach ($jumpQuery as $name => $value)
                @if (!is_array($value))
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endif
            @endforeach
            <label for="{{ $pageName }}-jump">跳至</label>
            <input id="{{ $pageName }}-jump"
                   type="number"
                   name="{{ $pageName }}"
                   value="{{ $paginator->currentPage() }}"
                   min="1"
                   max="{{ $paginator->lastPage() }}"
                   inputmode="numeric">
            <button type="submit" class="button button-secondary">跳转</button>
        </form>
    </nav>
@endif
