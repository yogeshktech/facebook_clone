@extends('layouts.app')

@section('title', 'My Ads')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Client Ad Dashboard</h1>
            <p class="text-gray-500 text-sm">Create ads, set budget, pay — ad runs automatically after successful payment.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('ads.create') }}" class="btn-primary flex items-center gap-2 text-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create New Ad
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-50 text-green-700 border border-green-200 p-3 rounded-lg text-sm font-medium">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 bg-red-50 text-red-600 border border-red-200 p-3 rounded-lg text-sm">{{ session('error') }}</div>
    @endif

    @if($ads->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
            <h3 class="text-lg font-semibold text-gray-900 mb-1">No ads yet</h3>
            <p class="text-gray-500 mb-6 max-w-md mx-auto">Step 1: Create ad → Step 2: Choose budget plan → Step 3: Pay → Ad goes live automatically.</p>
            <a href="{{ route('ads.create') }}" class="btn-primary inline-flex items-center gap-2">Create Your First Ad</a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($ads as $ad)
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden flex flex-col justify-between">
                    <div>
                        <div class="relative h-44 bg-gray-100">
                            @if($ad->image_url)
                                <img src="{{ $ad->image_url }}" alt="{{ $ad->title }}" class="w-full h-full object-cover">
                            @endif
                            <div class="absolute top-3 right-3 flex flex-col gap-1 items-end">
                                @if($ad->isRunning())
                                    <span class="bg-green-100 text-green-800 text-xs font-semibold px-2.5 py-1 rounded-full border border-green-200">Running</span>
                                @elseif($ad->payment_status === 'failed')
                                    <span class="bg-red-100 text-red-800 text-xs font-semibold px-2.5 py-1 rounded-full border border-red-200">Payment Failed</span>
                                @elseif($ad->payment_status === 'pending')
                                    <span class="bg-amber-100 text-amber-800 text-xs font-semibold px-2.5 py-1 rounded-full border border-amber-200">Payment Pending</span>
                                @elseif($ad->status === 'rejected')
                                    <span class="bg-red-100 text-red-800 text-xs font-semibold px-2.5 py-1 rounded-full border border-red-200">Stopped</span>
                                @else
                                    <span class="bg-gray-100 text-gray-700 text-xs font-semibold px-2.5 py-1 rounded-full">Not Running</span>
                                @endif
                            </div>
                        </div>

                        <div class="p-5">
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">{{ $ad->plan_label }}</span>
                                <span class="text-sm font-semibold text-gray-900">Budget: ₹{{ number_format($ad->amount, 0) }}</span>
                            </div>
                            <h3 class="font-bold text-gray-900 text-lg mb-1 truncate">{{ $ad->title }}</h3>
                            <p class="text-gray-600 text-sm line-clamp-2 mb-3">{{ $ad->description }}</p>

                            <div class="bg-fb-gray rounded-lg p-3 text-xs space-y-1.5">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Payment</span>
                                    <span class="font-bold {{ $ad->payment_status === 'paid' ? 'text-green-600' : ($ad->payment_status === 'failed' ? 'text-red-600' : 'text-amber-600') }}">
                                        {{ $ad->payment_status_label }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Campaign</span>
                                    <span class="font-bold text-gray-800">{{ $ad->campaign_status_label }}</span>
                                </div>
                                @if($ad->isRunning() && $ad->expires_at)
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Runs until</span>
                                        <span class="font-medium">{{ $ad->expires_at->format('M d, Y') }}</span>
                                    </div>
                                @endif
                            </div>

                            <div class="mt-4 bg-fb-gray p-3 rounded-lg text-center">
                                <span class="block text-xs text-gray-500">Leads</span>
                                <span class="text-xl font-bold">{{ $ad->leads_count }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="px-5 pb-5 flex gap-2">
                        @if($ad->needsPayment())
                            <a href="{{ route('ads.payment', $ad) }}" class="flex-1 text-center bg-indigo-600 text-white py-2 rounded-lg font-semibold hover:bg-indigo-700 transition text-sm">
                                {{ $ad->payment_status === 'failed' ? 'Retry Payment' : 'Pay Now' }}
                            </a>
                        @else
                            <a href="{{ route('ads.leads', $ad) }}" class="flex-1 text-center bg-gray-100 hover:bg-gray-200 text-gray-800 py-2 rounded-lg font-semibold transition text-sm">
                                View Leads
                            </a>
                            <a href="{{ route('ads.leads.download', $ad) }}" class="px-3 flex items-center justify-center bg-indigo-50 text-indigo-600 rounded-lg" title="Download CSV">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
