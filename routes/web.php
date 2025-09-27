<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\ChunkUploadController;

// default remove it later
Route::get('/', function () {
    return view('welcome');
});

// import task routes
Route::get('/import', [ProductImportController::class, 'showImportForm'])->name('products.import.form');
Route::post('/import', [ProductImportController::class, 'import'])->name('products.import');

// upload 
Route::post('/uploads/initiate', [ChunkUploadController::class, 'initiate'])->name("uploads.initiate");
Route::post('/uploads/chunk',    [ChunkUploadController::class, 'uploadChunk'])->name("uploads.chunk");
Route::post('/uploads/complete', [ChunkUploadController::class, 'complete'])->name("uploads.complete");
