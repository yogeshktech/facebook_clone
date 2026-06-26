<div class="space-y-2">
  <div class="flex gap-2 {{ $comment->parent_id ? 'ml-10' : '' }}">
    <img src="{{ $comment->user->avatar_url }}" alt="" class="w-8 h-8 rounded-full object-cover flex-shrink-0">
    <div class="flex-1 min-w-0">
      <div class="bg-fb-gray rounded-2xl px-3 py-2 inline-block max-w-full">
        <a href="{{ route('profile.show', $comment->user) }}" class="font-semibold text-sm hover:underline">{{ $comment->user->name }}</a>
        <p class="text-sm whitespace-pre-wrap break-words">{{ $comment->content }}</p>
      </div>
      <div class="flex items-center gap-3 mt-1 ml-3">
        <span class="text-xs text-gray-500">{{ $comment->created_at->diffForHumans() }}</span>
        <button type="button"
          onclick="document.getElementById('reply-form-{{ $comment->id }}').classList.toggle('hidden')"
          class="text-xs font-semibold text-gray-600 hover:text-fb-blue">
          Reply
        </button>
      </div>

      <form action="{{ route('posts.comment', $post) }}" method="POST"
        class="mt-2 flex gap-2 hidden" id="reply-form-{{ $comment->id }}">
        @csrf
        <input type="hidden" name="parent_id" value="{{ $comment->id }}">
        <img src="{{ auth()->user()->avatar_url }}" alt="" class="w-7 h-7 rounded-full object-cover flex-shrink-0">
        <input type="text" name="content" placeholder="Reply to {{ $comment->user->name }}..." required
          class="flex-1 bg-fb-gray rounded-full px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fb-blue">
        <button type="submit" class="text-fb-blue font-semibold text-sm whitespace-nowrap">Reply</button>
      </form>
    </div>
  </div>

  @if($comment->relationLoaded('replies') && $comment->replies->count())
    @foreach($comment->replies as $reply)
      @include('components.comment-item', ['comment' => $reply, 'post' => $post])
    @endforeach
  @endif
</div>
