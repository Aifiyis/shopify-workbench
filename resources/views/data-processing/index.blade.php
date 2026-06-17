@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Data Processing</h1>

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

    <!-- Upload Form -->
    <div class="bg-white rounded shadow-md p-6 mb-8">
        <h2 class="text-xl font-bold mb-4">Upload Order File</h2>

        <form method="POST" action="{{ route('data-processing.upload') }}" enctype="multipart/form-data" class="space-y-4"
              id="uploadForm" onsubmit="showProcessingState()">
            @csrf

            <div class="border-2 border-dashed border-gray-300 rounded p-6 text-center hover:border-blue-500 transition"
                 id="dropZone">
                <p class="text-gray-600 mb-2" id="uploadPrompt">Drag and drop your file here, or click to select</p>
                <input type="file" name="file" id="fileInput" accept=".xlsx,.xls,.csv" required
                       class="hidden" onchange="updateFileName(this)">
                <button type="button" id="chooseFileButton" onclick="document.getElementById('fileInput').click()"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Choose File
                </button>
                <p id="fileName" class="text-gray-600 mt-2 text-sm selected-file-name"></p>
            </div>

            <div class="text-sm text-gray-600">
                <p>Supported formats: Excel (.xlsx, .xls), CSV</p>
                <p>上传表格文件名格式：order_日期时间范围.xlsx  （order后面要用_，范围连接用-，日期时间范围将作为第一列导表日期值显示  示例: order_060109-060209.xlsx）</p>
            </div>

            <button type="submit" id="processButton" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-6 rounded w-full">
                <span id="processButtonText">Process File</span>
                <span id="processingSpinner" class="processing-spinner hidden"></span>
            </button>
        </form>
    </div>

    <!-- Processed Files List -->
    <div class="bg-white rounded shadow-md p-6">
        <h2 class="text-xl font-bold mb-4">Processed Files</h2>

        @if($processedFiles->isEmpty())
            <p class="text-gray-500">No processed files yet</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 px-4 py-2 text-left">File Name</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Status</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Uploaded</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Expires</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Actions</th>
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
                                        <span class="text-orange-600 font-bold">Processing</span>
                                    @elseif($file->status === 'failed')
                                        <span class="text-red-600 font-bold">Failed</span>
                                        @if($file->error_message)
                                            <div class="text-xs text-red-600 mt-1">{{ $file->error_message }}</div>
                                        @endif
                                    @else
                                        <span class="text-green-600 font-bold">Completed</span>
                                    @endif
                                </td>
                                <td class="border border-gray-300 px-4 py-2 text-sm">
                                    {{ $file->uploaded_at->format('Y-m-d H:i') }}
                                </td>
                                <td class="border border-gray-300 px-4 py-2 text-sm">
                                    @if($file->expiry_info['is_expired'])
                                        <span class="text-red-600 font-bold">Expired</span>
                                    @else
                                        <span class="text-gray-600">
                                            {{ $file->expires_at->format('Y-m-d H:i') }}
                                        </span>
                                        <div class="text-xs text-orange-600 mt-1">
                                            Expires in {{ $file->expiry_info['expires_in_minutes'] }} minutes
                                        </div>
                                    @endif
                                </td>
                                <td class="border border-gray-300 px-4 py-2 text-center">
                                    @if($file->status === 'completed' && !$file->expiry_info['is_expired'])
                                        <a href="{{ route('data-processing.download', $file->id) }}"
                                           class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-3 rounded text-sm inline-block mr-2">
                                            Download All
                                        </a>
                                    @elseif($file->status === 'processing')
                                        <span class="text-sm text-gray-600 inline-block mr-2">Processing...</span>
                                    @endif

                                    <form method="POST" action="{{ route('data-processing.delete', $file->id) }}"
                                          class="inline" onsubmit="return confirm('Are you sure?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-3 rounded text-sm">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
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
        chooseFileButton.textContent = 'Change File';
        chooseFileButton.style.display = 'inline-block';
        uploadPrompt.style.display = 'none';
    } else {
        fileName.textContent = '';
        chooseFileButton.textContent = 'Choose File';
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
    border-radius: 9999px;
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
