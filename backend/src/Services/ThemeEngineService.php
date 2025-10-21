<?php

namespace Prism\Backend\Services;

use Monolog\Logger;

class ThemeEngineService
{
    private Logger $logger;
    private array $themes = [];
    private string $currentTheme = 'default';
    private array $userCustomizations = [];
    private bool $autoTheme = false;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->initializeThemes();
    }

    public function getAvailableThemes(): array
    {
        return array_map(function($theme) {
            return [
                'id' => $theme['id'],
                'name' => $theme['name'],
                'description' => $theme['description'],
                'type' => $theme['type'],
                'preview' => $theme['preview'] ?? null
            ];
        }, $this->themes);
    }

    public function setTheme(string $themeId): bool
    {
        if (!isset($this->themes[$themeId])) {
            $this->logger->error('Theme not found', ['themeId' => $themeId]);
            return false;
        }

        $this->currentTheme = $themeId;
        $this->logger->info('Theme changed', ['themeId' => $themeId]);
        return true;
    }

    public function getCurrentTheme(): array
    {
        $theme = $this->themes[$this->currentTheme];
        return array_merge($theme, [
            'customizations' => $this->userCustomizations
        ]);
    }

    public function customizeTheme(array $customizations): bool
    {
        try {
            $this->userCustomizations = array_merge($this->userCustomizations, $customizations);
            $this->logger->info('Theme customized', ['customizations' => $customizations]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to customize theme: ' . $e->getMessage());
            return false;
        }
    }

    public function resetCustomizations(): bool
    {
        $this->userCustomizations = [];
        $this->logger->info('Theme customizations reset');
        return true;
    }

    public function enableAutoTheme(): bool
    {
        $this->autoTheme = true;
        $this->logger->info('Auto theme enabled');
        return true;
    }

    public function disableAutoTheme(): bool
    {
        $this->autoTheme = false;
        $this->logger->info('Auto theme disabled');
        return true;
    }

    public function createCustomTheme(string $name, array $colors, array $typography = []): string
    {
        $themeId = 'custom_' . uniqid();
        
        $this->themes[$themeId] = [
            'id' => $themeId,
            'name' => $name,
            'description' => 'Custom user theme',
            'type' => 'custom',
            'colors' => $colors,
            'typography' => $typography,
            'created_at' => date('c')
        ];

        $this->logger->info('Custom theme created', ['themeId' => $themeId, 'name' => $name]);
        return $themeId;
    }

    public function exportTheme(string $themeId): array
    {
        if (!isset($this->themes[$themeId])) {
            throw new \InvalidArgumentException('Theme not found');
        }

        return $this->themes[$themeId];
    }

    public function importTheme(array $themeData): string
    {
        $themeId = 'imported_' . uniqid();
        $themeData['id'] = $themeId;
        $themeData['type'] = 'imported';
        
        $this->themes[$themeId] = $themeData;
        
        $this->logger->info('Theme imported', ['themeId' => $themeId]);
        return $themeId;
    }

    public function getThemeCSS(string $themeId = null): string
    {
        $themeId = $themeId ?? $this->currentTheme;
        $theme = $this->themes[$themeId];
        
        $css = $this->generateCSS($theme);
        
        // Apply customizations
        if (!empty($this->userCustomizations)) {
            $css .= $this->generateCustomizationCSS($this->userCustomizations);
        }
        
        return $css;
    }

    private function initializeThemes(): void
    {
        $this->themes = [
            'default' => [
                'id' => 'default',
                'name' => 'Default',
                'description' => 'Clean and modern default theme',
                'type' => 'builtin',
                'colors' => [
                    'primary' => '#3b82f6',
                    'secondary' => '#64748b',
                    'background' => '#ffffff',
                    'surface' => '#f8fafc',
                    'text' => '#1e293b',
                    'text_secondary' => '#64748b',
                    'border' => '#e2e8f0',
                    'accent' => '#f59e0b',
                    'success' => '#10b981',
                    'warning' => '#f59e0b',
                    'error' => '#ef4444'
                ],
                'typography' => [
                    'font_family' => 'Inter, system-ui, sans-serif',
                    'font_size_base' => '16px',
                    'font_size_sm' => '14px',
                    'font_size_lg' => '18px',
                    'font_weight_normal' => '400',
                    'font_weight_medium' => '500',
                    'font_weight_semibold' => '600',
                    'font_weight_bold' => '700'
                ]
            ],
            'dark' => [
                'id' => 'dark',
                'name' => 'Dark',
                'description' => 'Dark theme for low-light environments',
                'type' => 'builtin',
                'colors' => [
                    'primary' => '#60a5fa',
                    'secondary' => '#94a3b8',
                    'background' => '#0f172a',
                    'surface' => '#1e293b',
                    'text' => '#f1f5f9',
                    'text_secondary' => '#94a3b8',
                    'border' => '#334155',
                    'accent' => '#fbbf24',
                    'success' => '#34d399',
                    'warning' => '#fbbf24',
                    'error' => '#f87171'
                ],
                'typography' => [
                    'font_family' => 'Inter, system-ui, sans-serif',
                    'font_size_base' => '16px',
                    'font_size_sm' => '14px',
                    'font_size_lg' => '18px',
                    'font_weight_normal' => '400',
                    'font_weight_medium' => '500',
                    'font_weight_semibold' => '600',
                    'font_weight_bold' => '700'
                ]
            ],
            'high_contrast' => [
                'id' => 'high_contrast',
                'name' => 'High Contrast',
                'description' => 'High contrast theme for accessibility',
                'type' => 'builtin',
                'colors' => [
                    'primary' => '#0000ff',
                    'secondary' => '#000000',
                    'background' => '#ffffff',
                    'surface' => '#ffffff',
                    'text' => '#000000',
                    'text_secondary' => '#000000',
                    'border' => '#000000',
                    'accent' => '#ff0000',
                    'success' => '#008000',
                    'warning' => '#ffa500',
                    'error' => '#ff0000'
                ],
                'typography' => [
                    'font_family' => 'Arial, sans-serif',
                    'font_size_base' => '18px',
                    'font_size_sm' => '16px',
                    'font_size_lg' => '20px',
                    'font_weight_normal' => '400',
                    'font_weight_medium' => '600',
                    'font_weight_semibold' => '700',
                    'font_weight_bold' => '800'
                ]
            ],
            'ocean' => [
                'id' => 'ocean',
                'name' => 'Ocean',
                'description' => 'Calming ocean-inspired theme',
                'type' => 'builtin',
                'colors' => [
                    'primary' => '#0ea5e9',
                    'secondary' => '#06b6d4',
                    'background' => '#f0f9ff',
                    'surface' => '#e0f2fe',
                    'text' => '#0c4a6e',
                    'text_secondary' => '#0369a1',
                    'border' => '#bae6fd',
                    'accent' => '#f97316',
                    'success' => '#059669',
                    'warning' => '#d97706',
                    'error' => '#dc2626'
                ],
                'typography' => [
                    'font_family' => 'Inter, system-ui, sans-serif',
                    'font_size_base' => '16px',
                    'font_size_sm' => '14px',
                    'font_size_lg' => '18px',
                    'font_weight_normal' => '400',
                    'font_weight_medium' => '500',
                    'font_weight_semibold' => '600',
                    'font_weight_bold' => '700'
                ]
            ],
            'forest' => [
                'id' => 'forest',
                'name' => 'Forest',
                'description' => 'Nature-inspired green theme',
                'type' => 'builtin',
                'colors' => [
                    'primary' => '#16a34a',
                    'secondary' => '#22c55e',
                    'background' => '#f0fdf4',
                    'surface' => '#dcfce7',
                    'text' => '#14532d',
                    'text_secondary' => '#166534',
                    'border' => '#bbf7d0',
                    'accent' => '#ea580c',
                    'success' => '#16a34a',
                    'warning' => '#ca8a04',
                    'error' => '#dc2626'
                ],
                'typography' => [
                    'font_family' => 'Inter, system-ui, sans-serif',
                    'font_size_base' => '16px',
                    'font_size_sm' => '14px',
                    'font_size_lg' => '18px',
                    'font_weight_normal' => '400',
                    'font_weight_medium' => '500',
                    'font_weight_semibold' => '600',
                    'font_weight_bold' => '700'
                ]
            ],
            'sunset' => [
                'id' => 'sunset',
                'name' => 'Sunset',
                'description' => 'Warm sunset color palette',
                'type' => 'builtin',
                'colors' => [
                    'primary' => '#f97316',
                    'secondary' => '#fb923c',
                    'background' => '#fff7ed',
                    'surface' => '#fed7aa',
                    'text' => '#9a3412',
                    'text_secondary' => '#c2410c',
                    'border' => '#fdba74',
                    'accent' => '#3b82f6',
                    'success' => '#16a34a',
                    'warning' => '#eab308',
                    'error' => '#dc2626'
                ],
                'typography' => [
                    'font_family' => 'Inter, system-ui, sans-serif',
                    'font_size_base' => '16px',
                    'font_size_sm' => '14px',
                    'font_size_lg' => '18px',
                    'font_weight_normal' => '400',
                    'font_weight_medium' => '500',
                    'font_weight_semibold' => '600',
                    'font_weight_bold' => '700'
                ]
            ]
        ];
    }

    private function generateCSS(array $theme): string
    {
        $colors = $theme['colors'];
        $typography = $theme['typography'];
        
        return "
:root {
    --color-primary: {$colors['primary']};
    --color-secondary: {$colors['secondary']};
    --color-background: {$colors['background']};
    --color-surface: {$colors['surface']};
    --color-text: {$colors['text']};
    --color-text-secondary: {$colors['text_secondary']};
    --color-border: {$colors['border']};
    --color-accent: {$colors['accent']};
    --color-success: {$colors['success']};
    --color-warning: {$colors['warning']};
    --color-error: {$colors['error']};
    
    --font-family: {$typography['font_family']};
    --font-size-base: {$typography['font_size_base']};
    --font-size-sm: {$typography['font_size_sm']};
    --font-size-lg: {$typography['font_size_lg']};
    --font-weight-normal: {$typography['font_weight_normal']};
    --font-weight-medium: {$typography['font_weight_medium']};
    --font-weight-semibold: {$typography['font_weight_semibold']};
    --font-weight-bold: {$typography['font_weight_bold']};
}

body {
    background-color: var(--color-background);
    color: var(--color-text);
    font-family: var(--font-family);
    font-size: var(--font-size-base);
    font-weight: var(--font-weight-normal);
}

.toolbar {
    background-color: var(--color-surface);
    border-bottom: 1px solid var(--color-border);
}

.address-bar {
    background-color: var(--color-background);
    border: 1px solid var(--color-border);
    color: var(--color-text);
}

.tab {
    background-color: var(--color-surface);
    color: var(--color-text-secondary);
    border: 1px solid var(--color-border);
}

.tab.active {
    background-color: var(--color-background);
    color: var(--color-text);
}

.button {
    background-color: var(--color-primary);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    font-weight: var(--font-weight-medium);
}

.button:hover {
    background-color: var(--color-secondary);
}

.button.secondary {
    background-color: var(--color-surface);
    color: var(--color-text);
    border: 1px solid var(--color-border);
}

.sidebar {
    background-color: var(--color-surface);
    border-right: 1px solid var(--color-border);
}

.bookmark-item {
    color: var(--color-text);
    padding: 8px 12px;
}

.bookmark-item:hover {
    background-color: var(--color-background);
}

.status-bar {
    background-color: var(--color-surface);
    border-top: 1px solid var(--color-border);
    color: var(--color-text-secondary);
}

.notification {
    background-color: var(--color-surface);
    border: 1px solid var(--color-border);
    color: var(--color-text);
}

.notification.success {
    border-color: var(--color-success);
    background-color: rgba(16, 185, 129, 0.1);
}

.notification.warning {
    border-color: var(--color-warning);
    background-color: rgba(245, 158, 11, 0.1);
}

.notification.error {
    border-color: var(--color-error);
    background-color: rgba(239, 68, 68, 0.1);
}
";
    }

    private function generateCustomizationCSS(array $customizations): string
    {
        $css = "\n/* User Customizations */\n";
        
        foreach ($customizations as $property => $value) {
            $css .= ":root { --{$property}: {$value}; }\n";
        }
        
        return $css;
    }
}
