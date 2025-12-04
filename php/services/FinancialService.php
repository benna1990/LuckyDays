<?php
declare(strict_types=1);

require_once __DIR__ . '/MoneyCalculator.php';

/**
 * FinancialService - Centrale financiële bedrijfslogica voor LuckyDays
 *
 * Deze service centraliseert alle financiële berekeningen en zorgt voor consistentie
 * tussen UI, exports en rapportages. Alle bedragen worden intern als integers (centen)
 * verwerkt via de MoneyCalculator.
 *
 * BELANGRIJKE CONFIGURATIE:
 * - COMMISSION_BASE: Bepaalt of commissie wordt berekend over INZET of WINST
 * - COMMISSION_PERCENTAGE: Het commissiepercentage (standaard 30%)
 * - HOUSE_PERCENTAGE: Het huispercentage (standaard 70%)
 *
 * @author Senior Software Architect
 * @version 1.0.0
 */
class FinancialService
{
    /**
     * Commissie basis opties
     */
    const COMMISSION_ON_BET = 'bet';      // Commissie over de totale inzet (30% van inzet)
    const COMMISSION_ON_PROFIT = 'profit'; // Commissie over de winst/verlies (30% van huissaldo)

    /**
     * Configuratie: Over welke basis wordt commissie berekend?
     *
     * BELANGRIJK: Dit is de centrale schakelaar voor commissieberekening!
     *
     * Opties:
     * - COMMISSION_ON_BET: Commissie = 30% van totale inzet
     *   Voorbeeld: Bij €1000 inzet → Commissie = €300, Huispot = €700
     *
     * - COMMISSION_ON_PROFIT: Commissie = 30% van huissaldo (inzet - uitbetaling)
     *   Voorbeeld: Bij €1000 inzet, €200 uitbetaling → Huissaldo = €800 → Commissie = €240
     *
     * @var string
     */
    const COMMISSION_BASE = self::COMMISSION_ON_BET;

    /**
     * Commissiepercentage (30%)
     *
     * @var float
     */
    const COMMISSION_PERCENTAGE = 30.0;

    /**
     * Huispercentage (70%)
     *
     * @var float
     */
    const HOUSE_PERCENTAGE = 70.0;

    /**
     * Bereken commissie volgens de ingestelde basis
     *
     * Deze methode berekent de commissie op basis van COMMISSION_BASE:
     * - Als COMMISSION_ON_BET: commissie = 30% van inzet
     * - Als COMMISSION_ON_PROFIT: commissie = 30% van (inzet - uitbetaling)
     *
     * @param int $inzetCents Totale inzet in centen
     * @param int $winstCents Totale winst/uitbetaling in centen
     * @return array{
     *   commission: int,
     *   commission_euros: float,
     *   house_pot: int,
     *   house_pot_euros: float,
     *   net_house: int,
     *   net_house_euros: float,
     *   basis: string,
     *   commission_percentage: float
     * }
     *
     * @example
     * // Bij COMMISSION_ON_BET:
     * calculateCommission(100000, 20000) =>
     * [
     *   'commission' => 30000,        // €300 (30% van €1000)
     *   'commission_euros' => 300.0,
     *   'house_pot' => 70000,         // €700 (70% van €1000)
     *   'house_pot_euros' => 700.0,
     *   'net_house' => 50000,         // €500 (€700 - €200)
     *   'net_house_euros' => 500.0,
     *   'basis' => 'bet',
     *   'commission_percentage' => 30.0
     * ]
     */
    public static function calculateCommission(int $inzetCents, int $winstCents): array
    {
        if (self::COMMISSION_BASE === self::COMMISSION_ON_BET) {
            // Commissie over INZET
            $commission = MoneyCalculator::percentage($inzetCents, self::COMMISSION_PERCENTAGE);
            $housePot = MoneyCalculator::percentage($inzetCents, self::HOUSE_PERCENTAGE);
            $netHouse = MoneyCalculator::subtract($housePot, $winstCents);
        } else {
            // Commissie over WINST/PROFIT (huissaldo)
            $huisSaldo = MoneyCalculator::subtract($inzetCents, $winstCents);
            $commission = MoneyCalculator::percentage($huisSaldo, self::COMMISSION_PERCENTAGE);
            $housePot = MoneyCalculator::percentage($inzetCents, self::HOUSE_PERCENTAGE);
            $netHouse = MoneyCalculator::subtract($huisSaldo, $commission);
        }

        return [
            'commission' => $commission,
            'commission_euros' => MoneyCalculator::toEuros($commission),
            'house_pot' => $housePot,
            'house_pot_euros' => MoneyCalculator::toEuros($housePot),
            'net_house' => $netHouse,
            'net_house_euros' => MoneyCalculator::toEuros($netHouse),
            'basis' => self::COMMISSION_BASE,
            'commission_percentage' => self::COMMISSION_PERCENTAGE
        ];
    }

    /**
     * Bereken het huisaandeel (70%) van de inzet
     *
     * Dit is het bedrag dat beschikbaar is voor uitbetaling aan spelers.
     *
     * @param int $inzetCents Totale inzet in centen
     * @return array{
     *   house_share: int,
     *   house_share_euros: float,
     *   percentage: float
     * }
     *
     * @example
     * calculateHouseShare(100000) =>
     * [
     *   'house_share' => 70000,      // €700 (70% van €1000)
     *   'house_share_euros' => 700.0,
     *   'percentage' => 70.0
     * ]
     */
    public static function calculateHouseShare(int $inzetCents): array
    {
        $houseShare = MoneyCalculator::percentage($inzetCents, self::HOUSE_PERCENTAGE);

        return [
            'house_share' => $houseShare,
            'house_share_euros' => MoneyCalculator::toEuros($houseShare),
            'percentage' => self::HOUSE_PERCENTAGE
        ];
    }

    /**
     * Bereken netto huissaldo (huispot - uitbetalingen)
     *
     * Dit is het uiteindelijke resultaat voor het huis na uitbetalingen.
     *
     * @param int $inzetCents Totale inzet in centen
     * @param int $winstCents Totale uitbetalingen in centen
     * @return array{
     *   net_house: int,
     *   net_house_euros: float,
     *   is_positive: bool,
     *   is_negative: bool,
     *   is_zero: bool
     * }
     */
    public static function calculateNetHouse(int $inzetCents, int $winstCents): array
    {
        $housePot = MoneyCalculator::percentage($inzetCents, self::HOUSE_PERCENTAGE);
        $netHouse = MoneyCalculator::subtract($housePot, $winstCents);

        return [
            'net_house' => $netHouse,
            'net_house_euros' => MoneyCalculator::toEuros($netHouse),
            'is_positive' => MoneyCalculator::isPositive($netHouse),
            'is_negative' => MoneyCalculator::isNegative($netHouse),
            'is_zero' => MoneyCalculator::isZero($netHouse)
        ];
    }

    /**
     * Bereken complete financiële breakdown voor een periode
     *
     * Deze methode geeft een volledig overzicht van alle financiële metrics
     * voor een bepaalde periode (dag, week, etc.).
     *
     * @param int $inzetCents Totale inzet in centen
     * @param int $winstCents Totale uitbetalingen in centen
     * @return array{
     *   inzet: int,
     *   inzet_euros: float,
     *   winst: int,
     *   winst_euros: float,
     *   commission: int,
     *   commission_euros: float,
     *   house_pot: int,
     *   house_pot_euros: float,
     *   net_house: int,
     *   net_house_euros: float,
     *   commission_basis: string,
     *   commission_percentage: float,
     *   house_percentage: float
     * }
     *
     * @example
     * calculateFinancialBreakdown(100000, 20000) =>
     * [
     *   'inzet' => 100000,
     *   'inzet_euros' => 1000.0,
     *   'winst' => 20000,
     *   'winst_euros' => 200.0,
     *   'commission' => 30000,
     *   'commission_euros' => 300.0,
     *   'house_pot' => 70000,
     *   'house_pot_euros' => 700.0,
     *   'net_house' => 50000,
     *   'net_house_euros' => 500.0,
     *   'commission_basis' => 'bet',
     *   'commission_percentage' => 30.0,
     *   'house_percentage' => 70.0
     * ]
     */
    public static function calculateFinancialBreakdown(int $inzetCents, int $winstCents): array
    {
        $commissionData = self::calculateCommission($inzetCents, $winstCents);

        return [
            'inzet' => $inzetCents,
            'inzet_euros' => MoneyCalculator::toEuros($inzetCents),
            'winst' => $winstCents,
            'winst_euros' => MoneyCalculator::toEuros($winstCents),
            'commission' => $commissionData['commission'],
            'commission_euros' => $commissionData['commission_euros'],
            'house_pot' => $commissionData['house_pot'],
            'house_pot_euros' => $commissionData['house_pot_euros'],
            'net_house' => $commissionData['net_house'],
            'net_house_euros' => $commissionData['net_house_euros'],
            'commission_basis' => self::COMMISSION_BASE,
            'commission_percentage' => self::COMMISSION_PERCENTAGE,
            'house_percentage' => self::HOUSE_PERCENTAGE
        ];
    }

    /**
     * Bereken huissaldo vanuit spelersperspectief
     *
     * Dit draait de berekening om: wat heeft het huis gewonnen/verloren
     * op deze speler?
     *
     * @param int $spelerInzetCents Inzet van de speler in centen
     * @param int $spelerWinstCents Winst van de speler in centen
     * @return array{
     *   speler_saldo: int,
     *   speler_saldo_euros: float,
     *   huis_saldo: int,
     *   huis_saldo_euros: float,
     *   huis_wint: bool,
     *   speler_wint: bool,
     *   gelijk: bool
     * }
     *
     * @example
     * calculatePlayerVsHouse(10000, 15000) =>
     * [
     *   'speler_saldo' => 5000,        // Speler wint €50
     *   'speler_saldo_euros' => 50.0,
     *   'huis_saldo' => -5000,         // Huis verliest €50
     *   'huis_saldo_euros' => -50.0,
     *   'huis_wint' => false,
     *   'speler_wint' => true,
     *   'gelijk' => false
     * ]
     */
    public static function calculatePlayerVsHouse(int $spelerInzetCents, int $spelerWinstCents): array
    {
        $spelerSaldo = MoneyCalculator::subtract($spelerWinstCents, $spelerInzetCents);
        $huisSaldo = MoneyCalculator::subtract($spelerInzetCents, $spelerWinstCents);

        return [
            'speler_saldo' => $spelerSaldo,
            'speler_saldo_euros' => MoneyCalculator::toEuros($spelerSaldo),
            'huis_saldo' => $huisSaldo,
            'huis_saldo_euros' => MoneyCalculator::toEuros($huisSaldo),
            'huis_wint' => MoneyCalculator::isPositive($huisSaldo),
            'speler_wint' => MoneyCalculator::isPositive($spelerSaldo),
            'gelijk' => MoneyCalculator::isZero($huisSaldo)
        ];
    }

    /**
     * Legacy compatibiliteit: converteer oude float-based resultaten
     *
     * Gebruik deze methode om oude code geleidelijk te migreren.
     * Converteert floats naar centen en roept dan de moderne methode aan.
     *
     * @deprecated Gebruik calculateCommission() met centen
     *
     * @param float $inzetEuros Inzet in euro's (float)
     * @param float $winstEuros Winst in euro's (float)
     * @return array Zelfde output als calculateCommission() maar met floats
     */
    public static function calculateCommissionLegacy(float $inzetEuros, float $winstEuros): array
    {
        $inzetCents = MoneyCalculator::toCents($inzetEuros);
        $winstCents = MoneyCalculator::toCents($winstEuros);

        $result = self::calculateCommission($inzetCents, $winstCents);

        // Voor backwards compatibility, return floats in plaats van ints
        return [
            'commission' => $result['commission_euros'],
            'house_pot' => $result['house_pot_euros'],
            'net_house' => $result['net_house_euros'],
            'basis' => $result['basis'],
            'commission_percentage' => $result['commission_percentage']
        ];
    }
}
