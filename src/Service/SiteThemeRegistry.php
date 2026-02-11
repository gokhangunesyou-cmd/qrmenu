<?php

namespace App\Service;

class SiteThemeRegistry
{
    /**
     * @return array<string, array<string, string>>
     */
    public function getThemes(): array
    {
        return [
            'theme_1' => [
                'name' => 'Aurora',
                'font_head' => 'Fraunces',
                'font_body' => 'Manrope',
            ],
            'theme_2' => [
                'name' => 'Contour',
                'font_head' => 'Outfit',
                'font_body' => 'Plus Jakarta Sans',
            ],
            'theme_3' => [
                'name' => 'Pulse',
                'font_head' => 'Bricolage Grotesque',
                'font_body' => 'Archivo',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getDefaultPalette(string $theme): array
    {
        if ($theme === 'theme_2') {
            return [
                'primary' => '#f97316',
                'primaryDark' => '#c2410c',
                'primaryLight' => '#ffedd5',
                'accent' => '#0ea5e9',
                'background' => '#fff7ed',
                'surface' => '#ffffff',
                'text' => '#1f2937',
                'muted' => '#6b7280',
                'border' => 'rgba(148, 163, 184, 0.35)',
                'highlight' => '#fef3c7',
                'heroGlow' => 'rgba(249, 115, 22, 0.25)',
            ];
        }

        if ($theme === 'theme_3') {
            return [
                'primary' => '#0f172a',
                'primaryDark' => '#020617',
                'primaryLight' => '#dbeafe',
                'accent' => '#f43f5e',
                'background' => '#e0f2fe',
                'surface' => '#f8fafc',
                'text' => '#0b1120',
                'muted' => '#334155',
                'border' => 'rgba(15, 23, 42, 0.18)',
                'highlight' => '#ffe4e6',
                'heroGlow' => 'rgba(244, 63, 94, 0.28)',
            ];
        }

        return [
            'primary' => '#0f766e',
            'primaryDark' => '#134e4a',
            'primaryLight' => '#ccfbf1',
            'accent' => '#f59e0b',
            'background' => '#f0fdfa',
            'surface' => '#ffffff',
            'text' => '#0f172a',
            'muted' => '#64748b',
            'border' => 'rgba(15, 118, 110, 0.18)',
            'highlight' => '#ecfeff',
            'heroGlow' => 'rgba(15, 118, 110, 0.22)',
        ];
    }

    /**
     * @param array<string, mixed> $override
     * @return array<string, string>
     */
    public function mergePalette(string $theme, array $override): array
    {
        $palette = $this->getDefaultPalette($theme);
        foreach ($override as $key => $value) {
            if (!is_string($key) || !is_string($value) || $value === '') {
                continue;
            }
            $palette[$key] = $value;
        }

        return $palette;
    }
}
