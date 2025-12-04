<?php
/**
 * Hoofdnavigatie component
 * Herbruikbaar op alle pagina's
 * 
 * Gebruik: include 'components/main_nav.php';
 * Vereist: $activeWinkelTheme array met 'accent' key
 */

// Bepaal huidige pagina
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<nav class="bg-white border-b border-gray-100 sticky top-0 z-50">
    <div class="container-fixed py-3">
        <div class="flex items-center justify-between">
            <a href="dashboard.php" class="flex items-center gap-3 hover:opacity-80 transition">
                <span class="text-xl sm:text-2xl">🍀</span>
                <h1 class="text-lg sm:text-xl font-bold text-gray-800">Lucky Day</h1>
            </a>
            <div class="flex items-center gap-1 sm:gap-2">
                <a href="dashboard.php" 
                   class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?> px-3 py-2 text-sm font-medium transition rounded-t-lg <?= $current_page !== 'dashboard' ? 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' : '' ?>" 
                   <?= $current_page === 'dashboard' ? 'style="color: ' . $activeWinkelTheme['accent'] . ';"' : '' ?>>
                    Dashboard
                </a>
                <a href="weekoverzicht.php" 
                   class="nav-link <?= $current_page === 'weekoverzicht' ? 'active' : '' ?> px-3 py-2 text-sm font-medium transition rounded-t-lg <?= $current_page !== 'weekoverzicht' ? 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' : '' ?>"
                   <?= $current_page === 'weekoverzicht' ? 'style="color: ' . $activeWinkelTheme['accent'] . ';"' : '' ?>>
                   Weekoverzicht
                </a>
                <a href="bonnen.php" 
                   class="nav-link <?= $current_page === 'bonnen' ? 'active' : '' ?> px-3 py-2 text-sm font-medium transition rounded-t-lg <?= $current_page !== 'bonnen' ? 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' : '' ?>"
                   <?= $current_page === 'bonnen' ? 'style="color: ' . $activeWinkelTheme['accent'] . ';"' : '' ?>>
                    Bonnen
                </a>
                <a href="spelers.php" 
                   class="nav-link <?= $current_page === 'spelers' ? 'active' : '' ?> px-3 py-2 text-sm font-medium transition rounded-t-lg <?= $current_page !== 'spelers' ? 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' : '' ?>"
                   <?= $current_page === 'spelers' ? 'style="color: ' . $activeWinkelTheme['accent'] . ';"' : '' ?>>
                   Spelers
                </a>
                <a href="overzichten.php" 
                   class="nav-link <?= $current_page === 'overzichten' ? 'active' : '' ?> px-3 py-2 text-sm font-medium transition rounded-t-lg hide-on-mobile <?= $current_page !== 'overzichten' ? 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' : '' ?>"
                   <?= $current_page === 'overzichten' ? 'style="color: ' . $activeWinkelTheme['accent'] . ';"' : '' ?>>
                    Overzichten
                </a>
                <a href="beheer.php" 
                   class="nav-link <?= $current_page === 'beheer' ? 'active' : '' ?> px-3 py-2 text-sm font-medium transition rounded-t-lg hide-on-mobile <?= $current_page !== 'beheer' ? 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' : '' ?>"
                   <?= $current_page === 'beheer' ? 'style="color: ' . $activeWinkelTheme['accent'] . ';"' : '' ?>>
                    Beheer
                </a>
                <a href="logout.php" class="px-2 sm:px-3 py-1.5 text-xs sm:text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition">
                    <span class="hidden sm:inline">Uitloggen</span>
                    <span class="sm:hidden">✕</span>
                </a>
            </div>
        </div>
    </div>
</nav>
