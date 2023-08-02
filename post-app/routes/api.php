<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PostController;
use App\Http\Controllers\CommentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::apiResource('posts', PostController::class);
// Route::get('posts', [PostController::class, 'index']);
// Route::post('posts', [PostController::class, 'store']);
// Route::get('posts/{post}', [PostController::class, 'show']);
// Route::put('posts/{post}', [PostController::class, 'update']);
// Route::delete('posts/{post}', [PostController::class, 'destroy']);

Route::get('posts/{post}/comments', [CommentController::class, 'index']);
Route::post('posts/{post}/comments', [CommentController::class, 'index']);
Route::get('posts/{post}/comments/{comment}', []);
Route::put('posts/{post}/comments/{comment}', []);
Route::delete('posts/{post}/comments/{comment}', []);