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

    init() {
        if (this._uiBound) return;

        this.overlay = document.getElementById('call-overlay');
        if (!this.overlay) return;

        this._uiBound = true;
        this.toneGen.bindUnlock();

        this.minimizedEl = document.getElementById('call-minimized');
        this.avatar = document.getElementById('call-user-avatar');
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
        this.pipWrap?.addEventListener('click', (e) => {
            e.preventDefault();
            this.swapVideos();
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

        this.bindChatCallButtons();
        this.registerSignalingListener();
        this.startInboxPolling();
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
        this._inboxTimer = setInterval(() => this.pullInbox(), 1000);
        // Also poll when tab becomes visible again (mobile background)
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                this.pullInbox();
            }
        });
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

    ingestSignal(payload) {
        if (!payload || !payload.type) return;

        const callId = payload.data?.call_id || '';
        const fromId = payload.from_user?.id || '';
        let signalId = payload._id;
        if (!signalId) {
            if (payload.type === 'offer' || payload.type === 'answer') {
                signalId = `${payload.type}:${callId}:${fromId}`;
            } else if (payload.type === 'candidate') {
                signalId = `candidate:${fromId}:${payload.data?.candidate || JSON.stringify(payload.data || {})}`;
            } else if (payload.type === 'media_state') {
                signalId = `media:${fromId}:${payload.data?.muted}:${payload.data?.video_off}:${payload._at || ''}`;
            } else {
                signalId = `${payload.type}:${callId}:${fromId}`;
            }
        }

        if (this._seenSignalIds.has(signalId)) return;
        this._seenSignalIds.add(signalId);

        // Same logical offer/answer from Echo + inbox
        if (callId && (payload.type === 'offer' || payload.type === 'answer')) {
            this._seenSignalIds.add(`${payload.type}:${callId}:${fromId}`);
        }

        if (this._seenSignalIds.size > 300) {
            const keep = [...this._seenSignalIds].slice(-120);
            this._seenSignalIds = new Set(keep);
        }

        // Own hangup/decline already handled locally — ignore inbox echo when idle.
        if (['hangup', 'decline'].includes(payload.type) && fromId === window.authUserId) {
            if (!(this.isCallActive || this.isCalling || this.isIncoming)) return;
        }

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
        const normalized = this.normalizeSdp(sdp);
        const lines = normalized.split(/\r?\n/).filter((line) => {
            const trimmed = line.trim();
            if (!trimmed) return false;
            // Legacy Plan-B ssrc/msid lines break setRemoteDescription on some Chromium builds
            if (trimmed.startsWith('a=ssrc:') && trimmed.includes(' msid:')) return false;
            if (trimmed.startsWith('a=ssrc-group:')) return false;
            return true;
        });

        return `${lines.join('\r\n')}\r\n`;
    }

    async setRemoteSdp(type, sdp) {
        const cleaned = this.prepareRemoteSdp(sdp);
        await this.peerConnection.setRemoteDescription(
            new RTCSessionDescription({ type, sdp: cleaned }),
        );
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

        this.sendSignal('media_state', {
            muted: this.localMuted,
            video_off: this.localVideoOff,
            is_video: this.isVideo,
        });
    }

    applyRemoteMediaState(data = {}) {
        this.remoteMuted = !!data.muted;
        if (typeof data.video_off === 'boolean') {
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

        const constraints = {
            audio: true,
            video: isVideo
                ? { facingMode: { ideal: this.facingMode } }
                : false,
        };

        try {
            return await navigator.mediaDevices.getUserMedia(constraints);
        } catch (error) {
            if (isVideo) {
                try {
                    return await navigator.mediaDevices.getUserMedia({
                        audio: true,
                        video: true,
                    });
                } catch (retryError) {
                    try {
                        return await navigator.mediaDevices.getUserMedia({
                            audio: true,
                            video: false,
                        });
                    } catch (audioOnlyError) {
                        throw audioOnlyError;
                    }
                }
            }
            throw error;
        }
    }

    setPeerInfo(peerInfo = {}) {
        if (peerInfo.name) {
            this.peerName = peerInfo.name;
            if (this.userName) this.userName.textContent = peerInfo.name;
            if (this.miniName) this.miniName.textContent = peerInfo.name;
        }
        if (peerInfo.avatar) {
            this.peerAvatar = peerInfo.avatar;
            if (this.avatar) this.avatar.src = peerInfo.avatar;
            if (this.miniAvatar) this.miniAvatar.src = peerInfo.avatar;
            if (this.mainVideoOffAvatar) this.mainVideoOffAvatar.src = peerInfo.avatar;
        }
    }

    playVideoEl(el) {
        if (!el) return;
        el.play().catch(() => {});
    }

    attachStreamsToVideos() {
        if (!this.isVideo) return;

        const local = this.localStream;
        const remote = this.remoteStream;
        const mainShowsRemote = !this.videosSwapped;

        // Keep video elements muted — remote audio always plays via #remote-audio (setSinkId).
        if (this.mainVideo) {
            this.mainVideo.srcObject = mainShowsRemote ? remote : local;
            this.mainVideo.muted = true;
            this.playVideoEl(this.mainVideo);
        }

        if (this.pipVideo) {
            this.pipVideo.srcObject = mainShowsRemote ? local : remote;
            this.pipVideo.muted = true;
            this.playVideoEl(this.pipVideo);
        }

        if (this.miniVideo) {
            this.miniVideo.srcObject = remote || local;
            this.miniVideo.muted = true;
            this.playVideoEl(this.miniVideo);
        }

        this.updateMediaIndicators();
        this.updateMinimizedUI();
    }

    attachRemoteStream(stream) {
        this.remoteStream = stream;

        if (this.remoteAudio) {
            this.remoteAudio.srcObject = stream;
            this.playVideoEl(this.remoteAudio);
            this.applyAudioOutput();
        }

        if (this.isVideo) {
            this.attachStreamsToVideos();
        }

        this.updateMediaIndicators();
        this.updateMinimizedUI();
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
            this.speakerBtn.classList.toggle('bg-indigo-600', active);
            this.speakerBtn.classList.toggle('border-indigo-400', active);
            this.speakerBtn.classList.toggle('bg-slate-600', !active);
            this.speakerBtn.classList.toggle('border-white/20', !active);
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

        this.videosContainer?.classList.remove('hidden');
        this.audioPulse?.classList.add('hidden');
        this.profileBlock?.classList.add('hidden');
        this.attachStreamsToVideos();
    }

    showMediaControls() {
        this.muteBtn?.classList.remove('hidden');
        this.speakerBtn?.classList.remove('hidden');
        this.updateSpeakerButtonUI();
        if (this.isVideo && this.localStream?.getVideoTracks().length) {
            this.videoBtn?.classList.remove('hidden');
            this.flipBtn?.classList.remove('hidden');
        } else {
            this.videoBtn?.classList.add('hidden');
            this.flipBtn?.classList.add('hidden');
        }
        if (this.isCallActive || this.isCalling) {
            this.minimizeBtn?.classList.remove('hidden');
        }
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
        btn.classList.toggle('bg-rose-600', active);
        btn.classList.toggle('border-rose-400', active);
        btn.classList.toggle('bg-slate-600', !active);
        btn.classList.toggle('border-white/20', !active);
        btn.classList.toggle('call-ctrl-off', active);

        const iconOn = btn.querySelector('[data-icon-on]');
        const iconOff = btn.querySelector('[data-icon-off]');
        if (iconOn && iconOff) {
            iconOn.classList.toggle('hidden', active);
            iconOff.classList.toggle('hidden', !active);
        }
    }

    updateMediaIndicators() {
        const mainShowsRemote = !this.videosSwapped;
        const mainMuted = mainShowsRemote ? this.remoteMuted : this.localMuted;
        const mainVideoOff = mainShowsRemote ? this.remoteVideoOff : this.localVideoOff;
        const pipMuted = mainShowsRemote ? this.localMuted : this.remoteMuted;
        const pipVideoOff = mainShowsRemote ? this.localVideoOff : this.remoteVideoOff;

        if (this.isVideo) {
            this.mainMutedBadge?.classList.toggle('hidden', !mainMuted);
            this.pipMutedBadge?.classList.toggle('hidden', !pipMuted);
            this.mainVideoOffOverlay?.classList.toggle('hidden', !mainVideoOff);
            this.pipVideoOffOverlay?.classList.toggle('hidden', !pipVideoOff);

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
        this.status.textContent = this.targetOffline
            ? 'Calling... (user offline)'
            : 'Calling...';
        this.declineBtn.classList.add('hidden');
        this.acceptBtn.classList.add('hidden');
        this.hangupBtn.classList.remove('hidden');
        this.muteBtn.classList.add('hidden');
        this.videoBtn.classList.add('hidden');
        this.speakerBtn?.classList.add('hidden');
        this.flipBtn?.classList.add('hidden');
        this.minimizeBtn?.classList.remove('hidden');
        this.resetAudioRoute();
        this.bindAudioDeviceWatcher();

        if (!this.targetOffline) {
            this.toneGen.startDial();
        }

        try {
            this.localStream = await this.getLocalMedia(isVideo);

            this.showLocalVideoPreview();
            this.showMediaControls();
            await this.refreshAudioOutputs();
            await this.applyAudioOutput();

            this.createPeerConnection();

            const offer = await this.peerConnection.createOffer({
                offerToReceiveAudio: true,
                offerToReceiveVideo: isVideo,
            });
            await this.peerConnection.setLocalDescription(offer);

            const sent = await this.sendSignal('offer', {
                sdp: this.normalizeSdp(offer.sdp),
                isVideo,
                call_id: this.callId,
                caller_id: this.callerUserId,
            });
            if (!sent.ok) {
                throw new Error(sent.message || 'Could not reach call server. On live: start Reverb (supervisorctl start newbook-reverb) and check nginx /app proxy.');
            }
        } catch (e) {
            console.error('Failed to start call:', e);
            this.cleanup();
            alert(e.message || 'Could not start call. Check microphone/camera permissions and Reverb server.');
        }
    }

    createPeerConnection() {
        this.peerConnection = new RTCPeerConnection({ iceServers: this.getIceServers() });

        if (this.localStream) {
            this.localStream.getTracks().forEach((track) => {
                this.peerConnection.addTrack(track, this.localStream);
            });
        }

        this.peerConnection.ontrack = (event) => {
            const stream = event.streams?.[0] || new MediaStream([event.track]);
            if (!this.remoteStream) {
                this.remoteStream = stream;
            } else if (event.track && !this.remoteStream.getTracks().includes(event.track)) {
                this.remoteStream.addTrack(event.track);
            }
            this.attachRemoteStream(this.remoteStream);
        };

        this.peerConnection.onicecandidate = (event) => {
            if (event.candidate) {
                this.sendSignal('candidate', event.candidate.toJSON());
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
                this.sendMediaState();
                return;
            }

            if (state === 'disconnected') {
                this.clearDisconnectTimer();
                this._disconnectTimer = setTimeout(() => {
                    if (this.peerConnection?.connectionState === 'disconnected') {
                        this.cleanup();
                    }
                }, 4000);
                return;
            }

            if (state === 'failed' || state === 'closed') {
                this.cleanup();
            }
        };
    }

    async handleIncomingSignal(payload) {
        const from_user = payload?.from_user || {};
        const type = payload?.type;
        const data = payload?.data || {};

        if (!type) return;

        try {
            switch (type) {
                case 'offer':
                    if (this.isCallActive || this.isCalling || this.isIncoming) {
                        // Same offer replayed — ignore; different caller — busy.
                        if (String(data.call_id || '') === String(this.callId || '')) return;
                        const prevRemote = this.remoteUserId;
                        this.remoteUserId = from_user.id;
                        await this.sendSignal('decline', {
                            reason: 'busy',
                            call_id: data.call_id,
                            caller_id: from_user.id,
                            is_video: !!(data.isVideo ?? data.is_video),
                        });
                        this.remoteUserId = prevRemote;
                        return;
                    }

                    if (!data.sdp) {
                        console.error('Incoming call missing SDP', payload);
                        return;
                    }

                    this.showIncomingCall(from_user, data);
                    break;

                case 'answer':
                    if (!this.peerConnection) return;
                    await this.setRemoteSdp('answer', data.sdp);
                    await this.flushIceCandidates();
                    this.wasAnswered = true;
                    this.isCalling = false;
                    this.clearCallTimeouts();
                    this.stopIncomingAlert();
                    this.updateCallUI();
                    this.sendMediaState();
                    break;

                case 'candidate':
                    if (this.peerConnection && this.peerConnection.remoteDescription) {
                        await this.peerConnection.addIceCandidate(new RTCIceCandidate(data));
                    } else {
                        this.iceCandidatesQueue.push(data);
                    }
                    break;

                case 'media_state':
                    if (!(this.isCallActive || this.isCalling || this.isIncoming)) break;
                    if (from_user?.id !== this.remoteUserId) break;
                    this.applyRemoteMediaState(data || {});
                    break;

                case 'decline':
                    if (!(this.isCallActive || this.isCalling || this.isIncoming)) break;
                    this.cleanup();
                    if (from_user?.id !== window.authUserId) {
                        const reason = data?.reason;
                        if (reason === 'busy') {
                            alert('User is on another call.');
                        } else if (reason !== 'missed') {
                            alert('Call declined.');
                        }
                    }
                    break;

                case 'hangup':
                    if (!(this.isCallActive || this.isCalling || this.isIncoming)) break;
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
                await this.peerConnection.addIceCandidate(new RTCIceCandidate(cand));
            } catch (e) {
                console.warn('ICE candidate error:', e);
            }
        }
    }

    async acceptCall() {
        if (!this.isIncoming || !this.remoteUserId || !this.incomingOfferSdp) return;
        if (this._endingCall) return;

        this.stopIncomingAlert();
        this.acceptBtn.classList.add('hidden');
        this.declineBtn.classList.add('hidden');
        this.hangupBtn.classList.remove('hidden');
        this.status.textContent = 'Connecting...';

        try {
            this.facingMode = 'user';
            this.videosSwapped = false;
            this.resetAudioRoute();
            this.bindAudioDeviceWatcher();
            this.localStream = await this.getLocalMedia(this.isVideo);

            this.showLocalVideoPreview();
            this.showMediaControls();
            await this.refreshAudioOutputs();
            await this.applyAudioOutput();

            this.createPeerConnection();

            await this.setRemoteSdp('offer', this.incomingOfferSdp);

            await this.flushIceCandidates();

            const answer = await this.peerConnection.createAnswer();
            await this.peerConnection.setLocalDescription(answer);
            await this.sendSignal('answer', { sdp: this.normalizeSdp(answer.sdp) });

            this.wasAnswered = true;
            this.isIncoming = false;
            this.isCallActive = true;
            this.clearCallTimeouts();
            this.updateCallUI();
            this.sendMediaState();
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

    minimizeCall() {
        if (!(this.isCallActive || this.isCalling || this.isIncoming)) return;
        if (this.isIncoming && !this.isCallActive) return;

        this.isMinimized = true;
        this.overlay?.classList.add('hidden');
        if (this.overlay) this.overlay.style.display = 'none';
        this.minimizedEl?.classList.remove('hidden');
        if (this.minimizedEl) this.minimizedEl.style.display = '';
        this.setCallUiMode('mini');
        this.updateMinimizedUI();
    }

    expandCall() {
        this.isMinimized = false;
        this.minimizedEl?.classList.add('hidden');
        if (this.minimizedEl) this.minimizedEl.style.display = 'none';
        this.overlay?.classList.remove('hidden');
        if (this.overlay) {
            this.overlay.style.display = 'flex';
            this.overlay.style.visibility = 'visible';
            this.overlay.style.opacity = '1';
            this.overlay.style.zIndex = '99999';
        }
        this.setCallUiMode('full');
        this.attachStreamsToVideos();
        this.updateMediaIndicators();
    }

    showIncomingCall(fromUser, data = {}) {
        this.isIncoming = true;
        this.isCalling = false;
        this.isCallActive = false;
        this.remoteUserId = fromUser.id;
        this.isVideo = !!(data.isVideo ?? data.is_video);
        this.incomingOfferSdp = this.prepareRemoteSdp(data.sdp);
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

        this.declineBtn?.classList.remove('hidden');
        this.acceptBtn?.classList.remove('hidden');
        this.hangupBtn?.classList.add('hidden');
        this.muteBtn?.classList.add('hidden');
        this.videoBtn?.classList.add('hidden');
        this.speakerBtn?.classList.add('hidden');
        this.flipBtn?.classList.add('hidden');
        this.minimizeBtn?.classList.add('hidden');
        this.videosContainer?.classList.add('hidden');
        this.audioPulse?.classList.add('hidden');
        this.profileBlock?.classList.remove('hidden');
        this.closeSpeakerPicker();

        // Force paint on mobile Safari
        if (this.overlay) {
            this.overlay.style.display = 'flex';
            this.overlay.style.visibility = 'visible';
            this.overlay.style.opacity = '1';
            this.overlay.style.zIndex = '99999';
        }

        this.startIncomingAlert();

        try {
            if (navigator.vibrate) navigator.vibrate([400, 200, 400, 200, 400]);
        } catch (e) {}
    }

    showOverlay() {
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
        this.profileBlock?.classList.remove('hidden');
    }

    updateCallUI() {
        this.declineBtn?.classList.add('hidden');
        this.acceptBtn?.classList.add('hidden');
        this.hangupBtn?.classList.remove('hidden');
        this.showMediaControls();

        if (this.isVideo) {
            this.videosContainer?.classList.remove('hidden');
            this.audioPulse?.classList.add('hidden');
            this.profileBlock?.classList.add('hidden');
            this.attachStreamsToVideos();
        } else {
            this.videosContainer?.classList.add('hidden');
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
        }
        this.minimizedEl?.classList.add('hidden');
        if (this.minimizedEl) {
            this.minimizedEl.style.display = 'none';
        }
        document.body.style.overflow = '';

        this.declineBtn?.classList.add('hidden');
        this.acceptBtn?.classList.add('hidden');
        this.muteBtn?.classList.add('hidden');
        this.videoBtn?.classList.add('hidden');
        this.speakerBtn?.classList.add('hidden');
        this.flipBtn?.classList.add('hidden');
        this.hangupBtn?.classList.add('hidden');
        this.minimizeBtn?.classList.add('hidden');
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
        this.remoteUserId = null;
    }
}

window.CallManager = new WebRTCCallManager();
