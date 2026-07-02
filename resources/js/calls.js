// Tone Generator to synthesize ringtones client-side
class ToneGenerator {
    constructor() {
        this.ctx = null;
        this.osc1 = null;
        this.osc2 = null;
        this.gainNode = null;
        this.timer = null;
    }

    startRing() {
        this.stop();
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            this.ctx = new AudioCtx();
            this.gainNode = this.ctx.createGain();
            this.gainNode.gain.setValueAtTime(0, this.ctx.currentTime);
            this.gainNode.connect(this.ctx.destination);

            this.osc1 = this.ctx.createOscillator();
            this.osc1.type = 'sine';
            this.osc1.frequency.value = 440;
            this.osc1.connect(this.gainNode);

            this.osc2 = this.ctx.createOscillator();
            this.osc2.type = 'sine';
            this.osc2.frequency.value = 480;
            this.osc2.connect(this.gainNode);

            this.osc1.start(0);
            this.osc2.start(0);

            const playRing = () => {
                if (!this.ctx) return;
                const now = this.ctx.currentTime;
                this.gainNode.gain.setValueAtTime(0, now);
                this.gainNode.gain.linearRampToValueAtTime(0.15, now + 0.1);
                this.gainNode.gain.setValueAtTime(0.15, now + 2.0);
                this.gainNode.gain.linearRampToValueAtTime(0, now + 2.1);
            };

            playRing();
            this.timer = setInterval(playRing, 4000);
        } catch (e) {
            console.error('Failed to generate ring tone', e);
        }
    }

    startDial() {
        this.stop();
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            this.ctx = new AudioCtx();
            this.gainNode = this.ctx.createGain();
            this.gainNode.gain.setValueAtTime(0, this.ctx.currentTime);
            this.gainNode.connect(this.ctx.destination);

            this.osc1 = this.ctx.createOscillator();
            this.osc1.type = 'sine';
            this.osc1.frequency.value = 350;
            this.osc1.connect(this.gainNode);

            this.osc2 = this.ctx.createOscillator();
            this.osc2.type = 'sine';
            this.osc2.frequency.value = 440;
            this.osc2.connect(this.gainNode);

            this.osc1.start(0);
            this.osc2.start(0);

            const playDial = () => {
                if (!this.ctx) return;
                const now = this.ctx.currentTime;
                this.gainNode.gain.setValueAtTime(0, now);
                this.gainNode.gain.linearRampToValueAtTime(0.1, now + 0.1);
                this.gainNode.gain.setValueAtTime(0.1, now + 1.2);
                this.gainNode.gain.linearRampToValueAtTime(0, now + 1.3);
            };

            playDial();
            this.timer = setInterval(playDial, 3000);
        } catch (e) {
            console.error('Failed to generate dial tone', e);
        }
    }

    stop() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
        try {
            if (this.osc1) this.osc1.stop();
            if (this.osc2) this.osc2.stop();
            if (this.ctx) this.ctx.close();
        } catch (e) {}
        this.osc1 = null;
        this.osc2 = null;
        this.ctx = null;
        this.gainNode = null;
    }
}

class WebRTCCallManager {
    constructor() {
        this.peerConnection = null;
        this.localStream = null;
        this.remoteUserId = null;
        this.isVideo = false;
        this.toneGen = new ToneGenerator();
        this.iceCandidatesQueue = [];
        this.isIncoming = false;
        this.isCalling = false;
        this.isCallActive = false;
        this.incomingOfferSdp = null;
        this.callId = null;
        this.wasAnswered = false;
        this.callerUserId = null;
        this.ringTimeout = null;
        this.dialTimeout = null;
        this._endingCall = false;
        this._disconnectTimer = null;

        this.overlay = null;
        this.avatar = null;
        this.userName = null;
        this.status = null;
        this.videosContainer = null;
        this.audioPulse = null;
        this.localVideo = null;
        this.remoteVideo = null;
        this.remoteAudio = null;

        this.declineBtn = null;
        this.acceptBtn = null;
        this.muteBtn = null;
        this.videoBtn = null;
        this.hangupBtn = null;
    }

    init() {
        this.overlay = document.getElementById('call-overlay');
        if (!this.overlay) return;

        this.avatar = document.getElementById('call-user-avatar');
        this.userName = document.getElementById('call-user-name');
        this.status = document.getElementById('call-status');
        this.videosContainer = document.getElementById('call-videos-container');
        this.audioPulse = document.getElementById('call-audio-pulse');
        this.localVideo = document.getElementById('local-video');
        this.remoteVideo = document.getElementById('remote-video');
        this.remoteAudio = document.getElementById('remote-audio');

        this.declineBtn = document.getElementById('decline-call-btn');
        this.acceptBtn = document.getElementById('accept-call-btn');
        this.muteBtn = document.getElementById('toggle-mute-btn');
        this.videoBtn = document.getElementById('toggle-video-btn');
        this.hangupBtn = document.getElementById('hangup-call-btn');

        this.declineBtn.onclick = () => this.declineCall();
        this.acceptBtn.onclick = () => this.acceptCall();
        this.muteBtn.onclick = () => this.toggleMute();
        this.videoBtn.onclick = () => this.toggleVideo();
        this.hangupBtn.onclick = () => this.hangupCall();

        this.bindChatCallButtons();
        this.registerSignalingListener();
    }

    registerSignalingListener() {
        if (!window.Echo || !window.authUserId) {
            console.warn('WebRTC calls need Reverb/Echo. Set BROADCAST_CONNECTION=reverb and run reverb:start.');
            return;
        }

        if (this._signalingBound) return;
        this._signalingBound = true;

        try {
            window.Echo.private(`user-signaling.${window.authUserId}`)
                .listen('.call.signal', (payload) => {
                    this.handleIncomingSignal(payload);
                });
        } catch (e) {
            console.warn('WebRTC signaling listener could not be registered.', e);
        }
    }

    bindChatCallButtons() {
        const wire = (buttonId, isVideo) => {
            const btn = document.getElementById(buttonId);
            if (!btn || btn.dataset.callBound === '1') return;
            btn.dataset.callBound = '1';

            btn.addEventListener('click', () => {
                const userId = parseInt(btn.dataset.targetUserId, 10);
                if (!userId) return;

                if (!window.Echo) {
                    alert('Voice/video calls need real-time server (Reverb). Ask admin to enable BROADCAST_CONNECTION=reverb.');
                    return;
                }

                this.startCall(userId, isVideo, {
                    name: btn.dataset.targetName || '',
                    avatar: btn.dataset.targetAvatar || '',
                });
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

    startDialTimeout() {
        this.clearCallTimeouts();
        this.dialTimeout = setTimeout(() => {
            if (this.isCalling && !this.wasAnswered) {
                this.hangupCall();
            }
        }, 60000);
    }

    async sendSignal(type, data = null) {
        if (!this.remoteUserId) return false;

        try {
            const res = await fetch('/chat/call/signal', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    to_user_id: this.remoteUserId,
                    type,
                    data: ['hangup', 'decline'].includes(type)
                        ? this.callMeta(data || {})
                        : data,
                }),
            });

            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                console.error('Call signal failed:', err.message || res.status);
                return false;
            }

            return true;
        } catch (e) {
            console.error('Failed to send call signal:', e);
            return false;
        }
    }

    setPeerInfo(peerInfo = {}) {
        if (peerInfo.name && this.userName) {
            this.userName.textContent = peerInfo.name;
        }
        if (peerInfo.avatar && this.avatar) {
            this.avatar.src = peerInfo.avatar;
        }
    }

    attachRemoteStream(stream) {
        if (this.isVideo && this.remoteVideo) {
            this.remoteVideo.srcObject = stream;
            this.remoteVideo.play().catch(() => {});
        }
        if (this.remoteAudio) {
            this.remoteAudio.srcObject = stream;
            this.remoteAudio.play().catch(() => {});
        }
    }

    async startCall(remoteUserId, isVideo = false, peerInfo = {}) {
        if (this.isCallActive || this.isCalling || this.isIncoming) return;

        this.isCalling = true;
        this.remoteUserId = remoteUserId;
        this.isVideo = isVideo;
        this.callId = crypto.randomUUID();
        this.wasAnswered = false;
        this.callerUserId = window.authUserId;
        this.startDialTimeout();

        this.showOverlay();
        this.setPeerInfo(peerInfo);
        this.status.textContent = 'Calling...';
        this.declineBtn.classList.add('hidden');
        this.acceptBtn.classList.add('hidden');
        this.hangupBtn.classList.remove('hidden');
        this.muteBtn.classList.add('hidden');
        this.videoBtn.classList.add('hidden');

        this.toneGen.startDial();

        try {
            this.localStream = await navigator.mediaDevices.getUserMedia({
                audio: true,
                video: isVideo,
            });

            if (isVideo && this.localVideo) {
                this.localVideo.srcObject = this.localStream;
            }

            this.createPeerConnection();

            const offer = await this.peerConnection.createOffer();
            await this.peerConnection.setLocalDescription(offer);

            const sent = await this.sendSignal('offer', {
                sdp: offer.sdp,
                isVideo,
                call_id: this.callId,
                caller_id: this.callerUserId,
            });
            if (!sent) {
                throw new Error('Could not reach call server.');
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
            if (event.streams?.[0]) {
                this.attachRemoteStream(event.streams[0]);
            }
        };

        this.peerConnection.onicecandidate = (event) => {
            if (event.candidate) {
                this.sendSignal('candidate', event.candidate);
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
                this.toneGen.stop();
                this.updateCallUI();
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
        const { from_user, type, data } = payload;

        try {
            switch (type) {
                case 'offer':
                    if (this.isCallActive || this.isCalling || this.isIncoming) {
                        this.remoteUserId = from_user.id;
                        await this.sendSignal('decline', {
                            reason: 'busy',
                            call_id: data.call_id,
                            caller_id: from_user.id,
                            is_video: !!data.isVideo,
                        });
                        this.remoteUserId = null;
                        return;
                    }
                    this.isIncoming = true;
                    this.remoteUserId = from_user.id;
                    this.isVideo = !!data.isVideo;
                    this.incomingOfferSdp = data.sdp;
                    this.callId = data.call_id || crypto.randomUUID();
                    this.callerUserId = from_user.id;
                    this.wasAnswered = false;
                    this.startRingTimeout();

                    this.showOverlay();
                    this.setPeerInfo({ name: from_user.name, avatar: from_user.avatar_url });
                    this.status.textContent = `Incoming ${this.isVideo ? 'Video' : 'Audio'} Call...`;

                    this.declineBtn.classList.remove('hidden');
                    this.acceptBtn.classList.remove('hidden');
                    this.hangupBtn.classList.add('hidden');
                    this.muteBtn.classList.add('hidden');
                    this.videoBtn.classList.add('hidden');

                    this.toneGen.startRing();
                    break;

                case 'answer':
                    if (!this.peerConnection) return;
                    await this.peerConnection.setRemoteDescription(new RTCSessionDescription({
                        type: 'answer',
                        sdp: data.sdp,
                    }));
                    await this.flushIceCandidates();
                    break;

                case 'candidate':
                    if (this.peerConnection && this.peerConnection.remoteDescription) {
                        await this.peerConnection.addIceCandidate(new RTCIceCandidate(data));
                    } else {
                        this.iceCandidatesQueue.push(data);
                    }
                    break;

                case 'decline':
                    if (!(this.isCallActive || this.isCalling || this.isIncoming)) break;
                    this.cleanup();
                    if (from_user?.id !== window.authUserId) {
                        alert('User is busy or declined the call.');
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

        this.toneGen.stop();
        this.acceptBtn.classList.add('hidden');
        this.declineBtn.classList.add('hidden');
        this.status.textContent = 'Connecting...';

        try {
            this.localStream = await navigator.mediaDevices.getUserMedia({
                audio: true,
                video: this.isVideo,
            });

            if (this.isVideo && this.localVideo) {
                this.localVideo.srcObject = this.localStream;
            }

            this.createPeerConnection();

            await this.peerConnection.setRemoteDescription(new RTCSessionDescription({
                type: 'offer',
                sdp: this.incomingOfferSdp,
            }));

            await this.flushIceCandidates();

            const answer = await this.peerConnection.createAnswer();
            await this.peerConnection.setLocalDescription(answer);
            await this.sendSignal('answer', { sdp: answer.sdp });
        } catch (e) {
            console.error('Failed to accept call:', e);
            this.cleanup();
            alert('Failed to connect call. Please check device permissions.');
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
        [this.remoteVideo, this.remoteAudio].forEach((element) => {
            if (!element?.srcObject) return;
            this.stopMediaStream(element.srcObject);
            element.srcObject = null;
        });
    }

    async endCall(signalType = 'hangup', extra = {}) {
        if (this._endingCall) return;
        this._endingCall = true;

        const remoteId = this.remoteUserId;

        try {
            if (remoteId) {
                await this.sendSignal(signalType, extra);
            }
        } catch (e) {
            console.error('Failed to send call end signal:', e);
        } finally {
            this.cleanup();
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
        if (audioTrack) {
            audioTrack.enabled = !audioTrack.enabled;
            this.muteBtn.classList.toggle('bg-red-600', !audioTrack.enabled);
            this.muteBtn.classList.toggle('hover:bg-red-700', !audioTrack.enabled);
        }
    }

    toggleVideo() {
        if (!this.localStream || !this.isVideo) return;
        const videoTrack = this.localStream.getVideoTracks()[0];
        if (videoTrack) {
            videoTrack.enabled = !videoTrack.enabled;
            this.videoBtn.classList.toggle('bg-red-600', !videoTrack.enabled);
            this.videoBtn.classList.toggle('hover:bg-red-700', !videoTrack.enabled);
        }
    }

    showOverlay() {
        this.overlay?.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    updateCallUI() {
        this.declineBtn.classList.add('hidden');
        this.acceptBtn.classList.add('hidden');
        this.hangupBtn.classList.remove('hidden');

        if (this.isVideo) {
            this.videosContainer?.classList.remove('hidden');
            this.audioPulse?.classList.add('hidden');
            this.videoBtn?.classList.remove('hidden');
        } else {
            this.videosContainer?.classList.add('hidden');
            this.audioPulse?.classList.remove('hidden');
            this.videoBtn?.classList.add('hidden');
        }
        this.muteBtn?.classList.remove('hidden');
    }

    cleanup() {
        this.toneGen.stop();
        this.clearCallTimeouts();
        this.clearDisconnectTimer();
        this.isIncoming = false;
        this.isCalling = false;
        this.isCallActive = false;
        this.incomingOfferSdp = null;
        this.callId = null;
        this.wasAnswered = false;
        this.callerUserId = null;

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

        if (this.localVideo) this.localVideo.srcObject = null;

        this.overlay?.classList.add('hidden');
        document.body.style.overflow = '';

        this.declineBtn?.classList.add('hidden');
        this.acceptBtn?.classList.add('hidden');
        this.muteBtn?.classList.add('hidden');
        this.videoBtn?.classList.add('hidden');
        this.hangupBtn?.classList.add('hidden');
        this.videosContainer?.classList.add('hidden');
        this.audioPulse?.classList.add('hidden');

        this.iceCandidatesQueue = [];
        this.remoteUserId = null;
    }
}

window.CallManager = new WebRTCCallManager();
