@extends('layouts.app')

@section('title', $user->name)

@section('content')
<div class="max-w-4xl mx-auto">
    {{-- Cover & Profile --}}
    <div class="bg-white shadow">
        {{-- <div class="h-48 md:h-64 bg-gradient-to-r from-fb-blue to-purple-600 relative">
            @if($user->cover_photo_url)
                <img src="{{ $user->cover_photo_url }}" alt="" class="w-full h-full object-cover">
            @endif
        </div> --}}
        <div class="px-4 pb-4">
            <div class="flex flex-col md:flex-row md:items-end gap-4 -mt-16" style="margin-top: 1px;">
                <img src="{{ $user->avatar_url }}" alt="" class="w-32 h-32 rounded-full border-4 border-white object-cover shadow">
                <div class="flex-1">
                    <h1 class="text-2xl font-bold">{{ $user->name }}</h1>
                    <p class="text-gray-500">{{ $friendsCount }} friends</p>
                    @if($user->bio)<p class="mt-2 text-gray-700">{{ $user->bio }}</p>@endif
                </div>
                <div class="flex gap-2">
                    @if(auth()->id() === $user->id)
                        <a href="{{ route('profile.edit') }}" class="btn-secondary">Edit Profile</a>
                    @else
                        @if($isFriend)
                            <form action="{{ route('friends.unfriend', $user) }}" method="POST">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-secondary">Unfriend</button>
                            </form>
                        @elseif($hasPendingRequest)
                            <button disabled class="btn-secondary opacity-50">Request Sent</button>
                        @else
                            <form action="{{ route('friends.send', $user) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn-primary">Add Friend</button>
                            </form>
                        @endif
                        @if($isFollowing)
                            <form action="{{ route('unfollow', $user) }}" method="POST">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-secondary">Unfollow</button>
                            </form>
                        @else
                            <form action="{{ route('follow', $user) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn-secondary">Follow</button>
                            </form>
                        @endif
                        @if($isFriend)
                        <form action="{{ route('chat.start', $user) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn-primary">Message</button>
                        </form>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

  <div class="p-4 space-y-4">
    @forelse($posts as $post)
      @include('components.post-card', ['post' => $post])
    @empty
      <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">No posts yet.</div>
    @endforelse
    {{ $posts->links() }}
  </div>
</div>
@endsection
