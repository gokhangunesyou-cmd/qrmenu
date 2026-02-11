<?php

namespace App\Service;

class SiteWidgetRegistry
{
    /**
     * @return array<string, array{label: string, fields: array<string, array{label: string, type: string, placeholder?: string}>, translatable: list<string>, jsonFields: list<string>}>
     */
    public function getDefinitions(): array
    {
        return [
            'hero' => [
                'label' => 'Hero / Kapak',
                'fields' => [
                    'eyebrow' => ['label' => 'Ust etiket', 'type' => 'text', 'placeholder' => 'Orn: Kurumsal QR Menu Altyapisi'],
                    'title' => ['label' => 'Baslik', 'type' => 'text', 'placeholder' => 'Buyuk baslik'],
                    'description' => ['label' => 'Aciklama', 'type' => 'textarea'],
                    'primary_text' => ['label' => 'Birincil buton metni', 'type' => 'text'],
                    'primary_url' => ['label' => 'Birincil buton URL', 'type' => 'text', 'placeholder' => '#plans'],
                    'secondary_text' => ['label' => 'Ikincil buton metni', 'type' => 'text'],
                    'secondary_url' => ['label' => 'Ikincil buton URL', 'type' => 'text', 'placeholder' => '#features'],
                    'badge' => ['label' => 'Rozet metni', 'type' => 'text', 'placeholder' => 'Orn: 24/7 Destek'],
                    'image_url' => ['label' => 'Hero gorsel URL', 'type' => 'text', 'placeholder' => '/assets/slider/banner-1.svg'],
                ],
                'translatable' => ['eyebrow', 'title', 'description', 'primary_text', 'secondary_text', 'badge'],
                'jsonFields' => [],
            ],
            'slider' => [
                'label' => 'Slider',
                'fields' => [
                    'title' => ['label' => 'Baslik', 'type' => 'text'],
                    'note' => ['label' => 'Aciklama', 'type' => 'textarea'],
                ],
                'translatable' => ['title', 'note'],
                'jsonFields' => [],
            ],
            'features' => [
                'label' => 'Ozellikler',
                'fields' => [
                    'title' => ['label' => 'Baslik', 'type' => 'text'],
                    'note' => ['label' => 'Aciklama', 'type' => 'textarea'],
                    'items_json' => ['label' => 'Ozellik listesi (JSON)', 'type' => 'json', 'placeholder' => '[{\"title\":\"...\",\"body\":\"...\"}]'],
                ],
                'translatable' => ['title', 'note', 'items_json'],
                'jsonFields' => ['items_json'],
            ],
            'panel' => [
                'label' => 'Panel Gorunumleri',
                'fields' => [
                    'title' => ['label' => 'Baslik', 'type' => 'text'],
                    'note' => ['label' => 'Aciklama', 'type' => 'textarea'],
                    'shots_json' => ['label' => 'Panel kartlari (JSON)', 'type' => 'json', 'placeholder' => '[{\"title\":\"...\",\"body\":\"...\",\"image\":\"/assets/panel/panel-1.svg\"}]'],
                ],
                'translatable' => ['title', 'note', 'shots_json'],
                'jsonFields' => ['shots_json'],
            ],
            'plans' => [
                'label' => 'Planlar',
                'fields' => [
                    'title' => ['label' => 'Baslik', 'type' => 'text'],
                    'note' => ['label' => 'Aciklama', 'type' => 'textarea'],
                    'badge' => ['label' => 'Rozet', 'type' => 'text'],
                ],
                'translatable' => ['title', 'note', 'badge'],
                'jsonFields' => [],
            ],
            'references' => [
                'label' => 'Referanslar',
                'fields' => [
                    'title' => ['label' => 'Baslik', 'type' => 'text'],
                    'note' => ['label' => 'Aciklama', 'type' => 'textarea'],
                    'items_json' => ['label' => 'Referans listesi (JSON)', 'type' => 'json', 'placeholder' => '[{\"title\":\"...\",\"body\":\"...\"}]'],
                ],
                'translatable' => ['title', 'note', 'items_json'],
                'jsonFields' => ['items_json'],
            ],
            'blog' => [
                'label' => 'Blog',
                'fields' => [
                    'title' => ['label' => 'Baslik', 'type' => 'text'],
                    'note' => ['label' => 'Aciklama', 'type' => 'textarea'],
                ],
                'translatable' => ['title', 'note'],
                'jsonFields' => [],
            ],
            'contact' => [
                'label' => 'Iletisim',
                'fields' => [
                    'title' => ['label' => 'Baslik', 'type' => 'text'],
                    'note' => ['label' => 'Aciklama', 'type' => 'textarea'],
                    'email' => ['label' => 'E-posta', 'type' => 'text'],
                    'phone' => ['label' => 'Telefon', 'type' => 'text'],
                ],
                'translatable' => ['title', 'note'],
                'jsonFields' => [],
            ],
            'custom_html' => [
                'label' => 'Ozel HTML',
                'fields' => [
                    'html' => ['label' => 'HTML icerigi', 'type' => 'textarea', 'placeholder' => '<section>...</section>'],
                ],
                'translatable' => ['html'],
                'jsonFields' => [],
            ],
        ];
    }

    /**
     * @return array{label: string, fields: array<string, array{label: string, type: string, placeholder?: string}>, translatable: list<string>, jsonFields: list<string>}|null
     */
    public function getDefinition(string $type): ?array
    {
        $definitions = $this->getDefinitions();

        return $definitions[$type] ?? null;
    }

    /**
     * @param array<string, mixed> $config
     * @param array<int, array<string, string>> $translations
     * @return array<string, mixed>
     */
    public function mergeTranslations(array $config, array $translations, int $entityId, ?array $definition): array
    {
        if ($definition === null) {
            return $config;
        }

        foreach ($definition['translatable'] as $field) {
            $value = $translations[$entityId][$field] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $config[$field] = $value;
            }
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function normalizeConfig(array $config, ?array $definition): array
    {
        if ($definition === null) {
            return $config;
        }

        foreach ($definition['jsonFields'] as $field) {
            if (!array_key_exists($field, $config)) {
                continue;
            }
            $value = $config[$field];
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $config[$field] = $decoded;
                }
            }
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, array{label: string, type: string, placeholder?: string}> $fields
     * @return array<string, string>
     */
    public function exportFormValues(array $config, array $fields): array
    {
        $values = [];
        foreach ($fields as $key => $field) {
            $value = $config[$key] ?? '';
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            }
            $values[$key] = is_string($value) ? $value : '';
        }

        return $values;
    }
}
