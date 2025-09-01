<?php

use App\Http\Controllers\OcrController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('upload');
});

Route::post('/bulk-ocr', [OcrController::class, 'bulkOcr']);
