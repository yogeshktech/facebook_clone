<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#002E5D">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="NEWBOOK">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    <title>@yield('title', 'NEWBOOK') - {{ config('app.name', 'NEWBOOK') }}</title>
    <script>
        window.togglePassword = function (inputId, btn) {
            const input = document.getElementById(inputId);
            if (!input) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.querySelector('.eye-open')?.classList.toggle('hidden', show);
            btn.querySelector('.eye-closed')?.classList.toggle('hidden', !show);
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        };
    </script>
    @auth
    @php
        $firebaseWebConfig = config('services.firebase.api_key') ? [
            'apiKey' => config('services.firebase.api_key'),
            'authDomain' => config('services.firebase.auth_domain'),
            'projectId' => config('services.firebase.project_id'),
            'storageBucket' => config('services.firebase.storage_bucket'),
            'messagingSenderId' => config('services.firebase.messaging_sender_id'),
            'appId' => config('services.firebase.app_id'),
            'measurementId' => config('services.firebase.measurement_id'),
            'vapidKey' => config('services.firebase.vapid_key'),
        ] : null;
        $reverbConfig = config('broadcasting.connections.reverb');
        $reverbPublicConfig = [
            'key' => $reverbConfig['key'] ?? null,
            'host' => env('REVERB_CLIENT_HOST', env('REVERB_HOST')),
            'port' => env('REVERB_CLIENT_PORT', env('REVERB_PORT', 443)),
            'scheme' => env('REVERB_CLIENT_SCHEME', env('REVERB_SCHEME', 'https')),
        ];
    @endphp
    <script>
        window.authUserId = {{ auth()->id() }};
        window.firebaseConfig = @json($firebaseWebConfig);
        window.maxVideoUploadMb = {{ config('media.max_video_mb') }};
        window.webrtcIceServers = @json(config('webrtc.ice_servers'));
        window.reverbConfig = @json($reverbPublicConfig);
    </script>
    @endauth
    @include('layouts.assets')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
</head>
<body class="bg-fb-gray min-h-screen">
    @auth
        @include('layouts.mobile-nav')
        @include('layouts.navbar')
        @include('layouts.mobile-menu')
        @include('layouts.pwa-install-modal')
    @endauth

    @if(session('success'))
        <div class="fixed top-20 right-4 z-50 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg" id="flash-success">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="fixed top-20 right-4 z-50 bg-red-500 text-white px-4 py-2 rounded-lg shadow-lg" id="flash-error">
            {{ session('error') }}
        </div>
    @endif
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <main class="@auth pt-16 pb-[4.5rem] md:pb-0 @endauth">
        @yield('content')
    </main>

    @auth
        <div id="notification-toast" class="hidden fixed bottom-20 md:bottom-4 right-4 z-50 bg-white rounded-lg shadow-xl p-4 max-w-sm border-l-4 border-fb-blue"></div>

        <div id="notification-permission-banner" class="hidden fixed bottom-20 md:bottom-4 left-3 right-3 md:left-auto md:right-4 md:max-w-sm z-[55] bg-white rounded-xl shadow-2xl border border-gray-200 p-4">
            <div class="flex gap-3">
                <img src="{{ asset('icons/icon-192.png') }}" alt="" class="w-12 h-12 rounded-xl flex-shrink-0">
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-sm text-gray-900">Turn on notifications</p>
                    <p class="text-xs text-gray-500 mt-0.5">Get message & friend request alerts with sound — like WhatsApp.</p>
                    <div class="flex gap-2 mt-3">
                        <button type="button" onclick="enablePushNotifications()"
                            class="flex-1 bg-fb-blue text-white text-xs font-semibold py-2 rounded-lg hover:bg-fb-blue-dark">
                            Allow
                        </button>
                        <button type="button" onclick="document.getElementById('notification-permission-banner').classList.add('hidden')"
                            class="px-3 text-xs font-medium text-gray-500 hover:text-gray-800">
                            Later
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Call UI Overlay (solid fullscreen — must stay opaque on mobile) --}}
        <div id="call-overlay" class="call-overlay hidden">
            <div class="call-overlay-bg" aria-hidden="true"></div>

            <!-- Top bar: minimize -->
            <button type="button" id="minimize-call-btn" class="hidden absolute top-3 left-3 z-30 w-11 h-11 rounded-full bg-white/15 hover:bg-white/25 border border-white/20 flex items-center justify-center text-white" title="Minimize call">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <span id="call-overlay-hint" class="hidden absolute top-4 right-3 z-30 text-[11px] font-semibold text-white/70 px-2 py-1 rounded-full bg-black/30">Tap ↓ to chat</span>

            <!-- Call Header / Profile -->
            <div id="call-profile-block" class="w-full max-w-md text-center mt-14 z-10 relative px-4">
                <div class="relative w-32 h-32 mx-auto flex items-center justify-center">
                    <div class="ripple-ring"></div>
                    <div class="ripple-ring"></div>
                    <div class="ripple-ring"></div>
                    <img id="call-user-avatar" src="" alt="" class="w-28 h-28 rounded-full object-cover border-4 border-white/30 shadow-2xl relative z-10 animate-call-pulse bg-slate-800">
                </div>
                <h3 id="call-user-name" class="text-2xl sm:text-3xl font-bold mt-5 tracking-tight text-white">Username</h3>
                <div class="inline-flex items-center gap-2 mt-3 px-4 py-1.5 rounded-full bg-white/10 border border-white/15 text-xs font-semibold tracking-wide text-white">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                    <span id="call-status">Connecting...</span>
                </div>
            </div>

            <!-- Video Streams Area -->
            <div id="call-videos-container" class="hidden relative w-full max-w-2xl aspect-video bg-slate-950/80 rounded-3xl overflow-hidden shadow-[0_20px_50px_rgba(0,0,0,0.5)] border border-white/10 my-4 flex-1 self-center flex items-center justify-center z-10">
                <video id="main-call-video" autoplay playsinline class="w-full h-full object-cover rounded-3xl bg-slate-900"></video>
                <div id="main-video-off-overlay" class="hidden absolute inset-0 flex flex-col items-center justify-center bg-slate-900/95 gap-3">
                    <img id="main-video-off-avatar" src="" alt="" class="w-20 h-20 rounded-full object-cover border-2 border-white/20">
                    <div class="flex items-center gap-2 text-white/80 text-sm">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18"/></svg>
                        <span>Camera off</span>
                    </div>
                </div>
                <div id="main-muted-badge" class="hidden absolute top-3 left-3 z-20 px-2.5 py-1 rounded-full bg-black/60 text-white text-xs font-medium flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18"/></svg>
                    Muted
                </div>
                <!-- PiP (tap to swap) -->
                <button type="button" id="pip-video-wrap" class="absolute bottom-4 right-4 w-[200px] h-[150px] rounded-2xl overflow-hidden border border-white/20 shadow-2xl z-20 transition-all duration-300 hover:scale-105 group bg-slate-900" title="Tap to swap" style="height:150px;width:200px;">
                    <video id="pip-call-video" autoplay playsinline muted class="w-full h-full object-cover bg-slate-900 pointer-events-none"></video>
                    <div id="pip-video-off-overlay" class="hidden absolute inset-0 flex items-center justify-center bg-slate-900/95">
                        <svg class="w-6 h-6 text-white/70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18"/></svg>
                    </div>
                    <div id="pip-muted-badge" class="hidden absolute top-1.5 left-1.5 z-20 w-6 h-6 rounded-full bg-black/70 text-white flex items-center justify-center">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18"/></svg>
                    </div>
                </button>
            </div>
            <audio id="remote-audio" autoplay playsinline class="sr-only"></audio>

            <!-- Audio Pulsing Animation -->
            <div id="call-audio-pulse" class="hidden my-auto flex items-center justify-center gap-2.5 h-24 z-10">
                <span class="w-1.5 bg-gradient-to-t from-indigo-500 to-purple-500 rounded-full animate-bounce" style="animation-delay: 0.1s; height: 2rem;"></span>
                <span class="w-1.5 bg-gradient-to-t from-indigo-500 to-purple-500 rounded-full animate-bounce" style="animation-delay: 0.2s; height: 3.5rem;"></span>
                <span class="w-1.5 bg-gradient-to-t from-indigo-500 to-purple-500 rounded-full animate-bounce" style="animation-delay: 0.3s; height: 5rem;"></span>
                <span class="w-1.5 bg-gradient-to-t from-indigo-500 to-purple-500 rounded-full animate-bounce" style="animation-delay: 0.4s; height: 4rem;"></span>
                <span class="w-1.5 bg-gradient-to-t from-indigo-500 to-purple-500 rounded-full animate-bounce" style="animation-delay: 0.5s; height: 6rem;"></span>
                <span class="w-1.5 bg-gradient-to-t from-indigo-500 to-purple-500 rounded-full animate-bounce" style="animation-delay: 0.6s; height: 4rem;"></span>
                <span class="w-1.5 bg-gradient-to-t from-indigo-500 to-purple-500 rounded-full animate-bounce" style="animation-delay: 0.7s; height: 5rem;"></span>
                <span class="w-1.5 bg-gradient-to-t from-indigo-500 to-purple-500 rounded-full animate-bounce" style="animation-delay: 0.8s; height: 3.5rem;"></span>
                <span class="w-1.5 bg-gradient-to-t from-indigo-500 to-purple-500 rounded-full animate-bounce" style="animation-delay: 0.9s; height: 2rem;"></span>
            </div>
            <div id="remote-audio-muted-badge" class="hidden my-2 px-3 py-1.5 rounded-full bg-black/50 text-white text-xs font-medium items-center gap-1.5 z-10">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18"/></svg>
                <span id="remote-audio-muted-label">Muted</span>
            </div>

            <!-- Call Control Actions Bar (solid, always visible on mobile) -->
            <div class="call-controls-bar w-full max-w-lg px-3 py-4 rounded-3xl flex justify-center items-center gap-3 sm:gap-4 mb-6 z-20 relative flex-wrap">
                <button type="button" id="decline-call-btn" class="hidden w-14 h-14 bg-rose-500 hover:bg-rose-600 active:scale-95 text-white rounded-full flex items-center justify-center shadow-lg transition-all duration-200 animate-heartbeat" title="Decline">
                    <svg class="w-6 h-6 rotate-[135deg]" fill="currentColor" viewBox="0 0 24 24"><path d="M6.62 10.79a15.05 15.05 0 006.59 6.59l2.2-2.2a1 1 0 011.01-.24c1.12.37 2.31.57 3.53.57.55 0 1 .45 1 1V20a1 1 0 01-1 1C9.99 21 3 14.01 3 6a1 1 0 011-1h2.62c.55 0 1 .45 1 1 0 1.22.2 2.41.57 3.53a1 1 0 01-.24 1.01l-2.33 2.25z"/></svg>
                </button>

                <button type="button" id="accept-call-btn" class="hidden w-14 h-14 bg-emerald-500 hover:bg-emerald-600 active:scale-95 text-white rounded-full flex items-center justify-center shadow-lg transition-all duration-200 animate-heartbeat" title="Accept">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.57a1.003 1.003 0 00-1.01.24l-2.2 2.2a15.045 15.045 0 01-6.59-6.59l2.2-2.2c.28-.27.36-.67.25-1.02A11.36 11.36 0 018.73 3.9a1 1 0 00-1-1H3.97a1 1 0 00-1 1C2.97 12.01 9.99 19 18.01 19a1 1 0 001-1v-2.62a1 1 0 00-1-1z"/></svg>
                </button>

                <button type="button" id="toggle-mute-btn" class="hidden call-ctrl-btn" title="Mute Audio">
                    <svg data-icon-on class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                    </svg>
                    <svg data-icon-off class="w-5 h-5 hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18"/>
                    </svg>
                </button>

                <button type="button" id="speaker-btn" class="hidden call-ctrl-btn" title="Audio output">
                    <svg id="speaker-btn-icon" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072M12 6v12l-4-4H4V10h4l4-4z"/>
                    </svg>
                </button>

                <button type="button" id="toggle-video-btn" class="hidden call-ctrl-btn" title="Toggle Camera">
                    <svg data-icon-on class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    <svg data-icon-off class="w-5 h-5 hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18"/>
                    </svg>
                </button>

                <button type="button" id="flip-camera-btn" class="hidden call-ctrl-btn" title="Flip Camera">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </button>

                <button type="button" id="hangup-call-btn" class="hidden w-14 h-14 bg-rose-600 hover:bg-rose-700 active:scale-95 text-white rounded-full flex items-center justify-center shadow-lg transition-all duration-200" title="End Call">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 9c-2.2 0-4.3.4-6.2 1.1-.6.2-1 .7-1.3 1.2l-1.2 1.2c-.5.5-.6 1.2-.3 1.8 1.1 2.2 2.7 4.1 4.7 5.5.5.4 1.2.3 1.6-.2l1.2-1.2c.4-.4.5-.9.3-1.4-.4-1.2-.6-2.4-.6-3.7v-.4c3.4-.6 6.9-.6 10.3 0v.4c0 1.3-.2 2.5-.6 3.7-.2.5-.1 1.1.3 1.4l1.2 1.2c.4.5 1.1.6 1.6.2 2-1.4 3.6-3.3 4.7-5.5.3-.6.2-1.3-.3-1.8l-1.2-1.2c-.3-.5-.7-1-1.3-1.2C16.3 9.4 14.2 9 12 9z"/></svg>
                </button>
            </div>

            <!-- Audio output picker (Phone / Speaker / Bluetooth) -->
            <div id="speaker-picker" class="hidden absolute inset-0 z-40 flex items-end justify-center">
                <button type="button" id="speaker-picker-backdrop" class="absolute inset-0 bg-black/50" aria-label="Close"></button>
                <div class="relative w-full max-w-md mx-4 mb-6 rounded-3xl bg-slate-900 border border-white/10 shadow-2xl overflow-hidden">
                    <div class="px-5 pt-4 pb-2 flex items-center justify-between">
                        <h4 class="text-sm font-semibold tracking-wide text-white/90">Audio output</h4>
                        <button type="button" id="speaker-picker-close" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center" title="Close">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <p id="speaker-picker-hint" class="hidden px-5 pb-2 text-[11px] text-white/50"></p>
                    <div id="speaker-picker-list" class="px-2 pb-3 max-h-72 overflow-y-auto"></div>
                </div>
            </div>
        </div>

        {{-- Ongoing call bar (always visible when minimized — WhatsApp style) --}}
        <div id="call-minimized" class="hidden call-ongoing-bar" role="button" tabindex="0" title="Tap to return to call">
            <div class="call-ongoing-inner">
                <div class="call-ongoing-pulse" aria-hidden="true"></div>
                <img id="mini-call-avatar" src="" alt="" class="w-9 h-9 rounded-full object-cover border-2 border-white/40 flex-shrink-0 bg-slate-700">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-white truncate leading-tight">
                        <span id="mini-call-name">Call</span>
                    </p>
                    <p class="text-[11px] text-emerald-100 font-medium truncate">
                        <span id="mini-call-status">Ongoing call · Tap to return</span>
                    </p>
                </div>
                <video id="mini-call-video" autoplay playsinline muted class="hidden w-10 h-10 rounded-lg object-cover flex-shrink-0"></video>
                <div id="mini-call-avatar-wrap" class="hidden"></div>
                <div id="mini-remote-muted" class="hidden w-7 h-7 rounded-full bg-black/40 flex items-center justify-center flex-shrink-0">
                    <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18"/></svg>
                </div>
                <button type="button" id="mini-hangup-btn" class="w-10 h-10 rounded-full bg-rose-500 hover:bg-rose-600 flex items-center justify-center flex-shrink-0 shadow-md" title="End Call">
                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12 9c-2.2 0-4.3.4-6.2 1.1-.6.2-1 .7-1.3 1.2l-1.2 1.2c-.5.5-.6 1.2-.3 1.8 1.1 2.2 2.7 4.1 4.7 5.5.5.4 1.2.3 1.6-.2l1.2-1.2c.4-.4.5-.9.3-1.4-.4-1.2-.6-2.4-.6-3.7v-.4c3.4-.6 6.9-.6 10.3 0v.4c0 1.3-.2 2.5-.6 3.7-.2.5-.1 1.1.3 1.4l1.2 1.2c.4.5 1.1.6 1.6.2 2-1.4 3.6-3.3 4.7-5.5.3-.6.2-1.3-.3-1.8l-1.2-1.2c-.3-.5-.7-1-1.3-1.2C16.3 9.4 14.2 9 12 9z"/></svg>
                </button>
            </div>
        </div>
    @endauth
</body>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!document.querySelector('.reelsSwiper') || typeof Swiper === 'undefined') return;

    new Swiper('.reelsSwiper', {
        slidesPerView: 2.2,
        spaceBetween: 12,
        breakpoints: {
            640: { slidesPerView: 3.2 },
            768: { slidesPerView: 4.2 },
            1024: { slidesPerView: 5.2 },
        },
    });
});
</script>
</html>
