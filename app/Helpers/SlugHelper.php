<?php

namespace App\Helpers;

class SlugHelper
{
    private const CYRILLIC_MAP = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
        'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    public static function brandToSlug(?string $brand): string
    {
        // Транслитерация ДО отсечения "мусора" — иначе кириллические бренды
        // (в parts_catalog их 1200+, напр. "СТК", "АвтоВаз") просто вырезались
        // бы регэкспом ниже и схлопывались в пустой/задублированный слаг.
        $translit = strtr(mb_strtolower(trim((string) $brand)), self::CYRILLIC_MAP);

        $slug = str_replace([' ', '/', '&', '+'], '-', $translit);
        $slug = preg_replace('/[^A-Za-z0-9_-]/', '', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }
}