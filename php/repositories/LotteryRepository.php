<?php
declare(strict_types=1);

/**
 * LotteryRepository - Centrale data access layer voor LuckyDays
 *
 * Deze repository centraliseert alle SQL queries en zorgt voor:
 * - Geen NULL-waarden (COALESCE overal)
 * - Geen dubbele tellingen (correcte JOINs en DISTINCT)
 * - Strikte winkel-filtering (shopId !== null check)
 * - Consistente query structuur
 *
 * DESIGN PRINCIPES:
 * - Repository Pattern: Scheidt SQL van business logic
 * - Defensive Programming: Alle SUM() met COALESCE(SUM(...), 0)
 * - Type Safety: strict_types=1 en type hints overal
 * - Expliciete Filtering: !== null checks voor optional filters
 *
 * @author Senior Software Architect
 * @version 1.0.0
 */
class LotteryRepository
{
    /**
     * Database connectie
     *
     * @var resource PostgreSQL connection
     */
    private $conn;

    /**
     * Constructor
     *
     * @param resource $connection PostgreSQL database connectie
     */
    public function __construct($connection)
    {
        $this->conn = $connection;
    }

    /**
     * Haal week totalen op voor een specifieke periode
     *
     * Deze methode geeft de totale statistieken voor een week terug:
     * - Aantal bonnen (met en zonder rijen)
     * - Aantal rijen
     * - Totale inzet
     * - Totale uitbetalingen
     * - Saldo (winst - inzet vanuit speler perspectief)
     *
     * BELANGRIJKE FIXES:
     * - total_bons telt via DISTINCT om duplicaten te voorkomen
     * - COALESCE zorgt dat NULL altijd 0 wordt
     * - Strikte shopId filtering (0 is een geldige winkel ID!)
     * - JOIN met rijen is LEFT JOIN om bonnen zonder rijen mee te tellen
     *
     * @param string $startDate Start datum (YYYY-MM-DD)
     * @param string $endDate Eind datum (YYYY-MM-DD)
     * @param int|null $shopId Optionele winkel filter (null = alle winkels)
     * @return array{
     *   total_bons: int,
     *   total_rijen: int,
     *   total_bet: string,
     *   total_winnings: string,
     *   saldo: string
     * }
     *
     * @example
     * $totals = $repo->getWeekTotals('2024-01-01', '2024-01-07', 1);
     * // Returns: ['total_bons' => 150, 'total_rijen' => 450, 'total_bet' => '15000.00', ...]
     */
    public function getWeekTotals(string $startDate, string $endDate, ?int $shopId = null): array
    {
        // KRITIEKE FIX: Gebruik !== null voor strikte null-check
        // Dit zorgt dat shopId=0 correct wordt gefilterd (niet als "alle winkels")
        $hasShopFilter = $shopId !== null;

        // Query opbouw met expliciete COALESCE en DISTINCT
        $query = "
            SELECT
                -- DISTINCT voorkomt dubbele tellingen bij JOIN
                COUNT(DISTINCT b.id) as total_bons,

                -- Tel alle rijen (kan 0 zijn als bonnen geen rijen hebben)
                COUNT(r.id) as total_rijen,

                -- COALESCE zorgt dat NULL altijd 0.00 wordt (geen NULL errors!)
                COALESCE(SUM(r.bet), 0) as total_bet,
                COALESCE(SUM(r.winnings), 0) as total_winnings,

                -- Saldo vanuit speler perspectief (winst - inzet)
                COALESCE(SUM(r.winnings), 0) - COALESCE(SUM(r.bet), 0) as saldo
            FROM
                bons b
            -- LEFT JOIN: bonnen zonder rijen worden ook meegeteld in total_bons
            LEFT JOIN
                rijen r ON r.bon_id = b.id
            WHERE
                b.date BETWEEN $1 AND $2
        ";

        // Voeg winkel filter toe als opgegeven
        $params = [$startDate, $endDate];
        if ($hasShopFilter) {
            $query .= " AND b.winkel_id = $3";
            $params[] = $shopId;
        }

        // Voer query uit
        $result = pg_query_params($this->conn, $query, $params);

        if (!$result) {
            // Defensive: return safe defaults bij DB error
            return [
                'total_bons' => 0,
                'total_rijen' => 0,
                'total_bet' => '0.00',
                'total_winnings' => '0.00',
                'saldo' => '0.00'
            ];
        }

        $data = pg_fetch_assoc($result);

        // Extra defensive: zorg dat alle velden bestaan
        return [
            'total_bons' => (int) ($data['total_bons'] ?? 0),
            'total_rijen' => (int) ($data['total_rijen'] ?? 0),
            'total_bet' => (string) ($data['total_bet'] ?? '0.00'),
            'total_winnings' => (string) ($data['total_winnings'] ?? '0.00'),
            'saldo' => (string) ($data['saldo'] ?? '0.00')
        ];
    }

    /**
     * Haal week statistieken per speler op
     *
     * Deze methode geeft een lijst van spelers met hun statistieken voor een week:
     * - Per speler: bonnen, rijen, inzet, winst, saldo
     * - Alleen spelers met minimaal 1 bon en 1 rij
     * - Gesorteerd op saldo (DESC)
     *
     * BELANGRIJKE FIXES:
     * - HAVING COUNT(DISTINCT b.id) > 0 EN COUNT(r.id) > 0 (geen lege spelers)
     * - COALESCE op alle sommen
     * - Strikte shopId filtering
     *
     * @param string $startDate Start datum (YYYY-MM-DD)
     * @param string $endDate Eind datum (YYYY-MM-DD)
     * @param int|null $shopId Optionele winkel filter
     * @return array[] Lijst van spelers met statistieken
     *
     * @example
     * $stats = $repo->getWeekStats('2024-01-01', '2024-01-07', 1);
     * // Returns: [
     * //   ['id' => 1, 'name' => 'Jan', 'total_bons' => 10, 'total_bet' => '100.00', ...],
     * //   ...
     * // ]
     */
    public function getWeekStats(string $startDate, string $endDate, ?int $shopId = null): array
    {
        $hasShopFilter = $shopId !== null;

        $query = "
            SELECT
                p.id,
                p.name,
                p.color,
                COUNT(DISTINCT b.id) as total_bons,
                COUNT(r.id) as total_rijen,
                COALESCE(SUM(r.bet), 0) as total_bet,
                COALESCE(SUM(r.winnings), 0) as total_winnings,
                COALESCE(SUM(r.winnings), 0) - COALESCE(SUM(r.bet), 0) as saldo
            FROM
                players p
            INNER JOIN
                bons b ON p.id = b.player_id
                AND b.date BETWEEN $1 AND $2
        ";

        $params = [$startDate, $endDate];

        // Winkel filter op bons
        if ($hasShopFilter) {
            $query .= " AND b.winkel_id = $3";
            $params[] = $shopId;
        }

        $query .= "
            INNER JOIN
                rijen r ON r.bon_id = b.id
            GROUP BY
                p.id, p.name, p.color
            -- KRITIEK: Alleen spelers met daadwerkelijk data
            HAVING
                COUNT(DISTINCT b.id) > 0
                AND COUNT(r.id) > 0
            ORDER BY
                saldo DESC
        ";

        $result = pg_query_params($this->conn, $query, $params);

        if (!$result) {
            return []; // Safe default
        }

        // Fetch alle rows
        $stats = [];
        while ($row = pg_fetch_assoc($result)) {
            $stats[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'color' => (string) ($row['color'] ?? '#3B82F6'),
                'total_bons' => (int) ($row['total_bons'] ?? 0),
                'total_rijen' => (int) ($row['total_rijen'] ?? 0),
                'total_bet' => (string) ($row['total_bet'] ?? '0.00'),
                'total_winnings' => (string) ($row['total_winnings'] ?? '0.00'),
                'saldo' => (string) ($row['saldo'] ?? '0.00')
            ];
        }

        return $stats;
    }

    /**
     * Haal dag statistieken op voor een specifieke datum
     *
     * Deze methode geeft de totalen voor één dag terug.
     *
     * BELANGRIJKE FIXES:
     * - Zelfde defensive programming als getWeekTotals
     * - COALESCE op alle sommen
     * - Strikte shopId filtering
     *
     * @param string $date Datum (YYYY-MM-DD)
     * @param int|null $shopId Optionele winkel filter
     * @return array{
     *   total_bons: int,
     *   total_players: int,
     *   total_rijen: int,
     *   total_bet: string,
     *   total_winnings: string
     * }
     *
     * @example
     * $dayStats = $repo->getDayStats('2024-01-01', 1);
     */
    public function getDayStats(string $date, ?int $shopId = null): array
    {
        $hasShopFilter = $shopId !== null;

        // Subqueries voor bonnen en spelers count
        $bonQuery = "SELECT COUNT(*) FROM bons WHERE date = $1";
        $playerQuery = "SELECT COUNT(DISTINCT player_id) FROM bons WHERE date = $1";

        if ($hasShopFilter) {
            $bonQuery .= " AND winkel_id = $2";
            $playerQuery .= " AND winkel_id = $2";
        }

        $query = "
            SELECT
                ($bonQuery) as total_bons,
                ($playerQuery) as total_players,
                COUNT(r.id) as total_rijen,
                COALESCE(SUM(r.bet), 0) as total_bet,
                COALESCE(SUM(r.winnings), 0) as total_winnings
            FROM
                rijen r
            INNER JOIN
                bons b ON r.bon_id = b.id
            WHERE
                b.date = $1
        ";

        $params = [$date];

        if ($hasShopFilter) {
            $query .= " AND b.winkel_id = $2";
            $params[] = $shopId;
        }

        $result = pg_query_params($this->conn, $query, $params);

        if (!$result) {
            return [
                'total_bons' => 0,
                'total_players' => 0,
                'total_rijen' => 0,
                'total_bet' => '0.00',
                'total_winnings' => '0.00'
            ];
        }

        $data = pg_fetch_assoc($result);

        return [
            'total_bons' => (int) ($data['total_bons'] ?? 0),
            'total_players' => (int) ($data['total_players'] ?? 0),
            'total_rijen' => (int) ($data['total_rijen'] ?? 0),
            'total_bet' => (string) ($data['total_bet'] ?? '0.00'),
            'total_winnings' => (string) ($data['total_winnings'] ?? '0.00')
        ];
    }

    /**
     * Haal alle bonnen op voor een specifieke datum
     *
     * Deze methode geeft een lijst van bonnen met aggregated rijen data.
     *
     * BELANGRIJKE FIXES:
     * - Subqueries voor rijen_count, total_bet, total_winnings
     * - Dit voorkomt duplicaten in de hoofdquery
     * - COALESCE op alle sommen
     * - EXISTS check voor bonnen met minimaal 1 rij
     *
     * @param string $date Datum (YYYY-MM-DD)
     * @param int|null $shopId Optionele winkel filter
     * @return array[] Lijst van bonnen
     */
    public function getBonnenByDate(string $date, ?int $shopId = null): array
    {
        $hasShopFilter = $shopId !== null;

        $query = "
            SELECT
                b.*,
                p.name as player_name,
                w.naam as winkel_name,

                -- Subqueries om duplicaten te voorkomen
                (SELECT COUNT(*) FROM rijen r WHERE r.bon_id = b.id) as rijen_count,
                (SELECT COALESCE(SUM(bet), 0) FROM rijen r WHERE r.bon_id = b.id) as total_bet,
                (SELECT COALESCE(SUM(winnings), 0) FROM rijen r WHERE r.bon_id = b.id) as total_winnings
            FROM
                bons b
            INNER JOIN
                players p ON b.player_id = p.id
            LEFT JOIN
                winkels w ON b.winkel_id = w.id
            WHERE
                b.date = $1
        ";

        $params = [$date];

        if ($hasShopFilter) {
            $query .= " AND b.winkel_id = $2";
            $params[] = $shopId;
        }

        // Alleen bonnen met minimaal 1 rij
        $query .= "
            AND EXISTS (SELECT 1 FROM rijen r WHERE r.bon_id = b.id)
            ORDER BY b.created_at DESC
        ";

        $result = pg_query_params($this->conn, $query, $params);

        if (!$result) {
            return [];
        }

        $bonnen = [];
        while ($row = pg_fetch_assoc($result)) {
            // Voeg player_color toe op basis van winkel
            $displayInfo = $this->getPlayerDisplayInfoByWinkel($row['winkel_name']);

            $bonnen[] = array_merge($row, [
                'player_color' => $displayInfo['color'],
                'rijen_count' => (int) ($row['rijen_count'] ?? 0),
                'total_bet' => (string) ($row['total_bet'] ?? '0.00'),
                'total_winnings' => (string) ($row['total_winnings'] ?? '0.00')
            ]);
        }

        return $bonnen;
    }

    /**
     * Haal statistieken per dag op voor een week range
     *
     * Deze methode itereert over alle dagen in een range en haalt
     * per dag de statistieken op.
     *
     * @param string $startDate Start datum (YYYY-MM-DD)
     * @param string $endDate Eind datum (YYYY-MM-DD)
     * @param int|null $shopId Optionele winkel filter
     * @return array[] Associative array met datum als key
     *
     * @example
     * $dailyStats = $repo->getDailyStatsForRange('2024-01-01', '2024-01-07', 1);
     * // Returns: [
     * //   '2024-01-01' => ['total_bons' => 10, 'total_bet' => '100.00', ...],
     * //   '2024-01-02' => ['total_bons' => 15, 'total_bet' => '150.00', ...],
     * //   ...
     * // ]
     */
    public function getDailyStatsForRange(string $startDate, string $endDate, ?int $shopId = null): array
    {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $stats = [];

        $current = clone $start;
        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');
            $stats[$dateStr] = $this->getDayStats($dateStr, $shopId);
            $current->modify('+1 day');
        }

        return $stats;
    }

    /**
     * Helper: Get player display info by winkel name
     *
     * @param string|null $winkelNaam Winkel naam
     * @return array{color: string, letter: string, winkel_naam: string}
     */
    private function getPlayerDisplayInfoByWinkel(?string $winkelNaam): array
    {
        // Deze functie bestaat al in functions.php
        // Voor nu: inline implementatie om dependency te vermijden
        $palette = [
            'Dapper' => ['accent' => '#FF9F40'],
            'Banne' => ['accent' => '#4A9EFF'],
            'Plein' => ['accent' => '#2ECC71'],
            'Jordy' => ['accent' => '#E74C8C'],
            'default' => ['accent' => '#2ECC71']
        ];

        $naam = $winkelNaam ?? '';
        $theme = $palette[$naam] ?? $palette['default'];
        $letter = $naam ? strtoupper(mb_substr($naam, 0, 1)) : '?';

        return [
            'color' => $theme['accent'],
            'letter' => $letter,
            'winkel_naam' => $naam ?: 'Onbekend'
        ];
    }

    /**
     * Haal spelers op die actief waren op een specifieke datum
     *
     * @param string $date Datum (YYYY-MM-DD)
     * @param int|null $shopId Optionele winkel filter
     * @return array[] Lijst van spelers
     */
    public function getPlayersByDate(string $date, ?int $shopId = null): array
    {
        $hasShopFilter = $shopId !== null;

        $query = "
            SELECT DISTINCT
                p.id,
                p.name,
                p.color
            FROM
                players p
            INNER JOIN
                bons b ON p.id = b.player_id
            WHERE
                b.date = $1
        ";

        $params = [$date];

        if ($hasShopFilter) {
            $query .= " AND b.winkel_id = $2";
            $params[] = $shopId;
        }

        $query .= " ORDER BY p.name";

        $result = pg_query_params($this->conn, $query, $params);

        if (!$result) {
            return [];
        }

        $players = [];
        while ($row = pg_fetch_assoc($result)) {
            $players[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'color' => (string) ($row['color'] ?? '#3B82F6')
            ];
        }

        return $players;
    }

    /**
     * Test of een query correct werkt (development helper)
     *
     * @return bool True als repository correct werkt
     */
    public function healthCheck(): bool
    {
        $result = pg_query($this->conn, "SELECT 1 as test");
        return $result && pg_num_rows($result) > 0;
    }
}
