<?php

if (!function_exists('hg_content_image_url')) {
    function hg_content_image_url($imageUrl, string $fallback = ''): string
    {
        $img = trim(str_replace('\\', '/', (string)$imageUrl));
        if ($img === '' || strtolower($img) === 'null') {
            return $fallback;
        }

        if (strpos($img, '/public/') === 0) {
            $img = substr($img, 7);
        } elseif (strpos($img, 'public/') === 0) {
            $img = '/' . substr($img, 7);
        } elseif (strpos($img, 'img/') === 0) {
            $img = '/' . $img;
        }

        return $img;
    }
}
