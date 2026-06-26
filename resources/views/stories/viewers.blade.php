@extends('layouts.app')

@section('title', 'Story Views')

@section('content')
<div class="max-w-md mx-auto p-4">
    <div class="bg-white rounded-lg shadow">
        <div class="p-4 border-b flex items-center gap-3">
            <a href="{{ route('stories.show', $story) }}" class="text-gray-500">&larr;</a>
            <h1 class="text-lg font-bold">{{ $viewers->count() }} Views</h1>
        </div>
        <div class="divide-y">
            @forelse($viewers as $viewer)
            <a href="{{ route('profile.show', $viewer) }}" class="flex items-center gap-3 p-4 hover:bg-fb-gray">
                <img src="{{ $viewer->avatar_url }}" class="w-12 h-12 rounded-full object-cover" alt="">
                <div>
                    <p class="font-semibold">{{ $viewer->name }}</p>
                    <p class="text-sm text-gray-500">{{ $viewer->pivot->viewed_at->diffForHumans() }}</p>
                </div>
            </a>
            @empty
            <p class="p-8 text-center text-gray-500">No one has viewed this story yet</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
