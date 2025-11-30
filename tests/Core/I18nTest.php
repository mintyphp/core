<?php

namespace MintyPHP\Tests\Core;

use MintyPHP\Core\I18n;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for the Core I18n class.
 * 
 * This test suite covers internationalization and localization features,
 * including currency formatting, date/time formatting, and translations.
 */
class I18nTest extends TestCase
{
    private I18n $i18n;
    private I18n $i18nDe;
    private I18n $i18nNl;

    public function setUp(): void
    {
        // Create instances with different locales
        $this->i18n = new I18n('default', 'en', 'en');
        $this->i18nDe = new I18n('default', 'de', 'en');
        $this->i18nNl = new I18n('default', 'nl', 'en');
    }

    public function testPriceEnglish(): void
    {
        $result = $this->i18n->price(1234.56);
        $this->assertEquals('€ 1,234.56', $result);
    }

    public function testPriceGerman(): void
    {
        $result = $this->i18nDe->price(1234.56);
        $this->assertEquals('€ 1.234,56', $result);
    }

    public function testPriceNull(): void
    {
        $result = $this->i18n->price(null);
        $this->assertEquals('', $result);
    }

    public function testCurrencyEnglish(): void
    {
        $result = $this->i18n->currency(1234.56);
        $this->assertEquals('1,234.56', $result);
    }

    public function testCurrencyGerman(): void
    {
        $result = $this->i18nDe->currency(1234.56);
        $this->assertEquals('1.234,56', $result);
    }

    public function testCurrencyWithMinMaxDecimals(): void
    {
        $result = $this->i18n->currency(1234.5, 2, 3);
        $this->assertEquals('1,234.50', $result);

        $result = $this->i18n->currency(1234.567, 2, 3);
        $this->assertEquals('1,234.567', $result);
    }

    public function testCurrencyNegative(): void
    {
        $result = $this->i18n->currency(-1234.56);
        $this->assertEquals('-1,234.56', $result);
    }

    public function testDate(): void
    {
        $result = $this->i18n->date('2024-03-15');
        $this->assertEquals('03/15/2024', $result);
    }

    public function testDateGerman(): void
    {
        $result = $this->i18nDe->date('2024-03-15');
        $this->assertEquals('15.03.2024', $result);
    }

    public function testDateDutch(): void
    {
        $result = $this->i18nNl->date('2024-03-15');
        $this->assertEquals('15-03-2024', $result);
    }

    public function testDateEmpty(): void
    {
        $result = $this->i18n->date('');
        $this->assertEquals('', $result);
    }

    public function testDatetime(): void
    {
        $result = $this->i18n->datetime('2024-03-15 14:30:45');
        $this->assertEquals('03/15/2024 14:30:45', $result);
    }

    public function testDatetimeGerman(): void
    {
        $result = $this->i18nDe->datetime('2024-03-15 14:30:45');
        $this->assertEquals('15.03.2024 14:30:45', $result);
    }

    public function testTime(): void
    {
        $result = $this->i18n->time(14, 30, 45);
        $this->assertEquals('14:30:45', $result);
    }

    public function testTimeWithoutSeconds(): void
    {
        $result = $this->i18n->time(14, 30);
        $this->assertEquals('14:30:00', $result);
    }

    public function testDuration(): void
    {
        $result = $this->i18n->duration(3665);
        $this->assertEquals('01:01:05', $result);
    }

    public function testDurationTrimmed(): void
    {
        $result = $this->i18n->duration(125, true);
        $this->assertEquals('02:05', $result);

        $result = $this->i18n->duration(3665, true);
        $this->assertEquals('01:01:05', $result);
    }

    public function testWeekDayEnglish(): void
    {
        $result = $this->i18n->weekDay(1);
        $this->assertEquals('Monday', $result);

        $result = $this->i18n->weekDay(0);
        $this->assertEquals('Sunday', $result);
    }

    public function testWeekDayGerman(): void
    {
        $result = $this->i18nDe->weekDay(1);
        $this->assertEquals('Montag', $result);

        $result = $this->i18nDe->weekDay(7);
        $this->assertEquals('Sonntag', $result);
    }

    public function testWeekDayDutch(): void
    {
        $result = $this->i18nNl->weekDay(5);
        $this->assertEquals('vrijdag', $result);
    }

    public function testMonthNameEnglish(): void
    {
        $result = $this->i18n->monthName(1);
        $this->assertEquals('January', $result);

        $result = $this->i18n->monthName(12);
        $this->assertEquals('December', $result);
    }

    public function testMonthNameGerman(): void
    {
        $result = $this->i18nDe->monthName(3);
        $this->assertEquals('März', $result);
    }

    public function testMonthNameDutch(): void
    {
        $result = $this->i18nNl->monthName(8);
        $this->assertEquals('augustus', $result);
    }

    public function testTranslateNonExistent(): void
    {
        $result = $this->i18n->translate('non.existent.key');
        $this->assertEquals('non.existent.key', $result, 'should return key if translation not found');
    }

    public function testDatetimeShortEmpty(): void
    {
        $result = $this->i18n->datetimeShort('');
        $this->assertEquals('', $result);
    }

    public function testDateUtc(): void
    {
        $result = $this->i18n->dateUtc('2024-03-15 12:00:00');
        // Result depends on system timezone, just check it's not empty
        $this->assertNotEmpty($result);
    }

    public function testDatetimeUtc(): void
    {
        $result = $this->i18n->datetimeUtc('2024-03-15 12:00:00');
        // Result depends on system timezone, just check it's not empty
        $this->assertNotEmpty($result);
    }

    public function testTimeUtc(): void
    {
        $result = $this->i18n->timeUtc(14, 30, 45);
        // Result depends on system timezone, just check it's not empty
        $this->assertNotEmpty($result);
    }

    public function testCurrencyNull(): void
    {
        $result = $this->i18n->currency(null);
        $this->assertEquals('', $result);
    }

    public function testCurrencyZero(): void
    {
        $result = $this->i18n->currency(0);
        $this->assertEquals('0.00', $result);
    }

    public function testPriceZero(): void
    {
        $result = $this->i18n->price(0);
        $this->assertEquals('€ 0.00', $result);
    }
}
