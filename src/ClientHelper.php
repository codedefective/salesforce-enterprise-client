<?php


if (!function_exists('isDate')){
    function isDate($value): bool
    {
        if (!$value) {
            return false;
        } else {
            $date = date_parse($value);
            if($date['error_count'] == 0 && $date['warning_count'] == 0){
                return checkdate($date['month'], $date['day'], $date['year']);
            } else {
                return false;
            }
        }
    }
}

if (!function_exists('strpos_array')){
    function strpos_array($haystack, $needle, $offset=0): bool
    {
        if(!is_array($needle)) $needle = [$needle];
        foreach($needle as $query) {
            if(strpos($haystack, $query, $offset) !== false) return true; // stop on first true result
        }
        return false;
    }
}
