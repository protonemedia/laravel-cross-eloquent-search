<?php

namespace ProtoneMedia\LaravelCrossEloquentSearch\Tests;

use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function blogs()
    {
        return $this->hasMany(Blog::class);
    }

    public function pages()
    {
        return $this->hasMany(Page::class);
    }
}
