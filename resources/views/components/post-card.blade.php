<div class="bg-white rounded-lg shadow p-4 mb-4" id="post-{{ $post->id }}">
  {{-- Post Header --}}
  <div class="flex items-center gap-3 mb-3">
    <a href="{{ route('profile.show', $post->user) }}">
      <img src="{{ $post->user->avatar_url }}" alt="" class="w-10 h-10 rounded-full object-cover">
    </a>
    <div class="flex-1">
      <a href="{{ route('profile.show', $post->user) }}" class="font-semibold hover:underline">{{ $post->user->name }}</a>
      <p class="text-xs text-gray-500">{{ $post->created_at->diffForHumans() }}</p>
    </div>
    @if($post->user_id === auth()->id())
      <form action="{{ route('posts.destroy', $post) }}" method="POST" onsubmit="return confirm('Delete this post?')">
        @csrf @method('DELETE')
        <button type="submit" class="text-gray-400 hover:text-red-500 text-sm">Delete</button>
      </form>
    @endif
  </div>

  {{-- Shared Post --}}
  @if($post->shared_post_id && $post->sharedPost)
    <div class="border border-gray-200 rounded-lg p-3 mb-3">
      @include('components.post-content', ['post' => $post->sharedPost, 'compact' => true])
    </div>
  @else
    @include('components.post-content', ['post' => $post])
  @endif

  {{-- Stats --}}
  <div class="flex items-center justify-between text-sm text-gray-500 py-2 border-b border-gray-100">
    <div class="likes-count-wrapper hover:underline cursor-pointer text-left" data-post-id="{{ $post->id }}">
      @if($post->likes_count > 0)
        <button type="button" onclick="openLikersModal({{ $post->id }})" class="hover:underline cursor-pointer text-left">
          {{ $post->likes_count }} {{ $post->likes_count === 1 ? 'like' : 'likes' }}
        </button>
      @else
        <span>0 likes</span>
      @endif
    </div>
    <span>
      <span class="comments-count-label" data-post-id="{{ $post->id }}">{{ $post->comments_count }}</span> comments · {{ $post->shares_count }} shares
    </span>
  </div>

  {{-- Actions --}}
  <div class="flex items-center justify-around py-1 border-b border-gray-100">
    <form action="{{ route('posts.like', $post) }}" method="POST" class="flex-1 like-form" data-post-id="{{ $post->id }}">
      @csrf
      <button type="submit" class="like-btn w-full flex items-center justify-center gap-2 py-2 rounded-lg hover:bg-gray-100 {{ $post->is_liked ? 'text-fb-blue' : 'text-gray-600' }}">
        <svg class="w-5 h-5" fill="{{ $post->is_liked ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"/></svg>
        Like
      </button>
    </form>
    <button type="button" onclick="document.getElementById('comment-form-{{ $post->id }}').classList.toggle('hidden')"
      class="flex-1 flex items-center justify-center gap-2 py-2 rounded-lg hover:bg-gray-100 text-gray-600">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
      Comment
    </button>
    <button type="button" onclick="openShareModal({{ $post->id }})"
      class="flex-1 flex items-center justify-center gap-2 py-2 rounded-lg hover:bg-gray-100 text-gray-600">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
      Share
    </button>
  </div>

  {{-- Share modal --}}
  <div id="share-modal-{{ $post->id }}" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-black/50" onclick="closeShareModal({{ $post->id }})"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md max-h-[80vh] flex flex-col z-10">
      <div class="flex items-center justify-between p-4 border-b">
        <h3 class="text-lg font-bold">Share post</h3>
        <button type="button" onclick="closeShareModal({{ $post->id }})" class="text-gray-500 hover:text-gray-800 text-2xl leading-none">&times;</button>
      </div>
      <div class="p-4 overflow-y-auto flex-1">
        <p class="text-sm font-semibold text-gray-600 mb-3">Send to friends</p>
        @forelse($friends as $friend)
          <form action="{{ route('posts.send', [$post, $friend]) }}" method="POST"
            class="flex items-center gap-3 p-2 rounded-lg hover:bg-fb-gray mb-1">
            @csrf
            <img src="{{ $friend->avatar_url }}" alt="" class="w-10 h-10 rounded-full object-cover">
            <span class="flex-1 font-medium text-sm truncate">{{ $friend->name }}</span>
            <button type="submit" class="bg-fb-blue text-white text-sm font-semibold px-4 py-1.5 rounded-lg hover:bg-fb-blue-dark">
              Send
            </button>
          </form>
        @empty
          <p class="text-sm text-gray-500 text-center py-4">No friends yet. Add friends to share posts.</p>
        @endforelse
      </div>
      <div class="p-4 border-t bg-fb-gray rounded-b-xl">
        <form action="{{ route('posts.share', $post) }}" method="POST">
          @csrf
          <button type="submit" class="w-full bg-white border border-gray-300 text-gray-800 font-semibold py-2.5 rounded-lg hover:bg-gray-50 transition">
            Share to your timeline
          </button>
        </form>
      </div>
    </div>
  </div>

  {{-- Likers modal --}}
  <div id="likers-modal-{{ $post->id }}" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog">
    <div class="absolute inset-0 bg-black/50" onclick="closeLikersModal({{ $post->id }})"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md max-h-[70vh] flex flex-col z-10">
      <div class="flex items-center justify-between p-4 border-b">
        <h3 class="text-lg font-bold">Liked by</h3>
        <button type="button" onclick="closeLikersModal({{ $post->id }})" class="text-gray-500 hover:text-gray-800 text-2xl leading-none">&times;</button>
      </div>
      <div id="likers-list-{{ $post->id }}" class="p-2 overflow-y-auto flex-1">
        <p class="text-center text-gray-500 py-4 text-sm">Loading...</p>
      </div>
    </div>
  </div>

  {{-- Comments --}}
  <div class="mt-3 space-y-3 comments-container" id="comments-container-{{ $post->id }}">
    @foreach($post->comments as $comment)
      @include('components.comment-item', ['comment' => $comment, 'post' => $post])
    @endforeach
  </div>

  <form action="{{ route('posts.comment', $post) }}" method="POST" class="mt-3 flex gap-2 hidden comment-form" id="comment-form-{{ $post->id }}" data-post-id="{{ $post->id }}">
    @csrf
    <img src="{{ auth()->user()->avatar_url }}" alt="" class="w-8 h-8 rounded-full object-cover">
    <input type="text" name="content" placeholder="Write a comment..." required
      class="flex-1 bg-fb-gray rounded-full px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fb-blue">
    <button type="submit" class="text-fb-blue font-semibold text-sm">Post</button>
  </form>
</div>
