@extends('layouts.app')

@section('title', 'Admin - Chat #' . $conversation->id)

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('admin.chats.index') }}" class="text-sm text-gray-500 hover:text-fb-blue">&larr; All chats</a>
        <h1 class="text-2xl font-extrabold text-gray-900 mt-1">Conversation #{{ $conversation->id }}</h1>
        <div class="flex flex-wrap gap-3 mt-2">
            @foreach($conversation->users as $user)
                <a href="{{ route('profile.show', $user) }}" class="inline-flex items-center gap-2 bg-fb-gray rounded-full pl-1 pr-3 py-1 text-sm font-medium hover:bg-gray-200">
                    <img src="{{ $user->avatar_url }}" alt="" class="w-8 h-8 rounded-full object-cover">
                    {{ $user->name }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="bg-white rounded-xl shadow border border-gray-100 p-4 space-y-3 max-h-[70vh] overflow-y-auto">
        @forelse($messages as $message)
            <div class="flex {{ $message->user_id === auth()->id() ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-md rounded-2xl px-3 py-2 {{ $message->user_id === auth()->id() ? 'bg-indigo-100' : 'bg-fb-gray' }}">
                    <p class="text-xs font-semibold text-gray-600 mb-0.5">{{ $message->user?->name }}</p>
                    @if($message->media_url)
                        @if($message->media_type === 'video')
                            <video src="{{ $message->media_url }}" controls class="rounded-lg max-w-full max-h-48 mb-1"></video>
                        @else
                            <img src="{{ $message->media_url }}" alt="" class="rounded-lg max-w-full max-h-48 mb-1 object-cover">
                        @endif
                    @endif
                    @if($message->body)
                        <p class="text-sm text-gray-900 break-words">{{ $message->body }}</p>
                    @endif
                    <p class="text-xs text-gray-500 mt-1">{{ $message->created_at->timezone(config('app.timezone'))->format('M j, g:i A') }}</p>
                </div>
            </div>
        @empty
            <p class="text-center text-gray-500 py-8">No messages in this conversation.</p>
        @endforelse
    </div>
</div>
@endsection
