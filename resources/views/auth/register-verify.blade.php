@extends('layouts.app')

@section('title', 'Verify OTP')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-fb-gray py-12">
    <div class="max-w-md w-full mx-4">
        <div class="text-center mb-8">
            <x-brand-logo size="md" class="justify-center mb-4" />
            <h1 class="text-3xl font-bold text-fb-blue">Verify Email</h1>
            <p class="text-gray-600 mt-2">OTP sent to: <strong>{{ $email }}</strong></p>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6">
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

            <form method="POST" action="{{ route('register.verify.submit') }}" class="space-y-4">
                @csrf
                <label class="block text-sm font-medium text-gray-700">Enter 6-digit OTP</label>
                <input type="text" name="otp" placeholder="000000" maxlength="6" pattern="[0-9]{6}"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg text-center text-2xl tracking-widest focus:outline-none focus:ring-2 focus:ring-fb-blue" required autofocus>
                <button type="submit" class="w-full bg-fb-blue text-white py-3 rounded-lg font-semibold hover:bg-fb-blue-dark transition">
                    Verify & Create Account
                </button>
            </form>

            <form method="POST" action="{{ route('register.resend-otp') }}" class="mt-4 text-center">
                @csrf
                <button type="submit" class="text-fb-blue text-sm hover:underline">Resend OTP</button>
            </form>

            <div class="mt-4 text-center">
                <a href="{{ route('register') }}" class="text-gray-500 text-sm hover:underline">Back to registration</a>
            </div>
        </div>
    </div>
</div>
@endsection
