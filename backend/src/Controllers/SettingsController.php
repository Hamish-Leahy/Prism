<?php

namespace Prism\Backend\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Prism\Backend\Services\DatabaseService;

class SettingsController
{
    private DatabaseService $database;

    public function __construct(DatabaseService $database)
    {
        $this->database = $database;
    }

    public function list(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $category = $queryParams['category'] ?? null;

            $sql = 'SELECT * FROM settings';
            $params = [];

            if ($category) {
                $sql .= ' WHERE category = ?';
                $params[] = $category;
            }

            $sql .= ' ORDER BY category, key';

            $settings = $this->database->query($sql, $params);

            // Group settings by category
            $groupedSettings = [];
            foreach ($settings as $setting) {
                $groupedSettings[$setting['category']][] = [
                    'key' => $setting['key'],
                    'value' => $setting['value'],
                    'updated_at' => $setting['updated_at']
                ];
            }

            $response->getBody()->write(json_encode($groupedSettings));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Failed to fetch settings']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function get(Request $request, Response $response): Response
    {
        $args = $request->getAttribute('route')->getArguments();
        $key = $args['key'] ?? '';

        if (empty($key)) {
            $response->getBody()->write(json_encode(['error' => 'Setting key is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $settings = $this->database->query(
                'SELECT * FROM settings WHERE key = ?',
                [$key]
            );

            if (empty($settings)) {
                $response->getBody()->write(json_encode(['error' => 'Setting not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode($settings[0]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Failed to fetch setting']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function update(Request $request, Response $response): Response
    {
        $args = $request->getAttribute('route')->getArguments();
        $key = $args['key'] ?? '';
        $data = json_decode($request->getBody()->getContents(), true);

        if (empty($key)) {
            $response->getBody()->write(json_encode(['error' => 'Setting key is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (!isset($data['value'])) {
            $response->getBody()->write(json_encode(['error' => 'Value is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            // Check if setting exists
            $existing = $this->database->query(
                'SELECT * FROM settings WHERE key = ?',
                [$key]
            );

            if (empty($existing)) {
                // Create new setting
                $category = $data['category'] ?? 'general';
                $this->database->execute(
                    'INSERT INTO settings (key, value, category) VALUES (?, ?, ?)',
                    [$key, $data['value'], $category]
                );
            } else {
                // Update existing setting
                $this->database->execute(
                    'UPDATE settings SET value = ?, updated_at = CURRENT_TIMESTAMP WHERE key = ?',
                    [$data['value'], $key]
                );
            }

            // Return updated setting
            $settings = $this->database->query(
                'SELECT * FROM settings WHERE key = ?',
                [$key]
            );

            $response->getBody()->write(json_encode($settings[0]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Failed to update setting']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function updateMultiple(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);

        if (!is_array($data)) {
            $response->getBody()->write(json_encode(['error' => 'Settings data must be an array']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $this->database->getPdo()->beginTransaction();

            foreach ($data as $key => $value) {
                if (is_array($value) && isset($value['value'])) {
                    $category = $value['category'] ?? 'general';
                    $settingValue = $value['value'];
                } else {
                    $category = 'general';
                    $settingValue = $value;
                }

                // Check if setting exists
                $existing = $this->database->query(
                    'SELECT * FROM settings WHERE key = ?',
                    [$key]
                );

                if (empty($existing)) {
                    // Create new setting
                    $this->database->execute(
                        'INSERT INTO settings (key, value, category) VALUES (?, ?, ?)',
                        [$key, $settingValue, $category]
                    );
                } else {
                    // Update existing setting
                    $this->database->execute(
                        'UPDATE settings SET value = ?, updated_at = CURRENT_TIMESTAMP WHERE key = ?',
                        [$settingValue, $key]
                    );
                }
            }

            $this->database->getPdo()->commit();

            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->database->getPdo()->rollBack();
            $response->getBody()->write(json_encode(['error' => 'Failed to update settings']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function delete(Request $request, Response $response): Response
    {
        $args = $request->getAttribute('route')->getArguments();
        $key = $args['key'] ?? '';

        if (empty($key)) {
            $response->getBody()->write(json_encode(['error' => 'Setting key is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $this->database->execute(
                'DELETE FROM settings WHERE key = ?',
                [$key]
            );

            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Failed to delete setting']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function reset(Request $request, Response $response): Response
    {
        try {
            // Delete all custom settings (keep defaults)
            $this->database->execute('DELETE FROM settings WHERE key NOT LIKE "browser.%" AND key NOT LIKE "privacy.%" AND key NOT LIKE "appearance.%" AND key NOT LIKE "performance.%" AND key NOT LIKE "security.%"');

            // Re-insert default settings
            $defaultSettings = [
                ['browser.default_engine', 'prism', 'engine'],
                ['browser.homepage', 'about:blank', 'general'],
                ['browser.new_tab_page', 'about:blank', 'general'],
                ['privacy.block_trackers', 'true', 'privacy'],
                ['privacy.block_ads', 'true', 'privacy'],
                ['privacy.clear_data_on_exit', 'false', 'privacy'],
                ['appearance.theme', 'dark', 'appearance'],
                ['appearance.font_size', '14', 'appearance'],
                ['performance.cache_size', '100', 'performance'],
                ['security.https_only', 'false', 'security']
            ];

            foreach ($defaultSettings as $setting) {
                $this->database->execute(
                    'INSERT OR REPLACE INTO settings (key, value, category) VALUES (?, ?, ?)',
                    [$setting[0], $setting[1], $setting[2]]
                );
            }

            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Failed to reset settings']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
