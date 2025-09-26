<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductImportController;

// default remove it later
Route::get('/', function () {
    return view('welcome');
});

// import task routes
Route::get('/import', [ProductImportController::class, 'showImportForm'])->name('products.import.form');
Route::post('/import', [ProductImportController::class, 'import'])->name('products.import');
