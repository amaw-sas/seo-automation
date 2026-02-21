<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\SpanishDateParser;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class SpanishDateParserTest extends TestCase
{
    /**
     * Test parsing standard Spanish date format
     */
    public function test_parse_standard_spanish_date(): void
    {
        $result = SpanishDateParser::parse('22 en. de 2025');

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals('2025-01-22', $result->format('Y-m-d'));
    }

    /**
     * Test parsing different month abbreviations
     */
    public function test_parse_different_months(): void
    {
        $testCases = [
            '15 feb. de 2024' => '2024-02-15',
            '3 mar. de 2026' => '2026-03-03',
            '10 abr. de 2025' => '2025-04-10',
            '25 may. de 2024' => '2024-05-25',
            '8 jun. de 2025' => '2025-06-08',
            '30 jul. de 2024' => '2024-07-30',
            '12 ago. de 2025' => '2025-08-12',
            '5 sep. de 2024' => '2024-09-05',
            '20 oct. de 2025' => '2025-10-20',
            '11 nov. de 2024' => '2024-11-11',
            '31 dic. de 2025' => '2025-12-31',
        ];

        foreach ($testCases as $input => $expected) {
            $result = SpanishDateParser::parse($input);
            $this->assertEquals($expected, $result->format('Y-m-d'), "Failed parsing: {$input}");
        }
    }

    /**
     * Test parsing with "ag." abbreviation for August
     */
    public function test_parse_august_short_abbreviation(): void
    {
        $result = SpanishDateParser::parse('15 ag. de 2025');

        $this->assertEquals('2025-08-15', $result->format('Y-m-d'));
    }

    /**
     * Test parsing with "sept." abbreviation for September
     */
    public function test_parse_september_long_abbreviation(): void
    {
        $result = SpanishDateParser::parse('10 sept. de 2024');

        $this->assertEquals('2024-09-10', $result->format('Y-m-d'));
    }

    /**
     * Test parsing null input
     */
    public function test_parse_null_returns_null(): void
    {
        $result = SpanishDateParser::parse(null);

        $this->assertNull($result);
    }

    /**
     * Test parsing empty string
     */
    public function test_parse_empty_string_returns_null(): void
    {
        $result = SpanishDateParser::parse('');

        $this->assertNull($result);
    }

    /**
     * Test parsing invalid format
     */
    public function test_parse_invalid_format_returns_null(): void
    {
        $result = SpanishDateParser::parse('invalid date');

        $this->assertNull($result);
    }

    /**
     * Test parsing standard date format (fallback)
     */
    public function test_parse_standard_date_format(): void
    {
        $result = SpanishDateParser::parse('2025-01-22');

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals('2025-01-22', $result->format('Y-m-d'));
    }

    /**
     * Test parseToString method
     */
    public function test_parse_to_string(): void
    {
        $result = SpanishDateParser::parseToString('22 en. de 2025');

        $this->assertEquals('2025-01-22', $result);
    }

    /**
     * Test parseToString with null input
     */
    public function test_parse_to_string_null_returns_null(): void
    {
        $result = SpanishDateParser::parseToString(null);

        $this->assertNull($result);
    }

    /**
     * Test case insensitivity
     */
    public function test_parse_case_insensitive(): void
    {
        $testCases = [
            '22 EN. de 2025' => '2025-01-22',
            '15 FEB. DE 2024' => '2024-02-15',
            '10 Mar. De 2026' => '2026-03-10',
        ];

        foreach ($testCases as $input => $expected) {
            $result = SpanishDateParser::parse($input);
            $this->assertEquals($expected, $result->format('Y-m-d'), "Failed parsing: {$input}");
        }
    }

    /**
     * Test parsing single-digit days
     */
    public function test_parse_single_digit_days(): void
    {
        $testCases = [
            '1 en. de 2025' => '2025-01-01',
            '5 feb. de 2024' => '2024-02-05',
            '9 mar. de 2026' => '2026-03-09',
        ];

        foreach ($testCases as $input => $expected) {
            $result = SpanishDateParser::parse($input);
            $this->assertEquals($expected, $result->format('Y-m-d'), "Failed parsing: {$input}");
        }
    }

    /**
     * Test parsing with extra whitespace
     */
    public function test_parse_with_extra_whitespace(): void
    {
        $result = SpanishDateParser::parse('  22  en.  de  2025  ');

        // The trim() handles outer whitespace, and Carbon::parse handles the rest
        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals('2025-01-22', $result->format('Y-m-d'));
    }

    /**
     * Test parsing full month names
     */
    public function test_parse_full_month_names(): void
    {
        $testCases = [
            '15 enero de 2025' => '2025-01-15',
            '20 febrero de 2024' => '2024-02-20',
            '10 marzo de 2026' => '2026-03-10',
        ];

        foreach ($testCases as $input => $expected) {
            $result = SpanishDateParser::parse($input);
            $this->assertEquals($expected, $result->format('Y-m-d'), "Failed parsing: {$input}");
        }
    }
}
