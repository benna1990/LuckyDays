# LuckyDays Complete UI Consistency Plan - Verbeterd

**Datum:** 28 november 2025  
**Status:** Plan klaar voor implementatie

---

## Probleem Overzicht

### 4 Hoofdproblemen:
1. **Niet alle knoppen gebruiken winkelkleur** - Tientallen knoppen missen nog winkelkleur styling
2. **Gradient in winkelpills NIET zichtbaar** - Huidige animatie te subtiel of niet geladen
3. **Dagselector te druk en vol** - Terug naar compactere scrollbare versie
4. **Headers verschillen per pagina** - Inconsistente headerhoogte en spacing

### 5 Aanvullende Eisen:
1. Logo + LuckyDays-tekst afstand uniform maken
2. Dagselector mooier, rustiger, compacter
3. Legenda beschrijving verbeteren
4. Knoppen zonder opmaak herstellen (Nieuwe Bon, Export CSV)
5. Dagselector direct positioneren zonder scroll-animatie

---

## Deel 1: Alle Knoppen Winkelkleur (Probleem 1 + Eis 4)

### Probleemanalyse

**Huidige situatie:**
- Dashboard: "Nieuwe bon" heeft styling maar niet altijd correct
- Overzichten: Tabs (Dagelijks, Wekelijks, Maandelijks, Jaarlijks) gebruiken winkelkleur MAAR niet uniform
- Spelers: Tabs (Lifetime Statistieken, Per Periode) hebben alleen border-color
- Beheer: Tabs (Instellingen, Databeheer, Gebruikers) gebruiken winkelkleur alleen voor active
- Weekoverzicht: "Export CSV" heeft GEEN styling
- Alle popups: Knoppen hebben geen consistente winkelkleur

### Oplossing: Universeel Tab/Button Systeem

#### Nieuwe CSS Classes

```css
/* Tab Pills met winkelkleur */
.tab-pill {
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 500;
    border-radius: var(--radius-lg);
    transition: all 0.2s ease;
    cursor: pointer;
    border: 2px solid transparent;
}

.tab-pill.inactive {
    background: white;
    color: var(--gray-700);
    border-color: var(--gray-200);
}

.tab-pill.inactive:hover {
    background: var(--gray-50);
    border-color: var(--tab-color, var(--color-plein));
    color: var(--tab-color, var(--color-plein));
}

.tab-pill.active {
    background: var(--tab-bg, var(--color-plein));
    color: white;
    border-color: var(--tab-bg, var(--color-plein));
    font-weight: 600;
}

/* Tab Underline (zoals Spelers pagina) */
.tab-underline {
    padding: 10px 16px;
    font-size: 14px;
    font-weight: 500;
    border-bottom: 2px solid transparent;
    transition: all 0.2s ease;
    cursor: pointer;
}

.tab-underline.inactive {
    color: var(--gray-600);
    border-bottom-color: transparent;
}

.tab-underline.inactive:hover {
    color: var(--tab-color, var(--color-plein));
    border-bottom-color: var(--tab-color, var(--color-plein))50;
}

.tab-underline.active {
    color: var(--tab-color, var(--color-plein));
    border-bottom-color: var(--tab-color, var(--color-plein));
    font-weight: 600;
}
```

### Implementatie Per Pagina

#### Overzichten.php - Period Tabs

**NU (emoji + inconsistente styling):**
```php
<a href="?view=daily" class="... <?= $view === 'daily' ? 'text-white' : 'bg-white text-gray-700' ?>">
    📅 Dagelijks
</a>
```

**WORDT:**
```php
<a href="?view=daily" 
   class="tab-pill <?= $view === 'daily' ? 'active' : 'inactive' ?>"
   style="--tab-bg: <?= $activeWinkelTheme['accent'] ?>; --tab-color: <?= $activeWinkelTheme['accent'] ?>;">
    Dagelijks
</a>
<a href="?view=weekly" 
   class="tab-pill <?= $view === 'weekly' ? 'active' : 'inactive' ?>"
   style="--tab-bg: <?= $activeWinkelTheme['accent'] ?>; --tab-color: <?= $activeWinkelTheme['accent'] ?>;">
    Wekelijks
</a>
<a href="?view=monthly" 
   class="tab-pill <?= $view === 'monthly' ? 'active' : 'inactive' ?>"
   style="--tab-bg: <?= $activeWinkelTheme['accent'] ?>; --tab-color: <?= $activeWinkelTheme['accent'] ?>;">
    Maandelijks
</a>
<a href="?view=yearly" 
   class="tab-pill <?= $view === 'yearly' ? 'active' : 'inactive' ?>"
   style="--tab-bg: <?= $activeWinkelTheme['accent'] ?>; --tab-color: <?= $activeWinkelTheme['accent'] ?>;">
    Jaarlijks
</a>
```

**Wijzigingen:**
- ✅ Verwijder emoji's (📅 📊 📈 📆)
- ✅ Gebruik `.tab-pill` classes
- ✅ Uniforme styling met winkelkleur
- ✅ Consistent hover gedrag

#### Spelers.php - Lifetime/Period Tabs

**NU:**
```php
<button onclick="switchTab('lifetime')" id="tab-lifetime" 
        class="px-4 py-2 border-b-2 font-medium text-sm" 
        style="border-color: <?= $activeWinkelTheme['accent'] ?>; color: <?= $activeWinkelTheme['accent'] ?>;">
    Lifetime Statistieken
</button>
```

**WORDT:**
```php
<button onclick="switchTab('lifetime')" id="tab-lifetime" 
        class="tab-underline active"
        style="--tab-color: <?= $activeWinkelTheme['accent'] ?>;">
    Lifetime Statistieken
</button>
<button onclick="switchTab('period')" id="tab-period" 
        class="tab-underline inactive"
        style="--tab-color: <?= $activeWinkelTheme['accent'] ?>;">
    Per Periode
</button>
```

**JavaScript update:**
```javascript
function switchTab(tabName) {
    // Remove active, add inactive
    document.querySelectorAll('.tab-underline').forEach(tab => {
        tab.classList.remove('active');
        tab.classList.add('inactive');
    });
    
    // Add active to clicked tab
    document.getElementById('tab-' + tabName).classList.remove('inactive');
    document.getElementById('tab-' + tabName).classList.add('active');
    
    // Show/hide content (existing logic)
    ...
}
```

#### Beheer.php - Settings Tabs

**NU:**
```php
<button onclick="showTab('instellingen')" id="tab-instellingen" 
        class="px-4 py-2 rounded-lg transition text-sm font-medium text-white" 
        style="background: <?= $activeWinkelTheme['accent'] ?>;">
    Instellingen
</button>
<button onclick="showTab('databeheer')" id="tab-databeheer" 
        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm font-medium">
    Databeheer
</button>
```

**WORDT:**
```php
<button onclick="showTab('instellingen')" id="tab-instellingen" 
        class="tab-pill active"
        style="--tab-bg: <?= $activeWinkelTheme['accent'] ?>; --tab-color: <?= $activeWinkelTheme['accent'] ?>;">
    Instellingen
</button>
<button onclick="showTab('databeheer')" id="tab-databeheer" 
        class="tab-pill inactive"
        style="--tab-bg: <?= $activeWinkelTheme['accent'] ?>; --tab-color: <?= $activeWinkelTheme['accent'] ?>;">
    Databeheer
</button>
<button onclick="showTab('gebruikers')" id="tab-gebruikers" 
        class="tab-pill inactive"
        style="--tab-bg: <?= $activeWinkelTheme['accent'] ?>; --tab-color: <?= $activeWinkelTheme['accent'] ?>;">
    Gebruikers
</button>
```

#### Dashboard.php - Nieuwe Bon Button Fix

**PROBLEEM:** Button heeft mogelijk geen/verkeerde styling

**FIX:**
```php
<?php if ($selectedWinkel !== null): ?>
    <button type="button" data-trigger="new-bon" 
            class="btn-primary"
            style="--btn-bg: <?= $activeWinkelTheme['accent'] ?>; 
                   --btn-shadow: 0 2px 8px <?= $activeWinkelTheme['accent'] ?>30; 
                   --btn-hover-shadow: 0 4px 12px <?= $activeWinkelTheme['accent'] ?>40;">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        <span>Nieuwe bon</span>
    </button>
<?php else: ?>
    <button type="button" data-trigger="new-bon" class="btn-primary neutral">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        <span>Nieuwe bon</span>
    </button>
<?php endif; ?>
```

#### Weekoverzicht.php - Export CSV Button Fix

**PROBLEEM:** Button heeft geen styling

**FIX:**
```php
<form method="post" class="inline-block">
    <input type="hidden" name="action" value="export_csv">
    <button type="submit" class="btn-secondary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <span>Exporteer CSV</span>
    </button>
</form>
```

---

## Deel 2: Zichtbare Gradient in Winkelpills (Probleem 2)

### Probleemanalyse

Huidige gradient animatie is NIET zichtbaar omdat:
1. Opacity te laag (6-8%)
2. Animatie te traag (8s)
3. Geen visueel contrast
4. Mogelijk CSS niet geladen

### Oplossing: 3 Gradient Varianten

#### Variant A: Soft Mesh Gradient (Aanbevolen)

**Zichtbaarheid:** Medium-High (12-20% opacity)  
**Snelheid:** 25s cycle  
**Effect:** Zachte mesh-achtige gradient die beweegt

```css
.winkel-btn::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(
        circle at 30% 50%,
        var(--gradient-color)18 0%,
        transparent 50%
    ),
    radial-gradient(
        circle at 70% 50%,
        var(--gradient-color)12 0%,
        transparent 50%
    );
    animation: meshMove 25s ease-in-out infinite;
    z-index: -1;
    will-change: transform;
}

@keyframes meshMove {
    0%, 100% {
        transform: translate(0, 0) rotate(0deg);
    }
    33% {
        transform: translate(10%, 5%) rotate(5deg);
    }
    66% {
        transform: translate(-5%, -10%) rotate(-5deg);
    }
}

.winkel-btn.active::before {
    background: radial-gradient(
        circle at 30% 50%,
        var(--gradient-color)25 0%,
        transparent 50%
    ),
    radial-gradient(
        circle at 70% 50%,
        var(--gradient-color)18 0%,
        transparent 50%
    );
    animation: meshMove 20s ease-in-out infinite;
}
```

#### Variant B: Light Moving Diagonal Pattern

**Zichtbaarheid:** High (15-25% opacity)  
**Snelheid:** 30s cycle  
**Effect:** Diagonale lichtstrepen die langzaam bewegen

```css
.winkel-btn::before {
    content: '';
    position: absolute;
    top: -100%;
    left: -100%;
    width: 300%;
    height: 300%;
    background: repeating-linear-gradient(
        45deg,
        transparent,
        transparent 40px,
        var(--gradient-color)15 40px,
        var(--gradient-color)15 80px,
        transparent 80px
    );
    animation: diagonalMove 30s linear infinite;
    z-index: -1;
    opacity: 0.6;
}

@keyframes diagonalMove {
    0% {
        transform: translate(0, 0);
    }
    100% {
        transform: translate(80px, 80px);
    }
}

.winkel-btn.active::before {
    background: repeating-linear-gradient(
        45deg,
        transparent,
        transparent 40px,
        var(--gradient-color)22 40px,
        var(--gradient-color)22 80px,
        transparent 80px
    );
    animation: diagonalMove 25s linear infinite;
    opacity: 0.8;
}
```

#### Variant C: Subtle Pulsing Glow Edges

**Zichtbaarheid:** Medium (10-18% opacity)  
**Snelheid:** 35s cycle  
**Effect:** Zachte gloed die pulseert rondom de edges

```css
.winkel-btn {
    position: relative;
    box-shadow: 0 0 0 0 var(--gradient-color)00;
    animation: edgePulse 35s ease-in-out infinite;
}

.winkel-btn::before {
    content: '';
    position: absolute;
    inset: -2px;
    border-radius: inherit;
    padding: 2px;
    background: linear-gradient(
        45deg,
        var(--gradient-color)18,
        var(--gradient-color)08,
        var(--gradient-color)15,
        var(--gradient-color)08
    );
    -webkit-mask: linear-gradient(#fff 0 0) content-box, 
                   linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    animation: edgeRotate 35s linear infinite;
    z-index: -1;
}

@keyframes edgePulse {
    0%, 100% {
        box-shadow: 0 0 0 0 var(--gradient-color)00;
    }
    50% {
        box-shadow: 0 0 20px 2px var(--gradient-color)12;
    }
}

@keyframes edgeRotate {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

.winkel-btn.active::before {
    background: linear-gradient(
        45deg,
        var(--gradient-color)25,
        var(--gradient-color)15,
        var(--gradient-color)22,
        var(--gradient-color)15
    );
    animation: edgeRotate 30s linear infinite;
}
```

### Aanbeveling

**Variant A (Soft Mesh Gradient)** omdat:
- ✅ Duidelijk zichtbaar (12-20% opacity)
- ✅ Niet te druk of afleidend
- ✅ Moderne, professionele uitstraling
- ✅ Goed werkend op alle winkels
- ✅ Performance efficiënt

### Zichtbaarheids Richtlijnen

**Te SUBTIEL (vermijden):**
- < 8% opacity
- > 40s animatie
- Monotone gradient

**GOED ZICHTBAAR (doel):**
- 12-20% opacity voor inactive
- 18-28% opacity voor active
- 20-35s animatie cycle
- Multi-dimensionale gradient

**Te DRUK (vermijden):**
- > 30% opacity
- < 15s animatie
- Hard contrast
- Schreeuwerige kleuren

---

## Deel 3: Dagselector Compact & Direct Positionering (Probleem 3 + Eis 2, 3, 5)

### Probleemanalyse

**Huidige problemen:**
- Te groot (scroll controls nemen veel ruimte)
- Niet gecentreerd op geselecteerde dag bij load
- Auto-scroll animatie is afleidend
- Week labels te prominent
- Legenda onduidelijk

### Oplossing: Compacte Scrollable Dagselector

#### Layout Wijziging

**NU:**
```
┌─────────────────────────────────────────────────────────────┐
│  Date Selector Container (padding: 20px)                   │
│  ┌───────────────────────────────────────────────────────┐ │
│  │ [<] Scroll door weken [>]                             │ │ ← VERWIJDER
│  └───────────────────────────────────────────────────────┘ │
│  ┌───────────────────────────────────────────────────────┐ │
│  │ Week 47  Week 48  Week 49                             │ │
│  │ ● ● ● ●  ● ● ● ●  ● ● ● ●                             │ │
│  └───────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

**WORDT:**
```
┌─────────────────────────────────────────────────────────────┐
│  Date Selector (padding: 12px, compact)                    │
│  ┌───────────────────────────────────────────────────────┐ │
│  │ Week 47   Week 48   Week 49   Week 50                 │ │ ← Kleiner
│  │ ● ● ● ●   ● ● ● ●   ● ● ● ●   ● ● ● ●                │ │ ← Compacter
│  └───────────────────────────────────────────────────────┘ │
│  ┌───────────────────────────────────────────────────────┐ │
│  │ ▬ Nummers opgehaald  ▬▬ Bonnen toegevoegd            │ │ ← Nieuwe legenda
│  └───────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

#### CSS Updates

```css
.date-selector-fullwidth {
    background: white;
    border-radius: var(--radius-lg);
    border: 1px solid var(--gray-200);
    padding: 12px;  /* Was 20px - COMPACTER */
    box-shadow: var(--shadow-sm);
    margin: 12px 0;  /* Was 16px */
}

/* VERWIJDER scroll controls */
.date-scroll-controls {
    display: none;
}

.date-track {
    display: flex;
    gap: 0;
    overflow-x: auto;
    overflow-y: hidden;
    scroll-behavior: auto;  /* Was smooth - DIRECT */
    scrollbar-width: thin;  /* Toon scrollbar */
    scrollbar-color: var(--date-accent-color)30 transparent;
    padding: 4px;
    /* Scroll naar center position op load */
    scroll-padding-inline: 40%;
}

.week-label {
    font-size: 9px;  /* Was 10px - KLEINER */
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;  /* Was 0.5px */
    color: var(--week-label-color);
    background: transparent;  /* Was colored bg - RUSTIGER */
    padding: 2px 6px;  /* Was 4px 10px - COMPACTER */
    border-radius: 4px;
    text-align: center;
    opacity: 0.6;  /* SUBTIEL */
}

.date-btn {
    width: 64px;  /* Was 72px - KLEINER */
    height: 48px;  /* Was 52px - COMPACTER */
    border-radius: var(--radius-lg);
    border: 1.5px solid var(--gray-200);
    background: white;
    font-size: 11px;  /* Was 12px */
    transition: all 0.2s ease;
}

.week-divider {
    width: 1px;
    background: linear-gradient(
        to bottom,
        transparent,
        var(--divider-color) 30%,  /* Was 20% */
        var(--divider-color) 70%,  /* Was 80% */
        transparent
    );
    margin: 20px 6px 0 6px;  /* Was 28px */
}
```

#### JavaScript: Direct Positionering

```javascript
// VERWIJDER oude auto-scroll animatie
// VERVANG met directe positionering

document.addEventListener('DOMContentLoaded', function() {
    const track = document.getElementById('dateTrack');
    const currentWeek = document.getElementById('currentWeek');
    
    if (track && currentWeek) {
        // Bereken center position
        const trackWidth = track.offsetWidth;
        const weekLeft = currentWeek.offsetLeft;
        const weekWidth = currentWeek.offsetWidth;
        
        // Positioneer direct (geen animatie)
        const scrollPosition = weekLeft - (trackWidth / 2) + (weekWidth / 2);
        track.scrollLeft = scrollPosition;
    }
});
```

#### Verbeterde Legenda

**NU:** Onduidelijk met icons

**WORDT:**
```html
<div class="date-legend">
    <div class="legend-item">
        <div class="legend-indicator thin"></div>
        <span>Nummers opgehaald</span>
    </div>
    <div class="legend-item">
        <div class="legend-indicator thick"></div>
        <span>Bonnen toegevoegd</span>
    </div>
</div>

<style>
.date-legend {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 24px;
    padding: 8px 0 4px;
    font-size: 11px;
    color: var(--gray-600);
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
}

.legend-indicator {
    width: 24px;
    height: 3px;
    border-radius: 2px;
    background: var(--date-accent-color);
}

.legend-indicator.thin {
    height: 2px;
    opacity: 0.4;
}

.legend-indicator.thick {
    height: 3px;
    opacity: 1;
}
</style>
```

---

## Deel 4: Uniforme Headers & Menu's (Probleem 4 + Eis 1)

### Probleemanalyse

**Huidige verschillen:**
- Spelers: Heeft nog oude winkelbanner structuur
- Overzichten: Andere header spacing
- Beheer: Andere titel marges
- Inconsistente top spacing

### Oplossing: Universele Header Template

#### Template Structuur

```html
<!-- ELKE PAGINA KRIJGT EXACT DEZE STRUCTUUR -->

<!-- 1. Main Nav (73px hoogte) -->
<nav class="bg-white border-b border-gray-100 sticky top-0 z-50">
    <div class="container-fixed py-3">
        <div class="flex items-center justify-between">
            <!-- Logo (CONSISTENTE SPACING) -->
            <a href="dashboard.php" class="flex items-center gap-3 hover:opacity-80 transition">
                <span class="text-2xl">🍀</span>
                <h1 class="text-xl font-bold text-gray-800">Lucky Day</h1>
            </a>
            
            <!-- Nav Links -->
            <div class="flex items-center gap-2">
                <a href="dashboard.php" class="nav-link [...]">Dashboard</a>
                <a href="weekoverzicht.php" class="nav-link [...]">Weekoverzicht</a>
                <a href="spelers.php" class="nav-link [...]">Spelers</a>
                <a href="overzichten.php" class="nav-link [...]">Overzichten</a>
                <a href="beheer.php" class="nav-link [...]">Beheer</a>
                <a href="logout.php" class="[...]">Uitloggen</a>
            </div>
        </div>
    </div>
</nav>

<!-- 2. Old Data Warning (indien nodig) -->
<?php include 'components/old_data_warning.php'; ?>

<!-- 3. Winkel Selector Bar (EXACT ZELFDE STYLING) -->
<div class="sticky top-[73px] z-40 border-b"
     style="background: linear-gradient(to bottom, <?= $activeWinkelTheme['accent'] ?>0C 0%, <?= $activeWinkelTheme['accent'] ?>08 100%); border-color: <?= $activeWinkelTheme['accent'] ?>15;">
    <div class="container-fixed">
        <div class="flex items-center justify-center gap-2 py-3.5 flex-wrap">
            <?php renderWinkelButtons($winkels, $selectedWinkel); ?>
        </div>
    </div>
</div>

<!-- 4. Main Content (EXACT ZELFDE SPACING) -->
<main class="container-fixed py-6">
    <!-- Page Title (EXACT ZELFDE MARGES) -->
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-1">[Page Title]</h2>
        <p class="text-sm text-gray-600">[Optional subtitle]</p>
    </div>
    
    <!-- Page Content -->
    [...]
</main>
```

#### Specifieke Fixes Per Pagina

**Spelers.php:**
```html
<!-- VERWIJDER oude winkelbanner -->
<!-- VERVANG met standaard template -->

<!-- NU: -->
<div class="sticky top-[73px] z-40 border-b"
     style="background: linear-gradient(to right, <?= $activeWinkelTheme['accent'] ?>05 0%, <?= $activeWinkelTheme['accent'] ?>03 50%, transparent 100%); border-color: <?= $activeWinkelTheme['accent'] ?>15;">
    [...]
</div>

<!-- WORDT: -->
<div class="sticky top-[73px] z-40 border-b"
     style="background: linear-gradient(to bottom, <?= $activeWinkelTheme['accent'] ?>0C 0%, <?= $activeWinkelTheme['accent'] ?>08 100%); border-color: <?= $activeWinkelTheme['accent'] ?>15;">
    <div class="container-fixed">
        <div class="flex items-center justify-center gap-2 py-3.5 flex-wrap">
            <?php renderWinkelButtons($winkels, $selectedWinkel); ?>
        </div>
    </div>
</div>

<!-- Titel spacing -->
<!-- NU: -->
<div class="mb-8">
    <h2 class="text-2xl font-bold text-gray-900 mb-4">Speler Overzicht</h2>

<!-- WORDT: -->
<main class="container-fixed py-6">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-1">Speler Overzicht</h2>
        <p class="text-sm text-gray-600">Bekijk statistieken per speler</p>
    </div>
```

**Overzichten.php:**
```html
<!-- Titel en subtitle spacing -->
<!-- NU: -->
<div class="mb-6">
    <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-2">Tijd Analyses</h2>
    <p class="text-sm text-gray-600">Bekijk trends over dagen, weken, maanden en jaren</p>
</div>

<!-- WORDT: -->
<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-900 mb-1">Tijd Analyses</h2>
    <p class="text-sm text-gray-600">Bekijk trends over dagen, weken, maanden en jaren</p>
</div>
```

**Beheer.php:**
```html
<!-- Titel spacing -->
<!-- NU: -->
<h2 class="text-2xl font-bold text-gray-900 mb-6">Beheer</h2>

<!-- WORDT: -->
<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-900 mb-1">Beheer</h2>
    <p class="text-sm text-gray-600">Instellingen en databeheer</p>
</div>
```

#### Logo Spacing Fix (Eis 1)

**Consistent op ALLE pagina's:**
```html
<a href="dashboard.php" class="flex items-center gap-3 hover:opacity-80 transition">
    <span class="text-2xl">🍀</span>  <!-- Exact 2xl -->
    <h1 class="text-xl font-bold text-gray-800">Lucky Day</h1>  <!-- Exact xl -->
</a>
```

**CSS:**
```css
nav .flex items-center gap-3 {
    gap: 0.75rem;  /* Exact 12px overal */
}
```

---

## Implementatie Volgorde

### Fase 1: Foundation Updates
1. ✅ Toevoegen `.tab-pill` en `.tab-underline` CSS classes
2. ✅ Toevoegen verbeterde gradient varianten CSS
3. ✅ Toevoegen compacte dagselector CSS
4. ✅ Toevoegen nieuwe legenda CSS

### Fase 2: Button Consistency
1. ✅ Overzichten: Update period tabs (verwijder emoji's)
2. ✅ Spelers: Update lifetime/period tabs
3. ✅ Beheer: Update settings tabs
4. ✅ Dashboard: Fix "Nieuwe bon" button styling
5. ✅ Weekoverzicht: Fix "Export CSV" button styling

### Fase 3: Gradient Visibility
1. ✅ Kies gradient variant (A, B, of C)
2. ✅ Update winkel_selector.php met nieuwe CSS variabelen
3. ✅ Test zichtbaarheid op alle winkels
4. ✅ Adjust opacity indien nodig

### Fase 4: Dagselector Compact
1. ✅ Update CSS: kleiner, compacter, rustiger
2. ✅ Verwijder scroll controls HTML
3. ✅ Update JavaScript: directe positionering
4. ✅ Toevoegen nieuwe legenda HTML

### Fase 5: Header Uniformity
1. ✅ Update spelers.php: winkel selector + titel spacing
2. ✅ Update overzichten.php: titel consistency
3. ✅ Update beheer.php: titel + subtitle
4. ✅ Check weekoverzicht.php: consistency
5. ✅ Verify dashboard.php: reference template

### Fase 6: Testing & Polish
1. ✅ Test alle tabs op alle pagina's
2. ✅ Verify gradient zichtbaarheid
3. ✅ Test dagselector direct positioning
4. ✅ Check header heights consistency
5. ✅ Mobile responsive check

---

## Verwacht Resultaat

✅ **Complete Button Consistency**
- Alle tabs gebruiken winkelkleur
- Alle primaire acties in winkelkleur
- Uniforme hover states
- Emoji's verwijderd

✅ **Zichtbare Gradient**
- 12-20% opacity (duidelijk zichtbaar)
- 25-35s cycle (rustig)
- Modern mesh pattern
- Performance efficient

✅ **Compacte Dagselector**
- 20% kleiner (padding, button sizes)
- Direct positionering (geen animatie)
- Duidelijke legenda
- Rustiger week labels

✅ **Uniforme Headers**
- Exact zelfde hoogte (73px nav)
- Exact zelfde winkel selector
- Exact zelfde titel spacing (mb-6)
- Exact zelfde logo gap (12px)

---

## Status: Klaar voor Implementatie

Alle 4 hoofdproblemen + 5 aanvullende eisen zijn geanalyseerd en opgelost in dit plan.


