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
                <span class="text-sm font-semibold text-gray-600" id="reel-file-label">Choose video (max 50MB)</span>
                <input type="file" name="media" accept="video/*" required class="hidden" id="reel-file-input">
            </label>
            <p id="reel-file-size" class="text-xs text-gray-500 hidden"></p>
            <button type="submit" id="reel-submit-btn" class="w-full bg-fb-blue text-white font-semibold py-2.5 rounded-lg hover:bg-fb-blue-dark transition disabled:opacity-50">Post Reel</button>
        </form>
    </div>

<script>
(function () {
    const MAX_BYTES = 50 * 1024 * 1024;
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
            sizeEl.textContent = 'Too large (' + formatSize(file.size) + '). Maximum is 50MB.';
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
            alert('Video must be under 50MB.');
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
        @forelse($reels as $reel)
        <div class="bg-black rounded-xl overflow-hidden relative" style="height: 70vh; max-height: 600px;" id="reel-{{ $reel->id }}">
            <video src="{{ $reel->media_url }}" class="w-full h-full object-contain" loop playsinline muted
                onclick="this.muted = false; this.paused ? this.play() : this.pause()"></video>

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

                <form action="{{ route('reels.share', $reel) }}" method="POST">
                    @csrf
                    <button type="submit" class="flex flex-col items-center text-white">
                        <div class="w-11 h-11 rounded-full bg-white/20 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                        </div>
                        <span class="text-xs mt-1">{{ $reel->shares_count }}</span>
                    </button>
                </form>
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
        </div>
        @empty
        <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
            <p class="text-lg font-semibold mb-2">No reels yet</p>
            <p class="text-sm">Upload a video above or follow friends to see their reels!</p>
        </div>
        @endforelse
    </div>

    @if($reels->hasPages())
        <div class="px-4 pb-8">{{ $reels->links() }}</div>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            const video = entry.target.querySelector('video');
            if (!video) return;
            if (entry.isIntersecting) {
                video.play().catch(() => {});
            } else {
                video.pause();
            }
        });
    }, { threshold: 0.6 });

    document.querySelectorAll('[id^="reel-"]').forEach(el => observer.observe(el));
});
</script>
@endsection
