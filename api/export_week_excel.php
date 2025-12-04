<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    echo 'Niet ingelogd';
    exit;
}

$week = isset($_GET['week']) ? intval($_GET['week']) : intval(date('W'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('o'));
$winkelParam = $_GET['winkel'] ?? 'all';
$selectedWinkel = ($winkelParam === 'all') ? null : intval($winkelParam);

$weekRange = getISOWeekRange(null, $year, $week);
$winkels = getAllWinkels($conn);

function fetchWeekStatsForWinkel($conn, $start, $end, $winkelId = null) {
    $query = "SELECT
                p.id,
                p.name,
                p.color,
                COUNT(DISTINCT b.id) as total_bons,
                COUNT(r.id) as total_rijen,
                COALESCE(SUM(r.bet), 0) as total_bet,
                COALESCE(SUM(r.winnings), 0) as total_winnings
              FROM players p
              LEFT JOIN bons b ON p.id = b.player_id AND DATE(b.date) BETWEEN $1 AND $2";
    $params = [$start, $end];
    if ($winkelId !== null) {
        $query .= " AND b.winkel_id = $3";
        $params[] = $winkelId;
    }
    $query .= " LEFT JOIN rijen r ON b.id = r.bon_id
               WHERE b.id IS NOT NULL AND r.id IS NOT NULL
               GROUP BY p.id, p.name, p.color
               HAVING COUNT(DISTINCT b.id) > 0 AND COUNT(r.id) > 0
               ORDER BY (COALESCE(SUM(r.bet), 0) - COALESCE(SUM(r.winnings), 0)) DESC";

    $res = db_query($query, $params);
    return db_fetch_all($res) ?: [];
}

function colLetter($n) {
    $r = '';
    while ($n >= 0) {
        $r = chr($n % 26 + 65) . $r;
        $n = intdiv($n, 26) - 1;
    }
    return $r;
}

function sheetXML($rows) {
    $xmlRows = '';
    foreach ($rows as $rIdx => $row) {
        $cells = '';
        foreach (array_values($row) as $cIdx => $val) {
            $cellRef = colLetter($cIdx) . ($rIdx + 1);
            $valEsc = htmlspecialchars($val, ENT_XML1);
            $cells .= "<c r=\"$cellRef\" t=\"inlineStr\"><is><t>$valEsc</t></is></c>";
        }
        $xmlRows .= "<row r=\"" . ($rIdx + 1) . "\">$cells</row>";
    }
    return "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><worksheet xmlns=\"http://schemas.openxmlformats.org/spreadsheetml/2006/main\"><sheetData>$xmlRows</sheetData></worksheet>";
}

function baseContentTypes($sheetsCount) {
    $sheets = '';
    for ($i = 1; $i <= $sheetsCount; $i++) {
        $sheets .= "<Override PartName=\"/xl/worksheets/sheet{$i}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
    }
    return "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>
    <Types xmlns=\"http://schemas.openxmlformats.org/package/2006/content-types\">
      <Default Extension=\"rels\" ContentType=\"application/vnd.openxmlformats-package.relationships+xml\"/>
      <Default Extension=\"xml\" ContentType=\"application/xml\"/>
      <Override PartName=\"/xl/workbook.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml\"/>
      <Override PartName=\"/xl/styles.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml\"/>
      $sheets
    </Types>";
}

function workbookXML($sheets) {
    $sheetEntries = '';
    foreach ($sheets as $i => $s) {
        $sheetId = $i + 1;
        $sheetEntries .= "<sheet name=\"" . htmlspecialchars($s['name'], ENT_XML1) . "\" sheetId=\"$sheetId\" r:id=\"rId$sheetId\"/>";
    }
    return "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>
    <workbook xmlns=\"http://schemas.openxmlformats.org/spreadsheetml/2006/main\" xmlns:r=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships\"><sheets>$sheetEntries</sheets></workbook>";
}

function workbookRelsXML($sheetsCount) {
    $rels = '';
    for ($i = 1; $i <= $sheetsCount; $i++) {
        $rels .= "<Relationship Id=\"rId{$i}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$i}.xml\"/>";
    }
    return "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>
    <Relationships xmlns=\"http://schemas.openxmlformats.org/package/2006/relationships\">
      <Relationship Id=\"rIdStyles\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles\" Target=\"styles.xml\"/>
      $rels
    </Relationships>";
}

$stylesXml = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>
<styleSheet xmlns=\"http://schemas.openxmlformats.org/spreadsheetml/2006/main\">
  <fonts count=\"1\"><font><sz val=\"11\"/><name val=\"Arial\"/></font></fonts>
  <fills count=\"1\"><fill><patternFill patternType=\"none\"/></fill></fills>
  <borders count=\"1\"><border/></borders>
  <cellStyleXfs count=\"1\"><xf numFmtId=\"0\" fontId=\"0\" fillId=\"0\" borderId=\"0\"/></cellStyleXfs>
  <cellXfs count=\"1\"><xf numFmtId=\"0\" fontId=\"0\" fillId=\"0\" borderId=\"0\" xfId=\"0\"/></cellXfs>
</styleSheet>";

$sheetsData = [];

if ($selectedWinkel !== null) {
    // Single winkel
    $stats = fetchWeekStatsForWinkel($conn, $weekRange['start'], $weekRange['end'], $selectedWinkel);
    $winkelNaam = 'Winkel ' . $selectedWinkel;
    foreach ($winkels as $w) {
        if (intval($w['id']) === $selectedWinkel) { $winkelNaam = $w['naam']; break; }
    }
    $rows = [];
    $rows[] = ["Weekoverzicht Week {$week} {$year}", '', '', '', '', '', ''];
    $rows[] = ["Periode: {$weekRange['start']} t/m {$weekRange['end']}", '', '', '', '', '', ''];
    $rows[] = ["Winkel: {$winkelNaam}", '', '', '', '', '', ''];
    $totalBet = array_sum(array_map(fn($p) => floatval($p['total_bet']), $stats));
    $totalWin = array_sum(array_map(fn($p) => floatval($p['total_winnings']), $stats));
    $commission = $totalBet * 0.30;
    $housePot = $totalBet * 0.70;
    $netHouse = $housePot - $totalWin;
    $rows[] = ["Totale inzet", number_format($totalBet, 2, ',', '.'), '', '', '', '', ''];
    $rows[] = ["Commissie (30%)", number_format($commission, 2, ',', '.'), '', '', '', '', ''];
    $rows[] = ["Huispot (70%)", number_format($housePot, 2, ',', '.'), '', '', '', '', ''];
    $rows[] = ["Uitbetaald", number_format($totalWin, 2, ',', '.'), '', '', '', '', ''];
    $rows[] = ["Netto huis", number_format($netHouse, 2, ',', '.'), '', '', '', '', ''];
    $rows[] = ['', '', '', '', '', '', ''];
    $rows[] = ['Speler', 'Bonnen', 'Rijen', 'Inzet', 'Uitbetaald', 'Huisresultaat', 'Richting'];
    foreach ($stats as $ps) {
        $huis = floatval($ps['total_bet']) - floatval($ps['total_winnings']);
        $richting = $huis > 0 ? 'Huis wint' : ($huis < 0 ? 'Huis verliest' : 'Gelijk');
        $rows[] = [
            $ps['name'],
            $ps['total_bons'],
            $ps['total_rijen'],
            number_format($ps['total_bet'], 2, ',', '.'),
            number_format($ps['total_winnings'], 2, ',', '.'),
            ($huis >= 0 ? '+' : '-') . number_format(abs($huis), 2, ',', '.'),
            $richting
        ];
    }
    $sheetsData[] = ['name' => $winkelNaam, 'xml' => sheetXML($rows)];
} else {
    // All winkels: one sheet per winkel
    foreach ($winkels as $w) {
        $stats = fetchWeekStatsForWinkel($conn, $weekRange['start'], $weekRange['end'], $w['id']);
        if (empty($stats)) continue;
        $rows = [];
        $rows[] = ["Weekoverzicht Week {$week} {$year}", '', '', '', '', '', ''];
        $rows[] = ["Periode: {$weekRange['start']} t/m {$weekRange['end']}", '', '', '', '', '', ''];
        $rows[] = ["Winkel: {$w['naam']}", '', '', '', '', '', ''];
        $totalBet = array_sum(array_map(fn($p) => floatval($p['total_bet']), $stats));
        $totalWin = array_sum(array_map(fn($p) => floatval($p['total_winnings']), $stats));
        $commission = $totalBet * 0.30;
        $housePot = $totalBet * 0.70;
        $netHouse = $housePot - $totalWin;
        $rows[] = ["Totale inzet", number_format($totalBet, 2, ',', '.'), '', '', '', '', ''];
        $rows[] = ["Commissie (30%)", number_format($commission, 2, ',', '.'), '', '', '', '', ''];
        $rows[] = ["Huispot (70%)", number_format($housePot, 2, ',', '.'), '', '', '', '', ''];
        $rows[] = ["Uitbetaald", number_format($totalWin, 2, ',', '.'), '', '', '', '', ''];
        $rows[] = ["Netto huis", number_format($netHouse, 2, ',', '.'), '', '', '', '', ''];
        $rows[] = ['', '', '', '', '', '', ''];
        $rows[] = ['Speler', 'Bonnen', 'Rijen', 'Inzet', 'Uitbetaald', 'Huisresultaat', 'Richting'];
        foreach ($stats as $ps) {
            $huis = floatval($ps['total_bet']) - floatval($ps['total_winnings']);
            $richting = $huis > 0 ? 'Huis wint' : ($huis < 0 ? 'Huis verliest' : 'Gelijk');
            $rows[] = [
                $ps['name'],
                $ps['total_bons'],
                $ps['total_rijen'],
                number_format($ps['total_bet'], 2, ',', '.'),
                number_format($ps['total_winnings'], 2, ',', '.'),
                ($huis >= 0 ? '+' : '-') . number_format(abs($huis), 2, ',', '.'),
                $richting
            ];
        }
        $sheetsData[] = ['name' => $w['naam'], 'xml' => sheetXML($rows)];
    }
}

if (empty($sheetsData)) {
    echo 'Geen data voor deze selectie';
    exit;
}

$sheetCount = count($sheetsData);

$zip = new ZipArchive();
$tmpFile = tempnam(sys_get_temp_dir(), 'xlsx');
$zip->open($tmpFile, ZipArchive::OVERWRITE);

$zip->addFromString('[Content_Types].xml', baseContentTypes($sheetCount));
$zip->addFromString('_rels/.rels', "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n<Relationships xmlns=\"http://schemas.openxmlformats.org/package/2006/relationships\">\n  <Relationship Id=\"rId1\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument\" Target=\"/xl/workbook.xml\"/>\n</Relationships>");
$zip->addFromString('xl/workbook.xml', workbookXML($sheetsData));
$zip->addFromString('xl/_rels/workbook.xml.rels', workbookRelsXML($sheetCount));
$zip->addFromString('xl/styles.xml', $stylesXml);

foreach ($sheetsData as $i => $sheet) {
    $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $sheet['xml']);
}

$zip->close();

$filename = 'weekoverzicht_week' . $week . '_' . $year . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
readfile($tmpFile);
unlink($tmpFile);
exit;
