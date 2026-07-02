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
            'host' => $reverbConfig['options']['host'] ?? null,
            'port' => $reverbConfig['options']['port'] ?? null,
            'scheme' => $reverbConfig['options']['scheme'] ?? null,
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
<link rel="stylesheet"href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
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

        {{-- Call UI Overlay --}}
        <div id="call-overlay" class="hidden fixed inset-0 z-[100] bg-slate-900/95 flex flex-col items-center justify-between p-6 text-white safe-area-bottom">
            <!-- Call Header -->
            <div class="w-full max-w-md text-center mt-12">
                <img id="call-user-avatar" src="" alt="" class="w-24 h-24 rounded-full mx-auto object-cover border-4 border-fb-blue shadow-xl">
                <h3 id="call-user-name" class="text-2xl font-bold mt-4">Username</h3>
                <p id="call-status" class="text-gray-400 text-sm mt-2">Connecting...</p>
            </div>

            <!-- Video Streams Area -->
            <div id="call-videos-container" class="hidden relative w-full max-w-lg aspect-video bg-black rounded-xl overflow-hidden shadow-2xl border border-slate-700 my-4 flex-1">
                <video id="remote-video" autoplay playsinline class="w-full h-full object-cover"></video>
                <video id="local-video" autoplay playsinline muted class="absolute bottom-4 right-4 w-32 aspect-video object-cover rounded-lg border-2 border-white shadow-md z-10"></video>
            </div>
            <audio id="remote-audio" autoplay playsinline class="sr-only"></audio>

            <!-- Audio Pulsing Animation -->
            <div id="call-audio-pulse" class="hidden my-auto flex items-center justify-center gap-1.5 h-16">
                <span class="w-2 bg-fb-blue rounded-full animate-bounce" style="animation-delay: 0.1s; height: 3rem;"></span>
                <span class="w-2 bg-fb-blue rounded-full animate-bounce" style="animation-delay: 0.2s; height: 4rem;"></span>
                <span class="w-2 bg-fb-blue rounded-full animate-bounce" style="animation-delay: 0.3s; height: 3rem;"></span>
                <span class="w-2 bg-fb-blue rounded-full animate-bounce" style="animation-delay: 0.4s; height: 4rem;"></span>
                <span class="w-2 bg-fb-blue rounded-full animate-bounce" style="animation-delay: 0.5s; height: 3rem;"></span>
            </div>

            <!-- Call Control Actions Bar -->
            <div class="w-full max-w-md flex justify-center items-center gap-6 mb-12">
                <button type="button" id="decline-call-btn" class="hidden w-14 h-14 bg-red-600 hover:bg-red-700 active:bg-red-800 text-white rounded-full flex items-center justify-center shadow-lg transition transform hover:scale-105" title="Decline">
                    <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24"><path d="M21 5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V5zm-4.7 9.3c-.4.4-1 .4-1.4 0L12 11.4l-2.9 2.9c-.4.4-1 .4-1.4 0s-.4-1 0-1.4l2.9-2.9-2.9-2.9c-.4-.4-.4-1 0-1.4s1-.4 1.4 0l2.9 2.9 2.9-2.9c.4-.4 1-.4 1.4 0s.4 1 0 1.4L13.4 10l2.9 2.9c.4.4.4 1 0 1.4z"/></svg>
                </button>

                <button type="button" id="accept-call-btn" class="hidden w-14 h-14 bg-green-600 hover:bg-green-700 active:bg-green-800 text-white rounded-full flex items-center justify-center shadow-lg transition transform hover:scale-105" title="Accept">
                    <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24"><path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.57a1.003 1.003 0 00-1.01.24l-2.2 2.2a15.045 15.045 0 01-6.59-6.59l2.2-2.2c.28-.27.36-.67.25-1.02A11.36 11.36 0 018.73 3.9a1 1 0 00-1-1H3.97a1 1 0 00-1 1C2.97 12.01 9.99 19 18.01 19a1 1 0 001-1v-2.62a1 1 0 00-1-1z"/></svg>
                </button>

                <button type="button" id="toggle-mute-btn" class="hidden w-12 h-12 bg-slate-700 hover:bg-slate-600 text-white rounded-full flex items-center justify-center shadow-md transition" title="Mute Audio">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                    </svg>
                </button>

                <button type="button" id="toggle-video-btn" class="hidden w-12 h-12 bg-slate-700 hover:bg-slate-600 text-white rounded-full flex items-center justify-center shadow-md transition" title="Toggle Camera">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                </button>

                <button type="button" id="hangup-call-btn" class="hidden w-14 h-14 bg-red-600 hover:bg-red-700 active:bg-red-800 text-white rounded-full flex items-center justify-center shadow-lg transition transform hover:scale-105" title="End Call">
                    <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24"><path d="M12 9c-2.2 0-4.3.4-6.2 1.1-.6.2-1 .7-1.3 1.2l-1.2 1.2c-.5.5-.6 1.2-.3 1.8 1.1 2.2 2.7 4.1 4.7 5.5.5.4 1.2.3 1.6-.2l1.2-1.2c.4-.4.5-.9.3-1.4-.4-1.2-.6-2.4-.6-3.7v-.4c3.4-.6 6.9-.6 10.3 0v.4c0 1.3-.2 2.5-.6 3.7-.2.5-.1 1.1.3 1.4l1.2 1.2c.4.5 1.1.6 1.6.2 2-1.4 3.6-3.3 4.7-5.5.3-.6.2-1.3-.3-1.8l-1.2-1.2c-.3-.5-.7-1-1.3-1.2C16.3 9.4 14.2 9 12 9z"/></svg>
                </button>
            </div>
        </div>
    @endauth
</body>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded',function(){

    if(document.querySelector('.reelsSwiper')){

        new Swiper('.reelsSwiper',{

            slidesPerView:2.2,
            spaceBetween:12,

            breakpoints:{

                640:{
                    slidesPerView:3.2
                },

                768:{
                    slidesPerView:4.2
                },

                1024:{
                    slidesPerView:5.2
                }

            }

        });

    }

});
</script>
</html>
