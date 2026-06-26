@extends('layouts.app')

@section('title', 'Friends')

@section('content')
<div class="max-w-4xl mx-auto p-4 space-y-6">
    @if($pendingRequests->count())
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">Friend Requests ({{ $pendingRequests->count() }})</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($pendingRequests as $request)
            <div class="flex items-center gap-3 p-3 border rounded-lg">
                <img src="{{ $request->user->avatar_url }}" alt="" class="w-12 h-12 rounded-full object-cover">
                <div class="flex-1">
                    <a href="{{ route('profile.show', $request->user) }}" class="font-semibold hover:underline">{{ $request->user->name }}</a>
                </div>
                <form action="{{ route('friends.accept', $request) }}" method="POST">@csrf<button class="btn-primary text-sm px-3 py-1">Accept</button></form>
                <form action="{{ route('friends.reject', $request) }}" method="POST">@csrf<button class="btn-secondary text-sm px-3 py-1">Reject</button></form>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">Your Friends ({{ $friends->count() }})</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            @forelse($friends as $friend)
            <div class="text-center p-4 border rounded-lg hover:shadow transition">
                <a href="{{ route('profile.show', $friend) }}">
                    <img src="{{ $friend->avatar_url }}" alt="" class="w-20 h-20 rounded-full object-cover mx-auto mb-2">
                    <p class="font-semibold">{{ $friend->name }}</p>
                </a>
                <form action="{{ route('chat.start', $friend) }}" method="POST" class="mt-3">
                    @csrf
                    <button type="submit" class="btn-primary text-sm w-full">Message</button>
                </form>
            </div>
            @empty
            <p class="text-gray-500 col-span-full text-center py-8">No friends yet. Search for people to connect!</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
