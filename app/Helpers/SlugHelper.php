<?php

namespace App\Helpers;

class SlugHelper
{
    public static function brandToSlug(string $brand): string
    {
        $slug = str_replace([' ', '/', '&', '+'], '-', trim($brand));
        $slug = preg_replace('/[^A-Za-z0-9_-]/', '', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return strtolower(trim($slug, '-'));
    }
}