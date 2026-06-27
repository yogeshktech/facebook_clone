@extends('layouts.app')

@section('title', 'Pay for Ad')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        {{-- Left: Checkout Form --}}
        <div class="lg:col-span-7 space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Payment Details</h2>

                @if($errors->any())
                    <div class="bg-red-50 text-red-600 border border-red-200 p-3 rounded-lg mb-4 text-sm">
                        @foreach($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <p class="text-xs text-gray-500 mb-4 bg-amber-50 border border-amber-100 rounded-lg p-3">
                    Demo payment: use card <strong>4111 1111 1111 1111</strong>, expiry <strong>12/30</strong>, CVV <strong>123</strong>
                </p>
                
                <form action="{{ route('ads.pay', $ad) }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label for="card_name" class="block text-sm font-semibold text-gray-700 mb-1">Name on Card *</label>
                        <input type="text" name="card_name" id="card_name" required placeholder="e.g. John Doe" value="{{ old('card_name', auth()->user()->name) }}" class="input-field">
                    </div>

                    <div>
                        <label for="card_number" class="block text-sm font-semibold text-gray-700 mb-1">Card Number *</label>
                        <div class="relative">
                            <input type="text" name="card_number" id="card_number" required placeholder="1234 5678 1234 5678" maxlength="19" class="input-field pl-10">
                            <span class="absolute left-3 top-3.5 text-gray-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="expiry" class="block text-sm font-semibold text-gray-700 mb-1">Expiry Date *</label>
                            <input type="text" name="expiry" id="expiry" required placeholder="MM/YY" maxlength="5" class="input-field text-center">
                        </div>
                        <div>
                            <label for="cvv" class="block text-sm font-semibold text-gray-700 mb-1">CVV / CVC *</label>
                            <input type="password" name="cvv" id="cvv" required placeholder="123" maxlength="4" class="input-field text-center">
                        </div>
                    </div>

                    <div class="bg-fb-gray p-4 rounded-lg flex items-start gap-3 mt-6">
                        <input type="checkbox" id="terms" required checked class="mt-1 rounded text-indigo-600 focus:ring-indigo-500">
                        <label for="terms" class="text-xs text-gray-600 leading-normal">
                            I authorize Newbook to charge my card for this subscription plan. Plan will renew automatically or expire depending on settings. All payments are non-refundable.
                        </label>
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded-lg transition mt-6 text-sm flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Pay ₹{{ number_format($ad->amount, 2) }}
                    </button>
                </form>
            </div>
        </div>

        {{-- Right: Order Summary --}}
        <div class="lg:col-span-5 space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                <h3 class="text-lg font-bold text-gray-900">Campaign Summary</h3>
                
                <div class="flex gap-4">
                    @if($ad->image_url)
                        <img src="{{ $ad->image_url }}" alt="" class="w-20 h-20 rounded-lg object-cover bg-gray-100 flex-shrink-0">
                    @endif
                    <div class="min-w-0">
                        <h4 class="font-bold text-gray-900 text-sm truncate">{{ $ad->title }}</h4>
                        <p class="text-xs text-gray-500 line-clamp-2 mt-1">{{ $ad->description }}</p>
                        <span class="text-xs font-semibold inline-block bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded mt-2">{{ $ad->cta_text }}</span>
                    </div>
                </div>

                <hr class="border-gray-100">

                <div class="space-y-2 text-sm text-gray-600">
                    <div class="flex justify-between">
                        <span>Selected Plan</span>
                        <span class="font-semibold text-gray-900">{{ $ad->plan_label }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Duration</span>
                        <span class="font-semibold text-gray-900">
                            @if($ad->plan === 'monthly') 30 Days
                            @elseif($ad->plan === 'quarterly') 90 Days
                            @elseif($ad->plan === 'half_yearly') 180 Days
                            @else 365 Days
                            @endif
                        </span>
                    </div>
                </div>

                <hr class="border-gray-100">

                <div class="flex justify-between items-center text-gray-900">
                    <span class="font-bold">Total Amount Due</span>
                    <span class="text-xl font-extrabold text-indigo-600">₹{{ number_format($ad->amount, 2) }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Format card number with spaces
    document.getElementById('card_number')?.addEventListener('input', function (e) {
        let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
        let formatted = '';
        for (let i = 0; i < value.length; i++) {
            if (i > 0 && i % 4 === 0) {
                formatted += ' ';
            }
            formatted += value[i];
        }
        e.target.value = formatted;
    });

    // Format expiry date as MM/YY
    document.getElementById('expiry')?.addEventListener('input', function (e) {
        let value = e.target.value.replace(/[^0-9]/g, '');
        if (value.length > 2) {
            e.target.value = value.substring(0, 2) + '/' + value.substring(2, 4);
        } else {
            e.target.value = value;
        }
    });
</script>
@endsection
