@extends('layouts.app')

@section('title', 'Manage Ads')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Advertisement Manager</h1>
            <p class="text-gray-500 text-sm">Create, run, and track leads for your sponsored ads.</p>
        </div>
        <div class="flex gap-2">
            @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.ads.index') }}" class="bg-gray-800 text-white px-4 py-2 rounded-lg font-semibold hover:bg-gray-700 transition text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                    Admin Dashboard
                </a>
            @endif
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
            <div class="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-1">No ads created yet</h3>
            <p class="text-gray-500 mb-6 max-w-md mx-auto">Get more leads and reach customers directly by creating an ad and launching it on Newbook feed.</p>
            <a href="{{ route('ads.create') }}" class="btn-primary inline-flex items-center gap-2">
                Create Your First Ad
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($ads as $ad)
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden flex flex-col justify-between hover:shadow-md transition">
                    <div>
                        {{-- Ad Header --}}
                        <div class="relative h-44 bg-gray-100">
                            @if($ad->image_url)
                                <img src="{{ $ad->image_url }}" alt="{{ $ad->title }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-400">
                                    No Image
                                </div>
                            @endif
                            <div class="absolute top-3 right-3">
                                @if($ad->status === 'approved')
                                    <span class="bg-green-100 text-green-800 text-xs font-semibold px-2.5 py-1 rounded-full border border-green-200">Active</span>
                                @elseif($ad->status === 'pending_approval')
                                    <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2.5 py-1 rounded-full border border-yellow-200">Pending Approval</span>
                                @elseif($ad->status === 'pending_payment')
                                    <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-1 rounded-full border border-blue-200">Pending Payment</span>
                                @else
                                    <span class="bg-red-100 text-red-800 text-xs font-semibold px-2.5 py-1 rounded-full border border-red-200">Rejected</span>
                                @endif
                            </div>
                        </div>

                        {{-- Ad Details --}}
                        <div class="p-5">
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">{{ $ad->plan_label }}</span>
                                <span class="text-sm font-semibold text-gray-900">₹{{ number_format($ad->amount, 2) }}</span>
                            </div>
                            <h3 class="font-bold text-gray-900 text-lg mb-1 truncate">{{ $ad->title }}</h3>
                            <p class="text-gray-600 text-sm line-clamp-2 mb-4">{{ $ad->description }}</p>

                            <hr class="border-gray-100 my-4">

                            {{-- Performance Card --}}
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-fb-gray p-3 rounded-lg text-center">
                                    <span class="block text-xs font-medium text-gray-500">Leads Captured</span>
                                    <span class="text-xl font-bold text-gray-950">{{ $ad->leads_count }}</span>
                                </div>
                                <div class="bg-fb-gray p-3 rounded-lg text-center">
                                    <span class="block text-xs font-medium text-gray-500">Payment Status</span>
                                    <span class="text-xs font-bold mt-1 inline-block {{ $ad->payment_status === 'paid' ? 'text-green-600' : 'text-red-500' }}">
                                        {{ strtoupper($ad->payment_status) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Card Footer --}}
                    <div class="px-5 pb-5 pt-0 flex gap-2">
                        @if($ad->status === 'pending_payment')
                            <a href="{{ route('ads.payment', $ad) }}" class="flex-1 text-center bg-indigo-600 text-white py-2 rounded-lg font-semibold hover:bg-indigo-700 transition text-sm">
                                Pay Now
                            </a>
                        @else
                            <a href="{{ route('ads.leads', $ad) }}" class="flex-1 text-center bg-gray-100 hover:bg-gray-200 text-gray-800 py-2 rounded-lg font-semibold transition text-sm">
                                View Leads
                            </a>
                            <a href="{{ route('ads.leads.download', $ad) }}" class="px-3 flex items-center justify-center bg-indigo-50 hover:bg-indigo-100 text-indigo-600 rounded-lg transition" title="Download Leads CSV">
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
