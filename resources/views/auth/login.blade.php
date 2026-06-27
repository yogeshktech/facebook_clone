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

            <!-- Quick Login for Testing -->
            <div class="mt-6 pt-4 border-t border-gray-150">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3 text-center">Quick Login (Demo)</p>
                <div class="grid grid-cols-2 gap-3">
                    <button type="button" onclick="quickLogin('demo@newbook.test', 'password')" class="flex flex-col items-center justify-center border border-indigo-200 hover:border-indigo-400 bg-indigo-50/50 hover:bg-indigo-50 p-2.5 rounded-lg transition cursor-pointer text-center">
                        <span class="text-xs font-bold text-indigo-700">Admin Login</span>
                        <span class="text-[10px] text-indigo-500 mt-0.5 truncate max-w-full">demo@newbook.test</span>
                        <span class="text-[10px] text-gray-400">→ Ad approvals</span>
                    </button>
                    <button type="button" onclick="quickLogin('yogesh@newbook.test', 'password')" class="flex flex-col items-center justify-center border border-violet-200 hover:border-violet-400 bg-violet-50/50 hover:bg-violet-50 p-2.5 rounded-lg transition cursor-pointer text-center">
                        <span class="text-xs font-bold text-violet-700">Customer Login</span>
                        <span class="text-[10px] text-violet-500 mt-0.5 truncate max-w-full">yogesh@newbook.test</span>
                        <span class="text-[10px] text-gray-400">→ Create & pay ads</span>
                    </button>
                </div>
            </div>

            <script>
                function quickLogin(username, password) {
                    const form = document.querySelector('form[action="{{ route('login') }}"]');
                    const loginInput = form?.querySelector('input[name="login"]');
                    const passwordInput = form?.querySelector('input[name="password"]');
                    if (loginInput && passwordInput && form) {
                        loginInput.value = username;
                        passwordInput.value = password;
                        form.submit();
                    }
                }
            </script>
        </div>
    </div>
</div>
@endsection
