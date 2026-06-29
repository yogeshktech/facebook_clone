{{-- PWA Install Modal --}}
<div id="pwa-install-modal" class="hidden fixed inset-0 z-[60] flex items-end sm:items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="pwa-install-title">
    <div class="absolute inset-0 bg-black/60" onclick="dismissPwaModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm z-10 overflow-hidden animate-slide-up">
        <div class="bg-[#002E5D] p-6 text-white text-center">
            <img src="{{ asset('images/apple-touch-icon.png') }}" alt="NEWBOOK" class="w-20 h-20 rounded-2xl mx-auto mb-3 shadow-lg bg-white p-1 object-contain">
            <h2 id="pwa-install-title" class="text-xl font-bold">Install NEWBOOK</h2>
            <p class="text-white/90 text-sm mt-1">New Chapter, New Knowledge</p>
        </div>
        <div class="p-5">
            <p id="pwa-install-message" class="text-sm text-gray-600 text-center mb-5">
                Install the app for a faster experience — works offline too!
            </p>
            <div id="pwa-install-actions" class="flex flex-col gap-2">
                <button type="button" id="pwa-install-confirm" onclick="installPwa()"
                    class="w-full bg-fb-blue text-white font-semibold py-3 rounded-xl hover:bg-fb-blue-dark transition">
                    Install App
                </button>
                <button type="button" onclick="dismissPwaModal()"
                    class="w-full text-gray-500 font-medium py-2 rounded-xl hover:bg-gray-100 transition">
                    Not now
                </button>
            </div>
            <div id="pwa-ios-instructions" class="hidden text-sm text-gray-600 space-y-2">
                <p class="font-semibold text-gray-800">Install on iPhone/iPad:</p>
                <ol class="list-decimal list-inside space-y-1 text-gray-600">
                    <li>Tap the <strong>Share</strong> button in Safari</li>
                    <li>Scroll down and tap <strong>Add to Home Screen</strong></li>
                    <li>Tap <strong>Add</strong> to confirm</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<style>
    @keyframes slide-up {
        from { transform: translateY(100%); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    .animate-slide-up { animation: slide-up 0.3s ease-out; }
</style>
