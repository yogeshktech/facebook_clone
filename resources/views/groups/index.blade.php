@extends('layouts.app')

@section('title', 'Groups')

@section('content')
<div class="max-w-4xl mx-auto p-4 space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold">Groups</h1>
        <a href="{{ route('groups.create') }}" class="btn-primary">Create Group</a>
    </div>

    @if($myGroups->count())
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Your Groups</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($myGroups as $group)
            <a href="{{ route('groups.show', $group) }}" class="flex items-center gap-3 p-3 border rounded-lg hover:shadow transition">
                <img src="{{ $group->avatar_url }}" alt="" class="w-14 h-14 rounded-lg object-cover">
                <div>
                    <p class="font-semibold">{{ $group->name }}</p>
                    <p class="text-sm text-gray-500">{{ $group->members_count }} members</p>
                </div>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Discover Groups</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @forelse($discoverGroups as $group)
            <div class="flex items-center gap-3 p-3 border rounded-lg">
                <img src="{{ $group->avatar_url }}" alt="" class="w-14 h-14 rounded-lg object-cover">
                <div class="flex-1">
                    <a href="{{ route('groups.show', $group) }}" class="font-semibold hover:underline">{{ $group->name }}</a>
                    <p class="text-sm text-gray-500">{{ $group->members_count }} members · {{ ucfirst($group->privacy) }}</p>
                </div>
                <form action="{{ route('groups.join', $group) }}" method="POST">@csrf<button class="btn-primary text-sm px-3 py-1">Join</button></form>
            </div>
            @empty
            <p class="text-gray-500 col-span-full">No groups to discover.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
