@extends('layouts.app')

@section('title', 'Friends')

@section('content')
<div class="max-w-2xl mx-auto p-4 space-y-4">
    @if($pendingRequests->count())
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100">
            <h2 class="text-lg font-bold text-gray-900">Friend Requests</h2>
            <p class="text-xs text-gray-500 mt-0.5">{{ $pendingRequests->count() }} pending</p>
        </div>
        <ul class="divide-y divide-gray-100">
            @foreach($pendingRequests as $request)
            <li class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition">
                <a href="{{ route('profile.show', $request->user) }}" class="flex-shrink-0">
                    <img src="{{ $request->user->avatar_url }}" alt="" class="w-12 h-12 rounded-full object-cover">
                </a>
                <div class="flex-1 min-w-0">
                    <a href="{{ route('profile.show', $request->user) }}" class="font-semibold text-gray-900 hover:underline block truncate">
                        {{ $request->user->name }}
                    </a>
                    <p class="text-xs text-gray-500">Wants to be your friend</p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <form action="{{ route('friends.accept', $request) }}" method="POST">
                        @csrf
                        <button type="submit" class="bg-fb-blue text-white text-sm font-semibold px-4 py-1.5 rounded-lg hover:bg-fb-blue-dark whitespace-nowrap">
                            Confirm
                        </button>
                    </form>
                    <form action="{{ route('friends.reject', $request) }}" method="POST">
                        @csrf
                        <button type="submit" class="bg-fb-gray text-gray-800 text-sm font-semibold px-4 py-1.5 rounded-lg hover:bg-gray-200 whitespace-nowrap">
                            Delete
                        </button>
                    </form>
                </div>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    @if($sentRequests->count())
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100">
            <h2 class="text-lg font-bold text-gray-900">Sent Requests</h2>
            <p class="text-xs text-gray-500 mt-0.5">{{ $sentRequests->count() }} waiting for response</p>
        </div>
        <ul class="divide-y divide-gray-100">
            @foreach($sentRequests as $request)
            <li class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition">
                <a href="{{ route('profile.show', $request->friend) }}" class="flex-shrink-0">
                    <img src="{{ $request->friend->avatar_url }}" alt="" class="w-12 h-12 rounded-full object-cover">
                </a>
                <div class="flex-1 min-w-0">
                    <a href="{{ route('profile.show', $request->friend) }}" class="font-semibold text-gray-900 hover:underline block truncate">
                        {{ $request->friend->name }}
                    </a>
                    <p class="text-xs text-amber-600 font-medium">Pending</p>
                </div>
                <form action="{{ route('friends.cancel', $request) }}" method="POST" class="flex-shrink-0">
                    @csrf
                    <button type="submit" class="bg-fb-gray text-gray-800 text-sm font-semibold px-4 py-1.5 rounded-lg hover:bg-gray-200 whitespace-nowrap">
                        Cancel
                    </button>
                </form>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100">
            <h2 class="text-lg font-bold text-gray-900">Your Friends</h2>
            <p class="text-xs text-gray-500 mt-0.5">{{ $friends->count() }} friends</p>
        </div>
        <ul class="divide-y divide-gray-100">
            @forelse($friends as $friend)
            <li class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition">
                <a href="{{ route('profile.show', $friend) }}" class="flex-shrink-0">
                    <img src="{{ $friend->avatar_url }}" alt="" class="w-12 h-12 rounded-full object-cover">
                </a>
                <div class="flex-1 min-w-0">
                    <a href="{{ route('profile.show', $friend) }}" class="font-semibold text-gray-900 hover:underline block truncate">
                        {{ $friend->name }}
                    </a>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <form action="{{ route('chat.start', $friend) }}" method="POST">
                        @csrf
                        <button type="submit" class="bg-fb-blue text-white text-sm font-semibold px-4 py-1.5 rounded-lg hover:bg-fb-blue-dark whitespace-nowrap">
                            Message
                        </button>
                    </form>
                    <form action="{{ route('friends.unfriend', $friend) }}" method="POST"
                        onsubmit="return confirm('Remove {{ $friend->name }} from friends?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="bg-fb-gray text-gray-800 text-sm font-semibold px-3 py-1.5 rounded-lg hover:bg-gray-200 whitespace-nowrap">
                            Unfriend
                        </button>
                    </form>
                </div>
            </li>
            @empty
            <li class="px-4 py-10 text-center text-gray-500 text-sm">
                No friends yet. Search for people to connect!
            </li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
