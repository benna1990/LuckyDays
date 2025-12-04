<?php
/**
 * Winkel selector bar component
 * Herbruikbaar op alle pagina's
 * 
 * Gebruik: include 'components/winkel_bar.php';
 * Vereist: $winkels, $selectedWinkel, $activeWinkelTheme
 */
?>
<!-- Full-width Winkel Selector Bar -->
<div class="sticky top-[73px] z-40 border-b" 
     style="background: linear-gradient(to bottom, <?= $activeWinkelTheme['accent'] ?>0C 0%, <?= $activeWinkelTheme['accent'] ?>08 100%); border-color: <?= $activeWinkelTheme['accent'] ?>15;">
    <div class="container-fixed">
        <div class="flex items-center justify-center gap-2 py-3.5 flex-wrap">
            <?php renderWinkelButtons($winkels, $selectedWinkel); ?>
        </div>
    </div>
</div>


