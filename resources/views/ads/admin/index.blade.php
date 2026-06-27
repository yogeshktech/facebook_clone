@extends('layouts.app')

@section('title', 'Admin - Client Ads')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-3xl font-extrabold text-gray-900 mt-2">Admin — Client Ads Monitor</h1>
        <p class="text-gray-500">Ads auto-approve after successful payment. Pending/failed payments do not run on feed.</p>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-50 text-green-700 border border-green-200 p-3 rounded-lg text-sm font-medium">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 bg-red-50 text-red-600 border border-red-200 p-3 rounded-lg text-sm">{{ session('error') }}</div>
    @endif

    @if($ads->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
            <h3 class="text-lg font-semibold text-gray-900 mb-1">No client ads yet</h3>
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Client</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Ad</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Budget</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Payment</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Campaign</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Leads</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($ads as $ad)
                        <tr>
                            <td class="px-4 py-4">
                                <div class="text-sm font-semibold">{{ $ad->user->name }}</div>
                                <div class="text-xs text-gray-500">{{ $ad->user->email }}</div>
                            </td>
                            <td class="px-4 py-4 max-w-xs">
                                <div class="font-bold text-sm truncate">{{ $ad->title }}</div>
                                <div class="text-xs text-gray-500 line-clamp-1">{{ $ad->description }}</div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm font-semibold">
                                ₹{{ number_format($ad->amount, 0) }}
                                <div class="text-xs text-gray-500">{{ $ad->plan_label }}</div>
                            </td>
                            <td class="px-4 py-4">
                                <span class="text-xs font-bold px-2 py-1 rounded-full {{ $ad->payment_status === 'paid' ? 'bg-green-100 text-green-800' : ($ad->payment_status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800') }}">
                                    {{ $ad->payment_status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <span class="text-xs font-bold {{ $ad->isRunning() ? 'text-green-700' : 'text-gray-600' }}">
                                    {{ $ad->campaign_status_label }}
                                </span>
                                @if($ad->expires_at)
                                    <div class="text-[10px] text-gray-400 mt-1">Until {{ $ad->expires_at->format('M d, Y') }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-center font-bold">{{ $ad->leads_count }}</td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="flex gap-2">
                                    @if($ad->isRunning())
                                        <form action="{{ route('admin.ads.reject', $ad) }}" method="POST" onsubmit="return confirm('Stop this running ad?')">
                                            @csrf
                                            <button type="submit" class="bg-red-600 text-white text-xs font-bold py-1.5 px-3 rounded">Stop Ad</button>
                                        </form>
                                    @endif
                                    <a href="{{ route('ads.leads', $ad) }}" class="bg-gray-100 text-gray-800 text-xs font-bold py-1.5 px-3 rounded">Leads</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
