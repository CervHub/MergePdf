<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Apis\MergePdfApi;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/pdf/merge/paths', [MergePdfApi::class, 'mergeByPaths']);
