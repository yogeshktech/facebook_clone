<div class="bg-white rounded-lg shadow mt-4 overflow-hidden border">

    <div class="p-4 flex items-center gap-3">

        <div class="w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold">
            AD
        </div>

        <div>
            <div class="font-bold text-sm">Sponsored</div>
            <div class="text-xs text-gray-500">Advertisement</div>
        </div>

    </div>

    @if($ad->image_url)
        <img src="{{ $ad->image_url }}" alt="" class="w-full max-h-[450px] object-cover">
    @endif

    <div class="p-4">

        <h3 class="font-bold text-lg">{{ $ad->title }}</h3>

        <p class="text-gray-600 mt-2">{{ $ad->description }}</p>

        <button
            onclick="openLeadModal(
                {{ $ad->id }},
                '{{ addslashes($ad->title) }}',
                '{{ addslashes($ad->cta_text) }}'
            )"
            class="mt-4 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg">
            {{ $ad->cta_text }}
        </button>

    </div>

</div>