@if(isset($reels) && $reels->isNotEmpty())

<div class="bg-white rounded-lg shadow mt-4 p-4">

    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold">🎬 Reels</h2>

        <a href="{{ route('reels.index') }}"
           class="text-blue-600 text-sm font-semibold">
            See All
        </a>
    </div>

    <div class="swiper reelsSwiper">

        <div class="swiper-wrapper">

            @foreach($reels as $reel)

                <div class="swiper-slide">

                    <a href="{{ route('reels.index') }}"
                       class="relative block rounded-xl overflow-hidden bg-black">

                        <video
                            src="{{ $reel->media_url }}"
                            muted
                            playsinline
                            preload="metadata"
                            class="w-full h-72 object-cover">
                        </video>

                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent"></div>

                        <div class="absolute bottom-3 left-3 right-3 flex items-center gap-2">

                            <img
                                src="{{ $reel->user->avatar_url }}"
                                class="w-9 h-9 rounded-full border-2 border-white object-cover">

                            <div class="text-white">

                                <div class="font-semibold text-sm truncate">
                                    {{ $reel->user->name }}
                                </div>

                                <div class="text-xs">
                                    ❤️ {{ $reel->likes_count }}
                                </div>

                            </div>

                        </div>

                    </a>

                </div>

            @endforeach

        </div>

    </div>

</div>

@endif