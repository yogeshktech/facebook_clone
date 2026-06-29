<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum video upload size (MB)
    |--------------------------------------------------------------------------
    |
    | Must be <= server PHP post_max_size and nginx client_max_body_size.
    | Live server: see deploy/README.md (recommended: 110M post_max_size, 128M nginx).
    |
    */

    'max_video_mb' => (int) env('MAX_VIDEO_UPLOAD_MB', 100),

    'max_video_kb' => (int) env('MAX_VIDEO_UPLOAD_MB', 100) * 1024,

];
