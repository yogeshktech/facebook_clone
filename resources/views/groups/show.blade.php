@extends('layouts.app')

@section('title', $group->name)

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white shadow">
        <div class="h-48 bg-gradient-to-r from-green-500 to-teal-600 relative">
            @if($group->cover_photo_url)<img src="{{ $group->cover_photo_url }}" class="w-full h-full object-cover">@endif
        </div>
        <div class="p-4 flex items-center gap-4">
            <img src="{{ $group->avatar_url }}" class="w-20 h-20 rounded-lg object-cover -mt-10 border-4 border-white">
            <div class="flex-1">
                <h1 class="text-2xl font-bold">{{ $group->name }}</h1>
                <p class="text-gray-500">{{ $group->members_count }} members · {{ ucfirst($group->privacy) }}</p>
            </div>
            @if($isMember)
                <form action="{{ route('groups.leave', $group) }}" method="POST">@csrf<button class="btn-secondary">Leave</button></form>
            @else
                <form action="{{ route('groups.join', $group) }}" method="POST">@csrf<button class="btn-primary">Join</button></form>
            @endif
        </div>
    </div>

    @if($isMember)
    <div class="p-4">
        <div class="bg-white rounded-lg shadow p-4 mb-4">
            <form action="/posts" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="group_id" value="{{ $group->id }}">
                <textarea name="content" rows="2" placeholder="Write something to the group..." class="w-full bg-fb-gray rounded-2xl px-4 py-3 resize-none focus:outline-none mb-3"></textarea>
                <div class="flex justify-between">
                    <input type="file" name="media" accept="image/*,video/*">
                    <button type="submit" class="btn-primary">Post</button>
                </div>
            </form>
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
