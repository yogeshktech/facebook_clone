@extends('layouts.app')

@section('title', 'Pages')

@section('content')
<div class="max-w-4xl mx-auto p-4 space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold">Pages</h1>
        <a href="{{ route('pages.create') }}" class="btn-primary">Create Page</a>
    </div>

    @if($myPages->count())
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Your Pages</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($myPages as $page)
            <a href="{{ route('pages.show', $page) }}" class="flex items-center gap-3 p-3 border rounded-lg hover:shadow">
                <img src="{{ $page->avatar_url }}" class="w-14 h-14 rounded-lg object-cover">
                <div><p class="font-semibold">{{ $page->name }}</p><p class="text-sm text-gray-500">{{ $page->followers_count }} followers</p></div>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Discover Pages</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @forelse($discoverPages as $page)
            <div class="flex items-center gap-3 p-3 border rounded-lg">
                <img src="{{ $page->avatar_url }}" class="w-14 h-14 rounded-lg object-cover">
                <div class="flex-1">
                    <a href="{{ route('pages.show', $page) }}" class="font-semibold hover:underline">{{ $page->name }}</a>
                    <p class="text-sm text-gray-500">{{ $page->category }} · {{ $page->followers_count }} followers</p>
                </div>
                <form action="{{ route('pages.follow', $page) }}" method="POST">@csrf<button class="btn-primary text-sm px-3 py-1">Follow</button></form>
            </div>
            @empty
            <p class="text-gray-500">No pages to discover.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
