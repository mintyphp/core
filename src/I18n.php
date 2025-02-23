<?php

namespace MintyPHP;

class I18n
{
    private static $strings = [];

    public static $domain = 'default';
    public static $locale = '';

    public static function price($price, $minDecimals = 2, $maxDecimals = 2): string
    {
        if ($price === null) return '';
        return "€ " . self::currency($price, $minDecimals, $maxDecimals);
    }

    public static function currency($currency, $minDecimals = 2, $maxDecimals = 2): string
    {
        if ($currency === null) return '';

        $formats = [
            'nl' => [
                'thousandSeparator' => '.',
                'decimalSeparator' => ',',
            ],
        ];

        $number = rtrim(sprintf("%0.{$maxDecimals}F", $currency), '0');
        $decimalPos = strpos($number, '.');
        if ($decimalPos === false) {
            $number .= '.';
        }
        list($whole, $fraction) = explode('.', $number);
        if ($number < 0) {
            $sign = '-';
            $whole = -1 * (int)$whole;
        } else {
            $sign = '';
        }
        $whole = (string) $whole;
        $format = $formats[self::$locale] ?? $formats['nl'];
        $whole = strrev(implode($format['thousandSeparator'], str_split(strrev($whole), 3)));
        $fraction = str_pad($fraction, $minDecimals, '0');
        return $sign . $whole . $format['decimalSeparator'] . $fraction;
    }

    public static function date($str): string
    {
        return $str ? self::format('date', "$str") : '';
    }

    public static function dateUtc($str): string
    {
        return $str ? self::format('date', "$str UTC") : '';
    }


    public static function datetime($str): string
    {
        return $str ? self::format('datetime', "$str") : '';
    }

    public static function datetimeUtc($str): string
    {
        return $str ? self::format('datetime', "$str UTC") : '';
    }

    public static function time(int $hours, int $minutes, int $seconds = 0): string
    {
        return self::format('time', date('Y-m-d ') . sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds));
    }

    public static function timeUtc(int $hours, int $minutes, int $seconds = 0): string
    {
        return self::format('time', date('Y-m-d ') . sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds) . 'UTC');
    }

    public static function weekDay(int $dayOfWeek): string
    {
        $weekDays = [
            'nl' => ['zondag', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag', 'zondag'],
        ];
        return $weekDays[self::$locale][$dayOfWeek] ?? $weekDays['nl'][$dayOfWeek];
    }

    public static function monthName(int $monthOfYear): string
    {
        $monthNames = [
            'nl' => ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'],
        ];
        return $monthNames[self::$locale][$monthOfYear - 1] ?? $monthNames['nl'][$monthOfYear - 1];
    }

    private static function format(string $type, string $str): string
    {
        $formats = [
            'nl' => [
                'date' => 'd-m-Y',
                'datetime' => 'd-m-Y H:i:s',
                'time' => 'H:i:s',
            ],
        ];
        $format = $formats[self::$locale] ?? $formats['nl'];
        return date($format[$type], strtotime($str));
    }

    public static function translate(string $id)
    {
        // read from disk or cache
        if (!isset(self::$strings[self::$domain][self::$locale])) {
            // load from disk
            $filename = 'i18n/' . self::$domain . '_' . self::$locale . '.json';
            if (file_exists($filename)) {
                self::$strings[self::$domain][self::$locale] = json_decode(file_get_contents($filename), true);
            }
        }
        // lookup id
        return self::$strings[self::$domain][self::$locale][$id] ?? $id;
    }
}
