@extends('layouts.app')

@section('title', 'Find Your Account')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-fb-gray py-12">
    <div class="max-w-md w-full mx-4">
        <div class="text-center mb-8">
            <x-brand-logo size="md" :showName="true" class="justify-center mb-2" />
            <p class="text-gray-600 mt-2">Find your account and reset your password</p>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold mb-2">Find Your Account</h2>
            <p class="text-sm text-gray-600 mb-4">Enter your email address or mobile number linked to your account.</p>

            @if($errors->any())
                <div class="bg-red-50 text-red-600 p-3 rounded-lg mb-4 text-sm">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                @csrf
                <input type="text" name="login" value="{{ old('login') }}" placeholder="Email or mobile number"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-fb-blue" required autofocus>
                <button type="submit" class="w-full bg-fb-blue text-white py-3 rounded-lg font-semibold hover:bg-fb-blue-dark transition">
                    Search
                </button>
            </form>

            <div class="mt-6 text-center space-y-2">
                <a href="{{ route('login') }}" class="text-fb-blue hover:underline block">Back to Log In</a>
                <a href="{{ route('register') }}" class="text-gray-600 hover:underline text-sm block">Create new account</a>
            </div>
        </div>
    </div>
</div>
@endsection
