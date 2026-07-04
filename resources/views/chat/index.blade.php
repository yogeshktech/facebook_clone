@extends('layouts.app')

@section('title', 'Messenger')

@section('content')
<div class="max-w-4xl mx-auto p-4 space-y-4 pb-24 md:pb-8">
    @if(session('success'))
        <div class="bg-green-50 text-green-700 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-rose-50 text-rose-700 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
    @endif

    {{-- Create group --}}
    @if($friends->count())
    <div class="bg-white rounded-lg shadow">
        <div class="p-4 border-b flex items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">New Group</h2>
                <p class="text-sm text-gray-500">Add friends and chat together</p>
            </div>
            <button type="button" id="toggle-group-form" class="btn-primary text-sm px-4 py-2">Create group</button>
        </div>
        <form id="group-form" action="{{ route('chat.group.create') }}" method="POST" class="hidden p-4 space-y-3 border-t">
            @csrf
            <input type="text" name="name" required maxlength="80" placeholder="Group name"
                class="input-field w-full">
            <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide">Select friends</p>
            <div class="max-h-48 overflow-y-auto divide-y border rounded-lg">
                @foreach($friends as $friend)
                <label class="flex items-center gap-3 p-3 hover:bg-fb-gray cursor-pointer">
                    <input type="checkbox" name="user_ids[]" value="{{ $friend->id }}" class="rounded text-fb-blue focus:ring-fb-blue">
                    <img src="{{ $friend->avatar_url }}" alt="" class="w-9 h-9 rounded-full object-cover">
                    <span class="font-medium truncate">{{ $friend->name }}</span>
                </label>
                @endforeach
            </div>
            <button type="submit" class="btn-primary w-full sm:w-auto">Create group chat</button>
        </form>
    </div>
    @endif

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
                    $isGroup = $conversation->isGroup();
                    $otherUser = $isGroup ? null : $conversation->users->where('id', '!=', auth()->id())->first();
                    $lastMessage = $conversation->latestMessage;
                    $title = $isGroup ? ($conversation->name ?: 'Group Chat') : ($otherUser?->name ?? 'Chat');
                    $avatar = $isGroup
                        ? 'https://ui-avatars.com/api/?name='.urlencode($title).'&background=6366F1&color=fff'
                        : ($otherUser?->avatar_url ?? '');
                @endphp
                <div class="flex items-center gap-2 p-4 hover:bg-fb-gray transition group">
                    <a href="{{ route('chat.show', $conversation) }}" class="flex items-center gap-3 flex-1 min-w-0">
                        <div class="relative flex-shrink-0">
                            <img src="{{ $avatar }}" alt="" class="w-14 h-14 rounded-full object-cover">
                            @if($isGroup)
                                <span class="absolute -bottom-0.5 -right-0.5 w-5 h-5 rounded-full bg-fb-blue text-white text-[10px] flex items-center justify-center border-2 border-white">{{ $conversation->users->count() }}</span>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold truncate">{{ $title }}</p>
                            @if($lastMessage)
                                <p class="text-sm text-gray-500 truncate">
                                    @if($lastMessage->isDeletedForEveryone())
                                        This message was deleted
                                    @elseif($lastMessage->isCall())
                                        {{ $lastMessage->callLabelFor(auth()->id()) }}
                                    @else
                                        @if($isGroup && $lastMessage->user_id !== auth()->id())
                                            <span class="font-medium">{{ $lastMessage->user?->name }}:</span>
                                        @endif
                                        @if($lastMessage->media_path)
                                            {{ $lastMessage->media_type === 'video' ? '🎥 Video' : '📷 Photo' }}
                                        @endif
                                        {{ $lastMessage->body }}
                                    @endif
                                </p>
                            @else
                                <p class="text-sm text-gray-400 italic">Start chatting...</p>
                            @endif
                        </div>
                        @if($lastMessage)
                            <span class="text-xs text-gray-400 flex-shrink-0">{{ $lastMessage->created_at->diffForHumans() }}</span>
                        @endif
                    </a>
                    <form action="{{ route('chat.destroy', $conversation) }}" method="POST" class="flex-shrink-0"
                        onsubmit="return confirm('Delete this chat from your list? Messages stay for others.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="p-2 text-gray-400 hover:text-rose-600 rounded-full hover:bg-rose-50" title="Delete chat">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </form>
                </div>
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

<script>
document.getElementById('toggle-group-form')?.addEventListener('click', () => {
    document.getElementById('group-form')?.classList.toggle('hidden');
});
</script>
@endsection
