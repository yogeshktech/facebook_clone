@extends('layouts.app')

@section('title', 'Admin - Search Chats')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('admin.chats.index') }}" class="text-sm text-gray-500 hover:text-fb-blue">&larr; All chats</a>
        <h1 class="text-2xl font-extrabold text-gray-900 mt-1">Search messages</h1>
    </div>

    <form action="{{ route('admin.chats.search') }}" method="GET" class="flex gap-2 mb-6">
        <input type="search" name="q" value="{{ $query }}" placeholder="Search in decrypted messages..." required minlength="2"
            class="input-field flex-1">
        <button type="submit" class="btn-primary">Search</button>
    </form>

    @if(strlen($query) >= 2)
        <p class="text-sm text-gray-500 mb-4">{{ $messages->count() }} result(s) for "{{ $query }}"</p>
        <div class="bg-white rounded-xl shadow border divide-y">
            @forelse($messages as $message)
                <div class="p-4 hover:bg-gray-50">
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <p class="text-sm font-semibold">{{ $message->user?->name }}</p>
                        <a href="{{ route('admin.chats.show', $message->conversation_id) }}" class="text-xs text-fb-blue font-medium">Open chat #{{ $message->conversation_id }}</a>
                    </div>
                    <p class="text-sm text-gray-800 break-words">{{ $message->body }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $message->created_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</p>
                </div>
            @empty
                <p class="p-8 text-center text-gray-500">No messages found.</p>
            @endforelse
        </div>
    @endif
</div>
@endsection
