@extends('layouts.app')

@section('title', 'Chat')

@section('content')
<style>
    @keyframes typing-bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-4px); }
    }
    .typing-dot {
        animation: typing-bounce 1s infinite ease-in-out;
    }
</style>
@php $otherUser = $conversation->users->where('id', '!=', auth()->id())->first(); @endphp
<div class="max-w-2xl mx-auto p-2 sm:p-4 pb-2">
    <div class="bg-white rounded-lg shadow flex flex-col h-[calc(100dvh-9.5rem)] md:h-[calc(100dvh-8rem)]">
        <div class="p-4 border-b flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('chat.index') }}" class="text-gray-500 hover:text-fb-blue">&larr;</a>
                <img src="{{ $otherUser?->avatar_url }}" alt="" class="w-10 h-10 rounded-full object-cover">
                <div>
                    <h2 class="font-semibold leading-tight">{{ $otherUser?->name }}</h2>
                    <p id="chat-presence" class="text-xs mt-0.5 {{ ($presence['online'] ?? false) ? 'text-green-600' : 'text-gray-500' }}">
                        {{ $presence['label'] ?? 'Offline' }}
                    </p>
                </div>
            </div>
            @if($otherUser)
                <div class="flex items-center gap-2">
                    <button type="button" id="audio-call-btn" 
                        data-target-user-id="{{ $otherUser->id }}"
                        data-target-name="{{ $otherUser->name }}"
                        data-target-avatar="{{ $otherUser->avatar_url }}"
                        data-target-online="{{ ($presence['online'] ?? false) ? '1' : '0' }}"
                        class="p-2 text-gray-500 hover:text-fb-blue rounded-full hover:bg-fb-gray transition" title="Audio Call">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                    </button>
                    <button type="button" id="video-call-btn" 
                        data-target-user-id="{{ $otherUser->id }}"
                        data-target-name="{{ $otherUser->name }}"
                        data-target-avatar="{{ $otherUser->avatar_url }}"
                        data-target-online="{{ ($presence['online'] ?? false) ? '1' : '0' }}"
                        class="p-2 text-gray-500 hover:text-fb-blue rounded-full hover:bg-fb-gray transition" title="Video Call">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </button>
                </div>
            @endif
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
    const typingUrl = @json(route('chat.typing', $conversation));
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const container = document.getElementById('chat-messages');
    const form = document.getElementById('chat-form');
    const input = document.getElementById('chat-input');
    const mediaInput = document.getElementById('chat-media');
    const sendBtn = document.getElementById('chat-send-btn');
    let lastMessageId = 0;
    let isNearBottom = true;
    let sending = false;
    let receiveTypingTimeout = null;
    let typingTimeout = null;
    let isCurrentlyTyping = false;

    // Create typing indicator element
    const typingBubble = document.createElement('div');
    typingBubble.id = 'typing-indicator';
    typingBubble.className = 'flex justify-start hidden mb-3';
    typingBubble.innerHTML = `
        <div class="flex items-center gap-1.5 px-3.5 py-2.5 bg-fb-gray text-gray-500 rounded-2xl rounded-bl-sm shadow-sm border border-gray-100 flex-row">
            <div class="flex gap-1 items-center">
                <span class="w-1.5 h-1.5 bg-gray-500 rounded-full typing-dot" style="animation-delay: 0ms;"></span>
                <span class="w-1.5 h-1.5 bg-gray-500 rounded-full typing-dot" style="animation-delay: 150ms;"></span>
                <span class="w-1.5 h-1.5 bg-gray-500 rounded-full typing-dot" style="animation-delay: 300ms;"></span>
            </div>
            <span class="text-xs font-normal text-gray-500 ml-1.5">typing...</span>
        </div>
    `;
    container.appendChild(typingBubble);

    function formatTime(dateString) {
        const d = new Date(dateString);
        let hours = d.getHours();
        const minutes = String(d.getMinutes()).padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;
        return `${hours}:${minutes} ${ampm}`;
    }

    let conversationChannel = null;
    let typingSendTimer = null;
    let lastTypingSent = false;

    function updatePresence(presence) {
        const el = document.getElementById('chat-presence');
        if (!el || !presence) return;
        el.textContent = presence.label || (presence.online ? 'Online' : 'Offline');
        el.classList.toggle('text-green-600', !!presence.online);
        el.classList.toggle('text-gray-500', !presence.online);
    }

    function sendTypingSignal(isTyping) {
        if (lastTypingSent === isTyping) return;
        lastTypingSent = isTyping;

        fetch(typingUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ typing: isTyping }),
        }).catch(() => {});
    }

    function queueTypingSignal(isTyping) {
        clearTimeout(typingSendTimer);
        if (isTyping) {
            sendTypingSignal(true);
            return;
        }
        typingSendTimer = setTimeout(() => sendTypingSignal(false), 250);
    }

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
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 25000);

            const res = await fetch(sendUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                signal: controller.signal,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            clearTimeout(timeoutId);

            if (res.status === 419) {
                alert('Session expired. Please refresh the page.');
                return;
            }

            if (res.status === 504 || res.status === 502) {
                alert('Server is busy. Please wait a moment and refresh the page.');
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

            if (isCurrentlyTyping) {
                isCurrentlyTyping = false;
                clearTimeout(typingTimeout);
                queueTypingSignal(false);
            }

            const msg = data.message;
            renderMessage({
                id: msg.id,
                body: msg.body,
                media_url: msg.media_url,
                media_type: msg.media_type,
                user_id: msg.user_id,
                time: msg.time || '',
                status: msg.status || 'sent',
            });
            lastMessageId = Math.max(lastMessageId, msg.id);
            scrollToBottom();
        } catch (e) {
            if (e.name === 'AbortError') {
                alert('Request timed out. Server may be overloaded — please refresh and try again.');
            } else {
                alert('Network error. Please try again.');
            }
        } finally {
            sending = false;
            sendBtn.disabled = false;
        }
    }

    const initialMessages = @json($initialMessages);

    function statusIcon(status) {
        const color = status === 'read' ? 'text-sky-300' : 'text-white/80';
        const check = `<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`;

        if (status === 'sent') {
            return `<span class="inline-flex ${color}" title="Sent">${check}</span>`;
        }

        const title = status === 'read' ? 'Seen' : 'Delivered';
        return `<span class="inline-flex ${color} -space-x-1.5" title="${title}">${check}${check}</span>`;
    }

    function updateMessageStatus(messageId, status) {
        const el = document.getElementById('msg-status-' + messageId);
        if (el && status) {
            el.innerHTML = statusIcon(status);
        }
    }

    function updateAllStatuses(statuses) {
        if (!statuses) return;
        Object.entries(statuses).forEach(([id, status]) => updateMessageStatus(id, status));
    }

    function callLabelForViewer(msg) {
        if (msg.call_label) {
            return msg.call_label;
        }

        const video = msg.call_is_video ? 'Video' : 'Voice';
        const isCaller = msg.user_id === authUserId;

        if (msg.call_status === 'declined') {
            return isCaller ? `${video} call declined` : `Declined ${video} call`;
        }

        if (msg.call_status === 'unanswered') {
            return isCaller ? `${video} call · No answer` : `Missed ${video} call`;
        }

        return `${video} call`;
    }

    function renderCallMessage(msg) {
        if (document.getElementById('msg-' + msg.id)) return;

        const wrap = document.createElement('div');
        wrap.id = 'msg-' + msg.id;
        wrap.className = 'flex justify-center my-2';

        const icon = msg.call_is_video ? '📹' : '📞';
        const label = callLabelForViewer(msg);
        const missed = msg.call_status === 'unanswered' && msg.user_id !== authUserId;

        wrap.innerHTML = `
            <div class="px-3 py-1.5 rounded-full text-xs flex items-center gap-1.5 ${missed ? 'bg-red-50 text-red-600' : 'bg-gray-200/80 text-gray-600'}">
                <span>${icon}</span>
                <span>${escapeHtml(label)}</span>
                <span class="opacity-60">· ${msg.time || ''}</span>
            </div>`;

        if (typingBubble.parentNode === container) {
            container.insertBefore(wrap, typingBubble);
        } else {
            container.appendChild(wrap);
        }

        lastMessageId = Math.max(lastMessageId, msg.id);
    }

    function renderMessage(msg) {
        if (document.getElementById('msg-' + msg.id)) return;

        if (msg.message_type === 'call') {
            renderCallMessage(msg);
            return;
        }

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

        const statusHtml = isSender
            ? `<span id="msg-status-${msg.id}">${statusIcon(msg.status || 'sent')}</span>`
            : '';

        wrap.innerHTML = `
            <div class="max-w-xs lg:max-w-md px-3 py-2 rounded-2xl ${bubbleClass}">
                ${mediaHtml}${bodyHtml}
                <p class="text-xs opacity-70 mt-1 flex items-center justify-end gap-1">
                    <span>${msg.time}</span>
                    ${statusHtml}
                </p>
            </div>`;

        if (msg.user_id !== authUserId) {
            typingBubble.classList.add('hidden');
            clearTimeout(receiveTypingTimeout);
        }

        if (typingBubble.parentNode === container) {
            container.insertBefore(wrap, typingBubble);
        } else {
            container.appendChild(wrap);
        }

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
            updateAllStatuses(data.statuses);
            updatePresence(data.presence);
            if (hadNew && isNearBottom) scrollToBottom();
        } catch (e) {}
    }

    let pollingInterval = setInterval(fetchNewMessages, 8000);

    if (window.Echo && typeof window.Echo.private === 'function') {
        clearInterval(pollingInterval);
        pollingInterval = setInterval(fetchNewMessages, 15000);

        try {
            conversationChannel = window.Echo.private(`conversation.${conversationId}`);
            conversationChannel
                .listen('.message.sent', (data) => {
                    if (document.getElementById('msg-' + data.id)) return;

                    renderMessage({
                        id: data.id,
                        body: data.body,
                        message_type: data.message_type || 'text',
                        call_status: data.call_status,
                        call_is_video: data.call_is_video,
                        media_url: data.media_url,
                        media_type: data.media_type,
                        user_id: data.user_id ?? data.user?.id,
                        time: formatTime(data.created_at),
                        status: 'delivered',
                    });

                    typingBubble.classList.add('hidden');
                    clearTimeout(receiveTypingTimeout);

                    if (isNearBottom) scrollToBottom();
                    fetchNewMessages();
                })
                .listen('.user.typing', (e) => {
                    if (e.user_id !== authUserId) {
                        if (e.typing) {
                            typingBubble.classList.remove('hidden');
                            if (isNearBottom) scrollToBottom();

                            clearTimeout(receiveTypingTimeout);
                            receiveTypingTimeout = setTimeout(() => {
                                typingBubble.classList.add('hidden');
                            }, 5000);
                        } else {
                            clearTimeout(receiveTypingTimeout);
                            typingBubble.classList.add('hidden');
                        }
                    }
                });
        } catch (e) {
            console.error('Failed to bind Echo listeners', e);
        }
    }

    fetchNewMessages();

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        await sendMessage();
    });

    input.addEventListener('input', () => {
        const hasText = input.value.trim().length > 0;
        if (hasText) {
            if (!isCurrentlyTyping) {
                isCurrentlyTyping = true;
                queueTypingSignal(true);
            }
            clearTimeout(typingTimeout);
            typingTimeout = setTimeout(() => {
                isCurrentlyTyping = false;
                queueTypingSignal(false);
            }, 3000);
        } else {
            if (isCurrentlyTyping) {
                isCurrentlyTyping = false;
                clearTimeout(typingTimeout);
                queueTypingSignal(false);
            }
        }
    });

    mediaInput?.addEventListener('change', async function () {
        if (this.files?.length) {
            await sendMessage();
        }
    });
})();
</script>
@endsection
