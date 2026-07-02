<?php

return [

    'ice_servers' => array_values(array_filter(
        json_decode(env('WEBRTC_ICE_SERVERS', ''), true) ?: [
            ['urls' => 'stun:stun.l.google.com:19302'],
            ['urls' => 'stun:stun1.l.google.com:19302'],
        ]
    )),

];
