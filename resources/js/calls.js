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

// WebRTC Call Manager class
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

        // UI references
        this.overlay = null;
        this.avatar = null;
        this.userName = null;
        this.status = null;
        this.videosContainer = null;
        this.audioPulse = null;
        this.localVideo = null;
        this.remoteVideo = null;

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

        // Listen for Echo Events
        if (window.Echo && window.authUserId) {
            try {
                window.Echo.private(`user-signaling.${window.authUserId}`)
                    .listen('.call.signal', (payload) => {
                        this.handleIncomingSignal(payload);
                    });
            } catch (e) {
                console.warn('WebRTC signaling listener could not be registered on Echo private channel.');
            }
        }
    }

    async sendSignal(type, data = null) {
        if (!this.remoteUserId) return;
        try {
            await fetch('/chat/call/signal', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    to_user_id: this.remoteUserId,
                    type: type,
                    data: data
                })
            });
        } catch (e) {
            console.error('Failed to send call signal:', e);
        }
    }

    async startCall(remoteUserId, isVideo = false) {
        if (this.isCallActive || this.isCalling || this.isIncoming) return;
        this.isCalling = true;
        this.remoteUserId = remoteUserId;
        this.isVideo = isVideo;

        this.showOverlay();
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
                video: isVideo
            });

            if (isVideo && this.localVideo) {
                this.localVideo.srcObject = this.localStream;
            }

            this.createPeerConnection();

            const offer = await this.peerConnection.createOffer();
            await this.peerConnection.setLocalDescription(offer);
            await this.sendSignal('offer', { sdp: offer.sdp, isVideo: isVideo });

        } catch (e) {
            console.error('Failed to start call:', e);
            this.cleanup();
            alert('Could not start call. Please check your camera/microphone permissions.');
        }
    }

    createPeerConnection() {
        const peerConfig = {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' }
            ]
        };

        this.peerConnection = new RTCPeerConnection(peerConfig);

        if (this.localStream) {
            this.localStream.getTracks().forEach(track => {
                this.peerConnection.addTrack(track, this.localStream);
            });
        }

        this.peerConnection.ontrack = (event) => {
            if (this.remoteVideo) {
                this.remoteVideo.srcObject = event.streams[0];
            }
        };

        this.peerConnection.onicecandidate = (event) => {
            if (event.candidate) {
                this.sendSignal('candidate', event.candidate);
            }
        };

        this.peerConnection.onconnectionstatechange = () => {
            if (this.peerConnection.connectionState === 'connected') {
                this.status.textContent = 'Connected';
                this.isCallActive = true;
                this.isCalling = false;
                this.toneGen.stop();
                this.updateCallUI();
            } else if (
                this.peerConnection.connectionState === 'disconnected' ||
                this.peerConnection.connectionState === 'failed' ||
                this.peerConnection.connectionState === 'closed'
            ) {
                this.cleanup();
            }
        };
    }

    async handleIncomingSignal(payload) {
        const { from_user, type, data } = payload;

        switch (type) {
            case 'offer':
                if (this.isCallActive || this.isCalling || this.isIncoming) {
                    this.remoteUserId = from_user.id;
                    await this.sendSignal('decline', { reason: 'busy' });
                    this.remoteUserId = null;
                    return;
                }
                this.isIncoming = true;
                this.remoteUserId = from_user.id;
                this.isVideo = data.isVideo;
                this.incomingOfferSdp = data.sdp;

                this.showOverlay();
                this.avatar.src = from_user.avatar_url || '';
                this.userName.textContent = from_user.name;
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
                    sdp: data.sdp
                }));
                while (this.iceCandidatesQueue.length > 0) {
                    const cand = this.iceCandidatesQueue.shift();
                    await this.peerConnection.addIceCandidate(new RTCIceCandidate(cand));
                }
                break;

            case 'candidate':
                if (this.peerConnection && this.peerConnection.remoteDescription) {
                    await this.peerConnection.addIceCandidate(new RTCIceCandidate(data));
                } else {
                    this.iceCandidatesQueue.push(data);
                }
                break;

            case 'decline':
                this.cleanup();
                alert('User is busy or declined the call.');
                break;

            case 'hangup':
                this.cleanup();
                break;
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
                video: this.isVideo
            });

            if (this.isVideo && this.localVideo) {
                this.localVideo.srcObject = this.localStream;
            }

            this.createPeerConnection();

            await this.peerConnection.setRemoteDescription(new RTCSessionDescription({
                type: 'offer',
                sdp: this.incomingOfferSdp
            }));

            while (this.iceCandidatesQueue.length > 0) {
                const cand = this.iceCandidatesQueue.shift();
                await this.peerConnection.addIceCandidate(new RTCIceCandidate(cand));
            }

            const answer = await this.peerConnection.createAnswer();
            await this.peerConnection.setLocalDescription(answer);
            await this.sendSignal('answer', { sdp: answer.sdp });

        } catch (e) {
            console.error('Failed to accept call:', e);
            this.cleanup();
            alert('Failed to connect call. Please check device permissions.');
        }
    }

    async declineCall() {
        if (!this.remoteUserId) return;
        await this.sendSignal('decline');
        this.cleanup();
    }

    async hangupCall() {
        if (!this.remoteUserId) return;
        await this.sendSignal('hangup');
        this.cleanup();
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
        if (this.overlay) {
            this.overlay.classList.remove('hidden');
        }
    }

    updateCallUI() {
        this.declineBtn.classList.add('hidden');
        this.acceptBtn.classList.add('hidden');
        this.hangupBtn.classList.remove('hidden');

        if (this.isVideo) {
            this.videosContainer.classList.remove('hidden');
            this.audioPulse.classList.add('hidden');
            this.videoBtn.classList.remove('hidden');
        } else {
            this.videosContainer.classList.add('hidden');
            this.audioPulse.classList.remove('hidden');
            this.videoBtn.classList.add('hidden');
        }
        this.muteBtn.classList.remove('hidden');
    }

    cleanup() {
        this.toneGen.stop();
        this.isIncoming = false;
        this.isCalling = false;
        this.isCallActive = false;
        this.incomingOfferSdp = null;

        if (this.peerConnection) {
            this.peerConnection.close();
            this.peerConnection = null;
        }

        if (this.localStream) {
            this.localStream.getTracks().forEach(track => track.stop());
            this.localStream = null;
        }

        if (this.localVideo) this.localVideo.srcObject = null;
        if (this.remoteVideo) this.remoteVideo.srcObject = null;

        if (this.overlay) {
            this.overlay.classList.add('hidden');
        }

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
