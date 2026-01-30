<?php

use Illuminate\Support\Facades\Route;
use Webkul\Paymob\Http\Controllers\PaymobController;

Route::group([
    'middleware' => ['web', 'theme', 'locale', 'currency'],
], function () {

    Route::get('paymob/redirect', [PaymobController::class, 'redirect'])
        ->name('paymob.redirect'); // ✅ الاسم الصح

    Route::any('paymob/callback', [PaymobController::class, 'callback'])
        ->name('paymob.callback');

});
