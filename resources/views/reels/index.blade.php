@extends('layouts.app')

@section('title', 'Reels')

@section('content')
<div class="max-w-lg mx-auto">
    {{-- Upload reel --}}
    <div class="bg-white rounded-lg shadow p-4 mb-4 mx-2 sm:mx-0">
        <h2 class="font-bold text-lg mb-3">Create Reel</h2>
        @if(session('error'))
            <div class="bg-red-50 text-red-600 border border-red-200 p-3 rounded-lg mb-3 text-sm">{{ session('error') }}</div>
        @endif
        <form action="{{ route('reels.store') }}" method="POST" enctype="multipart/form-data" class="space-y-3" id="reel-upload-form">
            @csrf
            <input type="text" name="content" placeholder="Add a caption..." maxlength="500"
                class="w-full bg-fb-gray rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fb-blue">
            <label class="flex items-center justify-center gap-2 border-2 border-dashed border-gray-300 rounded-lg p-4 cursor-pointer hover:border-fb-blue transition">
                <svg class="w-6 h-6 text-fb-blue" fill="currentColor" viewBox="0 0 24 24"><path d="M18 4l2 4h-3l-2-4h-2l2 4h-3l-2-4H8l2 4H7L5 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4h-4z"/></svg>
                <span class="text-sm font-semibold text-gray-600" id="reel-file-label">Choose video (max {{ config('media.max_video_mb') }}MB)</span>
                <input type="file" name="media" accept="video/*" required class="hidden" id="reel-file-input">
            </label>
            <p id="reel-file-size" class="text-xs text-gray-500 hidden"></p>
            <button type="submit" id="reel-submit-btn" class="w-full bg-fb-blue text-white font-semibold py-2.5 rounded-lg hover:bg-fb-blue-dark transition disabled:opacity-50">Post Reel</button>
        </form>
    </div>

<script>
(function () {
    const MAX_BYTES = {{ config('media.max_video_mb') }} * 1024 * 1024;
    const input = document.getElementById('reel-file-input');
    const label = document.getElementById('reel-file-label');
    const sizeEl = document.getElementById('reel-file-size');
    const form = document.getElementById('reel-upload-form');
    const btn = document.getElementById('reel-submit-btn');

    function formatSize(bytes) {
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    input?.addEventListener('change', function () {
        const file = this.files?.[0];
        if (!file) return;
        label.textContent = file.name;
        sizeEl.textContent = 'Size: ' + formatSize(file.size);
        sizeEl.classList.remove('hidden');
        if (file.size > MAX_BYTES) {
            sizeEl.className = 'text-xs text-red-600 font-medium';
            sizeEl.textContent = 'Too large (' + formatSize(file.size) + '). Maximum is {{ config('media.max_video_mb') }}MB.';
            btn.disabled = true;
        } else {
            sizeEl.className = 'text-xs text-gray-500';
            btn.disabled = false;
        }
    });

    form?.addEventListener('submit', function (e) {
        const file = input?.files?.[0];
        if (!file) return;
        if (file.size > MAX_BYTES) {
            e.preventDefault();
            alert('Video must be under {{ config('media.max_video_mb') }}MB.');
            return;
        }
        if (form.dataset.submitting === '1') {
            e.preventDefault();
            return;
        }
        form.dataset.submitting = '1';
        btn.disabled = true;
        btn.textContent = 'Uploading...';
    });
})();
</script>

    {{-- Reels feed --}}
    <div class="space-y-4 px-2 sm:px-0 pb-4" id="reels-feed-container">
        @forelse($reels as $reel)
        <div class="bg-black rounded-xl overflow-hidden relative" style="height: 70vh; max-height: 600px;" id="reel-{{ $reel->id }}" data-reel-id="{{ $reel->id }}">
            <video src="{{ $reel->media_url }}" class="w-full h-full object-contain reel-video" loop playsinline muted preload="metadata"></video>

            {{-- Sound toggle --}}
            <button type="button"
                class="reel-sound-btn absolute top-4 right-4 z-20 w-10 h-10 rounded-full bg-black/50 text-white flex items-center justify-center hover:bg-black/70 transition"
                aria-label="Toggle sound">
                <svg class="w-5 h-5 icon-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/>
                </svg>
                <svg class="w-5 h-5 icon-unmuted hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072M12 6v12m-6.536-9.536a9 9 0 0113.072 0M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
                </svg>
            </button>

            {{-- Tap hint when muted --}}
            <div class="reel-tap-sound absolute top-16 left-1/2 -translate-x-1/2 z-20 bg-black/60 text-white text-xs px-3 py-1.5 rounded-full pointer-events-none">
                Tap speaker for sound
            </div>

            {{-- Overlay info --}}
            <div class="absolute bottom-0 left-0 right-16 p-4 bg-gradient-to-t from-black/80 to-transparent">
                <a href="{{ route('profile.show', $reel->user) }}" class="flex items-center gap-2 mb-2">
                    <img src="{{ $reel->user->avatar_url }}" alt="" class="w-10 h-10 rounded-full border-2 border-white object-cover">
                    <span class="text-white font-semibold text-sm">{{ $reel->user->name }}</span>
                </a>
                @if($reel->content)
                    <p class="text-white text-sm line-clamp-2">{{ $reel->content }}</p>
                @endif
            </div>

            {{-- Side actions --}}
            <div class="absolute right-3 bottom-20 flex flex-col items-center gap-5">
                <div class="flex flex-col items-center text-white">
                    <div class="w-11 h-11 rounded-full bg-white/20 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </div>
                    <span class="text-xs mt-1" id="reel-views-{{ $reel->id }}">{{ $reel->views_count ?? 0 }}</span>
                </div>

                <form action="{{ route('reels.like', $reel) }}" method="POST">
                    @csrf
                    <button type="submit" class="flex flex-col items-center text-white">
                        <div class="w-11 h-11 rounded-full bg-white/20 flex items-center justify-center {{ $reel->is_liked ? 'text-red-500' : '' }}">
                            <svg class="w-6 h-6" fill="{{ $reel->is_liked ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                        </div>
                        <span class="text-xs mt-1">{{ $reel->likes_count }}</span>
                    </button>
                </form>

                <button type="button" onclick="document.getElementById('reel-comment-{{ $reel->id }}').classList.toggle('hidden')"
                    class="flex flex-col items-center text-white">
                    <div class="w-11 h-11 rounded-full bg-white/20 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    </div>
                    <span class="text-xs mt-1">{{ $reel->comments_count }}</span>
                </button>

                <button type="button" onclick="openReelShareModal({{ $reel->id }})"
                    class="flex flex-col items-center text-white">
                    <div class="w-11 h-11 rounded-full bg-white/20 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                    </div>
                    <span class="text-xs mt-1">{{ $reel->shares_count }}</span>
                </button>
            </div>

            {{-- Comment panel --}}
            <div id="reel-comment-{{ $reel->id }}" class="hidden absolute bottom-0 left-0 right-0 bg-white rounded-t-xl p-3 z-10">
                <form action="{{ route('reels.comment', $reel) }}" method="POST" class="flex gap-2 comment-form">
                    @csrf
                    <input type="text" name="content" placeholder="Add a comment..." required
                        class="flex-1 bg-fb-gray rounded-full px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fb-blue">
                    <button type="submit" class="text-fb-blue font-semibold text-sm">Post</button>
                </form>
            </div>

            {{-- Share to friends modal --}}
            <div id="reel-share-modal-{{ $reel->id }}" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true">
                <div class="absolute inset-0 bg-black/60" onclick="closeReelShareModal({{ $reel->id }})"></div>
                <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md max-h-[80vh] flex flex-col z-10">
                    <div class="flex items-center justify-between p-4 border-b">
                        <h3 class="text-lg font-bold">Share reel</h3>
                        <button type="button" onclick="closeReelShareModal({{ $reel->id }})" class="text-gray-500 hover:text-gray-800 text-2xl leading-none">&times;</button>
                    </div>
                    <div class="p-4 overflow-y-auto flex-1">
                        <p class="text-sm font-semibold text-gray-600 mb-3">Send to friends</p>
                        @forelse($friends as $friend)
                            <form action="{{ route('reels.send', [$reel, $friend]) }}" method="POST"
                                class="flex items-center gap-3 py-2.5 px-2 rounded-lg hover:bg-fb-gray border-b border-gray-50 last:border-0">
                                @csrf
                                <img src="{{ $friend->avatar_url }}" alt="" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                                <span class="flex-1 font-medium text-sm truncate">{{ $friend->name }}</span>
                                <button type="submit" class="bg-fb-blue text-white text-sm font-semibold px-4 py-1.5 rounded-lg hover:bg-fb-blue-dark flex-shrink-0">
                                    Send
                                </button>
                            </form>
                        @empty
                            <p class="text-sm text-gray-500 text-center py-6">No friends yet. Add friends to share reels.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
            <p class="text-lg font-semibold mb-2">No reels yet</p>
            <p class="text-sm">Upload a video above or follow friends to see their reels!</p>
        </div>
        @endforelse
    </div>

    <div id="infinite-scroll-trigger" class="py-6 text-center hidden">
        <span class="inline-block w-8 h-8 border-3 border-fb-blue border-t-transparent rounded-full animate-spin"></span>
    </div>

    <div id="pagination-wrapper" class="hidden">
        @if($reels->hasPages())
            <div class="px-4 pb-8">{{ $reels->links() }}</div>
        @endif
    </div>
</div>

<script>
window.openReelShareModal = function (reelId) {
    document.getElementById('reel-share-modal-' + reelId)?.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};
window.closeReelShareModal = function (reelId) {
    document.getElementById('reel-share-modal-' + reelId)?.classList.add('hidden');
    document.body.style.overflow = '';
};

document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const recordedViews = new Set();
    let reelsSoundOn = sessionStorage.getItem('reelsSoundOn') === '1';
    let activeReelEl = null;

    function getVideo(container) {
        return container?.querySelector('.reel-video');
    }

    function updateSoundUi(container) {
        const btn = container?.querySelector('.reel-sound-btn');
        const hint = container?.querySelector('.reel-tap-sound');
        const video = getVideo(container);
        if (!btn || !video) return;

        const isActive = container === activeReelEl;
        const muted = !reelsSoundOn || !isActive;
        video.muted = muted;
        video.volume = reelsSoundOn ? 1 : 0;

        btn.querySelector('.icon-muted')?.classList.toggle('hidden', !muted);
        btn.querySelector('.icon-unmuted')?.classList.toggle('hidden', muted);
        hint?.classList.toggle('hidden', reelsSoundOn);
    }

    function pauseAllExcept(activeContainer) {
        document.querySelectorAll('[data-reel-id]').forEach(container => {
            const video = getVideo(container);
            if (!video) return;
            if (container !== activeContainer) {
                video.pause();
            }
            updateSoundUi(container);
        });
    }

    function playReel(container) {
        const video = getVideo(container);
        if (!video) return;
        activeReelEl = container;
        pauseAllExcept(container);
        updateSoundUi(container);
        video.play().catch(() => {});
    }

    async function recordReelView(reelId) {
        if (recordedViews.has(reelId)) return;
        recordedViews.add(reelId);
        try {
            const res = await fetch('/reels/' + reelId + '/view', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
            });
            if (!res.ok) return;
            const data = await res.json();
            const el = document.getElementById('reel-views-' + reelId);
            if (el && data.views_count !== undefined) {
                el.textContent = data.views_count;
            }
        } catch (e) {}
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            const container = entry.target;
            const reelId = container.dataset.reelId;
            if (entry.isIntersecting) {
                playReel(container);
                if (reelId) recordReelView(reelId);
            } else if (activeReelEl === container) {
                getVideo(container)?.pause();
                activeReelEl = null;
            }
        });
    }, { threshold: 0.65 });

    function initReelListeners(el) {
        updateSoundUi(el);
        observer.observe(el);

        el.querySelector('.reel-sound-btn')?.addEventListener('click', (e) => {
            e.stopPropagation();
            reelsSoundOn = !reelsSoundOn;
            sessionStorage.setItem('reelsSoundOn', reelsSoundOn ? '1' : '0');
            document.querySelectorAll('[data-reel-id]').forEach(updateSoundUi);
            if (reelsSoundOn && activeReelEl) {
                const video = getVideo(activeReelEl);
                if (video) {
                    video.muted = false;
                    video.volume = 1;
                    video.play().catch(() => {});
                }
            }
        });

        getVideo(el)?.addEventListener('click', () => {
            const video = getVideo(el);
            if (!video) return;
            if (video.paused) {
                playReel(el);
            } else {
                video.pause();
            }
        });
    }

    document.querySelectorAll('[data-reel-id]').forEach(initReelListeners);

    // Infinite scroll
    let nextPageUrl = '{{ $reels->nextPageUrl() }}';
    let isLoading = false;
    const feedContainer = document.getElementById('reels-feed-container');
    const trigger = document.getElementById('infinite-scroll-trigger');

    if (nextPageUrl) {
        trigger.classList.remove('hidden');

        const scrollObserver = new IntersectionObserver(async (entries) => {
            if (entries[0].isIntersecting && !isLoading && nextPageUrl) {
                isLoading = true;
                trigger.classList.remove('hidden');

                try {
                    const res = await fetch(nextPageUrl, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    if (!res.ok) throw new Error('Failed to fetch');

                    const html = await res.text();
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');

                    const newReels = doc.querySelectorAll('#reels-feed-container [data-reel-id]');
                    newReels.forEach(reel => {
                        feedContainer.appendChild(reel);
                        initReelListeners(reel);
                    });

                    const nextLink = doc.querySelector('[rel="next"]');
                    nextPageUrl = nextLink ? nextLink.getAttribute('href') : null;

                    if (!nextPageUrl) {
                        trigger.classList.add('hidden');
                        scrollObserver.unobserve(trigger);
                    }
                } catch (e) {
                    console.error('Error loading more reels:', e);
                } finally {
                    isLoading = false;
                }
            }
        }, { rootMargin: '200px' });

        scrollObserver.observe(trigger);
    }
});
</script>
@endsection
