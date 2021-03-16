<?php

namespace Marquee\Core\String;

final class Util
{
    public static function ToSnakeCase(string $input): string
    {
        return strtolower(str_replace(' ', '_', Util::PascalToWords($input)));
    }

    public static function PascalToWords(string $input): string
    {
        return preg_replace('/([a-z])([A-Z])/', '$1 $2', $input);
    }
}