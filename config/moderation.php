<?php

return [
    'bad_words' => [
        'porn', 'xxx', 'sex', 'naked', 'nudity', 'nsfw', 'vulgar', 'pussy', 'dick', 'boobs', 'adult video',
        // Add more words as needed
    ],
    'thresholds' => [
        'nudity_raw' => 0.50,
        'nudity_partial' => 0.70,
        'gore' => 0.50,
    ]
];
