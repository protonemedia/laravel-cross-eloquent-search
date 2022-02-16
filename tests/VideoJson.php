<?php

namespace ProtoneMedia\LaravelCrossEloquentSearch\Tests;

use Illuminate\Database\Eloquent\Model;

class VideoJson extends Model
{
    protected $table = 'videos';

    protected $casts = ['title' => 'array'];

    public function searchType()
    {
        return 'awesome_video';
    }
}
