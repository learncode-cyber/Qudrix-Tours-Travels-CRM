<?php
namespace App\Models\Concerns;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait Taggable
{
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}
