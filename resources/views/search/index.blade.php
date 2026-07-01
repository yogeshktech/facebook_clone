@extends('layouts.app')

@section('title', 'Search')

@section('content')
<div class="max-w-4xl mx-auto p-4">
    <form action="{{ route('search') }}" method="GET" class="mb-6">
        <input type="search" name="q" value="{{ $query }}" placeholder="Search people, groups, pages..."
            class="w-full bg-white rounded-full px-6 py-3 shadow focus:outline-none focus:ring-2 focus:ring-fb-blue text-lg">
    </form>

    @if(strlen($query) >= 2)
        @if($users->count())
        <div class="bg-white rounded-lg shadow p-6 mb-4">
            <h2 class="text-lg font-semibold mb-4">People</h2>
            @foreach($users as $user)
            <div class="flex items-center gap-3 py-3 border-b last:border-0">
                <img src="{{ $user->avatar_url }}" class="w-12 h-12 rounded-full object-cover">
                <a href="{{ route('profile.show', $user) }}" class="flex-1 font-semibold hover:underline">{{ $user->name }}</a>
                @if($user->id !== auth()->id())
                    @if(auth()->user()->isFriendsWith($user))
                        <span class="text-gray-500 text-sm font-medium">Friends</span>
                    @elseif(auth()->user()->hasPendingRequestTo($user))
                        <span class="text-gray-500 text-sm font-medium italic">Request Sent</span>
                    @elseif($user->hasPendingRequestTo(auth()->user()))
                        @php
                            $friendship = \App\Models\Friendship::where('user_id', $user->id)
                                ->where('friend_id', auth()->id())
                                ->where('status', 'pending')
                                ->first();
                        @endphp
                        @if($friendship)
                            <div class="flex gap-2">
                                <form action="{{ route('friends.accept', $friendship) }}" method="POST">
                                    @csrf
                                    <button class="bg-fb-blue text-white text-xs px-2.5 py-1 rounded font-semibold hover:bg-fb-blue-dark transition">Accept</button>
                                </form>
                                <form action="{{ route('friends.reject', $friendship) }}" method="POST">
                                    @csrf
                                    <button class="bg-gray-200 text-gray-800 text-xs px-2.5 py-1 rounded font-semibold hover:bg-gray-300 transition">Decline</button>
                                </form>
                            </div>
                        @else
                            <span class="text-gray-500 text-sm font-medium">Pending Response</span>
                        @endif
                    @else
                        <form action="{{ route('friends.send', $user) }}" method="POST">
                            @csrf
                            <button class="btn-primary text-sm px-3 py-1">Add Friend</button>
                        </form>
                    @endif
                @endif
            </div>
            @endforeach
        </div>
        @endif

        @if($groups->count())
        <div class="bg-white rounded-lg shadow p-6 mb-4">
            <h2 class="text-lg font-semibold mb-4">Groups</h2>
            @foreach($groups as $group)
            <a href="{{ route('groups.show', $group) }}" class="flex items-center gap-3 py-3 border-b last:border-0 hover:bg-fb-gray">
                <img src="{{ $group->avatar_url }}" class="w-12 h-12 rounded-lg object-cover">
                <div><p class="font-semibold">{{ $group->name }}</p><p class="text-sm text-gray-500">{{ $group->members_count }} members</p></div>
            </a>
            @endforeach
        </div>
        @endif

        @if($pages->count())
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Pages</h2>
            @foreach($pages as $page)
            <a href="{{ route('pages.show', $page) }}" class="flex items-center gap-3 py-3 border-b last:border-0 hover:bg-fb-gray">
                <img src="{{ $page->avatar_url }}" class="w-12 h-12 rounded-lg object-cover">
                <div><p class="font-semibold">{{ $page->name }}</p><p class="text-sm text-gray-500">{{ $page->category }}</p></div>
            </a>
            @endforeach
        </div>
        @endif

        @if(!$users->count() && !$groups->count() && !$pages->count())
            <p class="text-center text-gray-500 py-8">No results found for "{{ $query }}"</p>
        @endif
    @else
        <p class="text-center text-gray-500 py-8">Type at least 2 characters to search</p>
    @endif
</div>
@endsection
