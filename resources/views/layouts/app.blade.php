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
    @endphp
    <script>
        window.authUserId = {{ auth()->id() }};
        window.firebaseConfig = @json($firebaseWebConfig);
        window.maxVideoUploadMb = {{ config('media.max_video_mb') }};
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
