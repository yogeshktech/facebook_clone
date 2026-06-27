@extends('layouts.app')

@section('title', 'Admin - Approve Ads')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('ads.index') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800 transition flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Advertisements
        </a>
        <h1 class="text-3xl font-extrabold text-gray-900 mt-2">Admin Ad Approvals</h1>
        <p class="text-gray-500">Approve, reject, or check payment statuses of all user advertisements.</p>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-50 text-green-700 border border-green-200 p-3 rounded-lg text-sm font-medium">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 bg-red-50 text-red-600 border border-red-200 p-3 rounded-lg text-sm">{{ session('error') }}</div>
    @endif

    @if($ads->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
            <div class="w-16 h-16 bg-gray-50 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-1">No ads found in the system</h3>
            <p class="text-gray-500">When users create ads, they will show up here for validation.</p>
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Advertiser</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Creative Info</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Plan & Price</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Payment Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Campaign Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @foreach($ads as $ad)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $ad->created_at->format('M d, Y h:i A') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <img src="{{ $ad->user->avatar_url }}" alt="" class="w-8 h-8 rounded-full object-cover">
                                        <div class="text-sm font-semibold text-gray-900">
                                            {{ $ad->user->name }}
                                            <span class="block text-xs font-normal text-gray-500">{{ $ad->user->email }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 max-w-sm">
                                    <div class="flex items-start gap-3">
                                        @if($ad->image_url)
                                            <a href="{{ $ad->image_url }}" target="_blank" class="block w-12 h-12 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0">
                                                <img src="{{ $ad->image_url }}" alt="" class="w-full h-full object-cover">
                                            </a>
                                        @endif
                                        <div class="min-w-0">
                                            <h4 class="text-sm font-bold text-gray-950 truncate">{{ $ad->title }}</h4>
                                            <p class="text-xs text-gray-500 line-clamp-2 mt-1">{{ $ad->description }}</p>
                                            <span class="text-xs font-semibold inline-block bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded mt-1">{{ $ad->cta_text }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-gray-900">
                                        ₹{{ number_format($ad->amount, 2) }}
                                        <span class="block text-xs font-normal text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded mt-1 text-center w-fit uppercase font-bold tracking-wider">{{ $ad->plan_label }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-xs font-bold px-2.5 py-1 rounded-full uppercase border {{ $ad->payment_status === 'paid' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' }}">
                                        {{ $ad->payment_status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($ad->status === 'approved')
                                        <span class="bg-green-100 text-green-800 text-xs font-bold px-2.5 py-1 rounded-full border border-green-200 uppercase">Active</span>
                                        @if($ad->expires_at)
                                            <span class="block text-2xs text-gray-500 mt-1">Expires: {{ $ad->expires_at->format('M d, Y') }}</span>
                                        @endif
                                    @elseif($ad->status === 'pending_approval')
                                        <span class="bg-yellow-100 text-yellow-800 text-xs font-bold px-2.5 py-1 rounded-full border border-yellow-200 uppercase">Pending Approval</span>
                                    @elseif($ad->status === 'pending_payment')
                                        <span class="bg-blue-100 text-blue-800 text-xs font-bold px-2.5 py-1 rounded-full border border-blue-200 uppercase">Pending Payment</span>
                                    @else
                                        <span class="bg-red-100 text-red-800 text-xs font-bold px-2.5 py-1 rounded-full border border-red-200 uppercase">Rejected</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex gap-2">
                                        @if($ad->payment_status === 'paid' && $ad->status === 'pending_approval')
                                            <form action="{{ route('admin.ads.approve', $ad) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold text-xs py-1.5 px-3 rounded transition">
                                                    Approve
                                                </button>
                                            </form>
                                        @elseif($ad->payment_status !== 'paid')
                                            <span class="text-xs text-amber-600 font-semibold self-center">Awaiting payment</span>
                                        @endif
                                        @if($ad->status !== 'rejected' && $ad->status !== 'approved')
                                            <form action="{{ route('admin.ads.reject', $ad) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold text-xs py-1.5 px-3 rounded transition">
                                                    Reject
                                                </button>
                                            </form>
                                        @endif
                                        <a href="{{ route('ads.leads', $ad) }}" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold text-xs py-1.5 px-3 rounded transition">
                                            Leads
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
