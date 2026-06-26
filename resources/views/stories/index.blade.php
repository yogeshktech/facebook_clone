@extends('layouts.app')

@section('title', 'Stories')

@section('content')
<div class="max-w-4xl mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4">Stories</h1>
    <div class="flex gap-3 overflow-x-auto pb-4">
        <form action="{{ route('stories.store') }}" method="POST" enctype="multipart/form-data" class="flex-shrink-0">
            @csrf
            <label class="cursor-pointer block w-32">
                <div class="w-32 h-48 rounded-xl bg-fb-gray border-2 border-dashed border-gray-300 flex flex-col items-center justify-center">
                    <span class="text-fb-blue font-semibold text-sm">+ Add Story</span>
                </div>
                <input type="file" name="media" accept="image/*,video/*" class="hidden" onchange="compressAndSubmitForm(this)">
            </label>
        </form>
        @foreach($stories as $userId => $userStories)
            @php
                $storyUser = $userStories->first()->user;
                $firstStory = $userStories->first();
                $previewStory = $userStories->last();
                $storyCount = $userStories->count();
            @endphp
            <a href="{{ route('stories.show', $firstStory) }}" class="flex-shrink-0 w-32 h-48 rounded-xl overflow-hidden relative">
                <img src="{{ $previewStory->media_url }}" class="w-full h-full object-cover">
                <img src="{{ $storyUser->avatar_url }}" class="absolute top-2 left-2 w-8 h-8 rounded-full border-2 border-fb-blue">
                <span class="absolute bottom-2 left-2 text-white text-xs font-semibold">{{ $storyUser->name }}</span>
                @if($storyCount > 1)
                    <span class="absolute top-2 right-2 bg-black/60 text-white text-xs px-2 py-0.5 rounded-full">{{ $storyCount }}</span>
                @endif
            </a>
        @endforeach
    </div>
</div>
@endsection
