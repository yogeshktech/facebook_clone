@extends('layouts.app')

@section('title', 'Story')

@section('content')
<div class="min-h-screen bg-black flex items-center justify-center">
    <div class="relative max-w-md w-full h-[80vh]">
        {{-- Progress bars (one per story in this user's set) --}}
        <div class="absolute top-0 left-0 right-0 z-20 px-2 pt-2 flex gap-1">
            @foreach($userStories as $index => $userStory)
                <div class="flex-1 h-1 bg-white/30 rounded-full overflow-hidden">
                    @if($index < $userStoryIndex)
                        <div class="h-full w-full bg-white rounded-full"></div>
                    @elseif($index === $userStoryIndex)
                        <div id="story-progress" class="h-full bg-white rounded-full w-0"></div>
                    @else
                        <div class="h-full w-0 bg-white rounded-full"></div>
                    @endif
                </div>
            @endforeach
        </div>

        <a href="{{ route('feed.index') }}" class="absolute top-6 left-4 z-10 text-white bg-black/50 rounded-full w-10 h-10 flex items-center justify-center">&times;</a>
        <div class="absolute top-6 left-16 right-4 z-10 flex items-center gap-2">
            <img src="{{ $story->user->avatar_url }}" class="w-10 h-10 rounded-full border-2 border-fb-blue object-cover" alt="">
            <div>
                <span class="text-white font-semibold block">{{ $story->user->name }}</span>
                <span class="text-white/70 text-xs">{{ $story->created_at->diffForHumans() }}</span>
            </div>
        </div>

        @if($story->media_type === 'video')
            <video id="story-media" src="{{ $story->media_url }}" class="w-full h-full object-contain" autoplay muted playsinline></video>
        @else
            <img id="story-media" src="{{ $story->media_url }}" class="w-full h-full object-contain" alt="">
        @endif

        @if($story->caption)
            <p class="absolute bottom-20 left-4 right-4 text-white text-center">{{ $story->caption }}</p>
        @endif

        @if($isOwner)
        <div class="absolute top-6 right-4 z-10 flex items-center gap-2">
            <form action="{{ route('stories.destroy', $story) }}" method="POST" onsubmit="return confirm('Delete this story?')">
                @csrf @method('DELETE')
                <button type="submit" class="text-white bg-red-600/80 hover:bg-red-600 rounded-full w-10 h-10 flex items-center justify-center" title="Delete story">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                </button>
            </form>
        </div>
        <div class="absolute bottom-4 left-4 right-4 z-10">
            <button type="button" onclick="document.getElementById('viewers-panel').classList.toggle('hidden')"
                class="w-full bg-white/20 backdrop-blur text-white rounded-full py-2 px-4 text-sm font-semibold hover:bg-white/30 transition flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                {{ $viewCount }} {{ $viewCount === 1 ? 'view' : 'views' }}
            </button>

            <div id="viewers-panel" class="hidden mt-3 bg-white rounded-xl max-h-48 overflow-y-auto shadow-xl">
                <div class="p-3 border-b font-semibold text-gray-800 text-sm">Viewers</div>
                @forelse($viewers as $viewer)
                <a href="{{ route('profile.show', $viewer) }}" class="flex items-center gap-3 p-3 hover:bg-fb-gray border-b last:border-0">
                    <img src="{{ $viewer->avatar_url }}" class="w-10 h-10 rounded-full object-cover" alt="">
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm truncate">{{ $viewer->name }}</p>
                        <p class="text-xs text-gray-500">
                            @if($viewer->pivot->viewed_at)
                                {{ $viewer->pivot->viewed_at->diffForHumans() }}
                            @endif
                        </p>
                    </div>
                </a>
                @empty
                <p class="p-4 text-center text-gray-500 text-sm">No views yet</p>
                @endforelse
            </div>
        </div>
        @endif

        {{-- Tap zones: left = previous, right = next --}}
        <button type="button" onclick="window.location.href='{{ $prevUrl }}'"
            class="absolute left-0 top-16 bottom-24 w-1/3 z-10 opacity-0" aria-label="Previous story"></button>
        <button type="button" onclick="goNext()"
            class="absolute right-0 top-16 bottom-24 w-1/3 z-10 opacity-0" aria-label="Next story"></button>
    </div>
</div>

<script>
(function () {
    const DURATION = 30000;
    const nextUrl = @json($nextUrl);
    const progress = document.getElementById('story-progress');
    const video = document.querySelector('video#story-media');
    let start = Date.now();

    function goNext() {
        window.location.href = nextUrl;
    }
    window.goNext = goNext;

    function tick() {
        const elapsed = Date.now() - start;
        const pct = Math.min((elapsed / DURATION) * 100, 100);
        progress.style.width = pct + '%';
        if (elapsed >= DURATION) {
            goNext();
            return;
        }
        requestAnimationFrame(tick);
    }

    if (video) {
        video.muted = false;
        video.play().catch(() => video.play());
        video.addEventListener('ended', goNext);
        const onMeta = () => {
            const ms = video.duration && !isNaN(video.duration)
                ? Math.min(video.duration * 1000, DURATION)
                : DURATION;
            setTimeout(goNext, ms);
            const step = () => {
                const elapsed = Date.now() - start;
                progress.style.width = Math.min((elapsed / ms) * 100, 100) + '%';
                if (elapsed < ms) requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
        };
        if (video.readyState >= 1) {
            onMeta();
        } else {
            video.addEventListener('loadedmetadata', onMeta, { once: true });
        }
    } else {
        requestAnimationFrame(tick);
    }
})();
</script>
@endsection
