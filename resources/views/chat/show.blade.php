@extends('layouts.app')

@section('title', 'Chat')

@section('content')
<style>
    @keyframes typing-bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-4px); }
    }
    .typing-dot { animation: typing-bounce 1s infinite ease-in-out; }
    .chat-link { text-decoration: underline; word-break: break-all; }
    .msg-mine .chat-link { color: #e0e7ff; }
    .msg-theirs .chat-link { color: #4f46e5; }
</style>
@php
    $isGroup = $conversation->isGroup();
    $chatTitle = $isGroup ? ($conversation->name ?: 'Group Chat') : ($otherUser?->name ?? 'Chat');
    $chatAvatar = $isGroup
        ? 'https://ui-avatars.com/api/?name='.urlencode($chatTitle).'&background=6366F1&color=fff'
        : ($otherUser?->avatar_url ?? '');
@endphp
<div class="max-w-2xl mx-auto p-2 sm:p-4 pb-2">
    <div class="bg-white rounded-lg shadow flex flex-col h-[calc(100dvh-9.5rem)] md:h-[calc(100dvh-8rem)]">
        <div class="p-3 sm:p-4 border-b flex items-center justify-between gap-2">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ route('chat.index') }}" class="text-gray-500 hover:text-fb-blue flex-shrink-0">&larr;</a>
                <img src="{{ $chatAvatar }}" alt="" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                <div class="min-w-0">
                    <h2 class="font-semibold leading-tight truncate">{{ $chatTitle }}</h2>
                    <p id="chat-presence" class="text-xs mt-0.5 {{ ($presence['online'] ?? false) ? 'text-green-600' : 'text-gray-500' }}">
                        {{ $presence['label'] ?? 'Offline' }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-1 sm:gap-2 flex-shrink-0">
                @if($isGroup && ($chatConfig['can_manage_group'] ?? false))
                    <button type="button" id="add-members-btn" class="p-2 text-fb-blue hover:bg-fb-blue/10 rounded-full" title="Add members">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    </button>
                @endif
                @if($otherUser)
                    <button type="button" id="audio-call-btn"
                        data-target-user-id="{{ $otherUser->id }}"
                        data-target-name="{{ $otherUser->name }}"
                        data-target-avatar="{{ $otherUser->avatar_url }}"
                        data-target-online="{{ ($presence['online'] ?? false) ? '1' : '0' }}"
                        class="p-2.5 text-fb-blue hover:text-white rounded-full bg-fb-blue/10 hover:bg-fb-blue transition-all duration-200 hover:scale-105 active:scale-95 shadow-sm border border-fb-blue/15 flex items-center justify-center cursor-pointer" title="Audio Call">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                    </button>
                    <button type="button" id="video-call-btn"
                        data-target-user-id="{{ $otherUser->id }}"
                        data-target-name="{{ $otherUser->name }}"
                        data-target-avatar="{{ $otherUser->avatar_url }}"
                        data-target-online="{{ ($presence['online'] ?? false) ? '1' : '0' }}"
                        class="p-2.5 text-fb-blue hover:text-white rounded-full bg-fb-blue/10 hover:bg-fb-blue transition-all duration-200 hover:scale-105 active:scale-95 shadow-sm border border-fb-blue/15 flex items-center justify-center cursor-pointer" title="Video Call">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </button>
                @endif
                <form action="{{ route('chat.destroy', $conversation) }}" method="POST"
                    onsubmit="return confirm('Delete this chat from your list?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="p-2 text-gray-400 hover:text-rose-600 rounded-full hover:bg-rose-50" title="Delete chat">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </form>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-3 flex flex-col" id="chat-messages"></div>

        <div id="reply-bar" class="hidden px-3 py-2 border-t bg-fb-gray/60 flex items-start gap-2">
            <div class="flex-1 min-w-0 border-l-4 border-fb-blue pl-2">
                <p class="text-xs font-semibold text-fb-blue" id="reply-bar-name"></p>
                <p class="text-xs text-gray-600 truncate" id="reply-bar-body"></p>
            </div>
            <button type="button" id="reply-bar-close" class="text-gray-400 hover:text-gray-700 p-1" title="Cancel reply">&times;</button>
        </div>

        <form action="{{ route('chat.send', $conversation) }}" method="POST" enctype="multipart/form-data"
                class="p-2 sm:p-3 border-t flex gap-1.5 sm:gap-2 items-center flex-shrink-0" id="chat-form">
            @csrf
            <input type="hidden" name="reply_to_id" id="reply-to-id" value="">
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

{{-- Message actions sheet --}}
<div id="msg-actions" class="hidden fixed inset-0 z-[80] flex items-end sm:items-center justify-center">
    <button type="button" id="msg-actions-backdrop" class="absolute inset-0 bg-black/40"></button>
    <div class="relative w-full max-w-sm mx-3 mb-4 sm:mb-0 bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="p-2" id="msg-actions-list"></div>
        <button type="button" id="msg-actions-cancel" class="w-full py-3 text-sm font-semibold text-gray-600 border-t hover:bg-fb-gray">Cancel</button>
    </div>
</div>

{{-- Add members modal --}}
@if($isGroup && ($chatConfig['can_manage_group'] ?? false))
<div id="add-members-modal" class="hidden fixed inset-0 z-[80] flex items-end sm:items-center justify-center">
    <button type="button" class="absolute inset-0 bg-black/40" data-close-members></button>
    <div class="relative w-full max-w-md mx-3 mb-4 sm:mb-0 bg-white rounded-2xl shadow-xl overflow-hidden max-h-[80vh] flex flex-col">
        <div class="p-4 border-b flex items-center justify-between">
            <h3 class="font-bold">Add members</h3>
            <button type="button" class="text-gray-400 text-xl" data-close-members>&times;</button>
        </div>
        <form action="{{ route('chat.members.add', $conversation) }}" method="POST" class="flex flex-col flex-1 min-h-0" id="add-members-form">
            @csrf
            <div class="overflow-y-auto flex-1 divide-y">
                @forelse($friends as $friend)
                <label class="flex items-center gap-3 p-3 hover:bg-fb-gray cursor-pointer">
                    <input type="checkbox" name="user_ids[]" value="{{ $friend->id }}" class="rounded text-fb-blue">
                    <img src="{{ $friend->avatar_url }}" alt="" class="w-9 h-9 rounded-full object-cover">
                    <span class="font-medium">{{ $friend->name }}</span>
                </label>
                @empty
                <p class="p-4 text-sm text-gray-500">All friends are already in this group.</p>
                @endforelse
            </div>
            @if($friends->count())
            <div class="p-3 border-t">
                <button type="submit" class="btn-primary w-full">Add selected</button>
            </div>
            @endif
        </form>
    </div>
</div>
@endif

<script>
(function () {
    const conversationId = {{ $conversation->id }};
    const authUserId = {{ auth()->id() }};
    const isGroup = @json($isGroup);
    const chatConfig = @json($chatConfig);
    const messagesUrl = @json(route('chat.messages', $conversation));
    const sendUrl = @json(route('chat.send', $conversation));
    const typingUrl = @json(route('chat.typing', $conversation));
    const editUrlBase = @json(url('/chat/messages'));
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const container = document.getElementById('chat-messages');
    const form = document.getElementById('chat-form');
    const input = document.getElementById('chat-input');
    const mediaInput = document.getElementById('chat-media');
    const sendBtn = document.getElementById('chat-send-btn');
    const replyBar = document.getElementById('reply-bar');
    const replyToInput = document.getElementById('reply-to-id');
    const replyBarName = document.getElementById('reply-bar-name');
    const replyBarBody = document.getElementById('reply-bar-body');
    const msgActions = document.getElementById('msg-actions');
    const msgActionsList = document.getElementById('msg-actions-list');

    let lastMessageId = 0;
    let isNearBottom = true;
    let sending = false;
    let receiveTypingTimeout = null;
    let typingTimeout = null;
    let isCurrentlyTyping = false;
    let replyToId = null;
    let editingMessageId = null;
    const messageCache = {};

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
        </div>`;
    container.appendChild(typingBubble);

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

    function escapeHtml(text) {
        const d = document.createElement('div');
        d.textContent = text ?? '';
        return d.innerHTML;
    }

    function linkify(text) {
        const escaped = escapeHtml(text);
        return escaped.replace(
            /(https?:\/\/[^\s<]+|www\.[^\s<]+)/gi,
            (url) => {
                const href = url.toLowerCase().startsWith('http') ? url : `https://${url}`;
                return `<a href="${href}" target="_blank" rel="noopener noreferrer" class="chat-link">${url}</a>`;
            }
        );
    }

    function formatTime(dateString) {
        const d = new Date(dateString);
        let hours = d.getHours();
        const minutes = String(d.getMinutes()).padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;
        return `${hours}:${minutes} ${ampm}`;
    }

    function statusIcon(status) {
        const color = status === 'read' ? 'text-sky-300' : 'text-white/80';
        const check = `<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`;
        if (status === 'sent') return `<span class="inline-flex ${color}" title="Sent">${check}</span>`;
        const title = status === 'read' ? 'Seen' : 'Delivered';
        return `<span class="inline-flex ${color} -space-x-1.5" title="${title}">${check}${check}</span>`;
    }

    function updateMessageStatus(messageId, status) {
        const el = document.getElementById('msg-status-' + messageId);
        if (el && status) el.innerHTML = statusIcon(status);
    }

    function updateAllStatuses(statuses) {
        if (!statuses) return;
        Object.entries(statuses).forEach(([id, status]) => updateMessageStatus(id, status));
    }

    function setReply(msg) {
        editingMessageId = null;
        replyToId = msg.id;
        replyToInput.value = msg.id;
        replyBarName.textContent = msg.user_id === authUserId ? 'You' : (msg.user_name || 'User');
        replyBarBody.textContent = msg.deleted_for_everyone
            ? 'This message was deleted'
            : (msg.body || (msg.media_url ? 'Media' : ''));
        replyBar.classList.remove('hidden');
        input.focus();
        input.placeholder = 'Type a reply...';
    }

    function clearReply() {
        replyToId = null;
        replyToInput.value = '';
        replyBar.classList.add('hidden');
        if (!editingMessageId) input.placeholder = 'Type a message...';
    }

    function startEdit(msg) {
        clearReply();
        editingMessageId = msg.id;
        input.value = msg.body || '';
        input.placeholder = 'Edit message...';
        input.focus();
        sendBtn.title = 'Save edit';
    }

    function clearEdit() {
        editingMessageId = null;
        input.placeholder = 'Type a message...';
        sendBtn.title = 'Send';
    }

    document.getElementById('reply-bar-close')?.addEventListener('click', clearReply);

    function closeActions() {
        msgActions?.classList.add('hidden');
        msgActionsList.innerHTML = '';
    }

    function openActions(msg) {
        if (msg.message_type === 'call') return;
        messageCache[msg.id] = msg;
        const items = [];

        items.push({ label: 'Reply', action: () => setReply(msg) });

        if (msg.is_sender && msg.can_edit && !msg.deleted_for_everyone) {
            items.push({ label: 'Edit', action: () => startEdit(msg) });
        }

        items.push({
            label: 'Delete for me',
            danger: true,
            action: () => deleteMessage(msg.id, 'me'),
        });

        if (msg.is_sender && msg.can_delete_everyone && !msg.deleted_for_everyone) {
            items.push({
                label: 'Delete for everyone',
                danger: true,
                action: () => deleteMessage(msg.id, 'everyone'),
            });
        }

        msgActionsList.innerHTML = '';
        items.forEach((item) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `w-full text-left px-4 py-3 text-sm font-medium hover:bg-fb-gray ${item.danger ? 'text-rose-600' : 'text-gray-800'}`;
            btn.textContent = item.label;
            btn.addEventListener('click', () => {
                closeActions();
                item.action();
            });
            msgActionsList.appendChild(btn);
        });
        msgActions.classList.remove('hidden');
    }

    document.getElementById('msg-actions-backdrop')?.addEventListener('click', closeActions);
    document.getElementById('msg-actions-cancel')?.addEventListener('click', closeActions);

    async function deleteMessage(id, scope) {
        const confirmText = scope === 'everyone'
            ? 'Delete this message for everyone?'
            : 'Delete this message only for you?';
        if (!confirm(confirmText)) return;

        try {
            const res = await fetch(`${editUrlBase}/${id}`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ scope }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                alert(data.message || 'Could not delete message.');
                return;
            }
            if (scope === 'me') {
                document.getElementById('msg-' + id)?.remove();
                delete messageCache[id];
            } else if (data.message) {
                applyMessageUpdate(data.message);
            }
        } catch (e) {
            alert('Network error.');
        }
    }

    async function saveEdit(id, body) {
        const res = await fetch(`${editUrlBase}/${id}`, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ body }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            alert(data.message || 'Could not edit message. Time may have expired.');
            return false;
        }
        if (data.message) applyMessageUpdate(data.message);
        return true;
    }

    function applyMessageUpdate(msg) {
        messageCache[msg.id] = { ...(messageCache[msg.id] || {}), ...msg, is_sender: msg.user_id === authUserId };
        const el = document.getElementById('msg-' + msg.id);
        if (!el) return;

        if (msg.deleted_for_everyone) {
            const bubble = el.querySelector('[data-bubble]');
            if (bubble) {
                bubble.innerHTML = `
                    <p class="italic text-sm opacity-80">This message was deleted</p>
                    <p class="text-xs opacity-70 mt-1 flex items-center justify-end gap-1">
                        <span>${msg.time || ''}</span>
                    </p>`;
            }
            return;
        }

        const bodyEl = el.querySelector('[data-body]');
        if (bodyEl) {
            bodyEl.innerHTML = linkify(msg.body || '');
        }
        let editedEl = el.querySelector('[data-edited]');
        if (msg.is_edited) {
            if (!editedEl) {
                const meta = el.querySelector('[data-meta]');
                if (meta) {
                    editedEl = document.createElement('span');
                    editedEl.setAttribute('data-edited', '1');
                    editedEl.className = 'opacity-70';
                    editedEl.textContent = 'edited';
                    meta.insertBefore(editedEl, meta.firstChild);
                }
            }
        }
    }

    function callLabelForViewer(msg) {
        if (msg.call_label) return msg.call_label;
        const video = msg.call_is_video ? 'Video' : 'Voice';
        const isCaller = msg.user_id === authUserId;
        if (msg.call_status === 'declined') return isCaller ? `${video} call declined` : `Declined ${video} call`;
        if (msg.call_status === 'unanswered') return isCaller ? `${video} call · No answer` : `Missed ${video} call`;
        return `${video} call`;
    }

    function renderCallMessage(msg) {
        if (document.getElementById('msg-' + msg.id)) return;
        const wrap = document.createElement('div');
        wrap.id = 'msg-' + msg.id;
        wrap.className = 'flex justify-center my-3';
        const label = callLabelForViewer(msg);
        const missed = msg.call_status === 'unanswered' && msg.user_id !== authUserId;
        const declined = msg.call_status === 'declined';
        let badgeClass = missed
            ? 'bg-rose-50 text-rose-600 border border-rose-100'
            : declined
                ? 'bg-amber-50 text-amber-700 border border-amber-100'
                : 'bg-slate-100 text-slate-600 border border-slate-200/60';
        wrap.innerHTML = `
            <div class="px-3.5 py-1.5 rounded-full text-xs font-medium flex items-center gap-2 ${badgeClass}">
                <span>${escapeHtml(label)}</span>
                <span class="opacity-60 text-[10px] font-normal">${msg.time || ''}</span>
            </div>`;
        insertMessageEl(wrap);
        lastMessageId = Math.max(lastMessageId, msg.id);
    }

    function insertMessageEl(wrap) {
        if (typingBubble.parentNode === container) {
            container.insertBefore(wrap, typingBubble);
        } else {
            container.appendChild(wrap);
        }
    }

    function renderMessage(msg) {
        if (document.getElementById('msg-' + msg.id)) {
            applyMessageUpdate(msg);
            return;
        }

        if (msg.message_type === 'call') {
            renderCallMessage(msg);
            return;
        }

        const isSender = msg.user_id === authUserId || msg.is_sender;
        msg.is_sender = isSender;
        messageCache[msg.id] = msg;

        const wrap = document.createElement('div');
        wrap.id = 'msg-' + msg.id;
        wrap.className = 'flex ' + (isSender ? 'justify-end' : 'justify-start');
        wrap.dataset.msgId = msg.id;

        const bubbleClass = isSender
            ? 'bg-fb-blue text-white rounded-br-sm msg-mine'
            : 'bg-fb-gray text-gray-900 rounded-bl-sm msg-theirs';

        let mediaHtml = '';
        if (msg.media_url && !msg.deleted_for_everyone) {
            mediaHtml = msg.media_type === 'video'
                ? `<video src="${msg.media_url}" controls controlsList="nodownload noplaybackrate noremoteplayback" disablePictureInPicture playsinline oncontextmenu="return false" class="rounded-lg max-w-full max-h-48 mb-1"></video>`
                : `<img src="${msg.media_url}" alt="" class="rounded-lg max-w-full max-h-48 mb-1 object-cover">`;
        }

        let replyHtml = '';
        if (msg.reply_to) {
            replyHtml = `
                <div class="mb-1 px-2 py-1 rounded-lg text-xs ${isSender ? 'bg-white/15' : 'bg-black/5'} border-l-2 ${isSender ? 'border-white/60' : 'border-fb-blue'}">
                    <p class="font-semibold opacity-90">${escapeHtml(msg.reply_to.user_id === authUserId ? 'You' : (msg.reply_to.user_name || 'User'))}</p>
                    <p class="opacity-80 truncate">${escapeHtml(msg.reply_to.body || '')}</p>
                </div>`;
        }

        let bodyHtml = '';
        if (msg.deleted_for_everyone) {
            bodyHtml = `<p class="italic text-sm opacity-80">This message was deleted</p>`;
        } else if (msg.body) {
            bodyHtml = `<p class="break-words" data-body>${linkify(msg.body)}</p>`;
        }

        const nameHtml = (!isSender && isGroup && msg.user_name)
            ? `<p class="text-[11px] font-semibold mb-0.5 opacity-80">${escapeHtml(msg.user_name)}</p>`
            : '';

        const statusHtml = isSender && !msg.deleted_for_everyone
            ? `<span id="msg-status-${msg.id}">${statusIcon(msg.status || 'sent')}</span>`
            : '';

        const editedHtml = msg.is_edited ? `<span data-edited class="opacity-70">edited</span>` : '';

        wrap.innerHTML = `
            <div data-bubble class="max-w-xs lg:max-w-md px-3 py-2 rounded-2xl ${bubbleClass} cursor-pointer select-text" title="Hold or click for options">
                ${nameHtml}${replyHtml}${mediaHtml}${bodyHtml}
                <p class="text-xs opacity-70 mt-1 flex items-center justify-end gap-1.5" data-meta>
                    ${editedHtml}
                    <span>${msg.time || ''}</span>
                    ${statusHtml}
                </p>
            </div>`;

        const bubble = wrap.querySelector('[data-bubble]');
        bubble.addEventListener('click', (e) => {
            if (e.target.closest('a, video, img, button')) return;
            openActions(messageCache[msg.id] || msg);
        });

        let pressTimer = null;
        bubble.addEventListener('touchstart', () => {
            pressTimer = setTimeout(() => openActions(messageCache[msg.id] || msg), 450);
        }, { passive: true });
        bubble.addEventListener('touchend', () => clearTimeout(pressTimer));
        bubble.addEventListener('touchmove', () => clearTimeout(pressTimer));

        if (msg.user_id !== authUserId) {
            typingBubble.classList.add('hidden');
            clearTimeout(receiveTypingTimeout);
        }

        insertMessageEl(wrap);
        lastMessageId = Math.max(lastMessageId, msg.id);
    }

    async function prepareFile(file) {
        if (window.prepareMediaFile) return window.prepareMediaFile(file);
        return file;
    }

    async function sendMessage() {
        if (sending) return;

        const body = input.value.trim();
        const mediaFile = mediaInput?.files?.[0];

        if (editingMessageId) {
            if (!body) return;
            sending = true;
            sendBtn.disabled = true;
            try {
                const ok = await saveEdit(editingMessageId, body);
                if (ok) {
                    input.value = '';
                    clearEdit();
                }
            } finally {
                sending = false;
                sendBtn.disabled = false;
            }
            return;
        }

        if (!body && !mediaFile) return;

        const formData = new FormData();
        formData.append('_token', csrfToken);
        if (body) formData.append('body', body);
        if (replyToId) formData.append('reply_to_id', replyToId);
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

            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                alert(data.message || data.error || 'Failed to send message.');
                return;
            }

            input.value = '';
            if (mediaInput) mediaInput.value = '';
            clearReply();

            if (isCurrentlyTyping) {
                isCurrentlyTyping = false;
                clearTimeout(typingTimeout);
                queueTypingSignal(false);
            }

            renderMessage(data.message);
            lastMessageId = Math.max(lastMessageId, data.message.id);
            scrollToBottom();
        } catch (e) {
            if (e.name === 'AbortError') {
                alert('Request timed out. Please refresh and try again.');
            } else {
                alert('Network error. Please try again.');
            }
        } finally {
            sending = false;
            sendBtn.disabled = false;
        }
    }

    function scrollToBottom() {
        container.scrollTop = container.scrollHeight;
    }

    container.addEventListener('scroll', () => {
        isNearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 80;
    });

    const initialMessages = @json($initialMessages);
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
                        user_name: data.user_name ?? data.user?.name,
                        time: formatTime(data.created_at),
                        status: 'delivered',
                        reply_to: data.reply_to || null,
                        is_edited: false,
                        deleted_for_everyone: false,
                        can_edit: false,
                        can_delete_everyone: false,
                    });
                    typingBubble.classList.add('hidden');
                    clearTimeout(receiveTypingTimeout);
                    if (isNearBottom) scrollToBottom();
                    fetchNewMessages();
                })
                .listen('.message.updated', (data) => {
                    applyMessageUpdate({
                        id: data.id,
                        body: data.body,
                        deleted_for_everyone: data.deleted_for_everyone,
                        is_edited: data.is_edited,
                        media_url: data.media_url,
                        media_type: data.media_type,
                        user_id: data.user_id,
                        time: messageCache[data.id]?.time || '',
                    });
                })
                .listen('.user.typing', (e) => {
                    if (e.user_id !== authUserId) {
                        if (e.typing) {
                            typingBubble.classList.remove('hidden');
                            if (isNearBottom) scrollToBottom();
                            clearTimeout(receiveTypingTimeout);
                            receiveTypingTimeout = setTimeout(() => typingBubble.classList.add('hidden'), 5000);
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
        if (editingMessageId) return;
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
        } else if (isCurrentlyTyping) {
            isCurrentlyTyping = false;
            clearTimeout(typingTimeout);
            queueTypingSignal(false);
        }
    });

    mediaInput?.addEventListener('change', async function () {
        if (this.files?.length) await sendMessage();
    });

    // Group add members
    const membersModal = document.getElementById('add-members-modal');
    document.getElementById('add-members-btn')?.addEventListener('click', () => {
        membersModal?.classList.remove('hidden');
    });
    membersModal?.querySelectorAll('[data-close-members]').forEach((el) => {
        el.addEventListener('click', () => membersModal.classList.add('hidden'));
    });
})();
</script>
@endsection
