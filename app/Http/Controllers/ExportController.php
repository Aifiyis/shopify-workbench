<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * 下载导出的文件
     */
    public function download($filename): BinaryFileResponse
    {
        $filepath = storage_path("exports/{$filename}");

        if (!file_exists($filepath)) {
            abort(404, 'File not found');
        }

        return response()->download($filepath);
    }
}
