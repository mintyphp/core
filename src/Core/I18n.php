<?php

namespace MintyPHP\Core;

/**
 * Internationalization and localization for MintyPHP
 * 
 * Provides formatting for currency, dates, times, and translation management
 * for multiple locales.
 */
class I18n
{
    /**
     * Static configuration parameters
     */
    public static string $__domain = 'default';
    public static string $__locale = '';
    public static string $__defaultLocale = 'en';

    /**
     * Actual configuration parameters
     */
    private readonly string $domain;
    private readonly string $locale;
    private readonly string $defaultLocale;

    /**
     * Translation strings cache
     * @var array<string, array<string, array<string, string>>>
     */
    private array $strings = [];

    /**
     * Format definitions for different locales
     * @var array<string, array<string, array<string, string>|array<int, string>>>
     */
    private array $formats = [
        'currency' => [
            'en' => ['thousandSeparator' => ',', 'decimalSeparator' => '.'],
            'de' => ['thousandSeparator' => '.', 'decimalSeparator' => ','],
            'fr' => ['thousandSeparator' => '.', 'decimalSeparator' => ','],
            'nl' => ['thousandSeparator' => '.', 'decimalSeparator' => ','],
            'es' => ['thousandSeparator' => '.', 'decimalSeparator' => ','],
            'it' => ['thousandSeparator' => '.', 'decimalSeparator' => ','],
            'pt' => ['thousandSeparator' => '.', 'decimalSeparator' => ','],
            'at' => ['thousandSeparator' => '.', 'decimalSeparator' => ','],
            'dk' => ['thousandSeparator' => '.', 'decimalSeparator' => ','],
            'se' => ['thousandSeparator' => ' ', 'decimalSeparator' => ','],
            'fi' => ['thousandSeparator' => ' ', 'decimalSeparator' => ','],
            'pl' => ['thousandSeparator' => ' ', 'decimalSeparator' => ','],
            'bg' => ['thousandSeparator' => ' ', 'decimalSeparator' => ','],
            'ro' => ['thousandSeparator' => '.', 'decimalSeparator' => ','],
            'lv' => ['thousandSeparator' => ' ', 'decimalSeparator' => ','],
            'lt' => ['thousandSeparator' => ' ', 'decimalSeparator' => ','],
            'ee' => ['thousandSeparator' => ' ', 'decimalSeparator' => ','],

        ],
        'datetime' => [
            'en' => ['date' => 'm/d/Y', 'datetime' => 'm/d/Y H:i:s', 'time' => 'H:i:s'],
            'de' => ['date' => 'd.m.Y', 'datetime' => 'd.m.Y H:i:s', 'time' => 'H:i:s'],
            'fr' => ['date' => 'd-m-Y', 'datetime' => 'd-m-Y H:i:s', 'time' => 'H:i:s'],
            'nl' => ['date' => 'd-m-Y', 'datetime' => 'd-m-Y H:i:s', 'time' => 'H:i:s'],
            'es' => ['date' => 'd/m/Y', 'datetime' => 'd/m/Y H:i:s', 'time' => 'H:i:s'],
            'it' => ['date' => 'd/m/Y', 'datetime' => 'd/m/Y H:i:s', 'time' => 'H:i:s'],
            'pt' => ['date' => 'd/m/Y', 'datetime' => 'd/m/Y H:i:s', 'time' => 'H:i:s'],
            'dk' => ['date' => 'd-m-Y', 'datetime' => 'd-m-Y H:i:s', 'time' => 'H:i:s'],
            'se' => ['date' => 'Y-m-d', 'datetime' => 'Y-m-d H:i:s', 'time' => 'H:i:s'],
            'fi' => ['date' => 'd.m.Y', 'datetime' => 'd.m.Y H:i:s', 'time' => 'H:i:s'],
            'pl' => ['date' => 'Y-m-d', 'datetime' => 'Y-m-d H:i:s', 'time' => 'H:i:s'],
            'bg' => ['date' => 'd.m.Y', 'datetime' => 'd.m.Y H:i:s', 'time' => 'H:i:s'],
            'ro' => ['date' => 'd.m.Y', 'datetime' => 'd.m.Y H:i:s', 'time' => 'H:i:s'],
            'lv' => ['date' => 'Y.m.d.', 'datetime' => 'Y.m.d. H:i:s', 'time' => 'H:i:s'],
            'lt' => ['date' => 'Y-m-d', 'datetime' => 'Y-m-d H:i:s', 'time' => 'H:i:s'],
            'ee' => ['date' => 'd.m.Y', 'datetime' => 'd.m.Y H:i:s', 'time' => 'H:i:s'],
        ],
        'weekDays' => [
            'en' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            'de' => ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'],
            'fr' => ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'],
            'nl' => ['zondag', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag', 'zondag'],
            'es' => ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'],
            'it' => ['domenica', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato', 'domenica'],
            'pt' => ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado', 'domingo'],
            'dk' => ['søndag', 'mandag', 'tirsdag', 'onsdag', 'torsdag', 'fredag', 'lørdag', 'søndag'],
            'se' => ['söndag', 'måndag', 'tisdag', 'onsdag', 'torsdag', 'fredag', 'lördag', 'söndag'],
            'fi' => ['sunnuntai', 'maanantai', 'tiistai', 'keskiviikko', 'torstai', 'perjantai', 'lauantai', 'sunnuntai'],
            'pl' => ['niedziela', 'poniedziałek', 'wtorek', 'środa', 'czwartek', 'piątek', 'sobota', 'niedziela'],
            'bg' => ['неделя', 'понеделник', 'вторник', 'сряда', 'четвъртък', 'петък', 'събота', 'неделя'],
            'ro' => ['duminică', 'luni', 'marți', 'miercuri', 'joi', 'vineri', 'sâmbătă', 'duminică'],
            'lv' => ['svētdiena', 'pirmdiena', 'otrdiena', 'trešdiena', 'ceturtdiena', 'piektdiena', 'sestdiena', 'svētdiena'],
            'lt' => ['sekmadienis', 'pirmadienis', 'antradienis', 'trečiadienis', 'ketvirtadienis', 'penktadienis', 'šeštadienis', 'sekmadienis'],
            'ee' => ['pühapäev', 'esmaspäev', 'teisipäev', 'kolmapäev', 'neljapäev', 'reede', 'laupäev', 'pühapäev'],
        ],
        'monthNames' => [
            'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
            'de' => ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
            'fr' => ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],
            'nl' => ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'],
            'es' => ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
            'it' => ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'],
            'pt' => ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'],
            'dk' => ['januar', 'februar', 'marts', 'april', 'maj', 'juni', 'juli', 'august', 'september', 'oktober', 'november', 'december'],
            'se' => ['januari', 'februari', 'mars', 'april', 'maj', 'juni', 'juli', 'augusti', 'september', 'oktober', 'november', 'december'],
            'fi' => ['tammikuu', 'helmikuu', 'maaliskuu', 'huhtikuu', 'toukokuu', 'kesäkuu', 'heinäkuu', 'elokuu', 'syyskuu', 'lokakuu', 'marraskuu', 'joulukuu'],
            'pl' => ['styczeń', 'luty', 'marzec', 'kwiecień', 'maj', 'czerwiec', 'lipiec', 'sierpień', 'wrzesień', 'październik', 'listopad', 'grudzień'],
            'bg' => ['януари', 'февруари', 'март', 'април', 'май', 'юни', 'юли', 'август', 'септември', 'октомври', 'ноември', 'декември'],
            'ro' => ['ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'],
            'lv' => ['janvāris', 'februāris', 'marts', 'aprīlis', 'maijs', 'jūnijs', 'jūlijs', 'augusts', 'septembris', 'oktobris', 'novembris', 'decembris'],
            'lt' => ['sausis', 'vasaris', 'kovas', 'balandis', 'gegužė', 'birželis', 'liepa', 'rugpjūtis', 'rugsėjis', 'spalis', 'lapkritis', 'gruodis'],
            'ee' => ['jaanuar', 'veebruar', 'märts', 'aprill', 'mai', 'juuni', 'juuli', 'august', 'september', 'oktoober', 'november', 'detsember'],
        ]
    ];

    /**
     * Constructor for the I18n class.
     * 
     * @param string $domain The translation domain.
     * @param string $locale The current locale (e.g., 'en', 'de', 'fr').
     * @param string $defaultLocale The fallback locale.
     */
    public function __construct(
        string $domain = 'default',
        string $locale = '',
        string $defaultLocale = 'en'
    ) {
        $this->domain = $domain;
        $this->locale = $locale;
        $this->defaultLocale = $defaultLocale;
    }

    /**
     * Format a price with currency symbol.
     * 
     * @param float|int|null $price The price value.
     * @param int $minDecimals Minimum number of decimal places.
     * @param int $maxDecimals Maximum number of decimal places.
     * @return string The formatted price string.
     */
    public function price($price, int $minDecimals = 2, int $maxDecimals = 2): string
    {
        if ($price === null) {
            return '';
        }
        return "€ " . $this->currency($price, $minDecimals, $maxDecimals);
    }

    /**
     * Format a currency value without symbol.
     * 
     * @param float|int|null $currency The currency value.
     * @param int $minDecimals Minimum number of decimal places.
     * @param int $maxDecimals Maximum number of decimal places.
     * @return string The formatted currency string.
     */
    public function currency($currency, int $minDecimals = 2, int $maxDecimals = 2): string
    {
        if ($currency === null) {
            return '';
        }

        $formats = $this->formats['currency'];

        $number = rtrim(sprintf("%0.{$maxDecimals}F", $currency), '0');
        $decimalPos = strpos($number, '.');
        if ($decimalPos === false) {
            $number .= '.';
        }
        list($whole, $fraction) = explode('.', $number);
        if ($number < 0) {
            $sign = '-';
            $whole = (string)(-1 * (int)$whole);
        } else {
            $sign = '';
        }
        $format = $formats[$this->locale] ?? $formats[$this->defaultLocale];
        if (!is_array($format) || !isset($format['thousandSeparator']) || !isset($format['decimalSeparator'])) {
            $format = ['thousandSeparator' => ',', 'decimalSeparator' => '.'];
        }
        /** @var string $thousandSep */
        $thousandSep = $format['thousandSeparator'];
        /** @var string $decimalSep */
        $decimalSep = $format['decimalSeparator'];
        $whole = strrev(implode($thousandSep, str_split(strrev($whole), 3)));
        $fraction = str_pad($fraction, $minDecimals, '0');
        return $sign . $whole . $decimalSep . $fraction;
    }

    /**
     * Format a date string.
     * 
     * @param string $str The date string to format.
     * @return string The formatted date.
     */
    public function date(string $str): string
    {
        return $str ? $this->formatDateTime('date', "$str") : '';
    }

    /**
     * Format a UTC date string.
     * 
     * @param string $str The UTC date string to format.
     * @return string The formatted date.
     */
    public function dateUtc(string $str): string
    {
        return $str ? $this->formatDateTime('date', "$str UTC") : '';
    }

    /**
     * Format a duration in seconds as HH:MM:SS.
     * 
     * @param int $seconds The duration in seconds.
     * @param bool $trim Whether to trim leading zeros.
     * @return string The formatted duration.
     */
    public function duration(int $seconds, bool $trim = false): string
    {
        $hours = floor($seconds / 3600);
        $seconds -= $hours * 3600;
        $minutes = floor($seconds / 60);
        $seconds -= $minutes * 60;
        $formatted = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
        if ($trim) {
            if (substr($formatted, 0, 3) == '00:') {
                $formatted = substr($formatted, 3);
            }
        }
        return $formatted;
    }

    /**
     * Format a datetime string.
     * 
     * @param string $str The datetime string to format.
     * @return string The formatted datetime.
     */
    public function datetime(string $str): string
    {
        return $str ? $this->formatDateTime('datetime', "$str") : '';
    }

    /**
     * Format a UTC datetime string.
     * 
     * @param string $str The UTC datetime string to format.
     * @return string The formatted datetime.
     */
    public function datetimeUtc(string $str): string
    {
        return $str ? $this->formatDateTime('datetime', "$str UTC") : '';
    }

    /**
     * Format a time value.
     * 
     * @param int $hours The hours component.
     * @param int $minutes The minutes component.
     * @param int $seconds The seconds component.
     * @return string The formatted time.
     */
    public function time(int $hours, int $minutes, int $seconds = 0): string
    {
        return $this->formatDateTime('time', date('Y-m-d ') . sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds));
    }

    /**
     * Format a UTC time value.
     * 
     * @param int $hours The hours component.
     * @param int $minutes The minutes component.
     * @param int $seconds The seconds component.
     * @return string The formatted time.
     */
    public function timeUtc(int $hours, int $minutes, int $seconds = 0): string
    {
        return $this->formatDateTime('time', date('Y-m-d ') . sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds) . 'UTC');
    }

    /**
     * Get the localized name of a weekday.
     * 
     * @param int $dayOfWeek The day of week (0-7, where 0 and 7 are Sunday).
     * @return string The localized weekday name.
     */
    public function weekDay(int $dayOfWeek): string
    {
        $weekDays = $this->formats['weekDays'];
        $localeWeekDays = $weekDays[$this->locale] ?? $weekDays[$this->defaultLocale];
        if (!is_array($localeWeekDays) || !isset($localeWeekDays[$dayOfWeek])) {
            return '';
        }
        return $localeWeekDays[$dayOfWeek];
    }

    /**
     * Get the localized name of a month.
     * 
     * @param int $monthOfYear The month number (1-12).
     * @return string The localized month name.
     */
    public function monthName(int $monthOfYear): string
    {
        $monthNames = $this->formats['monthNames'];
        $localeMonthNames = $monthNames[$this->locale] ?? $monthNames[$this->defaultLocale];
        if (!is_array($localeMonthNames) || !isset($localeMonthNames[$monthOfYear - 1])) {
            return '';
        }
        return $localeMonthNames[$monthOfYear - 1];
    }

    /**
     * Format a datetime string using locale-specific format.
     * 
     * @param string $type The type of formatting (date, datetime, time).
     * @param string $str The datetime string to format.
     * @return string The formatted datetime.
     */
    private function formatDateTime(string $type, string $str): string
    {
        $formats = $this->formats['datetime'];
        $format = $formats[$this->locale] ?? $formats[$this->defaultLocale];
        if (!is_array($format) || !isset($format[$type])) {
            return '';
        }
        /** @var string $dateFormat */
        $dateFormat = $format[$type];
        $timestamp = strtotime($str);
        if ($timestamp === false) {
            return '';
        }
        return date($dateFormat, $timestamp);
    }

    /**
     * Format a datetime string in a shortened format.
     * 
     * Shows year if different from current year, time if within 24 hours,
     * day abbreviation if within 7 days, or day and date otherwise.
     * 
     * @param string $str The datetime string to format.
     * @return string The formatted short datetime.
     */
    public function datetimeShort(string $str): string
    {
        if (!$str) {
            return '';
        }
        $formats = $this->formats['datetime'];
        $dateFormat = $formats['en']['date'] ?? 'm/d/Y';
        if (!is_string($dateFormat) || strlen($dateFormat) < 2) {
            $dateFormat = 'm/d/Y';
        }
        $sep = $dateFormat[1];
        if ($sep === '') {
            $sep = '/';
        }
        $timestamp = strtotime($str);
        if ($timestamp === false) {
            return '';
        }
        if (date('Y', $timestamp) !=  date('Y')) {
            return implode($sep, array_map('intval', explode($sep, $this->formatDateTime('date', "$str"))));
        }
        $timeNow = time();
        if ($timeNow - $timestamp  < 24 * 60 * 60) {
            return implode(':', array_slice(explode(':', $this->formatDateTime('time', "$str")), 0, 2));
        }
        if ($timeNow - $timestamp  < 7 * 24 * 60 * 60) {
            $dayNum = (int)date('N', $timestamp);
            $day = substr($this->weekDay($dayNum), 0, 2);
            $time = implode(':', array_slice(explode(':', $this->formatDateTime('time', "$str")), 0, 2));
            return "$day $time";
        }
        $dayNum = (int)date('N', $timestamp);
        $day = substr($this->weekDay($dayNum), 0, 2);
        $date = implode($sep, array_map('intval', array_slice(explode($sep, $this->formatDateTime('date', "$str")), 0, 2)));
        return "$day $date";
    }

    /**
     * Translate a string ID to the localized text.
     * 
     * Loads translation files from i18n/{domain}_{locale}.json on demand.
     * 
     * @param string $id The translation ID.
     * @return string The translated text, or the ID if not found.
     */
    public function translate(string $id): string
    {
        // read from disk or cache
        if (!isset($this->strings[$this->domain][$this->locale])) {
            // load from disk
            $filename = 'i18n/' . $this->domain . '_' . $this->locale . '.json';
            if (file_exists($filename)) {
                $content = file_get_contents($filename);
                if ($content !== false) {
                    $decoded = json_decode($content, true);
                    if (is_array($decoded)) {
                        $this->strings[$this->domain][$this->locale] = $decoded;
                    } else {
                        $this->strings[$this->domain][$this->locale] = [];
                    }
                } else {
                    $this->strings[$this->domain][$this->locale] = [];
                }
            } else {
                $this->strings[$this->domain][$this->locale] = [];
            }
        }
        // lookup id
        return $this->strings[$this->domain][$this->locale][$id] ?? $id;
    }
}
