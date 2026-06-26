@extends('layouts.app')

@section('title', 'Login')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-fb-gray py-12">
    <div class="max-w-md w-full mx-4">
        <div class="text-center mb-8">
            <x-brand-logo size="md" :showName="true" class="justify-center mb-2" />
            <p class="text-gray-600 mt-2">Log in with email or mobile</p>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Log In</h2>

            @if(session('success'))
                <div class="bg-green-50 text-green-700 p-3 rounded-lg mb-4 text-sm">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="bg-red-50 text-red-600 p-3 rounded-lg mb-4 text-sm">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                <input type="text" name="login" value="{{ old('login') }}" placeholder="Email or Mobile Number"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-fb-blue" required>
                <x-password-input name="password" placeholder="Password" />
                <div class="text-right">
                    <a href="{{ route('password.request') }}" class="text-sm text-fb-blue hover:underline">Forgot password?</a>
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="remember"> Remember me
                </label>
                <button type="submit" class="w-full bg-fb-blue text-white py-3 rounded-lg font-semibold hover:bg-fb-blue-dark transition">
                    Log In
                </button>
            </form>

            <div class="my-4 flex items-center gap-4">
                <div class="flex-1 h-px bg-gray-200"></div>
                <span class="text-gray-500 text-sm">or</span>
                <div class="flex-1 h-px bg-gray-200"></div>
            </div>

            <div class="space-y-3">
                <a href="{{ route('social.redirect', 'google') }}"
                    class="flex items-center justify-center gap-2 w-full border border-gray-300 py-3 rounded-lg hover:bg-gray-50 transition">
                    Continue with Google
                </a>
            </div>

            <div class="mt-6 text-center">
                <a href="{{ route('register') }}" class="text-fb-blue hover:underline">Create new account</a>
            </div>
        </div>
    </div>
</div>
@endsection
