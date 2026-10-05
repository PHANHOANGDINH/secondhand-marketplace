<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SlugGenerator
{
    public function generate(Builder $query, string $name, int $maxLength): string
    {
        $base = substr(Str::slug($name) ?: 'item', 0, $maxLength - 12);
        $slug = $base;
        for ($suffix = 2; (clone $query)->where('slug', $slug)->exists(); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
