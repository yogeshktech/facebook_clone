@props([
    'size' => 'md',
    'showName' => false,
    'showTagline' => false,
])

@php
    $heights = [
        'xs' => 'h-8',
        'sm' => 'h-10',
        'md' => 'h-20',
        'lg' => 'h-28',
    ];
    $height = $heights[$size] ?? $heights['md'];
@endphp

<div {{ $attributes->merge(['class' => 'inline-flex flex-col items-center']) }}>
    <img src="{{ asset('images/newbook-logo.jpg') }}" alt="NEWBOOK" class="{{ $height }} w-auto object-contain flex-shrink-0" width="auto" height="auto">
    @if($showTagline)
        <p class="text-[10px] sm:text-xs text-gray-500 tracking-widest mt-1 uppercase">New Chapter, New Knowledge</p>
    @endif
</div>
