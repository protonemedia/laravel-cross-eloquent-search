<?php

namespace ProtoneMedia\LaravelCrossEloquentSearch\Tests;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    public function blogs()
    {
        return $this->hasMany(Blog::class);
    }
}
