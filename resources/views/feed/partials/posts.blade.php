@php $postIndex = 0; @endphp

@foreach($posts as $post)

    @include('components.post-card', ['post' => $post])

    {{-- Inject an ad after every 2nd post (optional) --}}
    @if($postIndex == 1 && isset($activeAds) && !$activeAds->isEmpty())
        @php
            $ad = $activeAds->first();
        @endphp

        <div class="bg-white rounded-lg shadow p-4 border border-indigo-100/50 relative space-y-3">

            <div class="flex items-center gap-2">
                <div class="w-9 h-9 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold text-sm">
                    Ad
                </div>

                <div>
                    <div class="flex items-center gap-1">
                        <span class="font-bold text-sm">
                            Sponsored Campaign
                        </span>

                        <span class="bg-indigo-100 text-indigo-700 text-[10px] font-bold px-2 py-0.5 rounded-full">
                            AD
                        </span>
                    </div>

                    <span class="text-xs text-gray-500">
                        Sponsored
                    </span>
                </div>
            </div>

            <p class="text-sm">
                {{ $ad->description }}
            </p>

            @if($ad->image_url)
                <img
                    src="{{ $ad->image_url }}"
                    class="rounded-lg w-full">
            @endif

            <div class="flex justify-between items-center border rounded-lg p-3">

                <div>
                    <h4 class="font-bold">
                        {{ $ad->title }}
                    </h4>

                    <small>{{ url('/') }}</small>
                </div>

                <button
                    onclick="openLeadModal({{ $ad->id }}, '{{ addslashes($ad->title) }}', '{{ addslashes($ad->cta_text) }}')"
                    class="btn-primary">

                    {{ $ad->cta_text }}

                </button>

            </div>

        </div>

    @endif

    @php $postIndex++; @endphp

@endforeach

@if($posts->hasMorePages())

    <div
        id="feed-next-page"
        data-url="{{ $posts->nextPageUrl() }}">
    </div>

@endif