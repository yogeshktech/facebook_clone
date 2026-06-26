<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\FriendController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ResourceController;
use Illuminate\Support\Facades\Route;

Route::post('/register/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/register/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Posts & Feed
    Route::get('/feed', [PostController::class, 'index']);
    Route::apiResource('posts', PostController::class)
        ->only(['store', 'show', 'destroy'])
        ->names([
            'store' => 'api.posts.store',
            'show' => 'api.posts.show',
            'destroy' => 'api.posts.destroy',
        ]);
    Route::post('/posts/{post}/like', [PostController::class, 'like']);
    Route::post('/posts/{post}/comment', [PostController::class, 'comment']);
    Route::post('/posts/{post}/share', [PostController::class, 'share']);

    // Friends
    Route::get('/friends', [FriendController::class, 'index']);
    Route::post('/friends/{user}', [FriendController::class, 'send']);
    Route::post('/friendships/{friendship}/accept', [FriendController::class, 'accept']);
    Route::post('/friendships/{friendship}/reject', [FriendController::class, 'reject']);

    // Follow
    Route::post('/users/{user}/follow', [ResourceController::class, 'follow']);
    Route::delete('/users/{user}/follow', [ResourceController::class, 'unfollow']);

    // Profile
    Route::get('/users/{user}', [ResourceController::class, 'profile']);
    Route::put('/profile', [ResourceController::class, 'updateProfile']);

    // Stories
    Route::get('/stories', [ResourceController::class, 'stories']);
    Route::post('/stories', [ResourceController::class, 'storeStory']);

    // Groups & Pages
    Route::get('/groups', [ResourceController::class, 'groups']);
    Route::get('/pages', [ResourceController::class, 'pages']);

    // Chat
    Route::get('/conversations', [ChatController::class, 'index']);
    Route::get('/conversations/{conversation}', [ChatController::class, 'show']);
    Route::post('/conversations/start/{user}', [ChatController::class, 'start']);
    Route::post('/conversations/{conversation}/messages', [ChatController::class, 'send']);

    // Search & Notifications
    Route::get('/search', [ResourceController::class, 'search']);
    Route::get('/notifications', [ResourceController::class, 'notifications']);
});
