<?php

use App\Http\Controllers\Admin\ProductImportController;
use Illuminate\Support\Facades\Route;
use Webkul\Core\Http\Middleware\NoCacheMiddleware;

Route::group(['middleware' => ['admin', NoCacheMiddleware::class], 'prefix' => config('app.admin_url')], function () {
    Route::prefix('catalog/products/import')->group(function () {
        Route::post('upload', [ProductImportController::class, 'upload'])->name('admin.catalog.products.import.upload');
        Route::post('folder', [ProductImportController::class, 'fromFolder'])->name('admin.catalog.products.import.folder');
        Route::get('progress/{batchId}', [ProductImportController::class, 'progress'])->name('admin.catalog.products.import.progress');
        Route::get('log/{batchId}', [ProductImportController::class, 'downloadLog'])->name('admin.catalog.products.import.log');
        Route::get('folders', [ProductImportController::class, 'folders'])->name('admin.catalog.products.import.folders');
    });
});
