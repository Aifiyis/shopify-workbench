@extends('layouts.app')

@section('title', '数据处理 - 千兴工作台')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">数据处理</h1>

    @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded shadow-md p-6 mb-8">
        <h2 class="text-xl font-bold mb-4">上传订单文件</h2>

        <form method="POST" action="{{ route('data-processing.upload') }}" enctype="multipart/form-data" class="space-y-4"
              id="uploadForm" onsubmit="showProcessingState()">
            @csrf

            <div class="border-2 border-dashed border-gray-300 rounded p-6 text-center hover:border-blue-500 transition"
                 id="dropZone">
                <p class="text-gray-600 mb-2" id="uploadPrompt">将文件拖放到此处，或点击选择文件</p>
                <input type="file" name="file" id="fileInput" accept=".xlsx,.xls,.csv" required
                       class="hidden" onchange="updateFileName(this)">
                <button type="button" id="chooseFileButton" onclick="document.getElementById('fileInput').click()"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    选择文件
                </button>
                <p id="fileName" class="text-gray-600 mt-2 text-sm selected-file-name"></p>
            </div>

            <div class="text-sm text-gray-600">
                <p>支持格式：Excel（.xlsx、.xls）、CSV</p>
                <p>上传表格文件名格式：order_日期时间范围.xlsx  （order后面要用_，范围连接用-，日期时间范围将作为第一列导表日期值显示  示例: order_060109-060209.xlsx）</p>
            </div>

            <button type="submit" id="processButton" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-6 rounded w-full">
                <span id="processButtonText">处理文件</span>
                <span id="processingSpinner" class="processing-spinner hidden"></span>
            </button>
        </form>
    </div>

    <div class="bg-white rounded shadow-md p-6">
        <h2 class="text-xl font-bold mb-4">处理文件列表</h2>

        @if($processedFiles->isEmpty())
            <p class="text-gray-500">暂无处理文件</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 px-4 py-2 text-left">文件名</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">状态</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">上传时间</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">过期时间</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($processedFiles as $file)
                            <tr class="hover:bg-gray-50 {{ $file->expiry_info['is_expired'] ? 'bg-red-50' : '' }}">
                                <td class="border border-gray-300 px-4 py-2">
                                    <div class="font-semibold">{{ $file->processed_filename }}</div>
                                    <div class="text-sm text-gray-600">{{ $file->original_filename }}</div>
                                </td>
                                <td class="border border-gray-300 px-4 py-2 text-sm">
                                    @if($file->status === 'processing')
                                        <span class="text-orange-600 font-bold">处理中</span>
                                    @elseif($file->status === 'failed')
                                        <span class="text-red-600 font-bold">失败</span>
                                        @if($file->error_message)
                                            <div class="text-xs text-red-600 mt-1">{{ $file->error_message }}</div>
                                        @endif
                                    @else
                                        <span class="text-green-600 font-bold">已完成</span>
                                    @endif
                                </td>
                                <td class="border border-gray-300 px-4 py-2 text-sm">
                                    {{ $file->uploaded_at->format('Y-m-d H:i') }}
                                </td>
                                <td class="border border-gray-300 px-4 py-2 text-sm">
                                    @if($file->expiry_info['is_expired'])
                                        <span class="text-red-600 font-bold">已过期</span>
                                    @else
                                        <span class="text-gray-600">
                                            {{ $file->expires_at->format('Y-m-d H:i') }}
                                        </span>
                                        <div class="text-xs text-orange-600 mt-1">
                                            {{ $file->expiry_info['expires_in_minutes'] }} 分钟后过期
                                        </div>
                                    @endif
                                </td>
                                <td class="border border-gray-300 px-4 py-2 text-center">
                                    @if($file->status === 'completed' && !$file->expiry_info['is_expired'])
                                        <a href="{{ route('data-processing.download', $file->id) }}"
                                           class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-3 rounded text-sm inline-block mr-2">
                                            下载全部
                                        </a>
                                    @elseif($file->status === 'processing')
                                        <span class="text-sm text-gray-600 inline-block mr-2">处理中...</span>
                                    @endif

                                    <form method="POST" action="{{ route('data-processing.delete', $file->id) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button"
                                                class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-3 rounded text-sm"
                                                data-delete-trigger
                                                data-delete-title="删除处理文件"
                                                data-delete-message="删除后将无法恢复，确定要删除此处理文件吗？">
                                            删除
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $processedFiles->links() }}
            </div>
        @endif
    </div>
</div>

<script>
@if($processedFiles->contains(function ($file) { return $file->status === 'processing'; }))
setTimeout(function () {
    window.location.reload();
}, 10000);
@endif

function updateFileName(input) {
    const fileName = document.getElementById('fileName');
    const chooseFileButton = document.getElementById('chooseFileButton');
    const uploadPrompt = document.getElementById('uploadPrompt');

    if (input.files && input.files[0]) {
        fileName.innerHTML = '<span class="file-icon" aria-hidden="true"></span><span>' + escapeHtml(input.files[0].name) + '</span>';
        chooseFileButton.textContent = '更换文件';
        chooseFileButton.style.display = 'inline-block';
        uploadPrompt.style.display = 'none';
    } else {
        fileName.textContent = '';
        chooseFileButton.textContent = '选择文件';
        chooseFileButton.style.display = 'inline-block';
        uploadPrompt.style.display = 'block';
    }
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function showProcessingState() {
    const processButton = document.getElementById('processButton');
    const processButtonText = document.getElementById('processButtonText');
    const processingSpinner = document.getElementById('processingSpinner');

    processButton.disabled = true;
    processButton.classList.add('opacity-75');
    processButtonText.textContent = '处理中...';
    processingSpinner.classList.remove('hidden');
}

const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, highlight, false);
});

['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, unhighlight, false);
});

function highlight(e) {
    dropZone.style.borderColor = '#3b82f6';
    dropZone.style.backgroundColor = '#eff6ff';
}

function unhighlight(e) {
    dropZone.style.borderColor = '#d1d5db';
    dropZone.style.backgroundColor = 'white';
}

dropZone.addEventListener('drop', handleDrop, false);

function handleDrop(e) {
    const dt = e.dataTransfer;
    const dataTransfer = new DataTransfer();

    if (dt.files && dt.files[0]) {
        dataTransfer.items.add(dt.files[0]);
        fileInput.files = dataTransfer.files;
        updateFileName({ files: dataTransfer.files });
    }
}
</script>

<style>
.bg-red-50 {
    background-color: #fef2f2;
}

.text-orange-600 {
    color: #ea580c;
}

.overflow-x-auto {
    overflow-x: auto;
}

table {
    width: 100%;
}

th, td {
    text-align: left;
    padding: 0.5rem;
}

th {
    background-color: #f3f4f6;
    font-weight: 600;
}

tr:hover {
    background-color: #f9fafb;
}

.processing-spinner {
    display: inline-block;
    width: 1rem;
    height: 1rem;
    margin-left: 0.5rem;
    border: 2px solid rgba(255, 255, 255, 0.5);
    border-top-color: #ffffff;
    border-radius: 50%;
    vertical-align: middle;
    animation: processing-spin 0.8s linear infinite;
}

.hidden {
    display: none;
}

.opacity-75 {
    opacity: 0.75;
}

.selected-file-name {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.file-icon {
    position: relative;
    display: inline-block;
    width: 0.9rem;
    height: 1.1rem;
    border: 1.5px solid #4b5563;
    border-radius: 2px;
    background: #ffffff;
}

.file-icon::after {
    content: '';
    position: absolute;
    right: -1.5px;
    top: -1.5px;
    width: 0.35rem;
    height: 0.35rem;
    border-left: 1.5px solid #4b5563;
    border-bottom: 1.5px solid #4b5563;
    background: #f3f4f6;
}

@keyframes processing-spin {
    to {
        transform: rotate(360deg);
    }
}
</style>
@endsection
