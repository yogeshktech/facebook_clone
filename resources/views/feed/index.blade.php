@extends('layouts.app')

@section('title', 'News Feed')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-4 grid grid-cols-1 lg:grid-cols-12 gap-4">
    {{-- Left Sidebar --}}
    <aside class="hidden lg:block lg:col-span-3 space-y-2">
        <a href="{{ route('profile.show', auth()->user()) }}" class="sidebar-link">
            <img src="{{ auth()->user()->avatar_url }}" alt="" class="w-9 h-9 rounded-full object-cover">
            <span>{{ auth()->user()->name }}</span>
        </a>
        <a href="{{ route('friends.index') }}" class="sidebar-link">
            <svg class="w-9 h-9 text-fb-blue" fill="currentColor" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            <span>Friends</span>
        </a>
        <a href="{{ route('groups.index') }}" class="sidebar-link">
            <svg class="w-9 h-9 text-fb-blue" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/></svg>
            <span>Groups</span>
        </a>
        <a href="{{ route('pages.index') }}" class="sidebar-link">
            <svg class="w-9 h-9 text-fb-blue" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l-5.5 9h11z"/><circle cx="17.5" cy="17.5" r="4.5"/><circle cx="6.5" cy="17.5" r="4.5"/></svg>
            <span>Pages</span>
        </a>
        <a href="{{ route('chat.index') }}" class="sidebar-link">
            <svg class="w-9 h-9 text-fb-blue" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
            <span>Messenger</span>
        </a>
    </aside>

    {{-- Main Feed --}}
    <div class="lg:col-span-6 space-y-4">
        {{-- Stories --}}
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex gap-3 overflow-x-auto pb-2">
                <form action="{{ route('stories.store') }}" method="POST" enctype="multipart/form-data" class="flex-shrink-0">
                    @csrf
                    <label class="cursor-pointer block w-28">
                        <div class="w-28 h-44 rounded-xl bg-fb-gray border-2 border-dashed border-gray-300 flex flex-col items-center justify-center hover:border-fb-blue transition">
                            <svg class="w-8 h-8 text-fb-blue mb-2" fill="currentColor" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                            <span class="text-xs font-semibold text-fb-blue">Create Story</span>
                        </div>
                        <input type="file" name="media" accept="image/*,video/*" class="hidden" onchange="compressAndSubmitForm(this)">
                    </label>
                </form>
                @foreach($stories as $userId => $userStories)
                    @php
                        $storyUser = $userStories->first()->user;
                        $firstStory = $userStories->first();
                        $previewStory = $userStories->last();
                        $storyCount = $userStories->count();
                        $isOwnStory = $userId == auth()->id();
                    @endphp
                    <a href="{{ route('stories.show', $firstStory) }}" class="flex-shrink-0 w-28 h-44 rounded-xl overflow-hidden relative bg-gradient-to-b from-fb-blue to-purple-600">
                        <img src="{{ $storyUser->avatar_url }}" alt="" class="absolute top-3 left-3 w-10 h-10 rounded-full border-4 border-fb-blue object-cover z-10">
                        <img src="{{ $previewStory->media_url }}" alt="" class="w-full h-full object-cover opacity-80">
                        <span class="absolute bottom-2 left-2 right-2 text-white text-xs font-semibold truncate">{{ $storyUser->name }}</span>
                        @if($storyCount > 1)
                            <span class="absolute top-3 right-3 bg-black/60 text-white text-xs px-2 py-0.5 rounded-full z-10">{{ $storyCount }} stories</span>
                        @elseif($isOwnStory && isset($previewStory->views_count) && $previewStory->views_count > 0)
                            <span class="absolute top-3 right-3 bg-black/60 text-white text-xs px-2 py-0.5 rounded-full z-10">{{ $previewStory->views_count }} views</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Create Post --}}
        <div class="bg-white rounded-lg shadow p-4" id="create-post-card">
            <div id="post-form-alert" class="hidden mb-3 p-3 rounded-lg text-sm font-medium"></div>
            @if(session('error'))
                <div class="bg-red-50 text-red-600 border border-red-200 p-3 rounded-lg mb-3 text-sm">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="bg-red-50 text-red-600 border border-red-200 p-3 rounded-lg mb-3 text-sm">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif
            <form action="/posts" method="POST" enctype="multipart/form-data" id="create-post-form">
                @csrf
                <div class="flex gap-3 mb-3">
                    <img src="{{ auth()->user()->avatar_url }}" alt="" class="w-10 h-10 rounded-full object-cover">
                    <textarea name="content" rows="2" placeholder="What's on your mind, {{ auth()->user()->name }}?"
                        class="flex-1 bg-fb-gray rounded-2xl px-4 py-3 resize-none focus:outline-none focus:ring-2 focus:ring-fb-blue">{{ old('content') }}</textarea>
                </div>
                <div id="media-preview" class="hidden mb-3 px-2">
                    <img id="media-preview-img" src="" alt="" class="max-h-48 rounded-lg object-cover hidden">
                    <video id="media-preview-video" src="" controls class="max-h-48 rounded-lg hidden"></video>
                    <p id="media-preview-name" class="text-sm text-gray-500 mt-1"></p>
                </div>
                <div class="flex items-center justify-between border-t pt-3">
                    <label class="flex items-center gap-2 text-gray-600 hover:bg-gray-100 px-3 py-2 rounded-lg cursor-pointer">
                        <svg class="w-6 h-6 text-green-500" fill="currentColor" viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
                        <span class="text-sm font-medium">Photo/Video</span>
                        <input type="file" name="media" accept="image/*,video/*" class="hidden" id="post-media-input">
                    </label>
                    <button type="submit" id="post-submit-btn" class="bg-fb-blue text-white px-6 py-2 rounded-lg font-semibold hover:bg-fb-blue-dark transition disabled:opacity-60">Post</button>
                </div>
            </form>
        </div>
        <script>
            (function () {
                const form = document.getElementById('create-post-form');
                const alertEl = document.getElementById('post-form-alert');
                const submitBtn = document.getElementById('post-submit-btn');
                const contentInput = form?.querySelector('[name="content"]');
                const mediaInput = document.getElementById('post-media-input');

                function showAlert(message, type) {
                    if (!alertEl) return;
                    alertEl.textContent = message;
                    alertEl.className = type === 'success'
                        ? 'mb-3 p-3 rounded-lg text-sm font-medium bg-green-50 text-green-700 border border-green-200'
                        : 'mb-3 p-3 rounded-lg text-sm font-medium bg-red-50 text-red-600 border border-red-200';
                    alertEl.classList.remove('hidden');
                    alertEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }

                mediaInput?.addEventListener('change', function (e) {
                    const file = e.target.files[0];
                    const preview = document.getElementById('media-preview');
                    const img = document.getElementById('media-preview-img');
                    const video = document.getElementById('media-preview-video');
                    const name = document.getElementById('media-preview-name');
                    if (!file) return;
                    preview.classList.remove('hidden');
                    name.textContent = file.name;
                    const url = URL.createObjectURL(file);
                    if (file.type.startsWith('video/')) {
                        img.classList.add('hidden');
                        video.classList.remove('hidden');
                        video.src = url;
                    } else {
                        video.classList.add('hidden');
                        img.classList.remove('hidden');
                        img.src = url;
                    }
                });

                form?.addEventListener('submit', async function (e) {
                    e.preventDefault();

                    const content = contentInput?.value.trim() || '';
                    const hasMedia = mediaInput?.files?.length > 0;

                    if (!content && !hasMedia) {
                        showAlert('Please write something or attach a photo/video.', 'error');
                        return;
                    }

                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Posting...';

                    try {
                        const formData = new FormData(form);
                        const mediaFile = mediaInput?.files?.[0];
                        if (mediaFile) {
                            const prepared = await window.prepareMediaFile(mediaFile);
                            formData.set('media', prepared);
                        }

                        const res = await fetch('/posts', {
                            method: 'POST',
                            body: formData,
                            credentials: 'same-origin',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            },
                        });

                        if (res.status === 419) {
                            showAlert('Session expired. Please refresh the page and try again.', 'error');
                            return;
                        }

                        const data = await res.json().catch(() => ({}));

                        if (res.ok && data.success) {
                            window.location.reload();
                            return;
                        }

                        const message = data.message
                            || (data.errors ? Object.values(data.errors).flat().join(' ') : null)
                            || 'Could not create post. Please try again.';
                        showAlert(message, 'error');
                    } catch (err) {
                        const message = err.message || 'Network error. Check your connection and try again.';
                        showAlert(message, 'error');
                    } finally {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Post';
                    }
                });

            })();
        </script>

        {{-- Posts Feed --}}
        @forelse($posts as $post)
            @include('components.post-card', ['post' => $post])
        @empty
            <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                <p>No posts yet. Add friends or create your first post!</p>
            </div>
        @endforelse

        {{ $posts->links() }}
    </div>

    {{-- Right Sidebar --}}
    <aside class="hidden lg:block lg:col-span-3 space-y-4">
        <div class="bg-white rounded-lg shadow p-4">
            <h3 class="font-semibold text-gray-600 mb-3">People you may know</h3>
            @foreach($suggestions as $user)
                <div class="flex items-center gap-3 mb-3">
                    <img src="{{ $user->avatar_url }}" alt="" class="w-10 h-10 rounded-full object-cover">
                    <div class="flex-1 min-w-0">
                        <a href="{{ route('profile.show', $user) }}" class="font-semibold text-sm hover:underline truncate block">{{ $user->name }}</a>
                    </div>
                    <form action="{{ route('friends.send', $user) }}" method="POST">
                        @csrf
                        <button type="submit" class="bg-fb-gray hover:bg-gray-200 text-fb-blue text-sm font-semibold px-3 py-1 rounded-lg">Add</button>
                    </form>
                </div>
            @endforeach
        </div>
    </aside>
</div>
@endsection
