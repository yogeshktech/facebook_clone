@extends('layouts.app')

@section('title', 'Check Your Email')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-fb-gray py-12">
    <div class="max-w-md w-full mx-4">
        <div class="text-center mb-8">
            <x-brand-logo size="md" :showTagline="true" class="justify-center mb-2" />
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6 text-center">
            <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-fb-blue" fill="currentColor" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
            </div>

            <h2 class="text-xl font-semibold mb-2">Check your email</h2>

            @if($accountFound && $maskedEmail)
                <p class="text-gray-600 text-sm mb-4">
                    We sent a password reset link to <strong>{{ $maskedEmail }}</strong>.
                    Open the link in that email to create a new password.
                </p>
            @else
                <p class="text-gray-600 text-sm mb-4">
                    If an account exists with the details you entered, we sent a password reset link to the registered email address.
                </p>
            @endif

            <p class="text-gray-500 text-xs mb-6">The link expires in 60 minutes. Check your spam folder if you don't see it.</p>

            <a href="{{ route('login') }}" class="inline-block w-full bg-fb-blue text-white py-3 rounded-lg font-semibold hover:bg-fb-blue-dark transition">
                Back to Log In
            </a>

            <a href="{{ route('password.request') }}" class="text-fb-blue hover:underline text-sm mt-4 inline-block">
                Try another email or mobile
            </a>
        </div>
    </div>
</div>
@endsection
