<?php

namespace MintyPHP;

class I18n
{
    private static $strings = [];

    public static $domain = 'default';
    public static $locale = ''; // should be either: 'en', 'de', 'fr', 'nl'

    public static $formats = [
        'currency' => [
            'en' => ['thousandSeparator' => ',', 'decimalSeparator' => '.'],
            'de' => ['thousandSeparator' => '.', 'decimalSeparator' => ','],
            'fr' => ['thousandSeparator' => '.', 'decimalSeparator' => ','],
            'nl' => ['thousandSeparator' => '.', 'decimalSeparator' => ','],
        ],
        'datetime' => [
            'en' => ['date' => 'm/d/Y', 'datetime' => 'm/d/Y g:i:s A', 'time' => 'g:i:s A'],
            'de' => ['date' => 'd.m.Y', 'datetime' => 'd.m.Y H:i:s', 'time' => 'H:i:s'],
            'fr' => ['date' => 'd-m-Y', 'datetime' => 'd-m-Y H:i:s', 'time' => 'H:i:s'],
            'nl' => ['date' => 'd-m-Y', 'datetime' => 'd-m-Y H:i:s', 'time' => 'H:i:s'],
        ],
        'weekDays' => [
            'en' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            'de' => ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'],
            'fr' => ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'],
            'nl' => ['zondag', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag', 'zondag'],
        ],
        'monthNames' => [
            'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
            'de' => ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
            'fr' => ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],
            'nl' => ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'],
        ]

    ];

    public static function price($price, $minDecimals = 2, $maxDecimals = 2): string
    {
        if ($price === null) return '';
        return "€ " . self::currency($price, $minDecimals, $maxDecimals);
    }

    public static function currency($currency, $minDecimals = 2, $maxDecimals = 2): string
    {
        if ($currency === null) return '';

        $formats = self::$formats['currency'];

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
        return $str ? self::formatDateTime('date', "$str") : '';
    }

    public static function dateUtc($str): string
    {
        return $str ? self::formatDateTime('date', "$str UTC") : '';
    }


    public static function datetime($str): string
    {
        return $str ? self::formatDateTime('datetime', "$str") : '';
    }

    public static function datetimeUtc($str): string
    {
        return $str ? self::formatDateTime('datetime', "$str UTC") : '';
    }

    public static function time(int $hours, int $minutes, int $seconds = 0): string
    {
        return self::formatDateTime('time', date('Y-m-d ') . sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds));
    }

    public static function timeUtc(int $hours, int $minutes, int $seconds = 0): string
    {
        return self::formatDateTime('time', date('Y-m-d ') . sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds) . 'UTC');
    }

    public static function weekDay(int $dayOfWeek): string
    {
        $weekDays = self::$formats['weekDays'];
        return $weekDays[self::$locale][$dayOfWeek] ?? $weekDays['nl'][$dayOfWeek];
    }

    public static function monthName(int $monthOfYear): string
    {
        $monthNames = self::$formats['monthNames'];
        return $monthNames[self::$locale][$monthOfYear - 1] ?? $monthNames['nl'][$monthOfYear - 1];
    }

    private static function formatDateTime(string $type, string $str): string
    {
        $formats = self::$formats['datetime'];
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
