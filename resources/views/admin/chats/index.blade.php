@extends('layouts.app')

@section('title', 'Admin - Chats')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <a href="{{ route('admin.ads.index') }}" class="text-sm text-gray-500 hover:text-fb-blue">&larr; Admin panel</a>
            <h1 class="text-3xl font-extrabold text-gray-900 mt-1">Chat Monitor</h1>
            <p class="text-gray-500 text-sm mt-1">Encrypted messages — admin-only access. Users cannot read DB directly.</p>
        </div>
        <form action="{{ route('admin.chats.search') }}" method="GET" class="flex gap-2">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search messages..."
                class="input-field max-w-xs text-sm" minlength="2">
            <button type="submit" class="btn-primary text-sm">Search</button>
        </form>
    </div>

    <div class="bg-amber-50 border border-amber-200 text-amber-900 text-sm rounded-lg p-3 mb-6">
        Messages are encrypted in the database. Only participants and admins can read them through the app.
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Participants</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Last message</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Messages</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Updated</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($conversations as $conversation)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-600">#{{ $conversation->id }}</td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap gap-2">
                            @foreach($conversation->users as $user)
                                <a href="{{ route('profile.show', $user) }}" class="inline-flex items-center gap-1.5 text-sm font-medium hover:underline">
                                    <img src="{{ $user->avatar_url }}" alt="" class="w-6 h-6 rounded-full object-cover">
                                    {{ $user->name }}
                                </a>
                            @endforeach
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700 max-w-xs truncate">
                        @if($conversation->latestMessage)
                            <span class="font-medium">{{ $conversation->latestMessage->user?->name }}:</span>
                            {{ \Illuminate\Support\Str::limit($conversation->latestMessage->body ?: '[media]', 60) }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm">{{ $conversation->messages_count }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $conversation->updated_at->diffForHumans() }}</td>
                    <td class="px-4 py-3">
                        <a href="{{ route('admin.chats.show', $conversation) }}" class="text-fb-blue text-sm font-semibold hover:underline">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-12 text-center text-gray-500">No conversations yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $conversations->links() }}</div>
</div>
@endsection
