<?php

if (!function_exists('hg_character_avatar_fallback_by_gender')) {
    function hg_character_avatar_fallback_by_gender($gender): string
    {
        $g = strtolower(trim((string)$gender));
        if (in_array($g, ['m', 'male', 'h', 'hombre', 'masculino', 'man', '1'], true)) {
            return '/img/ui/avatar/avatar_nadie_1.png';
        }
        if (in_array($g, ['f', 'female', 'mujer', 'femenino', 'woman', '2'], true)) {
            return '/img/ui/avatar/avatar_nadie_2.png';
        }
        return '/img/ui/avatar/avatar_nadie_3.png';
    }
}

if (!function_exists('hg_character_avatar_url')) {
    function hg_character_avatar_url($imageUrl, $gender): string
    {
        $img = trim((string)$imageUrl);
        if ($img !== '' && strtolower($img) !== 'null') {
            if (strpos($img, '/public/') === 0) {
                return substr($img, 7);
            }
            return $img;
        }
        return hg_character_avatar_fallback_by_gender($gender);
    }
}
