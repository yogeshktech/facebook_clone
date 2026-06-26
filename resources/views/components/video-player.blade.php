@props(['src', 'class' => 'w-full rounded-lg mb-3 max-h-96'])

<video
    src="{{ $src }}"
    controls
    controlsList="nodownload noplaybackrate noremoteplayback"
    disablePictureInPicture
    playsinline
    oncontextmenu="return false;"
    {{ $attributes->merge(['class' => $class]) }}
></video>
