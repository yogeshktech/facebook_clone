@extends('layouts.app')

@section('title', $page->name)

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white shadow">
        <div class="h-48 bg-gradient-to-r from-red-500 to-pink-600 relative">
            @if($page->cover_photo_url)<img src="{{ $page->cover_photo_url }}" class="w-full h-full object-cover">@endif
        </div>
        <div class="p-4 flex items-center gap-4">
            <img src="{{ $page->avatar_url }}" class="w-20 h-20 rounded-lg object-cover -mt-10 border-4 border-white">
            <div class="flex-1">
                <h1 class="text-2xl font-bold">{{ $page->name }}</h1>
                <p class="text-gray-500">{{ $page->followers_count }} followers @if($page->category)· {{ $page->category }}@endif</p>
            </div>
            @if(!$isOwner)
                @if($isFollowing)
                    <form action="{{ route('pages.unfollow', $page) }}" method="POST">@csrf @method('DELETE')<button class="btn-secondary">Unfollow</button></form>
                @else
                    <form action="{{ route('pages.follow', $page) }}" method="POST">@csrf<button class="btn-primary">Follow</button></form>
                @endif
            @endif
        </div>
    </div>

    @if($isOwner)
    <div class="p-4">
        <div class="bg-white rounded-lg shadow p-4 mb-4">
            <form action="/posts" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="page_id" value="{{ $page->id }}">
                <textarea name="content" rows="2" placeholder="Post as {{ $page->name }}..." class="w-full bg-fb-gray rounded-2xl px-4 py-3 resize-none focus:outline-none mb-3"></textarea>
                <button type="submit" class="btn-primary">Post</button>
            </form>
        </div>
    </div>
    @endif

    <div class="p-4 space-y-4">
        @foreach($posts as $post)
            @include('components.post-card', ['post' => $post])
        @endforeach
        {{ $posts->links() }}
    </div>
</div>
@endsection
