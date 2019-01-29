<?php

namespace app\Console\Helpers;


class ConsoleHelpers
{
    /**
     * Вывод в консоль сообщения
     *
     * @param $text
     * @param $color
     */
    public static function log($text, $color)
    {
        echo "\e[{$color}m" . $text . "\e[0m \n";
    }
}