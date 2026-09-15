<?php

declare(strict_types=1);

use App\Http\Controllers\Acquisition\SourceAssetController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/admin/acquisition/source-assets/{source}/{asset}', SourceAssetController::class)
    ->middleware('auth')
    ->name('acquisition.source-assets.show');
