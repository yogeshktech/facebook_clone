@extends('layouts.app')

@section('title', 'Chat')

@section('content')
@php $otherUser = $conversation->users->where('id', '!=', auth()->id())->first(); @endphp
<div class="max-w-2xl mx-auto p-2 sm:p-4 pb-2">
    <div class="bg-white rounded-lg shadow flex flex-col h-[calc(100dvh-9.5rem)] md:h-[calc(100dvh-8rem)]">
        <div class="p-4 border-b flex items-center gap-3">
            <a href="{{ route('chat.index') }}" class="text-gray-500 hover:text-fb-blue">&larr;</a>
            <img src="{{ $otherUser?->avatar_url }}" alt="" class="w-10 h-10 rounded-full object-cover">
            <h2 class="font-semibold">{{ $otherUser?->name }}</h2>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-3 flex flex-col" id="chat-messages"></div>

        <form action="{{ route('chat.send', $conversation) }}" method="POST" enctype="multipart/form-data" 
                class="p-2 sm:p-3 border-t flex gap-1.5 sm:gap-2 items-center flex-shrink-0" id="chat-form">
            @csrf
            <label class="cursor-pointer text-fb-blue hover:bg-fb-gray p-2 rounded-full flex-shrink-0" title="Send image/video">
                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
                <input type="file" name="media" accept="image/*,video/*" class="hidden" id="chat-media">
            </label>
            <input type="text" name="body" placeholder="Type a message..."
                class="flex-1 min-w-0 bg-fb-gray rounded-full px-3 sm:px-4 py-2 text-sm sm:text-base focus:outline-none focus:ring-2 focus:ring-fb-blue" id="chat-input" autocomplete="off">
            <button type="submit" id="chat-send-btn" class="flex-shrink-0 w-9 h-9 sm:w-10 sm:h-10 flex items-center justify-center bg-fb-blue text-white rounded-full hover:bg-fb-blue-dark transition disabled:opacity-50" title="Send">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
            </button>
        </form>
    </div>
</div>

<script>
(function () {
    const conversationId = {{ $conversation->id }};
    const authUserId = {{ auth()->id() }};
    const messagesUrl = @json(route('chat.messages', $conversation));
    const sendUrl = @json(route('chat.send', $conversation));
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const container = document.getElementById('chat-messages');
    const form = document.getElementById('chat-form');
    const input = document.getElementById('chat-input');
    const mediaInput = document.getElementById('chat-media');
    const sendBtn = document.getElementById('chat-send-btn');
    let lastMessageId = 0;
    let isNearBottom = true;
    let sending = false;

    async function prepareFile(file) {
        if (window.prepareMediaFile) {
            return window.prepareMediaFile(file);
        }
        return file;
    }

    async function sendMessage() {
        if (sending) return;

        const body = input.value.trim();
        const mediaFile = mediaInput?.files?.[0];
        if (!body && !mediaFile) return;

        const formData = new FormData();
        formData.append('_token', csrfToken);
        if (body) formData.append('body', body);
        if (mediaFile) {
            try {
                formData.append('media', await prepareFile(mediaFile));
            } catch (error) {
                alert(error.message || 'Could not send this file.');
                return;
            }
        }

        sending = true;
        sendBtn.disabled = true;

        try {
            const res = await fetch(sendUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (res.status === 419) {
                alert('Session expired. Please refresh the page.');
                return;
            }

            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                const message = data.message
                    || data.error
                    || (data.errors ? Object.values(data.errors).flat().join(' ') : null)
                    || 'Failed to send message.';
                alert(message);
                return;
            }

            input.value = '';
            if (mediaInput) mediaInput.value = '';

            const msg = data.message;
            renderMessage({
                id: msg.id,
                body: msg.body,
                media_url: msg.media_url,
                media_type: msg.media_type,
                user_id: msg.user_id,
                time: new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }),
            });
            scrollToBottom();
        } catch (e) {
            alert('Network error. Please try again.');
        } finally {
            sending = false;
            sendBtn.disabled = false;
        }
    }

    const initialMessages = @json($initialMessages);

    function renderMessage(msg) {
        if (document.getElementById('msg-' + msg.id)) return;

        const isSender = msg.user_id === authUserId;
        const wrap = document.createElement('div');
        wrap.id = 'msg-' + msg.id;
        wrap.className = 'flex ' + (isSender ? 'justify-end' : 'justify-start');

        let mediaHtml = '';
        if (msg.media_url) {
            mediaHtml = msg.media_type === 'video'
                ? `<video src="${msg.media_url}" controls controlsList="nodownload noplaybackrate noremoteplayback" disablePictureInPicture playsinline oncontextmenu="return false" class="rounded-lg max-w-full max-h-48 mb-1"></video>`
                : `<img src="${msg.media_url}" alt="" class="rounded-lg max-w-full max-h-48 mb-1 object-cover">`;
        }

        const bodyHtml = msg.body ? `<p class="break-words">${escapeHtml(msg.body)}</p>` : '';
        const bubbleClass = isSender
            ? 'bg-fb-blue text-white rounded-br-sm'
            : 'bg-fb-gray text-gray-900 rounded-bl-sm';

        wrap.innerHTML = `
            <div class="max-w-xs lg:max-w-md px-3 py-2 rounded-2xl ${bubbleClass}">
                ${mediaHtml}${bodyHtml}
                <p class="text-xs opacity-70 mt-1 text-right">${msg.time}</p>
            </div>`;

        container.appendChild(wrap);
        lastMessageId = Math.max(lastMessageId, msg.id);
    }

    function escapeHtml(text) {
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    function scrollToBottom() {
        container.scrollTop = container.scrollHeight;
    }

    container.addEventListener('scroll', () => {
        isNearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 80;
    });

    initialMessages.forEach(renderMessage);
    scrollToBottom();

    async function fetchNewMessages() {
        try {
            const url = messagesUrl + (lastMessageId ? '?after_id=' + lastMessageId : '');
            const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) return;
            const data = await res.json();
            const hadNew = data.messages.length > 0;
            data.messages.forEach(renderMessage);
            if (hadNew && isNearBottom) scrollToBottom();
        } catch (e) {}
    }

    setInterval(fetchNewMessages, 2000);

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        await sendMessage();
    });

    mediaInput?.addEventListener('change', async function () {
        if (this.files?.length) {
            await sendMessage();
        }
    });
})();
</script>
@endsection
