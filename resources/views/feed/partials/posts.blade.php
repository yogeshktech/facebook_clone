@php
    $postIndex = $offset ?? 0;
@endphp

@foreach($posts as $post)

    {{-- Post Card --}}
    @include('components.post-card', ['post' => $post])

    @php
        $postIndex++;
    @endphp

    {{-- Advertisement after every 5 posts --}}
    @if(
        $postIndex % 5 == 0 &&
        isset($activeAds) &&
        $activeAds->isNotEmpty()
    )

        @php
            $adIndex = (int)(($postIndex / 5) - 1) % $activeAds->count();
            $ad = $activeAds[$adIndex];
        @endphp

        @include('feed.partials.ad-card', ['ad' => $ad])

    @endif

    {{-- Reels after every 10 posts --}}
    @if(
        $postIndex % 10 == 0 &&
        isset($reels) &&
        $reels->isNotEmpty()
    )

        @include('feed.partials.reels-strip', [
            'reels' => $reels
        ])

    @endif

@endforeach


@if($posts->hasMorePages())

<div
    id="feed-next-page"
    data-url="{{ $posts->nextPageUrl() }}"
    data-offset="{{ $postIndex }}">
</div>

@endif