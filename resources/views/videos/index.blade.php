@extends('layouts.app')

@section('title', 'Reels')

@section('content')
<div class="max-w-lg mx-auto">
    {{-- Upload reel --}}
    <div class="bg-white rounded-lg shadow p-4 mb-4 mx-2 sm:mx-0">
        <h2 class="font-bold text-lg mb-3">Create Videos</h2>
        @if(session('error'))
            <div class="bg-red-50 text-red-600 border border-red-200 p-3 rounded-lg mb-3 text-sm">{{ session('error') }}</div>
        @endif
        <form action="{{ route('videos.store') }}" method="POST" enctype="multipart/form-data" class="space-y-3" id="video-upload-form">
            @csrf
            <input type="text" name="content" placeholder="Add a caption..." maxlength="500"
                class="w-full bg-fb-gray rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fb-blue">
            <label class="flex items-center justify-center gap-2 border-2 border-dashed border-gray-300 rounded-lg p-4 cursor-pointer hover:border-fb-blue transition">
                <svg class="w-6 h-6 text-fb-blue" fill="currentColor" viewBox="0 0 24 24"><path d="M18 4l2 4h-3l-2-4h-2l2 4h-3l-2-4H8l2 4H7L5 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4h-4z"/></svg>
                <span class="text-sm font-semibold text-gray-600" id="video-file-label">Choose video (max {{ config('media.max_video_mb') }}MB)</span>
                <input type="file" name="media" accept="video/*" required class="hidden" id="video-file-input">
            </label>
            <p id="video-file-size" class="text-xs text-gray-500 hidden"></p>
            <button type="submit" id="video-submit-btn" class="w-full bg-fb-blue text-white font-semibold py-2.5 rounded-lg hover:bg-fb-blue-dark transition disabled:opacity-50">Post Video</button>
        </form>
    </div>

<script>
(function () {
    const MAX_BYTES = {{ config('media.max_video_mb') }} * 1024 * 1024;
    const input = document.getElementById('video-file-input');
    const label = document.getElementById('video-file-label');
    const sizeEl = document.getElementById('video-file-size');
    const form = document.getElementById('video-upload-form');
    const btn = document.getElementById('video-submit-btn');

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
    <div class="space-y-4 px-2 sm:px-0 pb-4">
        @forelse($videos as $video)
        <div class="bg-black rounded-xl overflow-hidden relative" style="height: 70vh; max-height: 600px;" id="video-{{ $video->id }}" data-video-id="{{ $video->id }}">
            <video src="{{ $video->media_url }}" class="w-full h-full object-contain video-video" playsinline muted preload="metadata"></video>

            {{-- Progress bar --}}
            <div class="video-progress-wrap absolute left-0 right-0 bottom-0 z-20 px-2 pb-1">
                <div class="video-progress-bar relative w-full h-1.5 bg-white/30 rounded-full cursor-pointer group">
                    <div class="video-progress-fill absolute left-0 top-0 h-full bg-fb-blue rounded-full" style="width:0%"></div>
                    <div class="video-progress-handle absolute top-1/2 -translate-y-1/2 -translate-x-1/2 w-3 h-3 bg-white rounded-full shadow opacity-0 group-hover:opacity-100 transition" style="left:0%"></div>
                </div>
                <div class="flex justify-between mt-1 px-0.5">
                    <span class="video-time-current text-[10px] text-white/90 font-medium">0:00</span>
                    <span class="video-time-duration text-[10px] text-white/90 font-medium">0:00</span>
                </div>
            </div>

            {{-- Sound toggle --}}
            <button type="button"
                class="video-sound-btn absolute top-4 right-4 z-20 w-10 h-10 rounded-full bg-black/50 text-white flex items-center justify-center hover:bg-black/70 transition"
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
            <div class="video-tap-sound absolute top-16 left-1/2 -translate-x-1/2 z-20 bg-black/60 text-white text-xs px-3 py-1.5 rounded-full pointer-events-none">
                Tap speaker for sound
            </div>

            {{-- Overlay info --}}
            <div class="absolute bottom-7 left-0 right-16 p-4 bg-gradient-to-t from-black/80 to-transparent">
                <a href="{{ route('profile.show', $video->user) }}" class="flex items-center gap-2 mb-2">
                    <img src="{{ $video->user->avatar_url }}" alt="" class="w-10 h-10 rounded-full border-2 border-white object-cover">
                    <span class="text-white font-semibold text-sm">{{ $video->user->name }}</span>
                </a>
                @if($video->content)
                    <p class="text-white text-sm line-clamp-2">{{ $video->content }}</p>
                @endif
            </div>

            {{-- Side actions --}}
            <div class="absolute right-3 bottom-20 flex flex-col items-center gap-5">
                <div class="flex flex-col items-center text-white">
                    <div class="w-11 h-11 rounded-full bg-white/20 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </div>
                    <span class="text-xs mt-1" id="video-views-{{ $video->id }}">{{ $video->views_count ?? 0 }}</span>
                </div>

                <form action="{{ route('videos.like', $video) }}" method="POST">
                    @csrf
                    <button type="submit" class="flex flex-col items-center text-white">
                        <div class="w-11 h-11 rounded-full bg-white/20 flex items-center justify-center {{ $video->is_liked ? 'text-red-500' : '' }}">
                            <svg class="w-6 h-6" fill="{{ $video->is_liked ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                        </div>
                        <span class="text-xs mt-1">{{ $video->likes_count }}</span>
                    </button>
                </form>

                <button type="button" onclick="document.getElementById('video-comment-{{ $video->id }}').classList.toggle('hidden')"
                    class="flex flex-col items-center text-white">
                    <div class="w-11 h-11 rounded-full bg-white/20 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    </div>
                    <span class="text-xs mt-1">{{ $video->comments_count }}</span>
                </button>

                <button type="button" onclick="openVideoShareModal({{ $video->id }})"
                    class="flex flex-col items-center text-white">
                    <div class="w-11 h-11 rounded-full bg-white/20 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                    </div>
                    <span class="text-xs mt-1">{{ $video->shares_count }}</span>
                </button>
            </div>

            {{-- Comment panel --}}
            <div id="video-comment-{{ $video->id }}" class="hidden absolute bottom-0 left-0 right-0 bg-white rounded-t-xl p-3 z-10">
                <form action="{{ route('videos.comment', $video) }}" method="POST" class="flex gap-2 comment-form">
                    @csrf
                    <input type="text" name="content" placeholder="Add a comment..." required
                        class="flex-1 bg-fb-gray rounded-full px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fb-blue">
                    <button type="submit" class="text-fb-blue font-semibold text-sm">Post</button>
                </form>
            </div>

            {{-- Share to friends modal --}}
            <div id="video-share-modal-{{ $video->id }}" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true">
                <div class="absolute inset-0 bg-black/60" onclick="closeVideoShareModal({{ $video->id }})"></div>
                <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md max-h-[80vh] flex flex-col z-10">
                    <div class="flex items-center justify-between p-4 border-b">
                        <h3 class="text-lg font-bold">Share video</h3>
                        <button type="button" onclick="closeVideoShareModal({{ $video->id }})" class="text-gray-500 hover:text-gray-800 text-2xl leading-none">&times;</button>
                    </div>
                    <div class="p-4 overflow-y-auto flex-1">
                        <p class="text-sm font-semibold text-gray-600 mb-3">Send to friends</p>
                        @forelse($friends as $friend)
                            <form action="{{ route('videos.send', [$video, $friend]) }}" method="POST"
                                class="flex items-center gap-3 py-2.5 px-2 rounded-lg hover:bg-fb-gray border-b border-gray-50 last:border-0">
                                @csrf
                                <img src="{{ $friend->avatar_url }}" alt="" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                                <span class="flex-1 font-medium text-sm truncate">{{ $friend->name }}</span>
                                <button type="submit" class="bg-fb-blue text-white text-sm font-semibold px-4 py-1.5 rounded-lg hover:bg-fb-blue-dark flex-shrink-0">
                                    Send
                                </button>
                            </form>
                        @empty
                            <p class="text-sm text-gray-500 text-center py-6">No friends yet. Add friends to share videos.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
            <p class="text-lg font-semibold mb-2">No videos yet</p>
            <p class="text-sm">Upload a video above or follow friends to see their videos!</p>
        </div>
        @endforelse
    </div>

    @if($videos->hasPages())
        <div class="px-4 pb-8">{{ $videos->links() }}</div>
    @endif
</div>

<script>
window.openVideoShareModal = function (videoId) {
    document.getElementById('video-share-modal-' + videoId)?.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};
window.closeVideoShareModal = function (videoId) {
    document.getElementById('video-share-modal-' + videoId)?.classList.add('hidden');
    document.body.style.overflow = '';
};

document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const recordedViews = new Set();
    let videoSoundOn = sessionStorage.getItem('videoSoundOn') === '1';
    let activeVideoEl = null;

    function getVideoTag(container) {
        return container?.querySelector('.video-video');
    }

    function updateSoundUi(container) {
        const btn = container?.querySelector('.video-sound-btn');
        const hint = container?.querySelector('.video-tap-sound');
        const video = getVideoTag(container);
        if (!btn || !video) return;

        const isActive = container === activeVideoEl;
        const muted = !videoSoundOn || !isActive;
        video.muted = muted;
        video.volume = videoSoundOn ? 1 : 0;

        btn.querySelector('.icon-muted')?.classList.toggle('hidden', !muted);
        btn.querySelector('.icon-unmuted')?.classList.toggle('hidden', muted);
        hint?.classList.toggle('hidden', videoSoundOn);
    }

    function pauseAllExcept(activeContainer) {
        document.querySelectorAll('[data-video-id]').forEach(container => {
            const video = getVideoTag(container);
            if (!video) return;
            if (container !== activeContainer) {
                video.pause();
            }
            updateSoundUi(container);
        });
    }

    function playVideo(container) {
        const video = getVideoTag(container);
        if (!video) return;
        activeVideoEl = container;
        pauseAllExcept(container);
        updateSoundUi(container);
        video.play().catch(() => {});
    }

    document.querySelectorAll('.video-sound-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            videoSoundOn = !videoSoundOn;
            sessionStorage.setItem('videoSoundOn', videoSoundOn ? '1' : '0');
            document.querySelectorAll('[data-video-id]').forEach(updateSoundUi);
            if (videoSoundOn && activeVideoEl) {
                const video = getVideoTag(activeVideoEl);
                if (video) {
                    video.muted = false;
                    video.volume = 1;
                    video.play().catch(() => {});
                }
            }
        });
    });

    document.querySelectorAll('.video-video').forEach(video => {
        video.addEventListener('click', () => {
            const container = video.closest('[data-video-id]');
            if (!container) return;
            if (video.paused) {
                playVideo(container);
            } else {
                video.pause();
            }
        });
    });

    function formatTime(sec) {
        if (!isFinite(sec) || sec < 0) sec = 0;
        const m = Math.floor(sec / 60);
        const s = Math.floor(sec % 60);
        return m + ':' + String(s).padStart(2, '0');
    }

    function setupProgressBar(container) {
        const video = getVideoTag(container);
        const bar = container.querySelector('.video-progress-bar');
        const fill = container.querySelector('.video-progress-fill');
        const handle = container.querySelector('.video-progress-handle');
        const curEl = container.querySelector('.video-time-current');
        const durEl = container.querySelector('.video-time-duration');
        if (!video || !bar || !fill || !handle) return;

        let isDragging = false;

        function updateBar() {
            if (!video.duration || isNaN(video.duration)) return;
            const pct = (video.currentTime / video.duration) * 100;
            fill.style.width = pct + '%';
            handle.style.left = pct + '%';
            if (curEl) curEl.textContent = formatTime(video.currentTime);
        }

        function seekFromEvent(clientX) {
            const rect = bar.getBoundingClientRect();
            let pct = (clientX - rect.left) / rect.width;
            pct = Math.min(1, Math.max(0, pct));
            if (video.duration && !isNaN(video.duration)) {
                video.currentTime = pct * video.duration;
                fill.style.width = (pct * 100) + '%';
                handle.style.left = (pct * 100) + '%';
                if (curEl) curEl.textContent = formatTime(video.currentTime);
            }
        }

        video.addEventListener('loadedmetadata', () => {
            if (durEl) durEl.textContent = formatTime(video.duration);
        });
        video.addEventListener('timeupdate', updateBar);
        video.addEventListener('durationchange', () => {
            if (durEl) durEl.textContent = formatTime(video.duration);
        });

        bar.addEventListener('click', (e) => {
            e.stopPropagation();
            seekFromEvent(e.clientX);
        });

        bar.addEventListener('mousedown', (e) => {
            e.stopPropagation();
            isDragging = true;
            seekFromEvent(e.clientX);
        });
        document.addEventListener('mousemove', (e) => {
            if (isDragging) seekFromEvent(e.clientX);
        });
        document.addEventListener('mouseup', () => { isDragging = false; });

        bar.addEventListener('touchstart', (e) => {
            e.stopPropagation();
            isDragging = true;
            seekFromEvent(e.touches[0].clientX);
        }, { passive: true });
        bar.addEventListener('touchmove', (e) => {
            if (isDragging) seekFromEvent(e.touches[0].clientX);
        }, { passive: true });
        bar.addEventListener('touchend', () => { isDragging = false; });
    }

    document.querySelectorAll('[data-video-id]').forEach(setupProgressBar);

    async function recordVideoView(videoId) {
        if (recordedViews.has(videoId)) return;
        recordedViews.add(videoId);
        try {
            const res = await fetch('/videos/' + videoId + '/view', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
            });
            if (!res.ok) return;
            const data = await res.json();
            const el = document.getElementById('video-views-' + videoId);
            if (el && data.views_count !== undefined) {
                el.textContent = data.views_count;
            }
        } catch (e) {}
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            const container = entry.target;
            const videoId = container.dataset.videoId;
            if (entry.isIntersecting) {
                playVideo(container);
                if (videoId) recordVideoView(videoId);
            } else if (activeVideoEl === container) {
                getVideoTag(container)?.pause();
                activeVideoEl = null;
            }
        });
    }, { threshold: 0.65 });

    document.querySelectorAll('[data-video-id]').forEach(el => {
        updateSoundUi(el);
        observer.observe(el);
    });
});
</script>
@endsection