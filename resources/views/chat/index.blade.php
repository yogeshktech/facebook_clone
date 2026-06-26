@extends('layouts.app')

@section('title', 'Messenger')

@section('content')
<div class="max-w-4xl mx-auto p-4 space-y-4">
    {{-- Start new chat --}}
    @if($friends->count())
    <div class="bg-white rounded-lg shadow">
        <div class="p-4 border-b">
            <h2 class="text-lg font-bold">New Message</h2>
            <p class="text-sm text-gray-500">Message a friend</p>
        </div>
        <div class="divide-y max-h-64 overflow-y-auto">
            @foreach($friends as $friend)
            <div class="flex items-center gap-3 p-4 hover:bg-fb-gray">
                <a href="{{ route('profile.show', $friend) }}" class="flex items-center gap-3 flex-1 min-w-0">
                    <img src="{{ $friend->avatar_url }}" alt="" class="w-12 h-12 rounded-full object-cover">
                    <p class="font-semibold truncate">{{ $friend->name }}</p>
                </a>
                <form action="{{ route('chat.start', $friend) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn-primary text-sm px-4 py-2">Message</button>
                </form>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Existing conversations --}}
    <div class="bg-white rounded-lg shadow">
        <div class="p-4 border-b">
            <h1 class="text-2xl font-bold">Messenger</h1>
        </div>
        <div class="divide-y">
            @forelse($conversations as $conversation)
                @php
                    $otherUser = $conversation->users->where('id', '!=', auth()->id())->first();
                    $lastMessage = $conversation->latestMessage;
                @endphp
                <a href="{{ route('chat.show', $conversation) }}" class="flex items-center gap-3 p-4 hover:bg-fb-gray transition">
                    <img src="{{ $otherUser?->avatar_url ?? '' }}" alt="" class="w-14 h-14 rounded-full object-cover">
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold">{{ $otherUser?->name ?? 'Group Chat' }}</p>
                        @if($lastMessage)
                            <p class="text-sm text-gray-500 truncate">
                                @if($lastMessage->media_path)
                                    {{ $lastMessage->media_type === 'video' ? '🎥 Video' : '📷 Photo' }}
                                @endif
                                {{ $lastMessage->body }}
                            </p>
                        @else
                            <p class="text-sm text-gray-400 italic">Start chatting...</p>
                        @endif
                    </div>
                    @if($lastMessage)
                        <span class="text-xs text-gray-400">{{ $lastMessage->created_at->diffForHumans() }}</span>
                    @endif
                </a>
            @empty
                <div class="p-8 text-center text-gray-500">
                    @if($friends->count())
                        <p>Tap <strong>Message</strong> next to a friend above to start chatting.</p>
                    @else
                        <p>Add friends first, then you can chat here.</p>
                        <a href="{{ route('friends.index') }}" class="text-fb-blue hover:underline mt-2 inline-block">View friends</a>
                    @endif
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
