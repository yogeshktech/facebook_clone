@extends('layouts.app')

@section('title', 'Reset Password')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-fb-gray py-12">
    <div class="max-w-md w-full mx-4">
        <div class="text-center mb-8">
            <x-brand-logo size="md" :showName="true" class="justify-center mb-2" />
            <p class="text-gray-600 mt-2">Create a new password for your account</p>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Reset Password</h2>

            @if($errors->any())
                <div class="bg-red-50 text-red-600 p-3 rounded-lg mb-4 text-sm">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="email" name="email" value="{{ $email }}" placeholder="Email address"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-fb-blue" required readonly>
                <x-password-input name="password" placeholder="New password (min 8 chars)" />
                <x-password-input name="password_confirmation" placeholder="Confirm new password" />
                <button type="submit" class="w-full bg-fb-green text-white py-3 rounded-lg font-semibold hover:bg-green-600 transition">
                    Update Password
                </button>
            </form>

            <div class="mt-6 text-center">
                <a href="{{ route('login') }}" class="text-fb-blue hover:underline">Back to Log In</a>
            </div>
        </div>
    </div>
</div>
@endsection
