@if($post->content)
  <p class="mb-3 whitespace-pre-wrap">{{ $post->content }}</p>
@endif

@if($post->media_path)
  @if($post->type === 'video')
    <x-video-player :src="$post->media_url" />
  @else
    <img src="{{ $post->media_url }}" alt="" class="w-full rounded-lg mb-3 object-cover max-h-96">
  @endif
@endif
