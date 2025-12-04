<?php
declare(strict_types=1);

/**
 * MoneyCalculator - The Money Pattern voor LuckyDays
 *
 * Alle geldbedragen worden intern als integers (centen) opgeslagen en berekend.
 * Dit voorkomt floating-point afrondingsfouten die ontstaan bij het gebruik van floats.
 *
 * Voorbeeld:
 * - €12.50 wordt opgeslagen als 1250 (centen)
 * - €0.10 wordt opgeslagen als 10 (centen)
 *
 * @author Senior Software Architect
 * @version 1.0.0
 */
class MoneyCalculator
{
    /**
     * Converteert een euro-bedrag (float of string) naar centen (integer)
     *
     * @param float|string|int $euros Het bedrag in euro's (bijv. 12.50 of "12.50")
     * @return int Het bedrag in centen (bijv. 1250)
     *
     * @example
     * toCents(12.50)  => 1250
     * toCents("12.50") => 1250
     * toCents(0.10)   => 10
     */
    public static function toCents($euros): int
    {
        // Converteer naar string om precisie te behouden
        $euroString = (string) $euros;

        // Vervang komma door punt voor Nederlandse input (bijv. "12,50")
        $euroString = str_replace(',', '.', $euroString);

        // Vermenigvuldig met 100 en rond af naar integer
        // We gebruiken bcmath voor maximale precisie, fallback naar round()
        if (function_exists('bcmul')) {
            return (int) bcmul($euroString, '100', 0);
        }

        return (int) round((float) $euroString * 100);
    }

    /**
     * Converteert centen (integer) terug naar euro's (float) voor weergave
     *
     * @param int $cents Het bedrag in centen
     * @return float Het bedrag in euro's
     *
     * @example
     * toEuros(1250) => 12.50
     * toEuros(10)   => 0.10
     */
    public static function toEuros(int $cents): float
    {
        return $cents / 100;
    }

    /**
     * Tel twee bedragen op (in centen)
     *
     * @param int $a Bedrag A in centen
     * @param int $b Bedrag B in centen
     * @return int Som in centen
     *
     * @example
     * add(1250, 750) => 2000 (€12.50 + €7.50 = €20.00)
     */
    public static function add(int $a, int $b): int
    {
        return $a + $b;
    }

    /**
     * Trek twee bedragen van elkaar af (in centen)
     *
     * @param int $a Bedrag A in centen (aftrektal)
     * @param int $b Bedrag B in centen (aftrekker)
     * @return int Verschil in centen (kan negatief zijn)
     *
     * @example
     * subtract(2000, 750) => 1250 (€20.00 - €7.50 = €12.50)
     * subtract(500, 1000) => -500 (€5.00 - €10.00 = -€5.00)
     */
    public static function subtract(int $a, int $b): int
    {
        return $a - $b;
    }

    /**
     * Vermenigvuldig een bedrag met een factor
     *
     * @param int $amount Bedrag in centen
     * @param float $factor De vermenigvuldigingsfactor (bijv. 0.30 voor 30%)
     * @return int Resultaat in centen (afgerond naar beneden)
     *
     * @example
     * multiply(1000, 0.30) => 300 (€10.00 * 30% = €3.00)
     * multiply(1000, 2.5)  => 2500 (€10.00 * 2.5 = €25.00)
     */
    public static function multiply(int $amount, float $factor): int
    {
        // Vermenigvuldig en rond af naar integer
        // We gebruiken round() om banker's rounding toe te passen (half naar even)
        return (int) round($amount * $factor);
    }

    /**
     * Bereken percentage van een bedrag
     *
     * @param int $amount Bedrag in centen
     * @param float $percentage Percentage (bijv. 30.0 voor 30%)
     * @return int Resultaat in centen
     *
     * @example
     * percentage(1000, 30.0) => 300 (30% van €10.00 = €3.00)
     * percentage(1550, 15.5) => 240 (15.5% van €15.50 = €2.40)
     */
    public static function percentage(int $amount, float $percentage): int
    {
        return self::multiply($amount, $percentage / 100);
    }

    /**
     * Formatteer een bedrag in centen naar een mooi leesbare euro-string
     *
     * @param int $cents Bedrag in centen
     * @param bool $showSymbol Of het €-symbool moet worden getoond
     * @param string $decimalSeparator Decimale scheidingsteken (standaard komma voor NL)
     * @param string $thousandsSeparator Duizendtal scheidingsteken (standaard punt voor NL)
     * @return string Geformatteerde string (bijv. "€12,50")
     *
     * @example
     * formatEuro(1250)               => "€12,50"
     * formatEuro(1250, false)        => "12,50"
     * formatEuro(125000)             => "€1.250,00"
     * formatEuro(1250, true, '.', ',') => "€12.50" (US format)
     */
    public static function formatEuro(
        int $cents,
        bool $showSymbol = true,
        string $decimalSeparator = ',',
        string $thousandsSeparator = '.'
    ): string {
        $euros = self::toEuros($cents);
        $formatted = number_format($euros, 2, $decimalSeparator, $thousandsSeparator);

        return $showSymbol ? "€{$formatted}" : $formatted;
    }

    /**
     * Vergelijk twee bedragen
     *
     * @param int $a Bedrag A in centen
     * @param int $b Bedrag B in centen
     * @return int -1 als A < B, 0 als A == B, 1 als A > B
     *
     * @example
     * compare(1000, 500) => 1  (€10.00 > €5.00)
     * compare(500, 1000) => -1 (€5.00 < €10.00)
     * compare(1000, 1000) => 0 (€10.00 == €10.00)
     */
    public static function compare(int $a, int $b): int
    {
        if ($a > $b) return 1;
        if ($a < $b) return -1;
        return 0;
    }

    /**
     * Check of bedrag positief is (> 0)
     *
     * @param int $cents Bedrag in centen
     * @return bool True als positief
     */
    public static function isPositive(int $cents): bool
    {
        return $cents > 0;
    }

    /**
     * Check of bedrag negatief is (< 0)
     *
     * @param int $cents Bedrag in centen
     * @return bool True als negatief
     */
    public static function isNegative(int $cents): bool
    {
        return $cents < 0;
    }

    /**
     * Check of bedrag nul is
     *
     * @param int $cents Bedrag in centen
     * @return bool True als nul
     */
    public static function isZero(int $cents): bool
    {
        return $cents === 0;
    }

    /**
     * Bereken absolute waarde (verwijder minteken)
     *
     * @param int $cents Bedrag in centen
     * @return int Absolute waarde in centen
     *
     * @example
     * abs(-1250) => 1250
     * abs(1250)  => 1250
     */
    public static function abs(int $cents): int
    {
        return abs($cents);
    }
}
