<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['key', 'label_de', 'label_en', 'position'];

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
