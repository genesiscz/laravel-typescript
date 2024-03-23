<?php

if (!function_exists('indent')) {
    function indent($str, $spaces = 4): string
    {
        $parts = array_filter(explode("\n", $str));
        $parts = array_map(function ($part) use ($spaces) {
            return str_repeat(' ', $spaces).$part;
        }, $parts);
        return implode("\n", $parts);
    }
}

if (!function_exists('dedent')) {
    function dedent($str, $spaces = 4): string
    {
        $parts = array_filter(explode("\n", $str), function($part) {
            return trim($part);
        });
        $remove = min(array_map(function($part) use ($spaces) {
            preg_match('#^ *#', $part, $matches);
            return min(strlen($matches[0]), $spaces);
        }, $parts));
        $parts = array_map(function($part) use ($remove) {
            return substr($part, $remove);
        }, $parts);
        return implode("\n", $parts);
    }
}
