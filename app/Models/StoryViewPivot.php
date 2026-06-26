<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class StoryViewPivot extends Pivot
{
    protected $table = 'story_views';

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }
}
