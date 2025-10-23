<?php

namespace Prism\Backend\Services\Plugins;

use Monolog\Logger;

class ThemePlugin extends BasePlugin
{
    private array $themes = [];
    private string $currentTheme = 'default';
    private array $customThemes = [];
    private bool $isEnabled = false;
    private array $themeSettings = [];

    public function __construct(array $config = [], Logger $logger = null)
    {
        parent::__construct($config, $logger);
        $this->loadDefaultThemes();
    }

    public function initialize(): bool
    {
        try {
            $this->loadConfiguration();
            $this->logger->info('Theme plugin initialized');
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize Theme plugin: ' . $e->getMessage());
            return false;
        }
    }

    public function enable(): bool
    {
        $this->isEnabled = true;
        $this->logger->info('Theme plugin enabled');
        return true;
    }

    public function disable(): bool
    {
        $this->isEnabled = false;
        $this->logger->info('Theme plugin disabled');
        return true;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function getInfo(): array
    {
        return [
            'name' => 'Theme',
            'version' => '1.0.0',
            'description' => 'Manages browser themes and appearance',
            'author' => 'Prism Team',
            'enabled' => $this->isEnabled,
            'current_theme' => $this->currentTheme,
            'available_themes' => count($this->themes)
        ];
    }

    public function onEvent(string $eventName, array $data = []): mixed
    {
        switch ($eventName) {
            case 'page_load':
                return $this->handlePageLoad($data);
            case 'theme_change':
                return $this->handleThemeChange($data);
            case 'settings_update':
                return $this->handleSettingsUpdate($data);
            default:
                return null;
        }
    }

    public function setTheme(string $themeName): bool
    {
        if (!$this->isEnabled) {
            return false;
        }

        if (!isset($this->themes[$themeName])) {
            $this->logger->error('Theme not found', ['theme' => $themeName]);
            return false;
        }

        $this->currentTheme = $themeName;
        $this->logger->info('Theme changed', ['theme' => $themeName]);

        // Broadcast theme change event
        $this->broadcastEvent('theme_changed', [
            'theme' => $themeName,
            'theme_data' => $this->themes[$themeName]
        ]);

        return true;
    }

    public function getCurrentTheme(): string
    {
        return $this->currentTheme;
    }

    public function getThemeData(string $themeName = null): ?array
    {
        $theme = $themeName ?? $this->currentTheme;
        return $this->themes[$theme] ?? null;
    }

    public function getAllThemes(): array
    {
        return $this->themes;
    }

    public function createCustomTheme(string $name, array $themeData): bool
    {
        if (!$this->isEnabled) {
            return false;
        }

        if (isset($this->themes[$name])) {
            $this->logger->error('Theme already exists', ['theme' => $name]);
            return false;
        }

        $this->customThemes[$name] = $themeData;
        $this->themes[$name] = $themeData;

        $this->logger->info('Custom theme created', ['theme' => $name]);
        return true;
    }

    public function updateTheme(string $name, array $themeData): bool
    {
        if (!$this->isEnabled) {
            return false;
        }

        if (!isset($this->themes[$name])) {
            $this->logger->error('Theme not found for update', ['theme' => $name]);
            return false;
        }

        $this->themes[$name] = array_merge($this->themes[$name], $themeData);
        
        if (isset($this->customThemes[$name])) {
            $this->customThemes[$name] = $this->themes[$name];
        }

        $this->logger->info('Theme updated', ['theme' => $name]);
        return true;
    }

    public function deleteCustomTheme(string $name): bool
    {
        if (!$this->isEnabled) {
            return false;
        }

        if (!isset($this->customThemes[$name])) {
            $this->logger->error('Custom theme not found', ['theme' => $name]);
            return false;
        }

        unset($this->customThemes[$name]);
        unset($this->themes[$name]);

        // If we deleted the current theme, switch to default
        if ($this->currentTheme === $name) {
            $this->setTheme('default');
        }

        $this->logger->info('Custom theme deleted', ['theme' => $name]);
        return true;
    }

    public function getCustomThemes(): array
    {
        return $this->customThemes;
    }

    public function applyThemeToPage(string $html, string $themeName = null): string
    {
        if (!$this->isEnabled) {
            return $html;
        }

        $theme = $this->getThemeData($themeName);
        if (!$theme) {
            return $html;
        }

        // Inject theme CSS
        $css = $this->generateThemeCSS($theme);
        $html = $this->injectCSS($html, $css);

        // Apply theme variables
        $html = $this->applyThemeVariables($html, $theme);

        return $html;
    }

    public function getThemeCSS(string $themeName = null): string
    {
        $theme = $this->getThemeData($themeName);
        if (!$theme) {
            return '';
        }

        return $this->generateThemeCSS($theme);
    }

    private function loadDefaultThemes(): void
    {
        $this->themes = [
            'default' => [
                'name' => 'Default',
                'description' => 'Default Prism theme',
                'colors' => [
                    'primary' => '#007bff',
                    'secondary' => '#6c757d',
                    'success' => '#28a745',
                    'danger' => '#dc3545',
                    'warning' => '#ffc107',
                    'info' => '#17a2b8',
                    'light' => '#f8f9fa',
                    'dark' => '#343a40',
                    'background' => '#ffffff',
                    'text' => '#212529',
                    'border' => '#dee2e6'
                ],
                'fonts' => [
                    'primary' => 'system-ui, -apple-system, sans-serif',
                    'monospace' => 'Monaco, Consolas, monospace'
                ],
                'spacing' => [
                    'xs' => '0.25rem',
                    'sm' => '0.5rem',
                    'md' => '1rem',
                    'lg' => '1.5rem',
                    'xl' => '3rem'
                ],
                'border_radius' => '0.375rem',
                'shadows' => [
                    'sm' => '0 1px 2px rgba(0,0,0,0.05)',
                    'md' => '0 4px 6px rgba(0,0,0,0.1)',
                    'lg' => '0 10px 15px rgba(0,0,0,0.1)'
                ]
            ],
            'dark' => [
                'name' => 'Dark',
                'description' => 'Dark theme for low-light environments',
                'colors' => [
                    'primary' => '#0d6efd',
                    'secondary' => '#6c757d',
                    'success' => '#198754',
                    'danger' => '#dc3545',
                    'warning' => '#ffc107',
                    'info' => '#0dcaf0',
                    'light' => '#f8f9fa',
                    'dark' => '#212529',
                    'background' => '#1a1a1a',
                    'text' => '#ffffff',
                    'border' => '#495057'
                ],
                'fonts' => [
                    'primary' => 'system-ui, -apple-system, sans-serif',
                    'monospace' => 'Monaco, Consolas, monospace'
                ],
                'spacing' => [
                    'xs' => '0.25rem',
                    'sm' => '0.5rem',
                    'md' => '1rem',
                    'lg' => '1.5rem',
                    'xl' => '3rem'
                ],
                'border_radius' => '0.375rem',
                'shadows' => [
                    'sm' => '0 1px 2px rgba(0,0,0,0.3)',
                    'md' => '0 4px 6px rgba(0,0,0,0.4)',
                    'lg' => '0 10px 15px rgba(0,0,0,0.4)'
                ]
            ],
            'high_contrast' => [
                'name' => 'High Contrast',
                'description' => 'High contrast theme for accessibility',
                'colors' => [
                    'primary' => '#0000ff',
                    'secondary' => '#808080',
                    'success' => '#008000',
                    'danger' => '#ff0000',
                    'warning' => '#ffff00',
                    'info' => '#00ffff',
                    'light' => '#ffffff',
                    'dark' => '#000000',
                    'background' => '#ffffff',
                    'text' => '#000000',
                    'border' => '#000000'
                ],
                'fonts' => [
                    'primary' => 'Arial, sans-serif',
                    'monospace' => 'Courier New, monospace'
                ],
                'spacing' => [
                    'xs' => '0.25rem',
                    'sm' => '0.5rem',
                    'md' => '1rem',
                    'lg' => '1.5rem',
                    'xl' => '3rem'
                ],
                'border_radius' => '0',
                'shadows' => [
                    'sm' => 'none',
                    'md' => 'none',
                    'lg' => 'none'
                ]
            ]
        ];
    }

    private function loadConfiguration(): void
    {
        $config = $this->getConfig();
        
        if (isset($config['current_theme'])) {
            $this->currentTheme = $config['current_theme'];
        }

        if (isset($config['custom_themes'])) {
            foreach ($config['custom_themes'] as $name => $themeData) {
                $this->customThemes[$name] = $themeData;
                $this->themes[$name] = $themeData;
            }
        }

        if (isset($config['theme_settings'])) {
            $this->themeSettings = $config['theme_settings'];
        }
    }

    private function generateThemeCSS(array $theme): string
    {
        $css = ":root {\n";
        
        // Add color variables
        foreach ($theme['colors'] as $name => $value) {
            $css .= "  --color-{$name}: {$value};\n";
        }
        
        // Add font variables
        foreach ($theme['fonts'] as $name => $value) {
            $css .= "  --font-{$name}: {$value};\n";
        }
        
        // Add spacing variables
        foreach ($theme['spacing'] as $name => $value) {
            $css .= "  --spacing-{$name}: {$value};\n";
        }
        
        // Add other variables
        $css .= "  --border-radius: {$theme['border_radius']};\n";
        $css .= "  --shadow-sm: {$theme['shadows']['sm']};\n";
        $css .= "  --shadow-md: {$theme['shadows']['md']};\n";
        $css .= "  --shadow-lg: {$theme['shadows']['lg']};\n";
        
        $css .= "}\n\n";
        
        // Add base styles
        $css .= "body {\n";
        $css .= "  background-color: var(--color-background);\n";
        $css .= "  color: var(--color-text);\n";
        $css .= "  font-family: var(--font-primary);\n";
        $css .= "}\n\n";
        
        // Add component styles
        $css .= $this->generateComponentStyles($theme);
        
        return $css;
    }

    private function generateComponentStyles(array $theme): string
    {
        $css = "";
        
        // Button styles
        $css .= ".btn {\n";
        $css .= "  background-color: var(--color-primary);\n";
        $css .= "  color: var(--color-light);\n";
        $css .= "  border: 1px solid var(--color-primary);\n";
        $css .= "  border-radius: var(--border-radius);\n";
        $css .= "  padding: var(--spacing-sm) var(--spacing-md);\n";
        $css .= "  font-family: var(--font-primary);\n";
        $css .= "  cursor: pointer;\n";
        $css .= "  transition: all 0.2s ease;\n";
        $css .= "}\n\n";
        
        $css .= ".btn:hover {\n";
        $css .= "  background-color: var(--color-dark);\n";
        $css .= "  border-color: var(--color-dark);\n";
        $css .= "  box-shadow: var(--shadow-md);\n";
        $css .= "}\n\n";
        
        // Card styles
        $css .= ".card {\n";
        $css .= "  background-color: var(--color-background);\n";
        $css .= "  border: 1px solid var(--color-border);\n";
        $css .= "  border-radius: var(--border-radius);\n";
        $css .= "  box-shadow: var(--shadow-sm);\n";
        $css .= "  padding: var(--spacing-md);\n";
        $css .= "}\n\n";
        
        // Input styles
        $css .= "input, textarea, select {\n";
        $css .= "  background-color: var(--color-background);\n";
        $css .= "  color: var(--color-text);\n";
        $css .= "  border: 1px solid var(--color-border);\n";
        $css .= "  border-radius: var(--border-radius);\n";
        $css .= "  padding: var(--spacing-sm);\n";
        $css .= "  font-family: var(--font-primary);\n";
        $css .= "}\n\n";
        
        return $css;
    }

    private function injectCSS(string $html, string $css): string
    {
        // Find head tag and inject CSS
        if (preg_match('/<head[^>]*>/i', $html)) {
            $html = preg_replace(
                '/(<head[^>]*>)/i',
                "$1\n<style>\n{$css}\n</style>",
                $html
            );
        } else {
            // If no head tag, add one
            $html = "<head>\n<style>\n{$css}\n</style>\n</head>\n{$html}";
        }
        
        return $html;
    }

    private function applyThemeVariables(string $html, array $theme): string
    {
        // This would apply theme variables to specific elements
        // For now, we'll just return the HTML as-is
        return $html;
    }

    private function handlePageLoad(array $data): array
    {
        return [
            'theme_applied' => true,
            'theme' => $this->currentTheme,
            'css_injected' => true
        ];
    }

    private function handleThemeChange(array $data): array
    {
        $themeName = $data['theme'] ?? null;
        if ($themeName && $this->setTheme($themeName)) {
            return ['success' => true, 'theme' => $themeName];
        }
        return ['success' => false];
    }

    private function handleSettingsUpdate(array $data): array
    {
        if (isset($data['theme'])) {
            return $this->handleThemeChange($data);
        }
        return ['success' => false, 'message' => 'No theme settings found'];
    }

    public function getStatistics(): array
    {
        return [
            'enabled' => $this->isEnabled,
            'current_theme' => $this->currentTheme,
            'total_themes' => count($this->themes),
            'custom_themes' => count($this->customThemes),
            'available_themes' => array_keys($this->themes)
        ];
    }
}
