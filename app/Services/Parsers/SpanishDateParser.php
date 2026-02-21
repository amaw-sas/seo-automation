<?php

namespace App\Services\Parsers;

use Carbon\Carbon;
use Exception;

class SpanishDateParser
{
    /**
     * Mapa de meses en español a números
     */
    private const SPANISH_MONTHS = [
        'en.' => '01', 'ene.' => '01', 'enero' => '01',
        'feb.' => '02', 'febrero' => '02',
        'mar.' => '03', 'marzo' => '03',
        'abr.' => '04', 'abril' => '04',
        'may.' => '05', 'mayo' => '05',
        'jun.' => '06', 'junio' => '06',
        'jul.' => '07', 'julio' => '07',
        'ag.' => '08', 'ago.' => '08', 'agosto' => '08',
        'sep.' => '09', 'sept.' => '09', 'septiembre' => '09',
        'oct.' => '10', 'octubre' => '10',
        'nov.' => '11', 'noviembre' => '11',
        'dic.' => '12', 'diciembre' => '12',
    ];

    /**
     * Parse Spanish date to Carbon
     *
     * @param string $spanishDate Fecha en formato español (ej: "22 en. de 2025", "5 feb. de 2024")
     * @return Carbon|null
     */
    public static function parse(?string $spanishDate): ?Carbon
    {
        if (empty($spanishDate)) {
            return null;
        }

        // Normalizar entrada (lowercase, trim)
        $date = trim(strtolower($spanishDate));

        // Patrón: "22 en. de 2025" o "5 feb. de 2024"
        if (preg_match('/^(\d{1,2})\s+([a-zñ\.]+)\s+de\s+(\d{4})$/i', $date, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $monthStr = $matches[2];
            $year = $matches[3];

            // Buscar mes en el mapa
            $month = self::SPANISH_MONTHS[$monthStr] ?? null;

            if ($month) {
                return Carbon::createFromFormat('Y-m-d', "$year-$month-$day");
            }
        }

        // Patrón alternativo: "22/01/2025" o "2025-01-22"
        try {
            return Carbon::parse($date);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Parse Spanish date to string format (Y-m-d)
     *
     * @param string|null $spanishDate
     * @return string|null
     */
    public static function parseToString(?string $spanishDate): ?string
    {
        $carbon = self::parse($spanishDate);
        return $carbon ? $carbon->format('Y-m-d') : null;
    }
}
