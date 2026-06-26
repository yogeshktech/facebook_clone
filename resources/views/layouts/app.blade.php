<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>@yield('title', 'Newbook') - {{ config('app.name', 'Newbook') }}</title>
    @include('layouts.assets')
</head>
<body class="bg-fb-gray min-h-screen">
    @auth
        @include('layouts.navbar')
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

    <main class="@auth pt-16 @endauth">
        @yield('content')
    </main>

    @auth
        <div id="notification-toast" class="hidden fixed bottom-4 right-4 z-50 bg-white rounded-lg shadow-xl p-4 max-w-sm border-l-4 border-fb-blue"></div>
    @endauth
</body>
</html>
