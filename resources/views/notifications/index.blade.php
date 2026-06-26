@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
<div class="max-w-2xl mx-auto p-4">
    <div class="bg-white rounded-lg shadow">
        <div class="p-4 border-b flex justify-between items-center">
            <h1 class="text-2xl font-bold">Notifications</h1>
            <form action="{{ route('notifications.readAll') }}" method="POST" id="mark-all-read">
                @csrf
                <button type="submit" class="text-fb-blue text-sm font-semibold hover:underline">Mark all as read</button>
            </form>
        </div>
        <div class="divide-y">
            @forelse($notifications as $notification)
            <div class="p-4 flex gap-3 {{ $notification->read_at ? '' : 'bg-blue-50' }}">
                @if(isset($notification->data['user']))
                <img src="{{ $notification->data['user']['avatar_url'] ?? '' }}" class="w-12 h-12 rounded-full object-cover">
                @endif
                <div class="flex-1">
                    <p class="text-sm">{{ $notification->data['message'] ?? 'New notification' }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                    @if(isset($notification->data['url']))
                    <a href="{{ $notification->data['url'] }}" class="text-fb-blue text-sm hover:underline mt-1 inline-block">View</a>
                    @endif
                </div>
            </div>
            @empty
            <p class="p-8 text-center text-gray-500">No notifications yet.</p>
            @endforelse
        </div>
        {{ $notifications->links() }}
    </div>
</div>
@endsection
