<?php

if (!function_exists('renderWinkelButtons')) {
    function renderWinkelButtons(array $winkels, ?int $selectedWinkel, string $buttonClass = 'winkel-btn'): void
    {
        $palette = getWinkelPalette();
        $fallback = $palette['default'];
        $allTheme = $palette['all'];

        $buttons = [];

        $buttons[] = [
            'label' => 'Alles',
            'attributes' => [
                'data-winkel' => 'null',
                'data-winkel-target' => 'all',
                'data-role' => 'winkel-button',
            ],
            'theme' => $allTheme,
            'is_active' => $selectedWinkel === null,
        ];

        foreach ($winkels as $winkel) {
            $theme = $palette[$winkel['naam']] ?? $fallback;
            $buttons[] = [
                'label' => htmlspecialchars($winkel['naam']),
                'attributes' => [
                    'data-winkel' => (int)$winkel['id'],
                    'data-winkel-target' => (int)$winkel['id'],
                    'data-role' => 'winkel-button',
                ],
                'theme' => $theme,
                'is_active' => (int)$selectedWinkel === (int)$winkel['id'],
            ];
        }

        foreach ($buttons as $button) {
                    // Verhoogde opacity voor ZICHTBARE gradient (Variant A: Soft Mesh)
                    $style = sprintf(
                        '--btn-text:%s;--btn-hover-bg:%s;--btn-hover-border:%s;--btn-hover-text:%s;--btn-active-bg:%s;--btn-active-text:%s;--btn-active-border:%s;--gradient-color:%s;--gradient-color-active:%s;',
                        $button['theme']['accent'] . 'D9',    // Text: 85% opacity
                        $button['theme']['accent'] . '06',    // Hover bg: 6%
                        $button['theme']['accent'] . '50',    // Hover border: 50%
                        $button['theme']['accent'],           // Hover text: 100%
                        $button['theme']['accent'] . '0F',    // Active bg: 15%
                        $button['theme']['accent'],           // Active text: 100%
                        $button['theme']['accent'],           // Active border: 100%
                        $button['theme']['accent'] . '12',    // Gradient inactive: 18% (was 06-08)
                        $button['theme']['accent'] . '1A'     // Gradient active: 26% (was 0F-15)
                    );

            $attributes = $button['attributes'];
            $attributes['type'] = 'button';
            $attributes['class'] = trim($buttonClass . ' ' . ($button['is_active'] ? 'active' : ''));
            $attributes['style'] = $style;

            $attrStringParts = [];
            foreach ($attributes as $attr => $value) {
                $attrStringParts[] = sprintf('%s="%s"', $attr, htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'));
            }

            printf(
                '<button %s>%s</button>',
                implode(' ', $attrStringParts),
                $button['label']
            );
        }
    }
}

?>


