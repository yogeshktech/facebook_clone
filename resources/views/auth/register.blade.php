@extends('layouts.app')

@section('title', 'Register')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-fb-gray py-12">
    <div class="max-w-md w-full mx-4">
        <div class="text-center mb-8">
            <x-brand-logo size="md" :showName="true" class="justify-center mb-2" />
            <p class="text-gray-600 mt-2">Create an account with email OTP verification</p>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6">
            @if($errors->any())
                <div class="bg-red-50 text-red-600 p-3 rounded-lg mb-4 text-sm">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="/register/send-otp" class="space-y-4">
                @csrf
                <input type="text" name="name" value="{{ old('name') }}" placeholder="Full name"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-fb-blue" required>
                <input type="email" name="email" value="{{ old('email') }}" placeholder="Email address"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-fb-blue" required>
                <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="Mobile (10 digits, e.g. 9876543210)"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-fb-blue"
                    pattern="[6-9][0-9]{9}" maxlength="10" required>
                <x-password-input name="password" placeholder="New password (min 8 chars)" />
                <x-password-input name="password_confirmation" placeholder="Confirm password" />
                <button type="submit" class="w-full bg-fb-green text-white py-3 rounded-lg font-semibold hover:bg-green-600 transition">
                    Send OTP to Email
                </button>
            </form>

            <div class="mt-6 text-center">
                <a href="{{ route('login') }}" class="text-fb-blue hover:underline">Already have an account?</a>
            </div>
        </div>
    </div>
</div>
@endsection
