@props([
    'size' => 'md',
    'showName' => false,
])

@php
    $sizes = [
        'sm' => ['icon' => 'w-10 h-10 text-lg', 'name' => 'text-lg'],
        'md' => ['icon' => 'w-16 h-16 text-3xl', 'name' => 'text-3xl'],
    ];
    $sz = $sizes[$size] ?? $sizes['md'];
@endphp

<div {{ $attributes->merge(['class' => 'inline-flex items-center gap-2']) }}>
    <div class="{{ $sz['icon'] }} bg-gradient-to-br from-indigo-500 to-violet-600 rounded-full flex items-center justify-center shadow-sm flex-shrink-0">
        <span class="text-white font-bold leading-none">N</span>
    </div>
    @if($showName)
        <span class="{{ $sz['name'] }} font-bold bg-gradient-to-r from-indigo-600 to-violet-600 bg-clip-text text-transparent">
            Newbook
        </span>
    @endif
</div>
