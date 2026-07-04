// Tone Generator to synthesize ringtones client-side
class ToneGenerator {
    constructor() {
        this.ctx = null;
        this.osc1 = null;
        this.osc2 = null;
        this.gainNode = null;
        this.timer = null;
        this._unlocked = false;
        this._unlockBound = false;
    }

    bindUnlock() {
        if (this._unlockBound) return;
        this._unlockBound = true;

        const unlock = async () => {
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) return;
                if (!this._silentCtx) {
                    this._silentCtx = new AudioCtx();
                }
                if (this._silentCtx.state === 'suspended') {
                    await this._silentCtx.resume();
                }
                const buffer = this._silentCtx.createBuffer(1, 1, 22050);
                const source = this._silentCtx.createBufferSource();
                source.buffer = buffer;
                source.connect(this._silentCtx.destination);
                source.start(0);
                this._unlocked = true;
            } catch (e) {}
        };

        ['pointerdown', 'touchstart', 'keydown', 'click'].forEach((eventName) => {
            document.addEventListener(eventName, unlock, { passive: true });
        });
    }

    async ensureContext() {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) throw new Error('AudioContext not supported');

        if (!this.ctx || this.ctx.state === 'closed') {
            this.ctx = this._silentCtx && this._silentCtx.state !== 'closed'
                ? this._silentCtx
                : new AudioCtx();
            this._silentCtx = this.ctx;
        }

        if (this.ctx.state === 'suspended') {
            await this.ctx.resume();
        }

        return this.ctx;
    }

    startRing() {
        this.stop(false);
        this._startTone({
            f1: 440,
            f2: 480,
            peak: 0.18,
            onMs: 2.0,
            offMs: 2.1,
            intervalMs: 4000,
        }).catch((e) => console.error('Failed to generate ring tone', e));
    }

    startDial() {
        this.stop(false);
        this._startTone({
            f1: 350,
            f2: 440,
            peak: 0.12,
            onMs: 1.2,
            offMs: 1.3,
            intervalMs: 3000,
        }).catch((e) => console.error('Failed to generate dial tone', e));
    }

    async _startTone({ f1, f2, peak, onMs, offMs, intervalMs }) {
        try {
            const ctx = await this.ensureContext();
            this.gainNode = ctx.createGain();
            this.gainNode.gain.setValueAtTime(0, ctx.currentTime);
            this.gainNode.connect(ctx.destination);

            this.osc1 = ctx.createOscillator();
            this.osc1.type = 'sine';
            this.osc1.frequency.value = f1;
            this.osc1.connect(this.gainNode);

            this.osc2 = ctx.createOscillator();
            this.osc2.type = 'sine';
            this.osc2.frequency.value = f2;
            this.osc2.connect(this.gainNode);

            this.osc1.start(0);
            this.osc2.start(0);

            const play = () => {
                if (!this.ctx || !this.gainNode) return;
                if (this.ctx.state === 'suspended') {
                    this.ctx.resume().catch(() => {});
                }
                const now = this.ctx.currentTime;
                this.gainNode.gain.cancelScheduledValues(now);
                this.gainNode.gain.setValueAtTime(0, now);
                this.gainNode.gain.linearRampToValueAtTime(peak, now + 0.1);
                this.gainNode.gain.setValueAtTime(peak, now + onMs);
                this.gainNode.gain.linearRampToValueAtTime(0, now + offMs);
            };

            play();
            this.timer = setInterval(play, intervalMs);
        } catch (e) {
            console.error('Failed to generate tone', e);
        }
    }

    stop(closeContext = true) {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
        try {
            if (this.osc1) this.osc1.stop();
            if (this.osc2) this.osc2.stop();
        } catch (e) {}
        this.osc1 = null;
        this.osc2 = null;
        this.gainNode = null;

        if (closeContext && this.ctx && this.ctx !== this._silentCtx) {
            try {
                this.ctx.close();
            } catch (e) {}
            this.ctx = null;
        }
    }
}

class WebRTCCallManager {
    constructor() {
        this.peerConnection = null;
        this.localStream = null;
        this.remoteStream = null;
        this.remoteUserId = null;
        this.isVideo = false;
        this.toneGen = new ToneGenerator();
        this.iceCandidatesQueue = [];
        this.isIncoming = false;
        this.isCalling = false;
        this.isCallActive = false;
        this.isMinimized = false;
        this.incomingOfferSdp = null;
        this.callId = null;
        this.wasAnswered = false;
        this.callerUserId = null;
        this.ringTimeout = null;
        this.dialTimeout = null;
        this._endingCall = false;
        this._disconnectTimer = null;
        this.targetOffline = false;
        this.facingMode = 'user';
        this.videosSwapped = false;
        this.localMuted = false;
        this.localVideoOff = false;
        this.remoteMuted = false;
        this.remoteVideoOff = false;
        this.peerName = '';
        this.peerAvatar = '';
        this.pipCorner = 'br';
        this._vibrateTimer = null;
        this._flippingCamera = false;
        this.audioOutputId = '';
        this.audioRoute = 'phone';
        this.audioOutputs = [];
        this._deviceChangeHandler = null;
        this._inboxAfter = 0;
        this._inboxTimer = null;
        this._seenSignalIds = new Set();
        this._echoRetryTimer = null;

        this.overlay = null;
        this.minimizedEl = null;
        this.avatar = null;
        this.avatarInitials = null;
        this.userName = null;
        this.status = null;
        this.videosContainer = null;
        this.audioPulse = null;
        this.mainVideo = null;
        this.pipVideo = null;
        this.remoteAudio = null;
        this.miniVideo = null;

        this.declineBtn = null;
        this.acceptBtn = null;
        this.muteBtn = null;
        this.videoBtn = null;
        this.speakerBtn = null;
        this.flipBtn = null;
        this.hangupBtn = null;
        this.minimizeBtn = null;
        this.pipWrap = null;
        this.speakerPicker = null;
        this.speakerPickerList = null;
        this.speakerPickerHint = null;
    }

    /** Re-query call DOM nodes (live can miss refs if init raced layout). */
    bindCallDom() {
        this.overlay = document.getElementById('call-overlay') || this.overlay;
        this.minimizedEl = document.getElementById('call-minimized');
        this.avatar = document.getElementById('call-user-avatar');
        this.avatarInitials = document.getElementById('call-user-initials');
        this.userName = document.getElementById('call-user-name');
        this.status = document.getElementById('call-status');
        this.videosContainer = document.getElementById('call-videos-container');
        this.audioPulse = document.getElementById('call-audio-pulse');
        this.mainVideo = document.getElementById('main-call-video');
        this.pipVideo = document.getElementById('pip-call-video');
        this.remoteAudio = document.getElementById('remote-audio');
        this.miniVideo = document.getElementById('mini-call-video');
        this.profileBlock = document.getElementById('call-profile-block');

        this.mainVideoOffOverlay = document.getElementById('main-video-off-overlay');
        this.mainVideoOffAvatar = document.getElementById('main-video-off-avatar');
        this.mainMutedBadge = document.getElementById('main-muted-badge');
        this.pipVideoOffOverlay = document.getElementById('pip-video-off-overlay');
        this.pipMutedBadge = document.getElementById('pip-muted-badge');
        this.remoteAudioMutedBadge = document.getElementById('remote-audio-muted-badge');
        this.remoteAudioMutedLabel = document.getElementById('remote-audio-muted-label');

        this.miniAvatarWrap = document.getElementById('mini-call-avatar-wrap');
        this.miniAvatar = document.getElementById('mini-call-avatar');
        this.miniName = document.getElementById('mini-call-name');
        this.miniStatus = document.getElementById('mini-call-status');
        this.miniRemoteMuted = document.getElementById('mini-remote-muted');
        this.miniHangupBtn = document.getElementById('mini-hangup-btn');

        this.declineBtn = document.getElementById('decline-call-btn');
        this.acceptBtn = document.getElementById('accept-call-btn');
        this.muteBtn = document.getElementById('toggle-mute-btn');
        this.videoBtn = document.getElementById('toggle-video-btn');
        this.speakerBtn = document.getElementById('speaker-btn');
        this.flipBtn = document.getElementById('flip-camera-btn');
        this.hangupBtn = document.getElementById('hangup-call-btn');
        this.minimizeBtn = document.getElementById('minimize-call-btn');
        this.pipWrap = document.getElementById('pip-video-wrap');
        this.speakerPicker = document.getElementById('speaker-picker');
        this.speakerPickerList = document.getElementById('speaker-picker-list');
        this.speakerPickerHint = document.getElementById('speaker-picker-hint');
        this.speakerPickerBackdrop = document.getElementById('speaker-picker-backdrop');
        this.speakerPickerClose = document.getElementById('speaker-picker-close');

        if (this.avatar && !this.avatar.dataset.avatarFallbackBound) {
            this.avatar.dataset.avatarFallbackBound = '1';
            this.avatar.addEventListener('error', () => this.showAvatarInitials(true));
            this.avatar.addEventListener('load', () => {
                if (this.avatar?.currentSrc || this.avatar?.src) {
                    this.revealAvatarImage();
                }
            });
        }
    }

    peerInitials(name = '') {
        const parts = String(name || this.peerName || 'U').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) return 'U';
        if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    /** @param {boolean} clearSrc - true when image failed (404 on live) */
    showAvatarInitials(clearSrc = false) {
        if (this.avatarInitials) {
            this.avatarInitials.textContent = this.peerInitials();
            this.avatarInitials.classList.remove('hidden');
            this.avatarInitials.style.setProperty('display', 'flex', 'important');
        }
        if (this.avatar) {
            this.avatar.classList.add('hidden');
            this.avatar.style.setProperty('display', 'none', 'important');
            if (clearSrc) {
                this.avatar.removeAttribute('src');
            }
        }
    }

    /** Show photo only after it actually loads (avoids empty white ring on live 404). */
    revealAvatarImage() {
        if (!this.avatar?.src) {
            this.showAvatarInitials(true);
            return;
        }
        this.avatar.classList.remove('hidden');
        this.avatar.style.setProperty('display', 'block', 'important');
        if (this.avatarInitials) {
            this.avatarInitials.classList.add('hidden');
            this.avatarInitials.style.setProperty('display', 'none', 'important');
        }
    }

    init() {
        if (this._uiBound) return;

        this.overlay = document.getElementById('call-overlay');
        if (!this.overlay) {
            console.error('Call UI missing (#call-overlay). Incoming calls cannot be shown.');
            return;
        }

        // Escape any parent stacking context so call UI works on Home/Feed/Chat.
        if (this.overlay.parentElement !== document.body) {
            document.body.appendChild(this.overlay);
        }
        const mini = document.getElementById('call-minimized');
        if (mini && mini.parentElement !== document.body) {
            document.body.appendChild(mini);
        }

        this._uiBound = true;
        this.toneGen.bindUnlock();

        this.bindCallDom();

        this.declineBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.declineCall();
        });
        this.acceptBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.acceptCall();
        });
        this.muteBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggleMute();
        });
        this.videoBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggleVideo();
        });
        this.flipBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.flipCamera();
        });
        this.speakerBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.openSpeakerPicker();
        });
        this.speakerPickerBackdrop?.addEventListener('click', (e) => {
            e.preventDefault();
            this.closeSpeakerPicker();
        });
        this.speakerPickerClose?.addEventListener('click', (e) => {
            e.preventDefault();
            this.closeSpeakerPicker();
        });
        this.hangupBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (this.isIncoming && !this.isCallActive) {
                this.declineCall();
            } else {
                this.hangupCall();
            }
        });
        this.minimizeBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.minimizeCall();
        });
        this.minimizedEl?.addEventListener('click', (e) => {
            if (e.target.closest('#mini-hangup-btn')) return;
            this.expandCall();
        });
        this.miniHangupBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.hangupCall();
        });

        this.bindPipDrag();
        this.bindChatCallButtons();
        this.registerSignalingListener();
        this.startInboxPolling();
    }

    /** Snap PiP preview to one of 4 corners (tl/tr/bl/br). Drag to move, tap to swap. */
    setPipCorner(corner = 'br') {
        const allowed = ['tl', 'tr', 'bl', 'br'];
        const next = allowed.includes(corner) ? corner : 'br';
        this.pipCorner = next;
        const el = this.pipWrap || document.getElementById('pip-video-wrap');
        if (!el) return;
        el.classList.remove('pip-tl', 'pip-tr', 'pip-bl', 'pip-br');
        el.classList.add(`pip-${next}`);
        try {
            localStorage.setItem('newbook_pip_corner', next);
        } catch (e) {}
    }

    bindPipDrag() {
        const el = this.pipWrap || document.getElementById('pip-video-wrap');
        if (!el || el.dataset.pipDragBound === '1') return;
        el.dataset.pipDragBound = '1';
        this.pipWrap = el;

        let saved = 'br';
        try {
            saved = localStorage.getItem('newbook_pip_corner') || 'br';
        } catch (e) {}
        this.setPipCorner(saved);

        let startX = 0;
        let startY = 0;
        let dragging = false;
        let moved = false;
        const threshold = 10;

        const pointFrom = (e) => {
            if (e.touches && e.touches[0]) return e.touches[0];
            if (e.changedTouches && e.changedTouches[0]) return e.changedTouches[0];
            return e;
        };

        const onStart = (e) => {
            const p = pointFrom(e);
            startX = p.clientX;
            startY = p.clientY;
            dragging = true;
            moved = false;
        };

        const onMove = (e) => {
            if (!dragging) return;
            const p = pointFrom(e);
            if (Math.abs(p.clientX - startX) > threshold || Math.abs(p.clientY - startY) > threshold) {
                moved = true;
                if (e.cancelable) e.preventDefault();
            }
        };

        const onEnd = (e) => {
            if (!dragging) return;
            dragging = false;
            const p = pointFrom(e);

            if (moved) {
                if (e.cancelable) e.preventDefault();
                e.stopPropagation();
                const container = this.videosContainer || this.overlay || document.body;
                const rect = container.getBoundingClientRect();
                const x = p.clientX - rect.left;
                const y = p.clientY - rect.top;
                const corner = `${y < rect.height / 2 ? 't' : 'b'}${x < rect.width / 2 ? 'l' : 'r'}`;
                this.setPipCorner(corner);
                return;
            }

            // Tap — swap main/pip cameras
            this.swapVideos();
        };

        el.addEventListener('pointerdown', onStart);
        window.addEventListener('pointermove', onMove, { passive: false });
        window.addEventListener('pointerup', onEnd);
        window.addEventListener('pointercancel', () => { dragging = false; });

        el.addEventListener('touchstart', onStart, { passive: true });
        el.addEventListener('touchmove', onMove, { passive: false });
        el.addEventListener('touchend', onEnd);
        el.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.swapVideos();
            }
        });
    }

    isEchoConnected() {
        const state = window.Echo?.connector?.pusher?.connection?.state;
        return state === 'connected';
    }

    async waitForEcho(maxMs = 10000) {
        if (this.isEchoConnected()) return true;

        const start = Date.now();
        while (Date.now() - start < maxMs) {
            await new Promise((resolve) => setTimeout(resolve, 400));
            if (this.isEchoConnected()) return true;
        }

        return false;
    }

    async ensureCallReady() {
        if (!window.authUserId) {
            alert('Please log in to make calls.');
            return false;
        }

        // Inbox polling works without Echo/Reverb — do not block calls.
        try {
            const res = await fetch('/chat/call/health', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json().catch(() => ({}));

            if (!res.ok || !data.ok) {
                // Still allow call attempt — inbox may work even if health is flaky.
                console.warn('Call health check failed', data);
            }
        } catch (e) {
            console.warn('Call health check network error', e);
        }

        await this.waitForEcho(2500);
        this.registerSignalingListener();
        this.startInboxPolling();

        return true;
    }

    async fetchPresence(userId) {
        try {
            const res = await fetch(`/chat/users/${userId}/presence`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) return null;
            return await res.json();
        } catch (e) {
            return null;
        }
    }

    registerSignalingListener() {
        if (this._signalingBound) return;

        if (!window.Echo || typeof window.Echo.private !== 'function') {
            this.scheduleEchoRetry();
            return;
        }

        try {
            window.Echo.private(`user-signaling.${window.authUserId}`)
                .listen('.call.signal', (payload) => {
                    this.ingestSignal(payload);
                });
            this._signalingBound = true;

            const pusher = window.Echo?.connector?.pusher;
            pusher?.connection?.bind?.('connected', () => {
                this._signalingBound = true;
            });
            pusher?.connection?.bind?.('disconnected', () => {
                this._signalingBound = false;
                this.scheduleEchoRetry();
            });
        } catch (e) {
            console.warn('WebRTC signaling listener could not be registered.', e);
            this.scheduleEchoRetry();
        }
    }

    scheduleEchoRetry() {
        if (this._echoRetryTimer) return;
        this._echoRetryTimer = setTimeout(() => {
            this._echoRetryTimer = null;
            if (!this._signalingBound) {
                this.registerSignalingListener();
            }
        }, 3000);
    }

    startInboxPolling() {
        if (this._inboxTimer) return;
        this.pullInbox();
        this._inboxTimer = setInterval(() => this.pullInbox(), this.inboxPollMs());
        // Also poll when tab becomes visible again (mobile background)
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                this.pullInbox();
            }
        });
    }

    inboxPollMs() {
        // Faster while ringing / connecting so answer + ICE arrive quickly.
        if (this.isCalling || this.isIncoming || (this.isCallActive && this.peerConnection?.connectionState !== 'connected')) {
            return 400;
        }
        return 1000;
    }

    bumpInboxPolling() {
        if (!this._inboxTimer) {
            this.startInboxPolling();
            return;
        }
        clearInterval(this._inboxTimer);
        this._inboxTimer = setInterval(() => this.pullInbox(), this.inboxPollMs());
        this.pullInbox();
    }

    signalData(extra = {}) {
        return {
            call_id: this.callId,
            caller_id: this.callerUserId,
            isVideo: this.isVideo,
            is_video: this.isVideo,
            ...extra,
        };
    }

    async pullInbox() {
        if (!window.authUserId) return;
        if (this._pullingInbox) return;
        this._pullingInbox = true;

        try {
            const url = `/chat/call/inbox${this._inboxAfter ? `?after=${encodeURIComponent(this._inboxAfter)}` : ''}`;
            const res = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!res.ok) {
                console.warn('Call inbox poll failed', res.status);
                return;
            }

            const data = await res.json();
            const signals = Array.isArray(data.signals) ? data.signals : [];

            for (const signal of signals) {
                if (signal._at && signal._at > this._inboxAfter) {
                    this._inboxAfter = signal._at;
                }
                try {
                    this.ingestSignal(signal);
                } catch (err) {
                    console.error('Failed to ingest call signal', err, signal);
                }
            }
        } catch (e) {
            console.warn('Call inbox network error', e);
        } finally {
            this._pullingInbox = false;
        }
    }

    sameUserId(a, b) {
        if (a == null || b == null || a === '' || b === '') return false;
        return Number(a) === Number(b);
    }

    sameCallId(data = {}) {
        const incoming = data?.call_id;
        if (!incoming || !this.callId) return true;
        return String(incoming) === String(this.callId);
    }

    markSignalSeen(ids = []) {
        ids.filter(Boolean).forEach((id) => this._seenSignalIds.add(id));
        if (this._seenSignalIds.size > 300) {
            const keep = [...this._seenSignalIds].slice(-120);
            this._seenSignalIds = new Set(keep);
        }
    }

    signalKeys(payload) {
        const callId = payload.data?.call_id || '';
        const fromId = payload.from_user?.id ?? '';
        const keys = [];

        if (payload._id) keys.push(payload._id);

        if (payload.type === 'offer' || payload.type === 'answer' || payload.type === 'hangup' || payload.type === 'decline') {
            if (callId) keys.push(`${payload.type}:${callId}:${fromId}`);
        } else if (payload.type === 'candidate') {
            keys.push(`candidate:${callId}:${fromId}:${payload.data?.candidate || ''}`);
        } else if (payload.type === 'media_state') {
            keys.push(`media:${callId}:${fromId}:${payload.data?.muted}:${payload.data?.video_off}:${payload._at || ''}`);
        } else if (callId) {
            keys.push(`${payload.type}:${callId}:${fromId}`);
        }

        return keys;
    }

    ingestSignal(payload) {
        if (!payload || !payload.type) return;

        const keys = this.signalKeys(payload);
        if (keys.some((key) => this._seenSignalIds.has(key))) return;

        const fromId = payload.from_user?.id;

        // Own hangup/decline already handled locally — ignore inbox echo when idle.
        if (['hangup', 'decline'].includes(payload.type) && this.sameUserId(fromId, window.authUserId)) {
            if (!(this.isCallActive || this.isCalling || this.isIncoming)) {
                this.markSignalSeen(keys);
                return;
            }
        }

        // Mark after we accept the signal for handling (failed answer can retry below).
        this.markSignalSeen(keys);
        this.handleIncomingSignal(payload);
    }

    /** Called from notifications when an incoming_call alert arrives. */
    checkPendingCall() {
        this.pullInbox();
    }

    bindChatCallButtons() {
        const wire = (buttonId, isVideo) => {
            const btn = document.getElementById(buttonId);
            if (!btn || btn.dataset.callBound === '1') return;
            btn.dataset.callBound = '1';

            btn.addEventListener('click', async () => {
                const userId = parseInt(btn.dataset.targetUserId, 10);
                if (!userId) return;

                const presence = await this.fetchPresence(userId);
                const targetOnline = presence?.online ?? btn.dataset.targetOnline === '1';

                await this.startCall(userId, isVideo, {
                    name: btn.dataset.targetName || '',
                    avatar: btn.dataset.targetAvatar || '',
                }, { targetOnline });
            });
        };

        wire('audio-call-btn', false);
        wire('video-call-btn', true);
    }

    getIceServers() {
        if (Array.isArray(window.webrtcIceServers) && window.webrtcIceServers.length) {
            return window.webrtcIceServers;
        }

        return [
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' },
        ];
    }

    normalizeSdp(sdp) {
        if (!sdp || typeof sdp !== 'string') return sdp;

        let text = sdp;
        if (text.includes('\\n')) {
            text = text.replace(/\\n/g, '\n');
        }

        text = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        const lines = text.split('\n').map((line) => line.trimEnd()).filter((line) => line.length > 0);

        return `${lines.join('\r\n')}\r\n`;
    }

    prepareRemoteSdp(sdp) {
        // Normalize line endings only — do not strip media lines (breaks remote video).
        return this.normalizeSdp(sdp);
    }

    async setRemoteSdp(type, sdp) {
        const cleaned = this.prepareRemoteSdp(sdp);
        try {
            await this.peerConnection.setRemoteDescription(
                new RTCSessionDescription({ type, sdp: cleaned }),
            );
        } catch (e) {
            // Rare Plan-B legacy SDP: retry without old ssrc/msid lines.
            const fallback = this.normalizeSdp(sdp)
                .split(/\r?\n/)
                .filter((line) => {
                    const trimmed = line.trim();
                    if (!trimmed) return false;
                    if (trimmed.startsWith('a=ssrc:') && trimmed.includes(' msid:')) return false;
                    if (trimmed.startsWith('a=ssrc-group:')) return false;
                    return true;
                })
                .join('\r\n') + '\r\n';
            await this.peerConnection.setRemoteDescription(
                new RTCSessionDescription({ type, sdp: fallback }),
            );
        }
    }

    callMeta(extra = {}) {
        return {
            call_id: this.callId,
            caller_id: this.callerUserId,
            is_video: this.isVideo,
            was_answered: this.wasAnswered,
            ...extra,
        };
    }

    clearCallTimeouts() {
        if (this.ringTimeout) {
            clearTimeout(this.ringTimeout);
            this.ringTimeout = null;
        }
        if (this.dialTimeout) {
            clearTimeout(this.dialTimeout);
            this.dialTimeout = null;
        }
    }

    startRingTimeout() {
        this.clearCallTimeouts();
        this.ringTimeout = setTimeout(() => {
            if (this.isIncoming && !this.wasAnswered) {
                this.declineCall('missed');
            }
        }, 45000);
    }

    startDialTimeout(ms = 60000) {
        this.clearCallTimeouts();
        this.dialTimeout = setTimeout(() => {
            if (this.isCalling && !this.wasAnswered) {
                if (this.targetOffline) {
                    this.status.textContent = 'User offline';
                    setTimeout(() => this.hangupCall(), 1500);
                    return;
                }
                this.hangupCall();
            }
        }, ms);
    }

    startVibrate() {
        this.stopVibrate();
        if (!navigator.vibrate) return;

        const pulse = () => {
            try {
                navigator.vibrate([400, 200, 400, 600]);
            } catch (e) {}
        };

        pulse();
        this._vibrateTimer = setInterval(pulse, 1600);
    }

    stopVibrate() {
        if (this._vibrateTimer) {
            clearInterval(this._vibrateTimer);
            this._vibrateTimer = null;
        }
        try {
            navigator.vibrate?.(0);
        } catch (e) {}
    }

    startIncomingAlert() {
        this.toneGen.startRing();
        this.startVibrate();
    }

    stopIncomingAlert() {
        this.toneGen.stop(false);
        this.stopVibrate();
    }

    async sendSignal(type, data = null, remoteUserId = null) {
        const targetId = remoteUserId ?? this.remoteUserId;
        if (!targetId) return { ok: false, message: 'No call recipient.' };

        try {
            const res = await fetch('/chat/call/signal', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    to_user_id: targetId,
                    type,
                    data: ['hangup', 'decline'].includes(type)
                        ? this.callMeta(data || {})
                        : data,
                }),
            });

            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                const message = err.message || `Call signal failed (${res.status})`;
                console.error('Call signal failed:', message);
                return { ok: false, message };
            }

            return { ok: true };
        } catch (e) {
            console.error('Failed to send call signal:', e);
            return { ok: false, message: 'Network error while starting call.' };
        }
    }

    sendMediaState() {
        if (!(this.isCallActive || this.isCalling) || !this.remoteUserId) return;

        this.sendSignal('media_state', this.signalData({
            muted: this.localMuted,
            video_off: this.localVideoOff,
        }));
    }

    applyRemoteMediaState(data = {}) {
        this.remoteMuted = !!data.muted;
        if (typeof data.video_off === 'boolean') {
            // Trust peer signal — do not fight it with track.readyState (causes camera blink).
            this.remoteVideoOff = data.video_off;
        }
        this.updateMediaIndicators();
        this.updateMinimizedUI();
    }

    mediaErrorMessage(error) {
        if (!error) return 'Could not access microphone/camera.';
        if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {
            return 'Microphone/camera blocked. Click the lock icon in the address bar and allow access, then try again.';
        }
        if (error.name === 'NotFoundError' || error.name === 'DevicesNotFoundError') {
            return 'No microphone or camera found. Connect a device and try again.';
        }
        if (error.name === 'NotReadableError') {
            return 'Microphone/camera is in use by another app. Close it and try again.';
        }
        return error.message || 'Could not access microphone/camera.';
    }

    async getLocalMedia(isVideo) {
        if (!navigator.mediaDevices?.getUserMedia) {
            throw new Error('This browser does not support calls.');
        }

        if (!isVideo) {
            return navigator.mediaDevices.getUserMedia({ audio: true, video: false });
        }

        // Video call: always require a camera track (no silent audio-only fallback).
        const attempts = [
            { audio: true, video: { facingMode: { ideal: this.facingMode || 'user' }, width: { ideal: 1280 }, height: { ideal: 720 } } },
            { audio: true, video: { facingMode: this.facingMode || 'user' } },
            { audio: true, video: true },
        ];

        let lastError = null;
        for (const constraints of attempts) {
            try {
                const stream = await navigator.mediaDevices.getUserMedia(constraints);
                if (!stream.getVideoTracks().length) {
                    stream.getTracks().forEach((t) => t.stop());
                    throw new Error('Camera track missing');
                }
                // Ensure camera is live/enabled so the other side receives frames.
                stream.getVideoTracks().forEach((t) => {
                    t.enabled = true;
                });
                return stream;
            } catch (error) {
                lastError = error;
            }
        }

        throw lastError || new Error('Could not access camera.');
    }

    setPeerInfo(peerInfo = {}) {
        if (peerInfo.name) {
            this.peerName = peerInfo.name;
            if (this.userName) this.userName.textContent = peerInfo.name;
            if (this.miniName) this.miniName.textContent = peerInfo.name;
        }

        if (this.avatarInitials) {
            this.avatarInitials.textContent = this.peerInitials(peerInfo.name || this.peerName);
        }

        const avatarUrl = (peerInfo.avatar || '').trim();
        // Skip broken/empty paths — live often 404s /media/avatars/* so show initials like local.
        const usableAvatar = avatarUrl
            && !avatarUrl.includes('undefined')
            && avatarUrl !== 'null';

        if (usableAvatar) {
            this.peerAvatar = avatarUrl;
            // Initials first; swap to photo only on successful load.
            this.showAvatarInitials(false);
            if (this.avatar) {
                this.avatar.src = avatarUrl;
            }
            if (this.miniAvatar) this.miniAvatar.src = avatarUrl;
            if (this.mainVideoOffAvatar) this.mainVideoOffAvatar.src = avatarUrl;
        } else {
            this.peerAvatar = '';
            this.showAvatarInitials(true);
            if (this.miniAvatar) this.miniAvatar.removeAttribute('src');
            if (this.mainVideoOffAvatar) this.mainVideoOffAvatar.removeAttribute('src');
        }
    }

    playVideoEl(el) {
        if (!el) return;
        el.setAttribute('playsinline', '');
        el.setAttribute('autoplay', '');
        el.playsInline = true;
        el.autoplay = true;
        const run = () => el.play().catch(() => {});
        run();
        // Retry — mobile browsers often block the first play() until layout is ready.
        setTimeout(run, 100);
        setTimeout(run, 400);
    }

    bindStreamToVideo(el, stream, { muted = true } = {}) {
        if (!el) return;
        // Same stream already bound — skip rebind (prevents camera on/off blink).
        if (el.srcObject === stream && el.muted === muted && stream) {
            return;
        }
        if (el.srcObject !== stream) {
            el.srcObject = null;
            el.srcObject = stream || null;
        }
        el.muted = muted;
        if (stream) this.playVideoEl(el);
    }

    hasLiveVideoTrack(stream) {
        return !!stream?.getVideoTracks?.().some(
            (t) => t && t.readyState === 'live' && t.enabled,
        );
    }

    showVideoStage() {
        if (!this.isVideo) return;
        if (this.videosContainer) {
            this.videosContainer.classList.remove('hidden');
            Object.assign(this.videosContainer.style, {
                display: 'flex',
                visibility: 'visible',
                opacity: '1',
                position: 'relative',
                zIndex: '10',
                width: '100%',
                maxWidth: '42rem',
                flex: '1 1 auto',
                minHeight: '220px',
            });
        }
        if (this.mainVideo) {
            Object.assign(this.mainVideo.style, {
                display: 'block',
                width: '100%',
                height: '100%',
                objectFit: 'cover',
                background: '#0f172a',
            });
        }
        this.profileBlock?.classList.add('hidden');
        this.audioPulse?.classList.add('hidden');
    }

    attachStreamsToVideos() {
        if (!this.isVideo) return;

        this.showVideoStage();

        const local = this.localStream;
        const remote = this.remoteStream;
        const mainShowsRemote = !this.videosSwapped;

        // Keep video elements muted — remote audio plays via #remote-audio.
        this.bindStreamToVideo(this.mainVideo, mainShowsRemote ? remote : local, { muted: true });
        this.bindStreamToVideo(this.pipVideo, mainShowsRemote ? local : remote, { muted: true });
        this.bindStreamToVideo(this.miniVideo, remote || local, { muted: true });

        this.updateMediaIndicators();
        this.updateMinimizedUI();
    }

    attachRemoteStream(stream, { force = false } = {}) {
        if (!stream) return;

        const prevIds = (this.remoteStream?.getTracks() || []).map((t) => t.id).sort().join(',');
        const nextIds = stream.getTracks().map((t) => t.id).sort().join(',');
        const sameStream = this.remoteStream === stream && prevIds === nextIds;

        this.remoteStream = stream;

        if (!sameStream || force) {
            stream.getTracks().forEach((track) => {
                if (track._callHandlersBound) return;
                track._callHandlersBound = true;
                track.onunmute = () => {
                    // First frames arrived — bind once, do not clear remoteVideoOff from media_state.
                    this.attachStreamsToVideos();
                };
                track.onmute = () => this.updateMediaIndicators();
                track.onended = () => this.syncRemoteTracksFromPeer();
            });
        }

        if (this.remoteAudio) {
            this.bindStreamToVideo(this.remoteAudio, stream, { muted: false });
            this.ensureRemoteAudioPlaying();
        }

        if (this.isVideo) {
            this.attachStreamsToVideos();
        } else {
            this.updateMediaIndicators();
            this.updateMinimizedUI();
        }
    }

    supportsSinkId() {
        return typeof HTMLMediaElement !== 'undefined'
            && typeof HTMLMediaElement.prototype.setSinkId === 'function';
    }

    categorizeOutputDevice(device) {
        const label = (device.label || '').toLowerCase();
        const id = (device.deviceId || '').toLowerCase();

        if (
            label.includes('bluetooth')
            || label.includes('airpods')
            || label.includes('buds')
            || label.includes('headset')
            || label.includes('hands-free')
            || label.includes('handsfree')
            || label.includes('a2dp')
            || label.includes('hfp')
        ) {
            return 'bluetooth';
        }

        if (
            label.includes('speaker')
            || label.includes('loudspeaker')
            || label.includes('loud speaker')
            || label.includes('external')
        ) {
            return 'speaker';
        }

        if (
            label.includes('earpiece')
            || label.includes('receiver')
            || label.includes('handset')
            || label.includes('phone')
            || id === 'communications'
        ) {
            return 'phone';
        }

        if (id === 'default' || label.includes('default')) {
            return 'phone';
        }

        return 'other';
    }

    routeMeta(route) {
        const map = {
            phone: {
                title: 'Phone',
                subtitle: 'Earpiece / normal speaker',
                icon: 'phone',
            },
            speaker: {
                title: 'Speaker',
                subtitle: 'Hands-free / loud speaker',
                icon: 'speaker',
            },
            bluetooth: {
                title: 'Bluetooth',
                subtitle: 'Wireless headset or earbuds',
                icon: 'bluetooth',
            },
            other: {
                title: 'Other device',
                subtitle: 'Connected audio output',
                icon: 'other',
            },
        };
        return map[route] || map.other;
    }

    routeIconSvg(route) {
        if (route === 'phone') {
            return '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>';
        }
        if (route === 'bluetooth') {
            return '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7l10 10-5 5V2l5 5L7 17"/></svg>';
        }
        if (route === 'speaker') {
            return '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072M18.364 5.636a9 9 0 010 12.728M12 6v12l-4-4H4V10h4l4-4z"/></svg>';
        }
        return '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>';
    }

    async refreshAudioOutputs() {
        this.audioOutputs = [];

        if (!navigator.mediaDevices?.enumerateDevices) {
            return this.buildFallbackRoutes();
        }

        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            const outputs = devices.filter((d) => d.kind === 'audiooutput');

            if (!outputs.length || !this.supportsSinkId()) {
                return this.buildFallbackRoutes();
            }

            const routes = [];
            const seen = new Set();

            outputs.forEach((device) => {
                const route = this.categorizeOutputDevice(device);
                const meta = this.routeMeta(route);
                const label = device.label?.trim();
                const key = device.deviceId || `${route}-${label}`;
                if (seen.has(key)) return;
                seen.add(key);

                routes.push({
                    id: device.deviceId,
                    route,
                    title: label || meta.title,
                    subtitle: label ? meta.title : meta.subtitle,
                });
            });

            // Ensure Phone + Speaker always appear even if OS only exposes "default".
            const hasPhone = routes.some((r) => r.route === 'phone');
            const hasSpeaker = routes.some((r) => r.route === 'speaker');
            const defaultDevice = outputs.find((d) => d.deviceId === 'default') || outputs[0];

            if (!hasPhone) {
                routes.unshift({
                    id: defaultDevice?.deviceId || '',
                    route: 'phone',
                    title: 'Phone',
                    subtitle: 'Earpiece / normal speaker',
                });
            }

            if (!hasSpeaker) {
                const speakerDevice = outputs.find((d) => this.categorizeOutputDevice(d) === 'speaker')
                    || outputs.find((d) => d.deviceId !== 'communications')
                    || defaultDevice;
                routes.push({
                    id: speakerDevice?.deviceId || defaultDevice?.deviceId || '',
                    route: 'speaker',
                    title: 'Speaker',
                    subtitle: 'Hands-free / loud speaker',
                });
            }

            this.audioOutputs = routes;
            return routes;
        } catch (e) {
            console.warn('Could not list audio outputs:', e);
            return this.buildFallbackRoutes();
        }
    }

    buildFallbackRoutes() {
        this.audioOutputs = [
            {
                id: '',
                route: 'phone',
                title: 'Phone',
                subtitle: 'Earpiece / normal speaker',
            },
            {
                id: 'speaker',
                route: 'speaker',
                title: 'Speaker',
                subtitle: 'Hands-free / loud speaker',
            },
            {
                id: 'bluetooth',
                route: 'bluetooth',
                title: 'Bluetooth',
                subtitle: this.supportsSinkId()
                    ? 'Connect a Bluetooth device in system settings'
                    : 'Use system Bluetooth settings',
                disabled: true,
            },
        ];
        return this.audioOutputs;
    }

    async applyAudioOutput() {
        if (!this.remoteAudio) return;

        const route = this.audioRoute || 'phone';
        const canSink = this.supportsSinkId() && typeof this.remoteAudio.setSinkId === 'function';

        if (canSink && this.audioOutputId !== undefined && this.audioOutputId !== 'speaker' && this.audioOutputId !== 'bluetooth') {
            try {
                await this.remoteAudio.setSinkId(this.audioOutputId || '');
            } catch (e) {
                console.warn('setSinkId failed:', e);
            }
        }

        // Loud speaker fallback when OS does not expose separate devices.
        this.remoteAudio.volume = route === 'speaker' ? 1 : 0.92;

        this.updateSpeakerButtonUI();
    }

    updateSpeakerButtonUI() {
        const active = this.audioRoute === 'speaker' || this.audioRoute === 'bluetooth';
        if (this.speakerBtn) {
            this.speakerBtn.style.background = active ? '#4f46e5' : '#334155';
            this.speakerBtn.style.borderColor = active ? '#818cf8' : 'rgba(255,255,255,0.12)';
            const meta = this.routeMeta(this.audioRoute);
            this.speakerBtn.title = `Audio: ${meta.title}`;
        }
    }

    async openSpeakerPicker() {
        await this.refreshAudioOutputs();
        this.renderSpeakerPicker();
        this.speakerPicker?.classList.remove('hidden');
    }

    closeSpeakerPicker() {
        this.speakerPicker?.classList.add('hidden');
    }

    renderSpeakerPicker() {
        if (!this.speakerPickerList) return;

        const canSink = this.supportsSinkId();
        if (this.speakerPickerHint) {
            if (!canSink) {
                this.speakerPickerHint.textContent = 'This browser has limited output control. Speaker boosts volume; Bluetooth is chosen from system settings.';
                this.speakerPickerHint.classList.remove('hidden');
            } else {
                this.speakerPickerHint.classList.add('hidden');
            }
        }

        this.speakerPickerList.innerHTML = '';

        this.audioOutputs.forEach((item) => {
            const isSelected = this.audioRoute === item.route
                && (this.audioOutputId || '') === (item.id || '');

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.disabled = !!item.disabled;
            btn.className = [
                'w-full flex items-center gap-3 px-3 py-3 rounded-2xl text-left transition-colors',
                item.disabled ? 'opacity-40 cursor-not-allowed' : 'hover:bg-white/10',
                isSelected ? 'bg-indigo-500/20 border border-indigo-400/40' : 'border border-transparent',
            ].join(' ');

            btn.innerHTML = `
                <span class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center shrink-0">${this.routeIconSvg(item.route)}</span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-white truncate">${item.title}</span>
                    <span class="block text-[11px] text-white/50 truncate">${item.subtitle || ''}</span>
                </span>
                <span class="shrink-0 w-5 h-5 rounded-full border ${isSelected ? 'border-indigo-400 bg-indigo-500' : 'border-white/30'} flex items-center justify-center">
                    ${isSelected ? '<span class="w-2 h-2 rounded-full bg-white"></span>' : ''}
                </span>
            `;

            if (!item.disabled) {
                btn.addEventListener('click', async () => {
                    await this.selectAudioOutput(item);
                    this.closeSpeakerPicker();
                });
            }

            this.speakerPickerList.appendChild(btn);
        });
    }

    async selectAudioOutput(item) {
        this.audioRoute = item.route;
        this.audioOutputId = item.id || '';
        await this.applyAudioOutput();
    }

    bindAudioDeviceWatcher() {
        if (this._deviceChangeHandler || !navigator.mediaDevices?.addEventListener) return;

        this._deviceChangeHandler = async () => {
            if (!(this.isCallActive || this.isCalling)) return;
            await this.refreshAudioOutputs();

            // If selected Bluetooth device disconnects, fall back to phone.
            const stillThere = this.audioOutputs.some((o) => o.id === this.audioOutputId && !o.disabled);
            if (this.audioOutputId && !stillThere && this.supportsSinkId()) {
                const phone = this.audioOutputs.find((o) => o.route === 'phone') || this.audioOutputs[0];
                if (phone) {
                    await this.selectAudioOutput(phone);
                }
            }

            if (this.speakerPicker && !this.speakerPicker.classList.contains('hidden')) {
                this.renderSpeakerPicker();
            }
        };

        navigator.mediaDevices.addEventListener('devicechange', this._deviceChangeHandler);
    }

    unbindAudioDeviceWatcher() {
        if (this._deviceChangeHandler && navigator.mediaDevices?.removeEventListener) {
            navigator.mediaDevices.removeEventListener('devicechange', this._deviceChangeHandler);
        }
        this._deviceChangeHandler = null;
    }

    showLocalVideoPreview() {
        if (!this.isVideo || !this.localStream) return;
        this.showVideoStage();
        this.attachStreamsToVideos();
    }

    setBtnVisible(btn, visible) {
        if (!btn) return;
        // Visibility is driven by #call-overlay[data-call-phase] CSS on live.
        // Clear inline display / Tailwind .hidden so they cannot hide buttons.
        btn.classList.remove('hidden');
        btn.removeAttribute('hidden');
        btn.style.removeProperty('display');
        btn.style.removeProperty('visibility');
        btn.style.removeProperty('opacity');
        btn.classList.toggle('call-btn-visible', !!visible);
    }

    /** phase: 'incoming' | 'outgoing' | 'active' | 'none' */
    setCallPhase(phase) {
        this.bindCallDom();

        const overlay = this.overlay || document.getElementById('call-overlay');
        if (overlay) {
            overlay.setAttribute('data-call-phase', phase || 'none');
            const videoOn = phase === 'active'
                && this.isVideo
                && !!this.localStream?.getVideoTracks?.().length;
            overlay.setAttribute('data-call-video', videoOn ? '1' : '0');
        }

        const incoming = phase === 'incoming';
        const outgoing = phase === 'outgoing';
        const active = phase === 'active';
        const showHangup = outgoing || active;
        const showMedia = active;
        const showVideoBtns = showMedia && this.isVideo && !!this.localStream?.getVideoTracks?.().length;

        // Keep class flags in sync (CSS data-call-phase is what actually shows buttons).
        this.setBtnVisible(this.acceptBtn, incoming);
        this.setBtnVisible(this.declineBtn, incoming);
        this.setBtnVisible(this.hangupBtn, showHangup);
        this.setBtnVisible(this.muteBtn, showMedia);
        this.setBtnVisible(this.speakerBtn, showMedia);
        this.setBtnVisible(this.videoBtn, showVideoBtns);
        this.setBtnVisible(this.flipBtn, showVideoBtns);
        if (this.minimizeBtn) {
            this.setBtnVisible(this.minimizeBtn, showHangup);
            this.minimizeBtn.classList.toggle('hidden', !showHangup);
        }

        // Ringing / incoming: always show profile (avatar or initials), hide video stage.
        if (incoming || outgoing) {
            this.profileBlock?.classList.remove('hidden');
            if (this.profileBlock) this.profileBlock.style.display = '';
            this.videosContainer?.classList.add('hidden');
            if (this.videosContainer) this.videosContainer.style.display = 'none';
            this.audioPulse?.classList.add('hidden');
        }

        if (showMedia) {
            this.updateLocalControlStyles();
            this.updateSpeakerButtonUI();
        }
    }

    showMediaControls() {
        this.setCallPhase(this.isIncoming && !this.isCallActive ? 'incoming' : (this.isCalling && !this.isCallActive ? 'outgoing' : 'active'));
    }

    updateLocalControlStyles() {
        this.setCtrlActive(this.muteBtn, this.localMuted);
        if (this.muteBtn) {
            this.muteBtn.title = this.localMuted ? 'Unmute' : 'Mute Audio';
            this.muteBtn.setAttribute('aria-pressed', this.localMuted ? 'true' : 'false');
        }

        this.setCtrlActive(this.videoBtn, this.localVideoOff);
        if (this.videoBtn) {
            this.videoBtn.title = this.localVideoOff ? 'Turn Camera On' : 'Turn Camera Off';
            this.videoBtn.setAttribute('aria-pressed', this.localVideoOff ? 'true' : 'false');
        }
    }

    setCtrlActive(btn, active) {
        if (!btn) return;
        btn.style.background = active ? '#e11d48' : '#334155';
        btn.style.borderColor = active ? '#fb7185' : 'rgba(255,255,255,0.12)';
        btn.classList.toggle('call-ctrl-off', active);

        const iconOn = btn.querySelector('[data-icon-on]');
        const iconOff = btn.querySelector('[data-icon-off]');
        if (iconOn && iconOff) {
            iconOn.style.display = active ? 'none' : 'block';
            iconOff.style.display = active ? 'block' : 'none';
            iconOn.classList.toggle('hidden', active);
            iconOff.classList.toggle('hidden', !active);
        }
    }

    updateMediaIndicators() {
        const mainShowsRemote = !this.videosSwapped;
        const mainMuted = mainShowsRemote ? this.remoteMuted : this.localMuted;
        const remoteCamOff = this.remoteVideoOff && !this.hasLiveVideoTrack(this.remoteStream);
        const localCamOff = this.localVideoOff && !this.hasLiveVideoTrack(this.localStream);
        const mainVideoOff = mainShowsRemote ? remoteCamOff : localCamOff;
        const pipMuted = mainShowsRemote ? this.localMuted : this.remoteMuted;
        const pipVideoOff = mainShowsRemote ? localCamOff : remoteCamOff;

        if (this.isVideo) {
            this.mainMutedBadge?.classList.toggle('hidden', !mainMuted);
            this.pipMutedBadge?.classList.toggle('hidden', !pipMuted);
            this.mainVideoOffOverlay?.classList.toggle('hidden', !mainVideoOff);
            if (this.mainVideoOffOverlay) {
                this.mainVideoOffOverlay.style.display = mainVideoOff ? 'flex' : 'none';
            }
            this.pipVideoOffOverlay?.classList.toggle('hidden', !pipVideoOff);
            if (this.pipVideoOffOverlay) {
                this.pipVideoOffOverlay.style.display = pipVideoOff ? 'flex' : 'none';
            }

            if (this.mainVideoOffAvatar) {
                this.mainVideoOffAvatar.src = mainShowsRemote ? (this.peerAvatar || '') : '';
                this.mainVideoOffAvatar.classList.toggle('hidden', !mainShowsRemote || !this.peerAvatar);
            }

            this.remoteAudioMutedBadge?.classList.add('hidden');
            this.remoteAudioMutedBadge?.classList.remove('inline-flex');
        } else {
            this.mainMutedBadge?.classList.add('hidden');
            this.pipMutedBadge?.classList.add('hidden');
            this.mainVideoOffOverlay?.classList.add('hidden');
            this.pipVideoOffOverlay?.classList.add('hidden');

            if (this.remoteMuted) {
                this.remoteAudioMutedBadge?.classList.remove('hidden');
                this.remoteAudioMutedBadge?.classList.add('inline-flex');
                if (this.remoteAudioMutedLabel) {
                    this.remoteAudioMutedLabel.textContent = `${this.peerName || 'User'} muted`;
                }
            } else {
                this.remoteAudioMutedBadge?.classList.add('hidden');
                this.remoteAudioMutedBadge?.classList.remove('inline-flex');
            }
        }

        this.miniRemoteMuted?.classList.toggle('hidden', !this.remoteMuted);
    }

    updateMinimizedUI() {
        if (!this.minimizedEl || this.minimizedEl.classList.contains('hidden')) return;

        const showVideo = this.isVideo && this.remoteStream && !this.remoteVideoOff;
        if (this.miniVideo) {
            this.miniVideo.classList.toggle('hidden', !showVideo);
            if (showVideo) {
                this.miniVideo.srcObject = this.remoteStream;
                this.playVideoEl(this.miniVideo);
            } else {
                this.miniVideo.srcObject = null;
            }
        }
        if (this.miniStatus) {
            const label = this.isCallActive ? 'Ongoing call · Tap to return' : 'Connecting… · Tap to open';
            this.miniStatus.textContent = this.remoteMuted ? `${label} · Muted` : label;
        }
        this.miniRemoteMuted?.classList.toggle('hidden', !this.remoteMuted);
        if (this.miniName && this.peerName) {
            this.miniName.textContent = this.peerName;
        }
        if (this.miniAvatar && this.peerAvatar) {
            this.miniAvatar.src = this.peerAvatar;
        }
        // Keep compact single-row layout on live (Vite CSS may be missing).
        this.forceMinimizedBarLayout();
    }

    setCallUiMode(mode) {
        // mode: 'full' | 'mini' | 'off'
        document.body.classList.toggle('call-ui-open', mode === 'full');
        document.body.classList.toggle('call-minimized-open', mode === 'mini');
        document.body.style.overflow = mode === 'full' ? 'hidden' : '';

        const hint = document.getElementById('call-overlay-hint');
        if (mode === 'full' && (this.isCallActive || this.isCalling)) {
            this.minimizeBtn?.classList.remove('hidden');
            hint?.classList.remove('hidden');
        } else {
            hint?.classList.add('hidden');
        }
    }

    async startCall(remoteUserId, isVideo = false, peerInfo = {}, options = {}) {
        if (this.isCallActive || this.isCalling || this.isIncoming || this._endingCall) return;

        if (!await this.ensureCallReady()) return;

        this._endingCall = false;
        this.targetOffline = false;
        this.resetMediaFlags();

        let targetOnline = options.targetOnline;
        if (targetOnline === undefined) {
            const presence = await this.fetchPresence(remoteUserId);
            targetOnline = presence?.online ?? true;
        }
        this.targetOffline = !targetOnline;

        this.isCalling = true;
        this.remoteUserId = remoteUserId;
        this.isVideo = isVideo;
        this.callId = crypto.randomUUID();
        this.wasAnswered = false;
        this.callerUserId = window.authUserId;
        this.facingMode = 'user';
        this.videosSwapped = false;
        this.startDialTimeout(this.targetOffline ? 25000 : 60000);

        this.showOverlay();
        this.setPeerInfo(peerInfo);
        if (this.status) {
            this.status.textContent = this.targetOffline
                ? 'Calling... (user offline)'
                : 'Calling...';
        }
        this.setCallPhase('outgoing');
        this.forceShowCallUi();
        this.resetAudioRoute();
        this.bindAudioDeviceWatcher();

        // Re-assert ringing UI (live CSS / DOM race — same as incoming)
        const assertOutgoing = () => {
            if (!this.isCalling || this.isCallActive) return;
            this.forceShowCallUi();
            this.setCallPhase('outgoing');
            if (this.status && !this.targetOffline) {
                this.status.textContent = 'Calling...';
            }
        };
        requestAnimationFrame(assertOutgoing);
        setTimeout(assertOutgoing, 50);
        setTimeout(assertOutgoing, 300);
        setTimeout(assertOutgoing, 800);

        if (!this.targetOffline) {
            this.toneGen.startDial();
        }

        try {
            this.localStream = await this.getLocalMedia(isVideo);

            // Keep profile + hangup during ring (do not switch to video stage yet).
            this.setCallPhase('outgoing');
            this.forceShowCallUi();
            await this.refreshAudioOutputs();
            await this.applyAudioOutput();

            this.createPeerConnection(true);
            await this.addLocalTracks();

            const offer = await this.peerConnection.createOffer({
                offerToReceiveAudio: true,
                offerToReceiveVideo: isVideo,
            });
            await this.peerConnection.setLocalDescription(offer);

            const sent = await this.sendSignal('offer', this.signalData({
                sdp: this.normalizeSdp(offer.sdp),
            }));
            if (!sent.ok) {
                throw new Error(sent.message || 'Could not reach call server.');
            }

            // Apply answer that arrived while media/PC was still setting up.
            if (this._pendingAnswer) {
                const pending = this._pendingAnswer;
                this._pendingAnswer = null;
                clearTimeout(this._pendingAnswerTimer);
                this._pendingAnswerTimer = null;
                await this.handleAnswer(pending.data, pending.payload);
            }

            this.bumpInboxPolling();
            // Caller must receive answer + ICE quickly.
            setTimeout(() => this.pullInbox(), 300);
            setTimeout(() => this.pullInbox(), 800);
            setTimeout(() => this.pullInbox(), 1500);
        } catch (e) {
            console.error('Failed to start call:', e);
            this.cleanup();
            alert(e.message || 'Could not start call. Check microphone/camera permissions.');
        }
    }

    localTracksToSend() {
        if (!this.localStream) return [];
        const tracks = [];
        const audioTrack = this.localStream.getAudioTracks()[0];
        if (audioTrack) {
            audioTrack.enabled = !this.localMuted;
            tracks.push(audioTrack);
        }
        if (this.isVideo) {
            const videoTrack = this.localStream.getVideoTracks()[0];
            if (videoTrack) {
                videoTrack.enabled = !this.localVideoOff;
                tracks.push(videoTrack);
            }
        }
        return tracks;
    }

    /**
     * Attach local mic/camera to the peer connection.
     * Answerer must reuse offer transceivers via replaceTrack — plain addTrack after
     * setRemoteDescription often leaves video as recvonly, so caller never sees callee camera.
     */
    async addLocalTracks() {
        if (!this.peerConnection || !this.localStream) return;

        for (const track of this.localTracksToSend()) {
            if (this.peerConnection.getSenders().some((s) => s.track === track)) {
                continue;
            }

            const transceiver = this.peerConnection.getTransceivers().find((t) => {
                if (t.stopped) return false;
                if (t.sender?.track?.kind === track.kind) return true;
                if (t.receiver?.track?.kind === track.kind) return true;
                return false;
            });

            if (transceiver?.sender) {
                try {
                    await transceiver.sender.replaceTrack(track);
                } catch (e) {
                    console.warn('replaceTrack failed, falling back to addTrack', e);
                    this.peerConnection.addTrack(track, this.localStream);
                    continue;
                }
                try {
                    if (transceiver.direction !== 'sendrecv' && transceiver.direction !== 'sendonly') {
                        transceiver.direction = 'sendrecv';
                    }
                } catch (e) {
                    // direction may be read-only in some states
                }
                try {
                    if (typeof transceiver.sender.setStreams === 'function') {
                        transceiver.sender.setStreams(this.localStream);
                    }
                } catch (e) {}
                continue;
            }

            // Caller path (no remote description yet): create sendrecv transceiver with track.
            try {
                this.peerConnection.addTransceiver(track, {
                    direction: 'sendrecv',
                    streams: [this.localStream],
                });
            } catch (e) {
                this.peerConnection.addTrack(track, this.localStream);
            }
        }
    }

    createPeerConnection(addTracks = true) {
        this.peerConnection = new RTCPeerConnection({
            iceServers: this.getIceServers(),
            iceCandidatePoolSize: 4,
        });

        // Tracks attached async via addLocalTracks() by callers (startCall / acceptCall).
        if (!addTracks && !this.localStream) {
            this.peerConnection.addTransceiver('audio', { direction: 'recvonly' });
            if (this.isVideo) {
                this.peerConnection.addTransceiver('video', { direction: 'recvonly' });
            }
        }

        this.peerConnection.ontrack = (event) => {
            this.ingestRemoteTrack(event.track, event.streams?.[0]);
        };

        this.startRemoteTrackSync();

        this.peerConnection.onicecandidate = (event) => {
            if (event.candidate) {
                this.sendSignal('candidate', this.signalData(event.candidate.toJSON()));
            }
        };

        this.peerConnection.oniceconnectionstatechange = () => {
            const ice = this.peerConnection?.iceConnectionState;
            if (ice === 'connected' || ice === 'completed') {
                if (this.status) this.status.textContent = 'Connected';
                this.isCallActive = true;
                this.isCalling = false;
                this.wasAnswered = true;
                this.clearCallTimeouts();
                this.stopIncomingAlert();
                this.updateCallUI();
                if (this.remoteStream) this.attachRemoteStream(this.remoteStream);
                this.attachStreamsToVideos();
                this.ensureRemoteAudioPlaying();
                this.bumpInboxPolling();
            } else if (ice === 'checking') {
                if (this.status && this.status.textContent !== 'Connected') {
                    this.status.textContent = 'Connecting...';
                }
            } else if (ice === 'failed') {
                this.tryIceRestart();
            }
        };

        this.peerConnection.onconnectionstatechange = () => {
            if (!this.peerConnection) return;

            const state = this.peerConnection.connectionState;

            if (state === 'connected') {
                this.status.textContent = 'Connected';
                this.isCallActive = true;
                this.isCalling = false;
                this.wasAnswered = true;
                this.clearCallTimeouts();
                this.stopIncomingAlert();
                this.updateCallUI();
                // Ensure remote camera paints after ICE connects.
                if (this.remoteStream) {
                    this.attachRemoteStream(this.remoteStream);
                }
                this.attachStreamsToVideos();
                this.ensureRemoteAudioPlaying();
                this.sendMediaState();
                this.bumpInboxPolling();
                return;
            }

            if (state === 'connecting') {
                if (this.status) this.status.textContent = 'Connecting...';
                this.bumpInboxPolling();
                return;
            }

            if (state === 'disconnected') {
                this.clearDisconnectTimer();
                this._disconnectTimer = setTimeout(() => {
                    if (this.peerConnection?.connectionState === 'disconnected') {
                        this.tryIceRestart();
                    }
                }, 3000);
                return;
            }

            if (state === 'failed') {
                this.tryIceRestart();
                return;
            }

            if (state === 'closed') {
                this.cleanup();
            }
        };
    }

    ingestRemoteTrack(track, eventStream) {
        if (!track) return;

        // Merge all known remote tracks (audio + video may arrive in separate ontrack events).
        const byId = new Map();
        (this.remoteStream?.getTracks() || []).forEach((t) => byId.set(t.id, t));
        if (eventStream) {
            eventStream.getTracks().forEach((t) => byId.set(t.id, t));
        }
        byId.set(track.id, track);
        const merged = new MediaStream([...byId.values()]);

        if (track.kind === 'video') {
            this.isVideo = true;
            // Do not force track.enabled / remoteVideoOff here — causes blink with media_state.
        }

        this.attachRemoteStream(merged, { force: true });
    }

    syncRemoteTracksFromPeer() {
        if (!this.peerConnection) return;

        const receivers = this.peerConnection.getReceivers?.() || [];
        const tracks = receivers.map((r) => r.track).filter((t) => t && t.readyState !== 'ended');
        if (!tracks.length) return;

        const prevIds = (this.remoteStream?.getTracks() || []).map((t) => t.id).sort().join(',');
        const nextIds = tracks.map((t) => t.id).sort().join(',');
        // Nothing new — do not rebind (was causing camera on/off blink every 1s).
        if (prevIds === nextIds) return;

        if (tracks.some((t) => t.kind === 'video')) {
            this.isVideo = true;
        }
        this.attachRemoteStream(new MediaStream(tracks), { force: true });
    }

    startRemoteTrackSync() {
        this.stopRemoteTrackSync();
        this._remoteTrackSyncTimer = setInterval(() => {
            if (!(this.isCallActive || this.isCalling || this.wasAnswered)) return;
            this.syncRemoteTracksFromPeer();
        }, 3000);
    }

    stopRemoteTrackSync() {
        if (this._remoteTrackSyncTimer) {
            clearInterval(this._remoteTrackSyncTimer);
            this._remoteTrackSyncTimer = null;
        }
    }

    tryIceRestart() {
        if (!this.peerConnection || this._iceRestarting) return;
        if (!['failed', 'disconnected'].includes(this.peerConnection.connectionState)
            && !['failed', 'disconnected'].includes(this.peerConnection.iceConnectionState)) {
            return;
        }

        this._iceRestarting = true;
        if (this.status) this.status.textContent = 'Reconnecting...';

        const pc = this.peerConnection;
        const doRestart = async () => {
            try {
                if (typeof pc.restartIce === 'function') {
                    pc.restartIce();
                }
                // Caller creates a new offer; callee waits for it via signaling.
                if (this.callerUserId === window.authUserId && pc.signalingState === 'stable') {
                    const offer = await pc.createOffer({ iceRestart: true });
                    await pc.setLocalDescription(offer);
                    await this.sendSignal('offer', this.signalData({
                        sdp: this.normalizeSdp(offer.sdp),
                        ice_restart: true,
                    }));
                }
            } catch (e) {
                console.warn('ICE restart failed', e);
            } finally {
                setTimeout(() => {
                    this._iceRestarting = false;
                    if (this.peerConnection
                        && ['failed', 'disconnected', 'closed'].includes(this.peerConnection.connectionState)) {
                        if (this.status) this.status.textContent = 'Connection failed';
                        setTimeout(() => this.hangupCall(), 1500);
                    }
                }, 8000);
            }
        };

        doRestart();
        this.bumpInboxPolling();
    }

    async handleIncomingSignal(payload) {
        const from_user = payload?.from_user || {};
        const type = payload?.type;
        const data = payload?.data || {};

        if (!type) return;

        try {
            switch (type) {
                case 'offer': {
                    if (!data.sdp) {
                        console.error('Incoming call missing SDP', payload);
                        return;
                    }

                    const fromId = Number(from_user.id);
                    const sameCall = data.call_id && String(data.call_id) === String(this.callId || '');
                    const sameCaller = fromId && this.sameUserId(this.remoteUserId, fromId);
                    const uiVisible = this.isCallUiVisible();

                    // Already in an active call — never tear it down for a replayed offer.
                    if (this.isCallActive && this.peerConnection) {
                        if (sameCaller || sameCall) {
                            if (data.ice_restart) {
                                await this.handleIceRestartOffer(data);
                            } else {
                                this.syncRemoteTracksFromPeer();
                                if (!uiVisible) this.forceShowCallUi();
                            }
                            return;
                        }
                        await this.sendBusyDecline(from_user, data);
                        return;
                    }

                    // ICE restart offer during active call — renegotiate, don't re-ring.
                    if (data.ice_restart && this.peerConnection && (sameCall || sameCaller)) {
                        await this.handleIceRestartOffer(data);
                        return;
                    }

                    // Duplicate delivery of the same offer (Echo + inbox) — ignore.
                    if (sameCall && (this.isIncoming || this.isCalling)) {
                        if (!uiVisible) this.forceShowCallUi();
                        return;
                    }

                    // Only busy when a REAL call UI is already up with someone else.
                    if ((uiVisible || this.isCalling || this.isIncoming) && !sameCaller) {
                        await this.sendBusyDecline(from_user, data);
                        return;
                    }

                    // Stuck ringing UI / same caller redial — reset only non-active state.
                    if (this.isIncoming || this.isCalling) {
                        this.softResetForIncoming();
                    }

                    this.showIncomingCall(from_user, data);
                    this.bumpInboxPolling();
                    break;
                }

                case 'answer':
                    // Ignore answers when idle or for a different / previous call.
                    if (!(this.isCalling || this.isCallActive || this.wasAnswered)) return;
                    if (!this.sameCallId(data)) return;
                    if (this.remoteUserId && !this.sameUserId(from_user.id, this.remoteUserId)) return;
                    await this.handleAnswer(data, payload);
                    break;

                case 'candidate':
                    if (!(this.isCallActive || this.isCalling || this.isIncoming || this.wasAnswered)) return;
                    if (!this.sameCallId(data)) return;
                    if (this.remoteUserId && !this.sameUserId(from_user.id, this.remoteUserId)) return;
                    await this.handleRemoteCandidate(data);
                    break;

                case 'media_state':
                    if (!(this.isCallActive || this.isCalling || this.isIncoming)) break;
                    if (!this.sameCallId(data)) break;
                    if (!this.sameUserId(from_user?.id, this.remoteUserId)) break;
                    this.applyRemoteMediaState(data || {});
                    break;

                case 'decline':
                    if (!(this.isCallActive || this.isCalling || this.isIncoming)) break;
                    if (!this.sameCallId(data)) break;
                    // Only end if it is from the peer (or our own multi-tab echo).
                    if (
                        this.remoteUserId
                        && !this.sameUserId(from_user?.id, this.remoteUserId)
                        && !this.sameUserId(from_user?.id, window.authUserId)
                    ) {
                        break;
                    }
                    this.cleanup();
                    if (!this.sameUserId(from_user?.id, window.authUserId)) {
                        const reason = data?.reason;
                        if (reason === 'busy') {
                            alert('User is busy on another call.');
                        } else if (reason !== 'missed') {
                            alert('Call declined.');
                        }
                    }
                    break;

                case 'hangup':
                    if (!(this.isCallActive || this.isCalling || this.isIncoming)) break;
                    if (!this.sameCallId(data)) break;
                    if (
                        this.remoteUserId
                        && !this.sameUserId(from_user?.id, this.remoteUserId)
                        && !this.sameUserId(from_user?.id, window.authUserId)
                    ) {
                        break;
                    }
                    this.cleanup();
                    break;
            }
        } catch (e) {
            console.error('Failed to handle call signal:', e);
        }
    }

    async flushIceCandidates() {
        while (this.iceCandidatesQueue.length > 0 && this.peerConnection) {
            const cand = this.iceCandidatesQueue.shift();
            try {
                const init = this.candidateInit(cand);
                if (!init) continue;
                await this.peerConnection.addIceCandidate(new RTCIceCandidate(init));
            } catch (e) {
                console.warn('ICE candidate error:', e);
            }
        }
    }

    candidateInit(data) {
        if (!data || typeof data !== 'object') return null;
        // Support both plain candidate JSON and wrapped payloads.
        const init = data.candidate !== undefined ? data : (data.data || data);
        if (!init || (init.candidate === '' || init.candidate == null) && init.sdpMid == null) {
            return null;
        }
        return {
            candidate: init.candidate,
            sdpMid: init.sdpMid ?? init.sdp_mid ?? null,
            sdpMLineIndex: init.sdpMLineIndex ?? init.sdp_m_line_index ?? null,
            usernameFragment: init.usernameFragment ?? init.username_fragment,
        };
    }

    async handleRemoteCandidate(data) {
        const init = this.candidateInit(data);
        if (!init) return;

        if (this.peerConnection && this.peerConnection.remoteDescription) {
            try {
                await this.peerConnection.addIceCandidate(new RTCIceCandidate(init));
            } catch (e) {
                console.warn('Add ICE candidate failed', e);
            }
            return;
        }

        this.iceCandidatesQueue.push(init);
    }

    async handleAnswer(data, payload = null) {
        if (!data?.sdp) {
            console.error('Answer missing SDP', data);
            return;
        }

        if (!this.peerConnection) {
            // Answer can arrive before local PC finishes setup — retry shortly.
            const tries = (this._pendingAnswer?.tries || 0) + 1;
            this._pendingAnswer = { data, payload, tries };
            if (tries > 20) {
                console.warn('Answer received but peerConnection missing');
                this._pendingAnswer = null;
                return;
            }
            clearTimeout(this._pendingAnswerTimer);
            this._pendingAnswerTimer = setTimeout(() => {
                this._pendingAnswerTimer = null;
                const pending = this._pendingAnswer;
                if (!pending) return;
                this.handleAnswer(pending.data, pending.payload);
            }, 250);
            return;
        }

        this._pendingAnswer = null;
        clearTimeout(this._pendingAnswerTimer);
        this._pendingAnswerTimer = null;

        try {
            // Ignore duplicate answers.
            if (this.peerConnection.signalingState === 'stable' && this.peerConnection.currentRemoteDescription) {
                this.wasAnswered = true;
                this.isCalling = false;
                await this.flushIceCandidates();
                this.ensureRemoteAudioPlaying();
                return;
            }

            await this.setRemoteSdp('answer', data.sdp);
            await this.flushIceCandidates();
            this.wasAnswered = true;
            this.isCalling = false;
            this.isCallActive = true;
            this.clearCallTimeouts();
            this.stopIncomingAlert();
            if (this.status) this.status.textContent = 'Connecting...';
            this.updateCallUI();
            this.syncRemoteTracksFromPeer();
            this.ensureRemoteAudioPlaying();
            this.sendMediaState();
            this.bumpInboxPolling();
            this.pullInbox();
            setTimeout(() => this.syncRemoteTracksFromPeer(), 300);
            setTimeout(() => this.syncRemoteTracksFromPeer(), 1000);
            setTimeout(() => this.ensureRemoteAudioPlaying(), 400);
        } catch (e) {
            console.error('Failed to apply answer SDP', e);
            if (this.status) this.status.textContent = 'Connection error';
        }
    }

    ensureRemoteAudioPlaying() {
        if (!this.remoteAudio) return;
        if (this.remoteStream && this.remoteAudio.srcObject !== this.remoteStream) {
            this.bindStreamToVideo(this.remoteAudio, this.remoteStream, { muted: false });
        }
        this.remoteAudio.muted = false;
        const play = () => this.remoteAudio.play().catch(() => {});
        play();
        setTimeout(play, 200);
        setTimeout(play, 800);
        this.applyAudioOutput();
    }

    async handleIceRestartOffer(data) {
        if (!this.peerConnection || !data?.sdp) return;
        try {
            await this.setRemoteSdp('offer', data.sdp);
            await this.flushIceCandidates();
            const answer = await this.peerConnection.createAnswer();
            await this.peerConnection.setLocalDescription(answer);
            await this.sendSignal('answer', this.signalData({
                sdp: this.normalizeSdp(answer.sdp),
                ice_restart: true,
            }));
            this.bumpInboxPolling();
        } catch (e) {
            console.warn('ICE restart answer failed', e);
        }
    }

    async acceptCall() {
        if (!this.isIncoming || !this.remoteUserId || !this.incomingOfferSdp) return;
        if (this._endingCall) return;

        this.stopIncomingAlert();
        if (this.status) this.status.textContent = 'Connecting...';
        this.setCallPhase('active');
        this.forceShowCallUi();
        this.bumpInboxPolling();

        try {
            this.facingMode = 'user';
            this.videosSwapped = false;
            this.resetAudioRoute();
            this.bindAudioDeviceWatcher();
            this.localStream = await this.getLocalMedia(this.isVideo);

            this.showLocalVideoPreview();
            this.setCallPhase('active');
            await this.refreshAudioOutputs();
            await this.applyAudioOutput();

            this.createPeerConnection(false);

            await this.setRemoteSdp('offer', this.incomingOfferSdp);
            await this.flushIceCandidates();

            // Must reuse offer m-lines (replaceTrack + sendrecv) so caller receives callee video.
            await this.addLocalTracks();

            let answer = await this.peerConnection.createAnswer();
            await this.peerConnection.setLocalDescription(answer);

            // Guard: answer must send video when this is a video call.
            if (this.isVideo && this.localStream?.getVideoTracks?.().length) {
                const sendingVideo = this.peerConnection.getSenders().some(
                    (s) => s.track?.kind === 'video' && s.track.readyState === 'live',
                );
                if (!sendingVideo) {
                    console.warn('Video sender missing after answer — retry attach');
                    await this.addLocalTracks();
                    answer = await this.peerConnection.createAnswer();
                    await this.peerConnection.setLocalDescription(answer);
                }
            }

            const sent = await this.sendSignal('answer', this.signalData({
                sdp: this.normalizeSdp(answer.sdp),
            }));
            if (!sent.ok) {
                throw new Error(sent.message || 'Could not send answer.');
            }

            this.wasAnswered = true;
            this.isIncoming = false;
            this.isCallActive = true;
            this.clearCallTimeouts();
            this.updateCallUI();
            this.syncRemoteTracksFromPeer();
            this.attachStreamsToVideos();
            this.ensureRemoteAudioPlaying();
            this.sendMediaState();
            this.bumpInboxPolling();
            // Pull immediately so caller-side candidates apply without waiting for interval.
            this.pullInbox();
            setTimeout(() => this.pullInbox(), 300);
            setTimeout(() => this.pullInbox(), 800);
            setTimeout(() => this.syncRemoteTracksFromPeer(), 400);
            setTimeout(() => this.attachStreamsToVideos(), 400);
            setTimeout(() => this.ensureRemoteAudioPlaying(), 400);
            setTimeout(() => this.syncRemoteTracksFromPeer(), 1200);
            setTimeout(() => this.attachStreamsToVideos(), 1200);
        } catch (e) {
            console.error('Failed to accept call:', e);
            const message = this.mediaErrorMessage(e);
            await this.endCall('decline', { reason: 'permission_denied' });
            alert(message);
        }
    }

    clearDisconnectTimer() {
        if (this._disconnectTimer) {
            clearTimeout(this._disconnectTimer);
            this._disconnectTimer = null;
        }
    }

    stopMediaStream(stream) {
        if (!stream) return;
        stream.getTracks().forEach((track) => track.stop());
    }

    stopRemotePlayback() {
        if (this.remoteAudio?.srcObject) {
            this.remoteAudio.srcObject = null;
        }
        [this.mainVideo, this.pipVideo, this.miniVideo].forEach((element) => {
            if (element) element.srcObject = null;
        });
        this.remoteStream = null;
    }

    async endCall(signalType = 'hangup', extra = {}) {
        if (this._endingCall) return;
        this._endingCall = true;

        const remoteId = this.remoteUserId;
        const payload = ['hangup', 'decline'].includes(signalType) ? this.callMeta(extra) : extra;

        this.cleanup();

        try {
            if (remoteId) {
                await this.sendSignal(signalType, payload, remoteId);
            }
        } finally {
            this._endingCall = false;
        }
    }

    async declineCall(reason = 'declined') {
        await this.endCall('decline', { reason });
    }

    async hangupCall() {
        await this.endCall('hangup');
    }

    toggleMute() {
        if (!this.localStream) return;
        const audioTrack = this.localStream.getAudioTracks()[0];
        if (!audioTrack) return;

        audioTrack.enabled = !audioTrack.enabled;
        this.localMuted = !audioTrack.enabled;
        this.updateLocalControlStyles();
        this.updateMediaIndicators();
        this.sendMediaState();
    }

    toggleVideo() {
        if (!this.localStream || !this.isVideo) return;
        const videoTrack = this.localStream.getVideoTracks()[0];
        if (!videoTrack) return;

        videoTrack.enabled = !videoTrack.enabled;
        this.localVideoOff = !videoTrack.enabled;
        this.updateLocalControlStyles();
        this.updateMediaIndicators();
        this.sendMediaState();
    }

    async flipCamera() {
        if (!this.isVideo || !this.localStream || this._flippingCamera) return;

        this._flippingCamera = true;
        const nextFacing = this.facingMode === 'user' ? 'environment' : 'user';

        try {
            const newStream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: { facingMode: { ideal: nextFacing } },
            });
            const newTrack = newStream.getVideoTracks()[0];
            if (!newTrack) {
                newStream.getTracks().forEach((t) => t.stop());
                return;
            }

            const sender = this.peerConnection?.getSenders().find((s) => s.track?.kind === 'video');
            if (sender) {
                await sender.replaceTrack(newTrack);
            }

            const oldTrack = this.localStream.getVideoTracks()[0];
            if (oldTrack) {
                this.localStream.removeTrack(oldTrack);
                oldTrack.stop();
            }
            this.localStream.addTrack(newTrack);
            this.facingMode = nextFacing;

            if (this.localVideoOff) {
                newTrack.enabled = false;
            }

            this.attachStreamsToVideos();
        } catch (e) {
            console.warn('Flip camera failed:', e);
            alert('Could not switch camera. Your device may have only one camera.');
        } finally {
            this._flippingCamera = false;
        }
    }

    swapVideos() {
        if (!this.isVideo || !this.isCallActive) return;
        this.videosSwapped = !this.videosSwapped;
        this.attachStreamsToVideos();
    }

    forceMinimizedBarLayout() {
        const bar = this.minimizedEl || document.getElementById('call-minimized');
        if (!bar) return;
        this.minimizedEl = bar;

        Object.assign(bar.style, {
            display: 'block',
            position: 'fixed',
            top: '0',
            left: '0',
            right: '0',
            zIndex: '100000',
            background: '#059669',
            paddingTop: 'env(safe-area-inset-top, 0px)',
            height: 'auto',
            maxHeight: 'calc(3.5rem + env(safe-area-inset-top, 0px))',
            overflow: 'hidden',
            boxShadow: '0 4px 20px rgba(5, 150, 105, 0.45)',
            boxSizing: 'border-box',
        });

        const inner = bar.querySelector('.call-ongoing-inner');
        if (inner) {
            Object.assign(inner.style, {
                display: 'flex',
                flexDirection: 'row',
                alignItems: 'center',
                gap: '0.75rem',
                padding: '0.55rem 0.85rem',
                minHeight: '3.25rem',
                maxHeight: '3.5rem',
                width: '100%',
                boxSizing: 'border-box',
            });
        }

        const avatar = bar.querySelector('#mini-call-avatar');
        if (avatar) {
            Object.assign(avatar.style, {
                width: '2.25rem',
                height: '2.25rem',
                borderRadius: '9999px',
                objectFit: 'cover',
                flexShrink: '0',
            });
        }

        const video = bar.querySelector('#mini-call-video');
        if (video && !video.classList.contains('hidden')) {
            Object.assign(video.style, {
                width: '2.5rem',
                height: '2.5rem',
                borderRadius: '0.5rem',
                objectFit: 'cover',
                flexShrink: '0',
            });
        }

        const hangup = bar.querySelector('#mini-hangup-btn');
        if (hangup) {
            Object.assign(hangup.style, {
                width: '2.5rem',
                height: '2.5rem',
                borderRadius: '9999px',
                flexShrink: '0',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
            });
        }
    }

    minimizeCall() {
        if (!(this.isCallActive || this.isCalling || this.isIncoming)) return;
        if (this.isIncoming && !this.isCallActive) return;

        this.isMinimized = true;
        this.overlay?.classList.add('hidden');
        if (this.overlay) this.overlay.style.display = 'none';
        this.minimizedEl?.classList.remove('hidden');
        this.forceMinimizedBarLayout();
        this.setCallUiMode('mini');
        this.updateMinimizedUI();
        // Re-assert compact layout after paint (live CSS race)
        requestAnimationFrame(() => this.forceMinimizedBarLayout());
        setTimeout(() => this.forceMinimizedBarLayout(), 50);
    }

    expandCall() {
        this.isMinimized = false;
        this.minimizedEl?.classList.add('hidden');
        if (this.minimizedEl) {
            this.minimizedEl.style.display = 'none';
        }
        this.overlay?.classList.remove('hidden');
        if (this.overlay) {
            this.overlay.style.display = 'flex';
            this.overlay.style.visibility = 'visible';
            this.overlay.style.opacity = '1';
            this.overlay.style.zIndex = '99999';
        }
        this.setCallUiMode('full');
        this.setPipCorner(this.pipCorner || 'br');
        this.attachStreamsToVideos();
        this.updateMediaIndicators();
    }

    isCallUiVisible() {
        if (!this.overlay) return false;
        if (this.overlay.classList.contains('hidden')) return false;
        const style = window.getComputedStyle(this.overlay);
        if (style.display === 'none' || style.visibility === 'hidden') return false;
        // Must cover the viewport — otherwise live CSS is missing and UI is "invisible"
        const rect = this.overlay.getBoundingClientRect();
        return rect.width > 100 && rect.height > 100;
    }

    softResetForIncoming() {
        this.stopIncomingAlert();
        this.clearCallTimeouts();
        this.clearDisconnectTimer();
        this.stopRemoteTrackSync();
        this.isIncoming = false;
        this.isCalling = false;
        this.isCallActive = false;
        this.incomingOfferSdp = null;
        this.wasAnswered = false;
        this._endingCall = false;
        this._pendingAnswer = null;
        clearTimeout(this._pendingAnswerTimer);
        this._pendingAnswerTimer = null;

        if (this.peerConnection) {
            try {
                this.peerConnection.onconnectionstatechange = null;
                this.peerConnection.onicecandidate = null;
                this.peerConnection.ontrack = null;
                this.peerConnection.close();
            } catch (e) {}
            this.peerConnection = null;
        }

        if (this.localStream) {
            this.stopMediaStream(this.localStream);
            this.localStream = null;
        }
        this.stopRemotePlayback();
        this.iceCandidatesQueue = [];
    }

    async sendBusyDecline(fromUser, data = {}) {
        const prevRemote = this.remoteUserId;
        this.remoteUserId = fromUser.id;
        try {
            await this.sendSignal('decline', {
                reason: 'busy',
                call_id: data.call_id,
                caller_id: fromUser.id,
                is_video: !!(data.isVideo ?? data.is_video),
            });
        } finally {
            this.remoteUserId = prevRemote;
        }
    }

    forceShowCallUi() {
        if (!this.overlay) {
            this.overlay = document.getElementById('call-overlay');
        }
        if (!this.overlay) return;

        this.overlay.classList.remove('hidden');
        // Inline styles so live works even when Vite CSS is stale/missing
        Object.assign(this.overlay.style, {
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'space-between',
            position: 'fixed',
            top: '0',
            right: '0',
            bottom: '0',
            left: '0',
            width: '100%',
            height: '100%',
            minHeight: '100dvh',
            visibility: 'visible',
            opacity: '1',
            zIndex: '99999',
            background: '#020617',
            color: '#fff',
            padding: '1.25rem',
            boxSizing: 'border-box',
        });

        document.body.classList.add('call-ui-open');
        document.body.style.overflow = 'hidden';
    }

    showIncomingCall(fromUser, data = {}) {
        this.isIncoming = true;
        this.isCalling = false;
        this.isCallActive = false;
        this.isMinimized = false;
        this.remoteUserId = fromUser.id;
        this.isVideo = !!(data.isVideo ?? data.is_video);
        try {
            this.incomingOfferSdp = this.prepareRemoteSdp(data.sdp);
        } catch (e) {
            console.error('Bad offer SDP', e);
            this.incomingOfferSdp = data.sdp;
        }
        this.callId = data.call_id || crypto.randomUUID();
        this.callerUserId = fromUser.id;
        this.wasAnswered = false;
        this.resetMediaFlags();
        this.startRingTimeout();

        this.showOverlay();
        this.setPeerInfo({
            name: fromUser.name || 'Incoming call',
            avatar: fromUser.avatar_url || '',
        });

        if (this.status) {
            this.status.textContent = `Incoming ${this.isVideo ? 'Video' : 'Voice'} Call...`;
        }

        this.videosContainer?.classList.add('hidden');
        this.audioPulse?.classList.add('hidden');
        this.profileBlock?.classList.remove('hidden');
        this.closeSpeakerPicker();

        // ONLY Accept (green) + Decline/Cut (red) — no media controls yet
        this.setCallPhase('incoming');
        this.forceShowCallUi();
        this.startIncomingAlert();

        try {
            if (navigator.vibrate) navigator.vibrate([400, 200, 400, 200, 400]);
        } catch (e) {}

        // Re-assert UI after paint (mobile Safari / live CSS race)
        const assertIncoming = () => {
            this.forceShowCallUi();
            this.setCallPhase('incoming');
        };
        requestAnimationFrame(assertIncoming);
        setTimeout(assertIncoming, 50);
        setTimeout(assertIncoming, 300);
        setTimeout(assertIncoming, 800);
    }

    showOverlay() {
        this.isMinimized = false;
        this.minimizedEl?.classList.add('hidden');
        if (this.minimizedEl) {
            this.minimizedEl.style.display = 'none';
        }
        this.forceShowCallUi();
        this.setCallUiMode('full');
        this.profileBlock?.classList.remove('hidden');
    }

    updateCallUI() {
        this.setCallPhase(this.isCalling && !this.wasAnswered ? 'outgoing' : 'active');
        this.forceShowCallUi();

        if (this.isVideo) {
            this.showVideoStage();
            this.attachStreamsToVideos();
        } else {
            this.videosContainer?.classList.add('hidden');
            if (this.videosContainer) this.videosContainer.style.display = 'none';
            this.audioPulse?.classList.remove('hidden');
            this.profileBlock?.classList.remove('hidden');
        }

        this.updateMediaIndicators();
        if (this.isMinimized) {
            this.updateMinimizedUI();
        }
    }

    resetMediaFlags() {
        this.localMuted = false;
        this.localVideoOff = false;
        this.remoteMuted = false;
        this.remoteVideoOff = false;
        this.videosSwapped = false;
        this.facingMode = 'user';
        this.updateLocalControlStyles();
        this.updateMediaIndicators();
    }

    resetAudioRoute() {
        this.audioOutputId = '';
        this.audioRoute = 'phone';
        this.audioOutputs = [];
        this.updateSpeakerButtonUI();
        if (this.remoteAudio) {
            this.remoteAudio.volume = 0.92;
        }
    }

    cleanup() {
        this.stopIncomingAlert();
        this.clearCallTimeouts();
        this.clearDisconnectTimer();
        this.stopRemoteTrackSync();
        this.unbindAudioDeviceWatcher();
        this.closeSpeakerPicker();
        this.isIncoming = false;
        this.isCalling = false;
        this.isCallActive = false;
        this.isMinimized = false;
        this.incomingOfferSdp = null;
        this.callId = null;
        this.wasAnswered = false;
        this.callerUserId = null;
        this.targetOffline = false;
        this.peerName = '';
        this.peerAvatar = '';
        this.resetAudioRoute();
        this.setCallUiMode('off');

        if (this.peerConnection) {
            this.peerConnection.onconnectionstatechange = null;
            this.peerConnection.onicecandidate = null;
            this.peerConnection.ontrack = null;
            try {
                this.peerConnection.close();
            } catch (e) {}
            this.peerConnection = null;
        }

        if (this.localStream) {
            this.stopMediaStream(this.localStream);
            this.localStream = null;
        }

        this.stopRemotePlayback();

        this.overlay?.classList.add('hidden');
        if (this.overlay) {
            this.overlay.style.display = 'none';
            this.overlay.style.visibility = 'hidden';
        }
        this.minimizedEl?.classList.add('hidden');
        if (this.minimizedEl) {
            this.minimizedEl.style.display = 'none';
        }
        document.body.style.overflow = '';
        document.body.classList.remove('call-ui-open', 'call-minimized-open');

        this.setCallPhase('none');
        this.videosContainer?.classList.add('hidden');
        this.audioPulse?.classList.add('hidden');
        this.profileBlock?.classList.remove('hidden');
        this.remoteAudioMutedBadge?.classList.add('hidden');
        this.remoteAudioMutedBadge?.classList.remove('inline-flex');
        this.mainVideoOffOverlay?.classList.add('hidden');
        this.pipVideoOffOverlay?.classList.add('hidden');
        this.mainMutedBadge?.classList.add('hidden');
        this.pipMutedBadge?.classList.add('hidden');

        this.resetMediaFlags();

        if (this.remoteAudio && typeof this.remoteAudio.setSinkId === 'function') {
            this.remoteAudio.setSinkId('').catch(() => {});
        }

        this.iceCandidatesQueue = [];
        this._pendingAnswer = null;
        clearTimeout(this._pendingAnswerTimer);
        this._pendingAnswerTimer = null;
        this.remoteUserId = null;
    }
}

window.CallManager = new WebRTCCallManager();
