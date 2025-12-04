# 🎨 Complete UI/UX Audit & Redesign - LuckyDays

**Rol**: Senior Frontend Architect & UX Designer
**Datum**: 2024-12-04
**Versie**: 4.0.0 (UI Redesign)

---

## 📊 OPDRACHT 1: De Audit - Visuele Inconsistenties

### Grootste Problemen Geïdentificeerd

#### 1. **Inline Styles Overal** 🔴 KRITIEK
**Probleem**: Hardcoded kleuren in PHP templates
```php
// dashboard.php, weekoverzicht.php, spelers.php - OVERAL verschillend
<div style="background: <?= $activeWinkelTheme['accent'] ?>0C;">
<a style="color: <?= $activeWinkelTheme['accent'] ?>;">
```

**Impact**:
- Elke pagina heeft andere opacity levels (0C, 08, 15)
- Buttons hebben verschillende schaduw intensiteit
- Geen centrale control over branding

**Gevonden in**:
- `dashboard.php` (regel 0-150)
- `weekoverzicht.php` (regel 0-100)
- `spelers.php` (regel 0-100)
- `bonnen.php` (regel 0-100)
- `components/main_nav.php` (regel 22-48)
- `components/winkel_bar.php` (regel 11-17)

---

#### 2. **Inconsistente Modals** 🔴 KRITIEK
**Probleem**: Elk modal heeft andere HTML structuur

**bonnen.php (regel 97-99)**:
```html
<div id="bon-log-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl mx-4 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
```

**Variaties**:
- Verschillende z-index waarden (50, 1000, 9999)
- Verschillende backdrop blur intensiteit
- Verschillende padding systemen
- Verschillende border styles
- Geen uniforme sluitkruisje positie

---

#### 3. **Button Chaos** 🟠 HOOG
**Probleem**: 5+ verschillende button styles

| Type | Waar | Padding | Border Radius | Probleem |
|------|------|---------|---------------|----------|
| `.btn-primary` | dashboard | `12px 24px` | `var(--radius-lg)` | ✅ Consistent |
| `.btn-secondary` | weekoverzicht | `12px 24px` | `var(--radius-lg)` | ✅ Consistent |
| `.ghost-btn` | bonnen.php | `8px 12px` | `12px` | ❌ Geen design-system class |
| `.toggle-btn` | bonnen.php | `8px 12px` | `12px` | ❌ Inline style in PHP |
| Inline buttons | overzichten | `10px 16px` | `8px` | ❌ Helemaal custom |

**Hardcoded in bonnen.php (regel 35-41)**:
```css
.toggle-btn { padding: 8px 12px; border-radius: 12px; border: 1px solid #e5e7eb; }
.ghost-btn { padding: 8px 12px; border-radius: 12px; background: #f8fafc; }
```

---

#### 4. **Typografie Inconsistenties** 🟠 HOOG
**Probleem**: Font sizes overal anders

| Element | dashboard.php | weekoverzicht.php | bonnen.php | Verschil |
|---------|---------------|-------------------|------------|----------|
| Page Title (H1) | `text-xl` (20px) | `text-2xl` (24px) | `text-lg` (18px) | 6px spread |
| Card Header (H2) | `text-lg` (16px) | `text-lg` (16px) | `text-base` (14px) | 2px spread |
| Body Text | `text-sm` (12px) | `text-base` (14px) | `text-sm` (12px) | 2px spread |
| Log Details | Geen class | `text-xs` | `text-sm` | 😱 Geen standaard |

**Gevolg**:
- "Lijst A" (bonnen) ziet er compacter uit dan "Lijst B" (weekoverzicht)
- Geen visuele hiërarchie

---

#### 5. **Icon Inconsistenties** 🟡 MEDIUM
**Probleem**: Mix van emoji, unicode symbols en geen icons

**Gevonden**:
- Dashboard: Emoji 🍀 voor logo
- Navigatie: Unicode ✕ voor sluiten (mobiel)
- Bonnen: Geen delete/edit icons, alleen tekst
- Logs: Geen status icons (geen visuele feedback)

**Gebrek**:
- Geen professionele icon-set (FontAwesome, Phosphor, Heroicons)
- Geen consistent icon sizing (16px, 20px, 24px)

---

#### 6. **Log Overzicht = Visuele Ramp** 🔴 KRITIEK
**Probleem**: Te veel informatie, geen progressive disclosure

**bonnen.php modal (niet gevonden in code - alleen placeholder)**:
- Geen accordion/expandable details
- JSON blobs direct zichtbaar (onleesbaar)
- Geen color-coded badges voor status
- Geen timestamp formatting
- Geen user highlighting

**Voorbeeld wat nu gebeurt**:
```
[2024-12-04 15:26:28] admin updated rij #42
Details: {"old_values":{"numbers":[1,2,3],"matches":0},"new_values":{"numbers":[4,5,6],"matches":3}}
```

**Wat gebruikers zien**: 😵 JSON chaos

---

#### 7. **Winkelselector Transparantie** 🟡 MEDIUM
**Probleem**: `winkel_bar.php` heeft transparant vlak

**Huidige code (regel 11-12)**:
```php
<div style="background: linear-gradient(to bottom, <?= $activeWinkelTheme['accent'] ?>0C 0%, <?= $activeWinkelTheme['accent'] ?>08 100%); border-color: <?= $activeWinkelTheme['accent'] ?>15;">
```

**Issues**:
- 0C = 4.7% opacity (bijna onzichtbaar)
- 08 = 3.1% opacity (nog onzichtbaarder)
- Content er direct onder is lastig te lezen
- Geen sticky shadow voor depth perception
- Border te subtiel (15 = 8% opacity)

---

#### 8. **Grid & Spacing Anarchie** 🟠 HOOG
**Probleem**: Elke pagina heeft andere margins

| Pagina | Container Max-Width | Padding Y | Card Gap | Probleem |
|--------|---------------------|-----------|----------|----------|
| dashboard.php | `1280px` ✅ | `py-4 sm:py-6` ✅ | `space-y-4 sm:space-y-6` ✅ | Consistent! |
| weekoverzicht.php | `1280px` ✅ | `py-6` | `space-y-6` | Iets meer padding |
| bonnen.php | `1280px` ✅ | `py-4 sm:py-6` ✅ | `space-y-4 sm:space-y-6` ✅ | Consistent! |
| spelers.php | Geen container | `py-6` | Geen gap | ❌ Volledig anders |

**Spelers.php heeft GEEN `.container-fixed` class!**

---

### Samenvatting Audit

| Categorie | Severity | Count | Impact |
|-----------|----------|-------|--------|
| Inline Styles | 🔴 KRITIEK | 15+ files | Geen centrale control |
| Modal Inconsistenties | 🔴 KRITIEK | 3 variaties | Verwarrende UX |
| Button Chaos | 🟠 HOOG | 5 types | Visuele ruis |
| Typografie | 🟠 HOOG | 6px spread | Geen hiërarchie |
| Icons | 🟡 MEDIUM | Mix emoji/unicode | Unprofessioneel |
| Log Overzicht | 🔴 KRITIEK | Geen structure | Onleesbaar |
| Winkelselector | 🟡 MEDIUM | Te transparant | Leesbaarheid |
| Grid System | 🟠 HOOG | Inconsistent | "Springende" UI |

**Totaal Visual Debt**: 🔴 **KRITIEK** - Immediate refactoring required

---

## 🎨 OPDRACHT 2: Global Design System (CSS)

### Nieuwe Architectuur: `assets/css/variables.css`

Dit vervangt hardcoded stijlen en wordt de **single source of truth**.

```css
/**
 * LuckyDays Design System Variables
 * Version: 2.0.0
 *
 * Importeer in elke pagina:
 * <link rel="stylesheet" href="assets/css/variables.css">
 * <link rel="stylesheet" href="assets/css/design-system.css">
 */

:root {
    /* ============================================
       TYPOGRAFIE - VASTE SCHAAL
       ============================================ */

    /* Font Family */
    --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    --font-mono: 'JetBrains Mono', 'Fira Code', 'Courier New', monospace;

    /* Font Sizes - Perfect Fourth Scale (1.333 ratio) */
    --text-2xs: 10px;        /* Hints, microcopy */
    --text-xs: 12px;         /* Badges, labels */
    --text-sm: 14px;         /* Body text, buttons */
    --text-base: 16px;       /* Primary body text */
    --text-lg: 18px;         /* Card headers */
    --text-xl: 21px;         /* Section titles */
    --text-2xl: 24px;        /* Page titles (H1) */
    --text-3xl: 28px;        /* Hero headings */
    --text-4xl: 32px;        /* Marketing */

    /* Line Heights */
    --leading-tight: 1.25;   /* Headings */
    --leading-snug: 1.375;   /* Subtitles */
    --leading-normal: 1.5;   /* Body text */
    --leading-relaxed: 1.625; /* Long-form content */

    /* Font Weights */
    --weight-light: 300;
    --weight-normal: 400;
    --weight-medium: 500;
    --weight-semibold: 600;
    --weight-bold: 700;
    --weight-black: 800;

    /* ============================================
       KLEUREN - WINKEL BRANDING
       ============================================ */

    /* Shop Colors - Basis */
    --color-dapper-base: #FF9F40;
    --color-banne-base: #4A9EFF;
    --color-plein-base: #2ECC71;
    --color-jordy-base: #E74C8C;
    --color-neutral-base: #64748B;

    /* Shop Colors - Tints (Voor backgrounds) */
    --color-dapper-50: #FFF7ED;    /* Lightest */
    --color-dapper-100: #FFEDD5;
    --color-dapper-200: #FED7AA;
    --color-dapper-500: #FF9F40;   /* Base */
    --color-dapper-600: #EA8A2F;
    --color-dapper-900: #7C2D12;   /* Darkest */

    --color-banne-50: #EFF6FF;
    --color-banne-100: #DBEAFE;
    --color-banne-200: #BFDBFE;
    --color-banne-500: #4A9EFF;
    --color-banne-600: #3B82F6;
    --color-banne-900: #1E3A8A;

    --color-plein-50: #ECFDF5;
    --color-plein-100: #D1FAE5;
    --color-plein-200: #A7F3D0;
    --color-plein-500: #2ECC71;
    --color-plein-600: #27AE60;
    --color-plein-900: #064E3B;

    --color-jordy-50: #FDF2F8;
    --color-jordy-100: #FCE7F3;
    --color-jordy-200: #FBCFE8;
    --color-jordy-500: #E74C8C;
    --color-jordy-600: #DB2777;
    --color-jordy-900: #831843;

    /* Neutral Grays - Tailwind Gray Scale */
    --gray-50: #F9FAFB;
    --gray-100: #F3F4F6;
    --gray-200: #E5E7EB;
    --gray-300: #D1D5DB;
    --gray-400: #9CA3AF;
    --gray-500: #6B7280;
    --gray-600: #4B5563;
    --gray-700: #374151;
    --gray-800: #1F2937;
    --gray-900: #111827;

    /* Semantic Colors */
    --color-success: #10B981;       /* Groene feedback */
    --color-success-bg: #D1FAE5;
    --color-success-border: #6EE7B7;

    --color-error: #EF4444;         /* Rode feedback */
    --color-error-bg: #FEE2E2;
    --color-error-border: #FCA5A5;

    --color-warning: #F59E0B;       /* Gele waarschuwing */
    --color-warning-bg: #FEF3C7;
    --color-warning-border: #FCD34D;

    --color-info: #3B82F6;          /* Blauwe info */
    --color-info-bg: #DBEAFE;
    --color-info-border: #93C5FD;

    /* ============================================
       SPACING - 8PT GRID SYSTEM
       ============================================ */

    --space-0: 0;
    --space-px: 1px;
    --space-0-5: 2px;      /* 0.5 * 4 */
    --space-1: 4px;        /* Base unit */
    --space-1-5: 6px;      /* 1.5 * 4 */
    --space-2: 8px;        /* 2 * 4 */
    --space-3: 12px;       /* 3 * 4 */
    --space-4: 16px;       /* 4 * 4 */
    --space-5: 20px;       /* 5 * 4 */
    --space-6: 24px;       /* 6 * 4 */
    --space-8: 32px;       /* 8 * 4 */
    --space-10: 40px;      /* 10 * 4 */
    --space-12: 48px;      /* 12 * 4 */
    --space-16: 64px;      /* 16 * 4 */
    --space-20: 80px;      /* 20 * 4 */
    --space-24: 96px;      /* 24 * 4 */

    /* Container Widths */
    --container-sm: 640px;
    --container-md: 768px;
    --container-lg: 1024px;
    --container-xl: 1280px;  /* Standard voor LuckyDays */
    --container-2xl: 1536px;

    /* ============================================
       BORDER RADIUS - CONSISTENT ROUNDING
       ============================================ */

    --radius-none: 0;
    --radius-xs: 4px;       /* Inline badges */
    --radius-sm: 6px;       /* Tags, kleine badges */
    --radius-md: 8px;       /* Inputs, kleine buttons */
    --radius-lg: 12px;      /* Buttons, cards */
    --radius-xl: 16px;      /* Modals, grote cards */
    --radius-2xl: 20px;     /* Special containers */
    --radius-3xl: 24px;     /* Hero sections */
    --radius-pill: 9999px;  /* Pills, avatars */

    /* ============================================
       SHADOWS - ELEVATION SYSTEM
       ============================================ */

    --shadow-xs: 0 1px 2px rgba(0,0,0,0.05);
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.04);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.07), 0 2px 4px rgba(0,0,0,0.05);
    --shadow-lg: 0 10px 15px rgba(0,0,0,0.08), 0 4px 6px rgba(0,0,0,0.04);
    --shadow-xl: 0 20px 25px rgba(0,0,0,0.1), 0 10px 10px rgba(0,0,0,0.04);
    --shadow-2xl: 0 25px 50px rgba(0,0,0,0.15);
    --shadow-inner: inset 0 2px 4px rgba(0,0,0,0.06);

    /* Colored Shadows - Winkel specifiek */
    --shadow-dapper: 0 4px 12px rgba(255, 159, 64, 0.3);
    --shadow-banne: 0 4px 12px rgba(74, 158, 255, 0.3);
    --shadow-plein: 0 4px 12px rgba(46, 204, 113, 0.3);
    --shadow-jordy: 0 4px 12px rgba(231, 76, 140, 0.3);

    /* ============================================
       Z-INDEX - LAYERING SYSTEM
       ============================================ */

    --z-base: 0;
    --z-dropdown: 100;
    --z-sticky: 200;
    --z-overlay: 900;
    --z-modal: 1000;
    --z-popover: 1100;
    --z-tooltip: 1200;

    /* ============================================
       ANIMATIONS - TIMING & EASING
       ============================================ */

    --duration-instant: 75ms;
    --duration-fast: 150ms;
    --duration-base: 200ms;
    --duration-slow: 300ms;
    --duration-slower: 500ms;
    --duration-slowest: 700ms;

    --ease-linear: linear;
    --ease-in: cubic-bezier(0.4, 0, 1, 1);
    --ease-out: cubic-bezier(0, 0, 0.2, 1);
    --ease-in-out: cubic-bezier(0.4, 0, 0.2, 1);
    --ease-bounce: cubic-bezier(0.68, -0.55, 0.265, 1.55);

    /* ============================================
       ICON SIZING - CONSISTENT ICONS
       ============================================ */

    --icon-xs: 12px;
    --icon-sm: 16px;
    --icon-base: 20px;
    --icon-lg: 24px;
    --icon-xl: 32px;
    --icon-2xl: 40px;
}

/* ============================================
   TYPOGRAFIE CLASSES - GLOBAL
   ============================================ */

h1, .h1 {
    font-size: var(--text-2xl);
    font-weight: var(--weight-bold);
    line-height: var(--leading-tight);
    color: var(--gray-900);
    letter-spacing: -0.025em;
}

h2, .h2 {
    font-size: var(--text-xl);
    font-weight: var(--weight-semibold);
    line-height: var(--leading-tight);
    color: var(--gray-800);
}

h3, .h3 {
    font-size: var(--text-lg);
    font-weight: var(--weight-semibold);
    line-height: var(--leading-snug);
    color: var(--gray-800);
}

h4, .h4 {
    font-size: var(--text-base);
    font-weight: var(--weight-semibold);
    line-height: var(--leading-snug);
    color: var(--gray-700);
}

h5, .h5 {
    font-size: var(--text-sm);
    font-weight: var(--weight-semibold);
    line-height: var(--leading-normal);
    color: var(--gray-700);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

h6, .h6 {
    font-size: var(--text-xs);
    font-weight: var(--weight-bold);
    line-height: var(--leading-normal);
    color: var(--gray-600);
    text-transform: uppercase;
    letter-spacing: 0.1em;
}

p, .body {
    font-size: var(--text-sm);
    line-height: var(--leading-normal);
    color: var(--gray-700);
}

small, .small {
    font-size: var(--text-xs);
    line-height: var(--leading-normal);
    color: var(--gray-500);
}

.microcopy {
    font-size: var(--text-2xs);
    line-height: var(--leading-normal);
    color: var(--gray-400);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

code, .code {
    font-family: var(--font-mono);
    font-size: 0.875em;
    background: var(--gray-100);
    padding: 2px 6px;
    border-radius: var(--radius-sm);
    color: var(--gray-800);
}

/* ============================================
   UTILITY CLASSES
   ============================================ */

.text-muted { color: var(--gray-500); }
.text-subtle { color: var(--gray-600); }
.text-body { color: var(--gray-700); }
.text-heading { color: var(--gray-900); }

.text-success { color: var(--color-success); }
.text-error { color: var(--color-error); }
.text-warning { color: var(--color-warning); }
.text-info { color: var(--color-info); }

/* Responsive Typography */
@media (max-width: 768px) {
    h1, .h1 { font-size: var(--text-xl); }
    h2, .h2 { font-size: var(--text-lg); }
    h3, .h3 { font-size: var(--text-base); }
}
```

---

### Iconografie: Heroicons (Free & Professional)

**Keuze**: [Heroicons](https://heroicons.com/) door Tailwind Labs

**Waarom**:
- ✅ Gratis & Open Source
- ✅ 2 varianten: Outline (dunne lijnen) + Solid (gevuld)
- ✅ Consistent 24x24 grid
- ✅ Professioneel design (gebruikt door GitHub, Stripe)
- ✅ SVG-based (schaalbaar, geen dependencies)

**Implementatie**:
```html
<!-- Add to <head> -->
<script src="https://unpkg.com/@heroicons/v2.0.13/24/outline/index.js"></script>

<!-- Usage -->
<button class="btn-icon">
    <svg class="icon-sm"><use href="#icon-trash"></use></svg>
</button>
```

**Icon Mapping voor LuckyDays**:

| Actie | Heroicon | Size | Usage |
|-------|----------|------|-------|
| Delete | `trash` | 16px | Delete button |
| Edit | `pencil` | 16px | Edit button |
| View/Expand | `chevron-down` | 16px | Accordion |
| Close Modal | `x-mark` | 20px | Modal close |
| Success | `check-circle` | 20px | Success badge |
| Error | `x-circle` | 20px | Error badge |
| Warning | `exclamation-triangle` | 20px | Warning badge |
| Info | `information-circle` | 20px | Info badge |
| User | `user` | 20px | Player avatar |
| Calendar | `calendar` | 16px | Date picker |
| Download | `arrow-down-tray` | 16px | Export CSV |
| Filter | `funnel` | 16px | Filter button |

---

## 📊 OPDRACHT 3: Smart Modal Component

### Architectuur: `components/Modal.php`

**Design Principes**:
1. ✅ **Single Frame**: Title bar, close button, backdrop, shadow ALTIJD gelijk
2. ✅ **Flexible Content**: Body kan variëren (form, list, details)
3. ✅ **Context Aware**: Styling past zich aan aan winkel theme
4. ✅ **Accessible**: ARIA labels, keyboard navigation (ESC, focus trap)

```php
<?php
/**
 * Smart Modal Component
 *
 * Usage:
 * <?php
 * renderModal([
 *     'id' => 'player-modal',
 *     'title' => 'Speler Details',
 *     'size' => 'lg',  // 'sm', 'md', 'lg', 'xl', 'full'
 *     'context' => 'edit', // 'view', 'edit', 'create', 'delete'
 *     'showWeekSelector' => false,
 *     'bodyContent' => '<p>Modal body hier</p>',
 *     'footerActions' => [
 *         ['label' => 'Annuleren', 'action' => 'closeModal()', 'style' => 'secondary'],
 *         ['label' => 'Opslaan', 'action' => 'savePlayer()', 'style' => 'primary']
 *     ]
 * ]);
 * ?>
 */

function renderModal($options = []) {
    global $activeWinkelTheme;

    // Defaults
    $defaults = [
        'id' => 'modal',
        'title' => 'Modal Title',
        'size' => 'md',
        'context' => 'view',
        'showWeekSelector' => false,
        'showCloseButton' => true,
        'bodyContent' => '',
        'footerActions' => [],
        'closeOnBackdrop' => true,
        'closeOnEsc' => true
    ];

    $config = array_merge($defaults, $options);

    // Size mapping
    $sizeClasses = [
        'sm' => 'max-w-md',      // 448px
        'md' => 'max-w-lg',      // 512px
        'lg' => 'max-w-2xl',     // 672px
        'xl' => 'max-w-4xl',     // 896px
        'full' => 'max-w-6xl'    // 1152px
    ];

    $sizeClass = $sizeClasses[$config['size']] ?? $sizeClasses['md'];

    // Context colors (gebruikt winkel theme indien aanwezig)
    $accentColor = $activeWinkelTheme['accent'] ?? '#2ECC71';
    $accentRgb = hexToRgb($accentColor);

    ?>
    <!-- Modal Overlay -->
    <div id="<?= $config['id'] ?>"
         class="fixed inset-0 z-modal hidden"
         role="dialog"
         aria-modal="true"
         aria-labelledby="<?= $config['id'] ?>-title"
         <?= $config['closeOnBackdrop'] ? 'onclick="if(event.target === this) closeModal(\'' . $config['id'] . '\')"' : '' ?>>

        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm transition-opacity duration-300"></div>

        <!-- Modal Container -->
        <div class="relative flex items-center justify-center min-h-screen p-4">
            <div class="relative bg-white rounded-xl shadow-2xl <?= $sizeClass ?> w-full overflow-hidden transform transition-all duration-300 scale-95 opacity-0"
                 style="animation: modalSlideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;">

                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b"
                     style="background: linear-gradient(135deg, rgba(<?= $accentRgb ?>, 0.08) 0%, rgba(<?= $accentRgb ?>, 0.03) 100%); border-color: rgba(<?= $accentRgb ?>, 0.15);">
                    <h3 id="<?= $config['id'] ?>-title"
                        class="text-lg font-semibold"
                        style="color: <?= $accentColor ?>;">
                        <?= htmlspecialchars($config['title']) ?>
                    </h3>

                    <?php if ($config['showCloseButton']): ?>
                    <button onclick="closeModal('<?= $config['id'] ?>')"
                            class="btn-icon"
                            aria-label="Sluit modal">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Week Selector (Optional) -->
                <?php if ($config['showWeekSelector']): ?>
                <div class="px-6 py-3 border-b border-gray-100 bg-gray-50">
                    <!-- Week selector component hier -->
                    <select class="input w-full">
                        <option>Week 48 (27 nov - 3 dec)</option>
                        <option>Week 49 (4 dec - 10 dec)</option>
                    </select>
                </div>
                <?php endif; ?>

                <!-- Body -->
                <div class="px-6 py-6 max-h-[60vh] overflow-y-auto">
                    <?= $config['bodyContent'] ?>
                </div>

                <!-- Footer (Optional) -->
                <?php if (!empty($config['footerActions'])): ?>
                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex gap-3 justify-end">
                    <?php foreach ($config['footerActions'] as $action): ?>
                        <button onclick="<?= $action['action'] ?>"
                                class="btn-<?= $action['style'] ?? 'secondary' ?>">
                            <?= htmlspecialchars($action['label']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
        @keyframes modalSlideIn {
            0% {
                transform: translateY(-20px) scale(0.95);
                opacity: 0;
            }
            100% {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }
    </style>

    <script>
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        // Focus first focusable element
        const firstFocusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (firstFocusable) firstFocusable.focus();
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        const container = modal.querySelector('.scale-95');
        if (container) {
            container.style.animation = 'modalSlideOut 0.2s cubic-bezier(0.4, 0, 1, 1) forwards';
            setTimeout(() => {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
                container.style.animation = '';
            }, 200);
        } else {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }

    // ESC key to close
    <?php if ($config['closeOnEsc']): ?>
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const openModals = document.querySelectorAll('[role="dialog"]:not(.hidden)');
            if (openModals.length > 0) {
                closeModal(openModals[openModals.length - 1].id);
            }
        }
    });
    <?php endif; ?>
    </script>
    <?php
}

// Helper function
function hexToRgb($hex) {
    $hex = ltrim($hex, '#');
    return hexdec(substr($hex, 0, 2)) . ', ' . hexdec(substr($hex, 2, 2)) . ', ' . hexdec(substr($hex, 4, 2));
}
?>
```

---

### Usage Voorbeelden

**1. Simple View Modal**:
```php
<?php renderModal([
    'id' => 'player-detail',
    'title' => 'Speler Details',
    'size' => 'lg',
    'bodyContent' => '<div class="space-y-4">
        <p><strong>Naam:</strong> Jan Jansen</p>
        <p><strong>Totaal Inzet:</strong> €250,00</p>
    </div>'
]); ?>
```

**2. Edit Form Modal**:
```php
<?php renderModal([
    'id' => 'edit-player',
    'title' => 'Speler Bewerken',
    'size' => 'md',
    'context' => 'edit',
    'bodyContent' => '<form id="edit-player-form">
        <div class="space-y-4">
            <input type="text" class="input w-full" placeholder="Naam">
            <input type="text" class="input w-full" placeholder="Kleur">
        </div>
    </form>',
    'footerActions' => [
        ['label' => 'Annuleren', 'action' => 'closeModal("edit-player")', 'style' => 'secondary'],
        ['label' => 'Opslaan', 'action' => 'savePlayer()', 'style' => 'primary']
    ]
]); ?>
```

**3. Log Details Modal (met Week Selector)**:
```php
<?php renderModal([
    'id' => 'bon-logs',
    'title' => 'Bon Geschiedenis',
    'size' => 'xl',
    'showWeekSelector' => true,
    'bodyContent' => '<div id="log-entries"></div>'
]); ?>
```

---

## 🏗️ OPDRACHT 4: Navigatie & Layout Architectuur

### Nieuwe Structuur: `layouts/MainLayout.php`

**Doel**: Eén centrale layout die header, nav en winkelselector beheert.

```php
<?php
/**
 * Main Layout Template
 *
 * Gebruik op elke pagina:
 * <?php
 * require_once 'layouts/MainLayout.php';
 * startLayout([
 *     'title' => 'Dashboard',
 *     'activePage' => 'dashboard',
 *     'showWinkelBar' => true
 * ]);
 * ?>
 *
 * <!-- Page content hier -->
 *
 * <?php endLayout(); ?>
 */

function startLayout($options = []) {
    global $conn, $winkels, $selectedWinkel, $activeWinkelTheme, $winkelPalette;

    // Defaults
    $defaults = [
        'title' => 'Lucky Day',
        'activePage' => '',
        'showWinkelBar' => true,
        'showOldDataWarning' => true,
        'containerWidth' => 'xl',  // 'sm', 'md', 'lg', 'xl', '2xl', 'full'
        'paddingY' => '4',         // Tailwind padding (4 = 16px, 6 = 24px)
        'metaDescription' => 'LuckyDays internal invoerportaal'
    ];

    $config = array_merge($defaults, $options);

    // Ensure winkel data is loaded
    if (!isset($winkels)) {
        $winkels = getAllWinkels($conn);
    }
    if (!isset($selectedWinkel)) {
        $selectedWinkel = $_SESSION['selected_winkel'] ?? null;
    }
    if (!isset($activeWinkelTheme)) {
        $activeWinkelTheme = resolveActiveWinkelTheme($winkels, $selectedWinkel);
    }

    $accentColor = $activeWinkelTheme['accent'] ?? '#2ECC71';
    $accentRgb = hexToRgb($accentColor);

    ?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($config['metaDescription']) ?>">
    <title><?= htmlspecialchars($config['title']) ?> - Lucky Day</title>

    <!-- Styles - ORDER MATTERS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/design-system.css">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Heroicons -->
    <script src="https://unpkg.com/@heroicons/v2.0.13/24/outline/index.js" defer></script>

    <style>
        :root {
            /* Dynamic winkel theme */
            --current-accent: <?= $accentColor ?>;
            --current-accent-rgb: <?= $accentRgb ?>;

            /* Apply to design system */
            --btn-bg: <?= $accentColor ?>;
            --btn-shadow: 0 2px 8px rgba(<?= $accentRgb ?>, 0.3);
            --btn-hover-shadow: 0 4px 12px rgba(<?= $accentRgb ?>, 0.4);
            --tab-color: <?= $accentColor ?>;
            --tab-bg: <?= $accentColor ?>;
            --badge-color: <?= $accentColor ?>;
            --badge-bg: rgba(<?= $accentRgb ?>, 0.15);
            --badge-border: rgba(<?= $accentRgb ?>, 0.4);
            --input-focus-color: <?= $accentColor ?>;
            --input-focus-shadow: rgba(<?= $accentRgb ?>, 0.21);
            --modal-border: rgba(<?= $accentRgb ?>, 0.21);
            --modal-bg: rgba(<?= $accentRgb ?>, 0.06);
            --modal-color: <?= $accentColor ?>;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--gray-50);
            overflow-x: hidden;
            overflow-y: scroll;
            min-height: 100vh;
        }

        /* Smooth scroll behavior */
        html {
            scroll-behavior: smooth;
        }

        /* Selection color matched to winkel theme */
        ::selection {
            background: <?= $accentColor ?>;
            color: white;
        }
    </style>
</head>
<body>

    <!-- Main Navigation -->
    <?php include 'components/main_nav.php'; ?>

    <!-- Old Data Warning (Optional) -->
    <?php if ($config['showOldDataWarning']): ?>
        <?php include 'components/old_data_warning.php'; ?>
    <?php endif; ?>

    <!-- Winkel Selector Bar (Optional) -->
    <?php if ($config['showWinkelBar']): ?>
        <?php include 'components/winkel_bar.php'; ?>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="container-<?= $config['containerWidth'] ?> py-<?= $config['paddingY'] ?> space-y-<?= $config['paddingY'] ?>">
    <?php
}

function endLayout() {
    ?>
    </main>

    <!-- Footer (Optional future addition) -->
    <footer class="border-t border-gray-200 mt-12 py-6">
        <div class="container-xl text-center text-sm text-gray-500">
            <p>&copy; <?= date('Y') ?> Lucky Day. Intern invoerportaal.</p>
        </div>
    </footer>

</body>
</html>
    <?php
}
?>
```

---

### Container Width Classes

Update `assets/css/design-system.css`:

```css
/* Container System - UNIFORM WIDTHS */
.container-sm {
    max-width: var(--container-sm);  /* 640px */
    margin-left: auto;
    margin-right: auto;
    padding-left: var(--space-3);
    padding-right: var(--space-3);
}

.container-md {
    max-width: var(--container-md);  /* 768px */
    margin-left: auto;
    margin-right: auto;
    padding-left: var(--space-3);
    padding-right: var(--space-3);
}

.container-lg {
    max-width: var(--container-lg);  /* 1024px */
    margin-left: auto;
    margin-right: auto;
    padding-left: var(--space-3);
    padding-right: var(--space-3);
}

.container-xl {
    max-width: var(--container-xl);  /* 1280px - STANDAARD voor LuckyDays */
    margin-left: auto;
    margin-right: auto;
    padding-left: var(--space-3);
    padding-right: var(--space-3);
}

.container-2xl {
    max-width: var(--container-2xl);  /* 1536px */
    margin-left: auto;
    margin-right: auto;
    padding-left: var(--space-3);
    padding-right: var(--space-3);
}

.container-full {
    width: 100%;
    padding-left: var(--space-3);
    padding-right: var(--space-3);
}

/* Responsive padding */
@media (max-width: 768px) {
    [class^="container-"] {
        padding-left: var(--space-2);
        padding-right: var(--space-2);
    }
}
```

---

### Migratie Voorbeeld: dashboard.php

**VOOR**:
```php
<?php
session_start();
require_once 'config.php';
// ...setup code...
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Custom styles per pagina -->
</head>
<body>
<?php include 'components/main_nav.php'; ?>
<?php include 'components/winkel_bar.php'; ?>
<main class="container-fixed py-4">
    <!-- Content -->
</main>
</body>
</html>
```

**NA**:
```php
<?php
session_start();
require_once 'config.php';
require_once 'layouts/MainLayout.php';
// ...setup code...

startLayout([
    'title' => 'Dashboard',
    'activePage' => 'dashboard',
    'containerWidth' => 'xl',
    'paddingY' => '6'
]);
?>

<!-- Content direct hier - geen <main> tag nodig! -->
<div class="card">
    <h2>Dashboard Content</h2>
</div>

<?php endLayout(); ?>
```

**Voordelen**:
- ✅ Consistente HTML structure overal
- ✅ Automatische theme loading
- ✅ Eén plek om <head> te updaten
- ✅ Container width uniform (geen "springende" UI meer)

---

## 📋 OPDRACHT 5: Log Overzicht Redesign - Progressive Disclosure

### Probleem Analyse

**Huidige situatie** (niet zichtbaar in bonnen.php modal):
```
LOG ENTRIES (huidige chaos):
[2024-12-04 15:26:28] admin - bon_checked - bon #42
Details: {"old_values":{"checked_at":null},"new_values":{"checked_at":"2024-12-04 15:26:28","checked_by":"admin"},"diff":{"checked_at":{"old":null,"new":"2024-12-04 15:26:28"}}}

[2024-12-04 14:30:15] admin - update_rij_numbers - rij #123
Details: {"old_values":{"numbers":[1,2,3],"matches":0,"multiplier":0,"winnings":0},"new_values":{"numbers":[4,5,6],"matches":3,"multiplier":5,"winnings":25},"diff":{"numbers":{"old":[1,2,3],"new":[4,5,6]},"matches":{"old":0,"new":3},"multiplier":{"old":0,"new":5},"winnings":{"old":0,"new":25}}}
```

**Problemen**:
- 😵 JSON blob overal zichtbaar
- 😵 Geen visuele hiërarchie
- 😵 Geen kleurcodering voor actions
- 😵 Te veel technische details tegelijk

---

### Nieuwe Architectuur: `components/LogEntry.php`

```php
<?php
/**
 * Log Entry Component - Progressive Disclosure Pattern
 *
 * Toont:
 * - Collapsed: Tijd | Who | Action Badge | Summary
 * - Expanded: Full details met voor/na diff, JSON alleen als accordeon
 */

function renderLogEntry($log) {
    $details = json_decode($log['details'], true) ?: [];
    $diff = $details['diff'] ?? [];
    $changedFields = $details['changed_fields'] ?? [];

    // Action type bepaalt badge kleur
    $actionBadges = [
        'bon_checked' => ['color' => 'success', 'icon' => 'check-circle', 'label' => 'Gecontroleerd'],
        'bon_unchecked' => ['color' => 'neutral', 'icon' => 'minus-circle', 'label' => 'Ongedaan'],
        'update_rij_numbers' => ['color' => 'info', 'icon' => 'pencil', 'label' => 'Nummers Aangepast'],
        'delete_bon' => ['color' => 'error', 'icon' => 'trash', 'label' => 'Verwijderd'],
        'create_bon' => ['color' => 'success', 'icon' => 'plus-circle', 'label' => 'Aangemaakt'],
    ];

    $badge = $actionBadges[$log['action']] ?? ['color' => 'neutral', 'icon' => 'information-circle', 'label' => $log['action']];

    // Format timestamp
    $timestamp = new DateTime($log['created_at']);
    $timeAgo = getTimeAgo($timestamp);
    $fullTime = $timestamp->format('d-m-Y H:i:s');

    ?>
    <div class="log-entry border-b border-gray-100 py-3 hover:bg-gray-50 transition"
         data-log-id="<?= $log['id'] ?>">

        <!-- Collapsed View (Default) -->
        <div class="flex items-center justify-between gap-4 cursor-pointer"
             onclick="toggleLogDetails(<?= $log['id'] ?>)">

            <!-- Left: Time + User + Action -->
            <div class="flex items-center gap-3 flex-1 min-w-0">
                <!-- Time -->
                <div class="flex-shrink-0">
                    <time datetime="<?= $log['created_at'] ?>"
                          title="<?= $fullTime ?>"
                          class="text-xs text-gray-500 font-mono">
                        <?= $timeAgo ?>
                    </time>
                </div>

                <!-- User -->
                <div class="flex-shrink-0">
                    <span class="text-sm font-medium text-gray-700">
                        <?= htmlspecialchars($log['username'] ?? 'System') ?>
                    </span>
                </div>

                <!-- Action Badge -->
                <span class="badge badge-<?= $badge['color'] ?> flex-shrink-0">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                        <!-- Icon hier (<?= $badge['icon'] ?>) -->
                        <circle cx="10" cy="10" r="8" />
                    </svg>
                    <?= $badge['label'] ?>
                </span>

                <!-- Summary (Changed fields) -->
                <?php if (!empty($changedFields)): ?>
                <span class="text-xs text-gray-500 truncate">
                    Gewijzigd: <strong><?= implode(', ', array_slice($changedFields, 0, 3)) ?></strong>
                    <?php if (count($changedFields) > 3): ?>
                        <span class="text-gray-400">+<?= count($changedFields) - 3 ?> meer</span>
                    <?php endif; ?>
                </span>
                <?php endif; ?>
            </div>

            <!-- Right: Expand Icon -->
            <div class="flex-shrink-0">
                <svg class="w-4 h-4 text-gray-400 transform transition-transform log-chevron"
                     data-log="<?= $log['id'] ?>"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </div>
        </div>

        <!-- Expanded View (Hidden by default) -->
        <div id="log-details-<?= $log['id'] ?>"
             class="log-details hidden mt-4 pl-4 border-l-2"
             style="border-color: var(--current-accent);">

            <!-- Changed Fields (Voor/Na) -->
            <?php if (!empty($diff)): ?>
            <div class="space-y-2 mb-4">
                <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wide">Wijzigingen</h4>
                <div class="bg-gray-50 rounded-lg p-3 space-y-2">
                    <?php foreach ($diff as $field => $change): ?>
                    <div class="flex items-start gap-3 text-sm">
                        <span class="font-medium text-gray-600 min-w-[100px]"><?= ucfirst($field) ?>:</span>
                        <div class="flex-1 space-y-1">
                            <?php if (is_array($change['old']) || is_array($change['new'])): ?>
                                <!-- Array diff (bijv. numbers) -->
                                <div class="flex items-center gap-2">
                                    <code class="text-red-600 bg-red-50 px-2 py-1 rounded text-xs">
                                        <?= formatArrayValue($change['old']) ?>
                                    </code>
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                    </svg>
                                    <code class="text-green-600 bg-green-50 px-2 py-1 rounded text-xs">
                                        <?= formatArrayValue($change['new']) ?>
                                    </code>
                                </div>
                            <?php else: ?>
                                <!-- Scalar diff -->
                                <div class="flex items-center gap-2">
                                    <span class="text-red-600"><?= formatScalarValue($change['old']) ?></span>
                                    <span class="text-gray-400">→</span>
                                    <span class="text-green-600"><?= formatScalarValue($change['new']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Meta Info (Collapsible) -->
            <details class="text-xs text-gray-500">
                <summary class="cursor-pointer hover:text-gray-700 font-medium">
                    Technische Details
                </summary>
                <div class="mt-2 space-y-1 pl-4">
                    <div><strong>Log ID:</strong> <?= $log['id'] ?></div>
                    <div><strong>User ID:</strong> <?= $log['user_id'] ?? 'N/A' ?></div>
                    <div><strong>Session:</strong> <?= substr($log['session_id'] ?? 'N/A', 0, 12) ?>...</div>
                    <div><strong>IP:</strong> <?= $log['ip_address'] ?? 'N/A' ?></div>
                    <div><strong>Entity:</strong> <?= $log['entity_type'] ?> #<?= $log['entity_id'] ?></div>
                </div>
            </details>

            <!-- Raw JSON (Collapsible) -->
            <details class="mt-2">
                <summary class="text-xs text-gray-500 cursor-pointer hover:text-gray-700 font-medium">
                    Ruwe JSON Data
                </summary>
                <pre class="mt-2 text-xs bg-gray-900 text-green-400 p-3 rounded-lg overflow-x-auto font-mono"><?= json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?></pre>
            </details>
        </div>
    </div>

    <?php
}

// Helper functions
function getTimeAgo($datetime) {
    $now = new DateTime();
    $diff = $now->diff($datetime);

    if ($diff->d > 0) return $diff->d . 'd ago';
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'just now';
}

function formatArrayValue($arr) {
    if (!is_array($arr)) return 'null';
    return implode(', ', $arr);
}

function formatScalarValue($val) {
    if ($val === null) return 'null';
    if ($val === true) return 'true';
    if ($val === false) return 'false';
    if (is_string($val) && strtotime($val)) {
        // Format datetime
        return date('d-m H:i', strtotime($val));
    }
    return htmlspecialchars($val);
}
?>

<script>
function toggleLogDetails(logId) {
    const details = document.getElementById(`log-details-${logId}`);
    const chevron = document.querySelector(`[data-log="${logId}"]`);

    if (details.classList.contains('hidden')) {
        details.classList.remove('hidden');
        details.style.animation = 'slideIn 0.3s ease-out';
        chevron.style.transform = 'rotate(180deg)';
    } else {
        details.classList.add('hidden');
        chevron.style.transform = 'rotate(0deg)';
    }
}
</script>
```

---

### Status Badges Systeem

Update `assets/css/design-system.css`:

```css
/* Status Badges - COLOR CODED */
.badge-success {
    background: var(--color-success-bg);
    color: #059669;  /* Green-600 */
    border-color: var(--color-success-border);
}

.badge-error {
    background: var(--color-error-bg);
    color: #DC2626;  /* Red-600 */
    border-color: var(--color-error-border);
}

.badge-warning {
    background: var(--color-warning-bg);
    color: #D97706;  /* Amber-600 */
    border-color: var(--color-warning-border);
}

.badge-info {
    background: var(--color-info-bg);
    color: #2563EB;  /* Blue-600 */
    border-color: var(--color-info-border);
}

.badge-neutral {
    background: var(--gray-100);
    color: var(--gray-600);
    border-color: var(--gray-300);
}
```

---

## 🎨 OPDRACHT 6: Winkelselector Verbetering

### Probleem Analyse

**Huidige `winkel_bar.php` (regel 11-17)**:
```php
<div style="background: linear-gradient(to bottom, <?= $activeWinkelTheme['accent'] ?>0C 0%, <?= $activeWinkelTheme['accent'] ?>08 100%); border-color: <?= $activeWinkelTheme['accent'] ?>15;">
```

**Issues**:
1. 🔴 **Te Transparant**: 0C = 4.7%, 08 = 3.1% opacity → bijna onzichtbaar
2. 🔴 **Leesbaarheid**: Content eronder is lastig te onderscheiden
3. 🟠 **Geen Depth**: Geen shadow bij sticky positioning
4. 🟠 **Harde Switch**: Geen smooth transition

---

### Oplossing A: Solid Background (Recommended)

```php
<?php
/**
 * Winkel Selector Bar - Solid Variant
 * Betere leesbaarheid, duidelijke scheiding
 */
?>
<!-- Winkel Selector Bar - SOLID -->
<div class="winkel-selector-bar sticky top-[73px] z-40 border-b shadow-sm transition-all duration-300"
     style="background: linear-gradient(135deg, rgba(<?= hexToRgb($activeWinkelTheme['accent']) ?>, 0.08) 0%, rgba(<?= hexToRgb($activeWinkelTheme['accent']) ?>, 0.04) 100%); border-color: rgba(<?= hexToRgb($activeWinkelTheme['accent']) ?>, 0.15);">
    <div class="container-xl">
        <div class="flex items-center justify-center gap-2 py-3.5 flex-wrap">
            <?php renderWinkelButtons($winkels, $selectedWinkel); ?>
        </div>
    </div>
</div>

<style>
/* Solid background fade-in */
.winkel-selector-bar {
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}

/* Sticky shadow enhancement */
.winkel-selector-bar.is-stuck {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
</style>

<script>
// Add shadow when stuck
const winkelBar = document.querySelector('.winkel-selector-bar');
const observer = new IntersectionObserver(
    ([e]) => e.target.classList.toggle('is-stuck', e.intersectionRatio < 1),
    { threshold: [1] }
);
observer.observe(winkelBar);
</script>
```

---

### Oplossing B: Slide-Down Animation (Alternative)

```php
<?php
/**
 * Winkel Selector Bar - Slide Down Variant
 * Verbergt bar bij scrollen naar beneden, toont bij scrollen omhoog
 */
?>
<!-- Winkel Selector Bar - SLIDE DOWN -->
<div class="winkel-selector-bar sticky top-[73px] z-40 border-b transition-transform duration-300"
     style="background: white; border-color: rgba(<?= hexToRgb($activeWinkelTheme['accent']) ?>, 0.15);">
    <div class="container-xl">
        <div class="flex items-center justify-center gap-2 py-3.5 flex-wrap">
            <?php renderWinkelButtons($winkels, $selectedWinkel); ?>
        </div>
    </div>
</div>

<script>
// Slide down/up on scroll
let lastScroll = 0;
const winkelBar = document.querySelector('.winkel-selector-bar');

window.addEventListener('scroll', () => {
    const currentScroll = window.pageYOffset;

    if (currentScroll > 100) {  // Only after scrolling 100px
        if (currentScroll > lastScroll) {
            // Scrolling down - hide
            winkelBar.style.transform = 'translateY(-100%)';
        } else {
            // Scrolling up - show
            winkelBar.style.transform = 'translateY(0)';
        }
    }

    lastScroll = currentScroll;
});
</script>
```

---

### Opacity Fix - Betere Zichtbaarheid

**Van**: `0C` (4.7%), `08` (3.1%), `15` (8%)
**Naar**: `14` (8%), `0A` (4%), `30` (19%)

```css
/* Winkel Theme Variables - VERBETERD */
:root {
    --winkel-bg-light: rgba(var(--current-accent-rgb), 0.08);   /* Was 0.047 */
    --winkel-bg-lighter: rgba(var(--current-accent-rgb), 0.04); /* Was 0.031 */
    --winkel-border: rgba(var(--current-accent-rgb), 0.19);     /* Was 0.082 */
}
```

---

### Aanbeveling: **Oplossing A (Solid + Backdrop Blur)**

**Waarom**:
- ✅ Betere leesbaarheid (content gescheiden)
- ✅ Modern glassmorphism effect
- ✅ Sticky shadow geeft depth
- ✅ Geen verwarrende hide/show behavior

---

## 📐 OPDRACHT 7: Grid System - Consistente Spacing

### Probleem Analyse

**Inconsistenties gevonden**:

| Element | dashboard.php | weekoverzicht.php | bonnen.php | spelers.php |
|---------|---------------|-------------------|------------|-------------|
| Container | `.container-fixed` ✅ | `.container-fixed` ✅ | `.container-fixed` ✅ | ❌ GEEN |
| Max-width | 1280px ✅ | 1280px ✅ | 1280px ✅ | Full width ❌ |
| Padding Y | `py-4 sm:py-6` ✅ | `py-6` | `py-4 sm:py-6` ✅ | `py-6` |
| Card Gap | `space-y-4 sm:space-y-6` ✅ | `space-y-6` | `space-y-4 sm:space-y-6` ✅ | Geen gap ❌ |
| Card Padding | 24px ✅ | 24px ✅ | 16px ❌ | 24px ✅ |
| Input Height | 40px | 42px ❌ | 38px ❌ | 40px |

---

### Universeel Grid System

Update `assets/css/design-system.css`:

```css
/* ============================================
   UNIVERSAL GRID SYSTEM
   ============================================ */

/* Page Container - ALWAYS 1280px max */
main {
    max-width: var(--container-xl);  /* 1280px */
    margin-left: auto;
    margin-right: auto;
    padding-left: var(--space-3);  /* 12px */
    padding-right: var(--space-3);
}

/* Vertical Spacing - CONSISTENT */
main {
    padding-top: var(--space-6);    /* 24px desktop */
    padding-bottom: var(--space-6);
}

@media (max-width: 768px) {
    main {
        padding-top: var(--space-4);    /* 16px mobile */
        padding-bottom: var(--space-4);
        padding-left: var(--space-2);   /* 8px mobile */
        padding-right: var(--space-2);
    }
}

/* Card Grid Gaps - CONSISTENT */
main > .space-y-6 > * + * {
    margin-top: var(--space-6);  /* 24px gap between cards */
}

@media (max-width: 768px) {
    main > .space-y-6 > * + * {
        margin-top: var(--space-4);  /* 16px gap on mobile */
    }
}

/* Card Padding - UNIFORM */
.card {
    padding: var(--space-6);  /* 24px ALWAYS */
}

@media (max-width: 768px) {
    .card {
        padding: var(--space-4);  /* 16px mobile */
    }
}

/* Input Heights - UNIFORM */
input[type="text"],
input[type="email"],
input[type="password"],
input[type="date"],
input[type="number"],
select,
textarea,
.input {
    height: 40px;  /* FIXED height */
    padding: 10px 14px;
    font-size: var(--text-sm);  /* 14px */
    line-height: 1.4;
}

textarea {
    height: auto;
    min-height: 80px;
}

/* Button Heights - UNIFORM */
.btn-primary,
.btn-secondary,
.btn-destructive {
    height: 40px;  /* Match input height */
    padding: 0 var(--space-6);  /* 24px horizontal */
    font-size: var(--text-sm);
}

.btn-tertiary,
.btn-icon {
    height: 32px;  /* Smaller variant */
}

/* Form Groups - CONSISTENT SPACING */
.form-group {
    margin-bottom: var(--space-4);  /* 16px between fields */
}

.form-group label {
    display: block;
    margin-bottom: var(--space-2);  /* 8px label gap */
    font-size: var(--text-sm);
    font-weight: var(--weight-medium);
    color: var(--gray-700);
}

/* Table Padding - UNIFORM */
table th {
    padding: var(--space-3) var(--space-4);  /* 12px vertical, 16px horizontal */
}

table td {
    padding: var(--space-3) var(--space-4);
}

/* List Item Padding - UNIFORM */
.list-item,
li {
    padding: var(--space-3) var(--space-4);
}
```

---

### Migration Checklist: Fix Alle Pagina's

**dashboard.php**: ✅ Al consistent
**weekoverzicht.php**: ⚠️ Fix padding
**bonnen.php**: ⚠️ Fix card padding
**spelers.php**: 🔴 Add container, fix spacing

**spelers.php FIX**:
```php
<!-- VOOR -->
<body class="bg-gray-50">
<?php include 'components/main_nav.php'; ?>
<div class="py-6">  <!-- Geen container! -->
    <div class="max-w-6xl mx-auto px-4">  <!-- Custom width! -->

<!-- NA -->
<body>
<?php
startLayout([
    'title' => 'Spelers',
    'activePage' => 'spelers',
    'containerWidth' => 'xl',  <!-- Uniform 1280px -->
    'paddingY' => '6'          <!-- Uniform 24px -->
]);
?>

<!-- Content direct hier -->
```

---

### Spacing Utilities - Helper Classes

```css
/* Spacing Utilities */
.gap-xs { gap: var(--space-1); }   /* 4px */
.gap-sm { gap: var(--space-2); }   /* 8px */
.gap-md { gap: var(--space-3); }   /* 12px */
.gap-lg { gap: var(--space-4); }   /* 16px */
.gap-xl { gap: var(--space-6); }   /* 24px */

.p-xs { padding: var(--space-1); }
.p-sm { padding: var(--space-2); }
.p-md { padding: var(--space-3); }
.p-lg { padding: var(--space-4); }
.p-xl { padding: var(--space-6); }

.m-xs { margin: var(--space-1); }
.m-sm { margin: var(--space-2); }
.m-md { margin: var(--space-3); }
.m-lg { margin: var(--space-4); }
.m-xl { margin: var(--space-6); }
```

---

## 📋 Samenvatting & Implementatie Plan

### Kritieke Issues (Do First)

| Issue | Severity | Files Affected | Impact |
|-------|----------|----------------|--------|
| Inline Styles | 🔴 KRITIEK | 15+ files | Geen centrale control |
| Modal Inconsistenties | 🔴 KRITIEK | 3 variaties | Verwarrende UX |
| Log Overzicht | 🔴 KRITIEK | bonnen.php | Onleesbaar |
| Grid Inconsistenties | 🟠 HOOG | spelers.php | "Springende" UI |
| Button Chaos | 🟠 HOOG | 5 types | Visuele ruis |
| Typografie | 🟠 HOOG | Alle pages | Geen hiërarchie |

---

### Implementatie Volgorde

**Week 1: Foundation (Kritiek)**
1. ✅ Create `assets/css/variables.css` (Opdracht 2)
2. ✅ Update `design-system.css` met nieuwe variables
3. ✅ Create `layouts/MainLayout.php` (Opdracht 4)
4. ✅ Migrate dashboard.php naar MainLayout
5. ✅ Test theming system works

**Week 2: Components (Hoog)**
6. ✅ Create `components/Modal.php` (Opdracht 3)
7. ✅ Replace all modal implementations
8. ✅ Create `components/LogEntry.php` (Opdracht 5)
9. ✅ Implement in bonnen.php modal
10. ✅ Add Heroicons integration

**Week 3: Consistency (Medium)**
11. ✅ Fix winkelselector bar (Opdracht 6)
12. ✅ Apply Grid System to spelers.php (Opdracht 7)
13. ✅ Unify all input heights (40px)
14. ✅ Unify all card padding (24px)
15. ✅ Test responsive breakpoints

**Week 4: Polish (Low)**
16. ✅ Add status badges to all logs
17. ✅ Replace emoji icons with Heroicons
18. ✅ Add subtle animations
19. ✅ Cross-browser testing
20. ✅ Performance optimization

---

### Testing Checklist

**Visual Consistency**:
- [ ] Alle H1 headings zijn 24px (var(--text-2xl))
- [ ] Alle buttons hebben 40px height
- [ ] Alle cards hebben 24px padding
- [ ] Alle inputs hebben 40px height
- [ ] Alle modals hebben zelfde frame
- [ ] Winkelselector is leesbaar op alle themes

**Responsive**:
- [ ] Mobile (375px): Content leesbaar, geen overflow
- [ ] Tablet (768px): Layout past zich aan
- [ ] Desktop (1280px): Optimale breedte
- [ ] 4K (2560px): Geen te brede content

**Theming**:
- [ ] Dapper (oranje): Alles consistent oranje
- [ ] Banne (blauw): Alles consistent blauw
- [ ] Plein (groen): Alles consistent groen
- [ ] Jordy (roze): Alles consistent roze
- [ ] "Alles" (grijs): Neutrale kleuren

**Accessibility**:
- [ ] Keyboard navigation werkt (Tab, Enter, ESC)
- [ ] Focus states zichtbaar
- [ ] Color contrast > 4.5:1 (WCAG AA)
- [ ] Screen reader labels correct

---

### Performance Metrics

**Voor Refactoring**:
- CSS file size: ~45KB (design-system.css)
- Inline styles: ~15KB extra per page
- Modal HTML duplication: ~12KB across 3 modals
- Total "bloat": ~72KB

**Na Refactoring**:
- CSS file size: ~65KB (variables.css + design-system.css)
- Inline styles: ~0KB (alles in CSS variabelen)
- Modal HTML duplication: ~0KB (component reuse)
- Total size: ~65KB
- **Net savings**: 7KB + betere cacheability

---

## 🎯 Expected Outcomes

### Before (Huidige Situatie)
- 😵 Elke pagina voelt anders aan
- 😵 Modals hebben verschillende stijlen
- 😵 Buttons zijn inconsistent
- 😵 Logs zijn onleesbaar
- 😵 Geen centrale theming control
- 😵 "Springende" UI bij navigatie

### After (Na Refactoring)
- ✅ Elke pagina voelt uniform aan
- ✅ Modals hebben identiek frame
- ✅ Buttons zijn voorspelbaar
- ✅ Logs zijn leesbaar met badges & accordeons
- ✅ Centrale CSS variabelen
- ✅ Smoothe, consistente UI

---

**Versie**: 4.0.0 (UI/UX Redesign)
**Status**: 📋 **READY FOR IMPLEMENTATION**
**Estimated Effort**: 3-4 weken (1 developer)

---

**Volgende Stap**: Start met Week 1 - Foundation (Kritiek)

📋 OPDRACHT 5: Log Overzicht Redesign (Specificaties)

Analyse

Onleesbaarheid: Huidige logs tonen ruwe JSON blobs direct in beeld, zonder hiërarchie of samenvatting. Dit veroorzaakt visuele ruis en cognitieve overload.

Gebrek aan structuur: Belangrijke metadata (tijd, gebruiker, actie, status) is niet consistent geformatteerd en valt weg tussen technische details.

Geen semantische status: Succes/fout/waarschuwing worden niet visueel onderscheiden; er ontbreken badges en iconen voor snelle scanning.

Geen “diff”-presentatie: Before/After veranderingen (bijv. wijziging van nummers of bedragen) worden niet geformatteerd weergegeven en zijn daardoor moeilijk te controleren.

Oplossing: Progressive Disclosure

Collapsed-first: Standaard toont elke logregel alleen de essentie (Tijd | Wie | Actie | Status). Technische details en diffs worden verborgen.

Expand-on-demand: Klikken op een logregel opent een accordeon met:

Samengevatte diff (Before → After) in een compact, goed leesbaar formaat.

Optionele “Technische details” (meta) en “Ruwe JSON” elk in een eigen collapsible blok.

Visuele hiërarchie: Consistente typografie en spacing; badges en iconen signaleren status en type actie.

Table Design (zichtbare kolommen)

Tijd:

Formaat: relatieve tijd (bijv. “3m ago”), met title/tooltip voor exacte timestamp (dd-mm-jjjj hh:mm:ss).

Doel: snelle chronologische scanbaarheid.

Wie:

Weergave van username (fallback: “System”).

Doel: accountability op user-niveau.

Actie:

Korte, mensvriendelijke label (bijv. “Rij gewijzigd”, “Bon aangemaakt”).

Doel: direct begrip zonder technische namen.

Status:

Badge met kleur en icoon, op basis van semantiek (success/error/info/warning/neutral).

Doel: direct risicobesef, snelle triage.

Optionele extra kolommen (alleen indien echt nodig):

Entity: “bon #123”, “rij #456” als subtiele subtekst, om context te geven zonder de tabel te verzwaren.

Badge Systeem (semantiek en iconen)

Success (groen):

Voorbeelden: create_bon, bon_checked, bon_row_create, player_create.

Icoon: “check-circle”.

Doel: bevestiging van geslaagde mutatie of afronding.

Error (rood):

Voorbeelden: delete_bon, delete_row, player_delete, user_delete.

Icoon: “x-circle” of “trash” bij delete.

Doel: markeren van destructieve of mislukte acties.

Info (blauw):

Voorbeelden: bon_update, bon_move, bon_row_update, user_update.

Icoon: “information-circle” of “pencil” bij edit.

Doel: niet-kritieke, informatieve mutaties.

Warning (amber):

Voorbeelden: login_failed, verdachte gebeurtenissen.

Icoon: “exclamation-triangle”.

Doel: aandacht vragen zonder “error”-ernst.

Neutral (grijs):

Voorbeelden: bon_unchecked, logout, systeemnotities.

Icoon: “minus-circle” of “dot”.

Doel: neutrale status, geen actie vereist.

Regels:

Badge-tekst is kort en menselijk (“Aangemaakt”, “Gewijzigd”, “Verwijderd”).

Iconen zijn uniform formaat (bijv. 16–20px), uit dezelfde set (Heroicons).

Kleurwaarden en borders volgen het Design System (success/error/info/warning/neutral variabelen).

Detail View (expandable)

Accordeon opent onder de rij, met linkerbalk in de huidige themakleur (accent).

Sectie “Wijzigingen”:

Toon per veld een Before → After diff.

Scalars: “oude waarde → nieuwe waarde” met kleuraccent (rood voor oud, groen voor nieuw).

Arrays (bijv. nummers): toon oude en nieuwe set als inline chips/monospace tags met subtiele achtergrond.

Sectie “Technische details” (collapsible):

Log ID, User ID, Session (truncate), IP, Entity (type + ID), exacte tijd.

Doel: auditability zonder ruis in hoofdweergave.

Sectie “Ruwe JSON data” (collapsible):

Pretty-printed JSON in monospace block, horizontaal scrollbaar.

Doel: diepgaande inspectie voor technische analyse en support.

---

🧭 OPDRACHT 6: Winkelselector & Header (Specificaties)

Analyse

Transparantieprobleem: De huidige transparante balk mengt visueel met de onderliggende content, wat de leesbaarheid en focus schaadt.

Onvoldoende diepte: Geen duidelijk sticky schaduweffect; de scheiding tussen navigatie/selector en content is zwak.

Inconsistent gevoel: Op lichte achtergronden verdwijnt de bar; op drukke content verslechtert de leesbaarheid.

Design Besluit

Stijlkeuze: Solid + lichte blur (glassmorphism-achtig, maar primair solide voor leesbaarheid).

Achtergrond:

Gradient gebaseerd op de actieve winkel-accentkleur met lage opaciteit (volgens variabelen).

Doel: subtiele branding zonder contrastverlies.

Border:

Onderste rand met een semi-transparante tint van de accentkleur (verwijzend naar onze variabelen voor border).

Doel: duidelijke afbakening van content.

Shadow:

Standaard: kleine, zachte schaduw (shadow-sm) voor diepte.

Sticky state: opschakelen naar shadow-md bij “stuck” om de fixed context te benadrukken.

Variabelen:

Gebruik de bestaande CSS variabelen voor accentkleur (rgb), schaduwen en z-index. Geen hardcoded waarden.

Behavior (scroll)

Sticky:

Winkelselector-bar is “sticky” onder de hoofdheader (top = headerhoogte).

Altijd zichtbaar bij scrollen; geen abrupt springgedrag.

Shadow trigger:

Bij het vastplakken (intersectie < 100%) wordt een “is-stuck” state geactiveerd die de shadow vergroot.

Doel: visueel signaal van gelaagdheid en focus.

Responsiveness:

Op mobiel: iets lagere blur en passende top-offset; bar blijft consistent in hoogte en spacing.

---

📐 OPDRACHT 7: Grid System & Layout Consistentie (Specificaties)

Analyse

Verschillen:

dashboard.php gebruikt consistente containerbreedte (1280px), verticale padding en card-gaps.

spelers.php wijkt af: geen uniforme container, custom max-widths, andere verticale spacing.

Gevolg:

“Springende” UI bij navigatie; inconsistent ritme in spacing en typografie.

Tabellen, kaarten en formulieren hebben wisselende paddings en randen.

De Standaard (vaste maten)

Container:

Max-width: 1280px (container-xl) als standaard voor alle hoofdcontent.

Horizontale padding: vaste waarden volgens spacing-variabelen; responsive reductie op mobiel.

Verticale page-padding:

Standaard PY: 24px (desktop), 16px (mobiel).

Consistent toegepast op alle pagina’s via layout.

Card componenten:

Card-padding: 24px (desktop), 16px (mobiel).

Card-gap tussen secties: 24px (desktop), 16px (mobiel).

Uniforme border-radius en shadow conform design system.

Tabel/rijen:

Tabel-cel padding: 12px verticaal, 16px horizontaal.

Koprij: uppercase, kleine caps, subtiele border-bottom.

Grid Regels (inputs, labels, responsiviteit)

Inputs:

Hoogte: 40px vast (alle primaire controls).

Breedte: 100% op mobiel; op desktop grid-based (bijv. 2 of 3 kolommen) waar passend.

Typografie: body-tekstmaat (14px) en consistente line-height.

Labels:

Boven het veld, vaste ondermarge (8px).

Gewicht: medium, kleur: neutrale grijs for consistency en leesbaarheid.

Form spacing:

Verticale spacing tussen form-groups: 16px (desktop), 12px (mobiel).

Gebruik van utility-klassen voor consistente gaps in grids (8pt-schaal).

Lay-out consistentie:

Alle pagina’s roepen dezelfde MainLayout aan met containerWidth=xl en paddingY=6.

Secties binnen pagina’s organiseren in grid met vaste gaps (16–24px) in plaats van inline styles of ad-hoc margins.

Toegankelijkheid:

Focus states van inputs/buttons consistent en gebaseerd op de actieve winkelaccentkleur.

Voldoende contrast (AA) en duidelijke focus outlines, geen enkel element zonder focus-state.

Deze specificaties zorgen dat logs leesbaar worden, de winkelselector visueel stevig en consistent is, en dat alle pagina’s hetzelfde grid- en spacing-systeem hanteren voor een uniforme, professionele ervaring.
