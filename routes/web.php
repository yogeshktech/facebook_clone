<?php

use App\Http\Controllers\Admin\ChatController as AdminChatController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\AdvertisementController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatMediaController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReelController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('feed.index')
        : redirect()->route('login');
});

Route::get('/media/{path}', [MediaController::class, 'show'])
    ->where('path', '.*')
    ->name('media.show');

Route::get('/firebase-config.js', function () {
    return response()
        ->view('firebase.config-js')
        ->header('Content-Type', 'application/javascript')
        ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
})->name('firebase.config');

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::get('/client/login', [LoginController::class, 'showClientLoginForm'])->name('client.login');
    Route::get('/admin/login', [LoginController::class, 'showAdminLoginForm'])->name('admin.login');
    Route::post('/login', [LoginController::class, 'login']);
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::get('/register/send-otp', fn () => redirect()->route('register'));
    Route::post('/register/send-otp', [RegisterController::class, 'sendOtp'])->name('register.send-otp');
    Route::get('/register/verify', [RegisterController::class, 'showVerifyForm'])->name('register.verify');
    Route::post('/register/verify', [RegisterController::class, 'verifyOtp'])->name('register.verify.submit');
    Route::post('/register/resend-otp', [RegisterController::class, 'resendOtp'])->name('register.resend-otp');

    Route::get('/password/forgot', [ForgotPasswordController::class, 'showForgotForm'])->name('password.request');
    Route::post('/password/forgot', [ForgotPasswordController::class, 'findAccount'])->name('password.email');
    Route::get('/password/sent', [ForgotPasswordController::class, 'showSentForm'])->name('password.sent');
    Route::get('/password/reset/{token}', [ForgotPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/password/reset', [ForgotPasswordController::class, 'reset'])->name('password.update');

    Route::get('/auth/{provider}', [SocialAuthController::class, 'redirect'])->name('social.redirect');
    Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('social.callback');
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Feed
    Route::get('/feed', [FeedController::class, 'index'])->name('feed.index');

    // Profile
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/{user}', [ProfileController::class, 'show'])->name('profile.show');

    // Posts
    Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
    Route::post('/posts/{post}/like', [PostController::class, 'like'])->name('posts.like');
    Route::get('/posts/{post}/likers', [PostController::class, 'likers'])->name('posts.likers');
    Route::post('/posts/{post}/comment', [PostController::class, 'comment'])->name('posts.comment');
    Route::post('/posts/{post}/share', [PostController::class, 'share'])->name('posts.share');
    Route::post('/posts/{post}/send/{user}', [PostController::class, 'sendToFriend'])->name('posts.send');

    // Friends
    Route::get('/friends', [FriendController::class, 'index'])->name('friends.index');
    Route::post('/friends/{user}', [FriendController::class, 'send'])->name('friends.send');
    Route::post('/friendships/{friendship}/accept', [FriendController::class, 'accept'])->name('friends.accept');
    Route::post('/friendships/{friendship}/reject', [FriendController::class, 'reject'])->name('friends.reject');
    Route::post('/friendships/{friendship}/cancel', [FriendController::class, 'cancel'])->name('friends.cancel');
    Route::delete('/friends/{user}', [FriendController::class, 'unfriend'])->name('friends.unfriend');

    // Follow
    Route::post('/follow/{user}', [FollowController::class, 'follow'])->name('follow');
    Route::delete('/unfollow/{user}', [FollowController::class, 'unfollow'])->name('unfollow');

    // Stories
    Route::get('/stories', [StoryController::class, 'index'])->name('stories.index');
    Route::post('/stories', [StoryController::class, 'store'])->name('stories.store');
    Route::get('/stories/{story}/viewers', [StoryController::class, 'viewers'])->name('stories.viewers');
    Route::delete('/stories/{story}', [StoryController::class, 'destroy'])->name('stories.destroy');
    Route::get('/stories/{story}', [StoryController::class, 'show'])->name('stories.show');

    // Reels
    Route::get('/reels', [ReelController::class, 'index'])->name('reels.index');
    Route::post('/reels', [ReelController::class, 'store'])->name('reels.store');
    Route::post('/reels/{reel}/like', [ReelController::class, 'like'])->name('reels.like');
    Route::post('/reels/{reel}/view', [ReelController::class, 'view'])->name('reels.view');
    Route::post('/reels/{reel}/comment', [ReelController::class, 'comment'])->name('reels.comment');
    Route::post('/reels/{reel}/share', [ReelController::class, 'share'])->name('reels.share');
    Route::post('/reels/{reel}/send/{user}', [ReelController::class, 'sendToFriend'])->name('reels.send');

    // Chat
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/media/{message}', [ChatMediaController::class, 'show'])->name('chat.media');
    Route::get('/chat/{conversation}/messages', [ChatController::class, 'messages'])->name('chat.messages');
    Route::get('/chat/{conversation}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/chat/start/{user}', [ChatController::class, 'start'])->name('chat.start');
    Route::post('/chat/{conversation}/send', [ChatController::class, 'send'])->name('chat.send');

    // Groups
    Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
    Route::get('/groups/create', [GroupController::class, 'create'])->name('groups.create');
    Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
    Route::get('/groups/{group}', [GroupController::class, 'show'])->name('groups.show');
    Route::post('/groups/{group}/join', [GroupController::class, 'join'])->name('groups.join');
    Route::post('/groups/{group}/leave', [GroupController::class, 'leave'])->name('groups.leave');

    // Pages
    Route::get('/pages', [PageController::class, 'index'])->name('pages.index');
    Route::get('/pages/create', [PageController::class, 'create'])->name('pages.create');
    Route::post('/pages', [PageController::class, 'store'])->name('pages.store');
    Route::get('/pages/{page}', [PageController::class, 'show'])->name('pages.show');
    Route::post('/pages/{page}/follow', [PageController::class, 'follow'])->name('pages.follow');
    Route::delete('/pages/{page}/unfollow', [PageController::class, 'unfollow'])->name('pages.unfollow');

    // Search & Notifications
    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/count', [NotificationController::class, 'count'])->name('notifications.count');
    Route::get('/notifications/unread', [NotificationController::class, 'unread'])->name('notifications.unread');
    Route::post('/notifications/device-token', [NotificationController::class, 'storeDeviceToken'])->name('notifications.device-token');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');

    // Client Advertisements & Leads (clients only — admins use /admin/ads)
    Route::middleware('client')->group(function () {
        Route::get('/ads', [AdvertisementController::class, 'index'])->name('ads.index');
        Route::get('/ads/create', [AdvertisementController::class, 'create'])->name('ads.create');
        Route::post('/ads', [AdvertisementController::class, 'store'])->name('ads.store');
        Route::get('/ads/{ad}/payment', [AdvertisementController::class, 'paymentScreen'])->name('ads.payment');
        Route::post('/ads/{ad}/payment', [AdvertisementController::class, 'processPayment'])->name('ads.pay');
        Route::get('/ads/{ad}/leads', [AdvertisementController::class, 'showLeads'])->name('ads.leads');
        Route::get('/ads/{ad}/leads/download', [AdvertisementController::class, 'downloadLeads'])->name('ads.leads.download');
    });

    Route::post('/ads/{ad}/lead', [AdvertisementController::class, 'submitLead'])->name('ads.lead.submit');

    // Admin Ads Panel
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/ads', [AdvertisementController::class, 'adminIndex'])->name('ads.index');
        Route::post('/ads/{ad}/reject', [AdvertisementController::class, 'rejectAd'])->name('ads.reject');
        Route::get('/ads/{ad}/leads', [AdvertisementController::class, 'adminShowLeads'])->name('ads.leads');
        Route::get('/ads/{ad}/leads/download', [AdvertisementController::class, 'adminDownloadLeads'])->name('ads.leads.download');
        Route::get('/chats', [AdminChatController::class, 'index'])->name('chats.index');
        Route::get('/chats/search', [AdminChatController::class, 'search'])->name('chats.search');
        Route::get('/chats/{conversation}', [AdminChatController::class, 'show'])->name('chats.show');
    });
});
