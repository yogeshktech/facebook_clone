<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\FriendController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ReelController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\VideoController;
use App\Http\Controllers\CallSignalingController;
use App\Http\Controllers\ChatController as WebChatController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

// Auth (public)
Route::post('/register/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/register/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/password/forgot', [AuthController::class, 'forgotPassword']);
Route::post('/password/reset', [AuthController::class, 'resetPassword']);

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
    Route::get('/posts/{post}/likers', [PostController::class, 'likers']);
    Route::post('/posts/{post}/comment', [PostController::class, 'comment']);
    Route::post('/posts/{post}/share', [PostController::class, 'share']);
    Route::post('/posts/{post}/send/{user}', [PostController::class, 'sendToFriend']);

    // Reels
    Route::get('/reels', [ReelController::class, 'index']);
    Route::post('/reels', [ReelController::class, 'store']);
    Route::post('/reels/{reel}/like', [ReelController::class, 'like']);
    Route::post('/reels/{reel}/comment', [ReelController::class, 'comment']);
    Route::post('/reels/{reel}/share', [ReelController::class, 'share']);
    Route::post('/reels/{reel}/view', [ReelController::class, 'view']);
    Route::post('/reels/{reel}/send/{user}', [ReelController::class, 'sendToFriend']);

    // Videos
    Route::get('/videos', [VideoController::class, 'index']);
    Route::post('/videos', [VideoController::class, 'store']);
    Route::get('/videos/{video}', [VideoController::class, 'show']);
    Route::post('/videos/{video}/like', [VideoController::class, 'like']);
    Route::post('/videos/{video}/view', [VideoController::class, 'view']);
    Route::post('/videos/{video}/comment', [VideoController::class, 'comment']);
    Route::post('/videos/{video}/share', [VideoController::class, 'share']);
    Route::post('/videos/{video}/send/{user}', [VideoController::class, 'sendToFriend']);

    // Friends
    Route::get('/friends', [FriendController::class, 'index']);
    Route::post('/friends/{user}', [FriendController::class, 'send']);
    Route::post('/friendships/{friendship}/accept', [FriendController::class, 'accept']);
    Route::post('/friendships/{friendship}/reject', [FriendController::class, 'reject']);
    Route::post('/friendships/{friendship}/cancel', [FriendController::class, 'cancel']);
    Route::delete('/friends/{user}', [FriendController::class, 'unfriend']);

    // Follow
    Route::post('/users/{user}/follow', [ResourceController::class, 'follow']);
    Route::delete('/users/{user}/follow', [ResourceController::class, 'unfollow']);

    // Profile
    Route::get('/users/{user}', [ResourceController::class, 'profile']);
    Route::put('/profile', [ResourceController::class, 'updateProfile']);

    // Stories
    Route::get('/stories', [ResourceController::class, 'stories']);
    Route::post('/stories', [ResourceController::class, 'storeStory']);
    Route::get('/stories/{story}', [ResourceController::class, 'showStory']);
    Route::get('/stories/{story}/viewers', [ResourceController::class, 'storyViewers']);
    Route::delete('/stories/{story}', [ResourceController::class, 'destroyStory']);

    // Groups & Pages
    Route::get('/groups', [GroupController::class, 'index']);
    Route::post('/groups', [GroupController::class, 'store']);
    Route::get('/groups/{group}', [GroupController::class, 'show']);
    Route::post('/groups/{group}/join', [GroupController::class, 'join']);
    Route::post('/groups/{group}/leave', [GroupController::class, 'leave']);

    Route::get('/pages', [PageController::class, 'index']);
    Route::post('/pages', [PageController::class, 'store']);
    Route::get('/pages/{page}', [PageController::class, 'show']);
    Route::post('/pages/{page}/follow', [PageController::class, 'follow']);
    Route::delete('/pages/{page}/follow', [PageController::class, 'unfollow']);

    // Chat
    Route::get('/conversations', [ChatController::class, 'index']);
    Route::post('/conversations/group', [ChatController::class, 'createGroup']);
    Route::get('/conversations/{conversation}', [ChatController::class, 'show']);
    Route::get('/conversations/{conversation}/messages', [WebChatController::class, 'messages']);
    Route::delete('/conversations/{conversation}', [ChatController::class, 'destroy']);
    Route::post('/conversations/start/{user}', [ChatController::class, 'start']);
    Route::post('/conversations/{conversation}/members', [WebChatController::class, 'addMembers']);
    Route::post('/conversations/{conversation}/typing', [WebChatController::class, 'typing']);
    Route::post('/conversations/{conversation}/messages', [ChatController::class, 'send']);
    Route::patch('/messages/{message}', [WebChatController::class, 'editMessage']);
    Route::delete('/messages/{message}', [WebChatController::class, 'deleteMessage']);

    // Voice/Video Calls
    Route::post('/calls/signal', [CallSignalingController::class, 'signal']);
    Route::get('/calls/inbox', [CallSignalingController::class, 'inbox']);
    Route::get('/calls/health', [CallSignalingController::class, 'health']);
    Route::get('/users/{user}/presence', [CallSignalingController::class, 'presence']);

    // Search & Notifications
    Route::get('/search', [ResourceController::class, 'search']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/count', [NotificationController::class, 'count']);
    Route::get('/notifications/unread', [NotificationController::class, 'unread']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/device-token', [NotificationController::class, 'storeDeviceToken']);
});
