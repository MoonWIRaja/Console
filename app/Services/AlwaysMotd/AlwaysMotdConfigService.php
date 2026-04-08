<?php

namespace Pterodactyl\Services\AlwaysMotd;

use RuntimeException;
use Illuminate\Http\UploadedFile;
use Symfony\Component\Yaml\Yaml;

class AlwaysMotdConfigService
{
    public function getViewData(): array
    {
        $sourcePath = $this->getSourceConfigPath();
        $config = $this->load();
        $iconPath = $this->resolveConfiguredPath(
            (string) data_get($config, 'live.server-icon', './server-icon.png'),
            $sourcePath
        );

        return [
            'config' => $config,
            'meta' => [
                'runtime_config_path' => $this->getRuntimeConfigPath(),
                'runtime_icon_path' => $this->getRuntimeIconPath(),
                'source_config_path' => $sourcePath,
                'icon_exists' => is_file($iconPath),
                'icon_data_uri' => is_file($iconPath)
                    ? 'data:image/png;base64,' . base64_encode(file_get_contents($iconPath))
                    : null,
                'exclude_eggs_input' => implode(', ', data_get($config, 'excludeEggs', [])),
                'detection_nest_names_input' => implode(', ', data_get($config, 'detection.nestNames', [])),
                'detection_nest_ids_input' => implode(', ', data_get($config, 'detection.nestIds', [])),
                'detection_egg_names_input' => implode(', ', data_get($config, 'detection.eggNames', [])),
                'detection_egg_ids_input' => implode(', ', data_get($config, 'detection.eggIds', [])),
            ],
        ];
    }

    public function load(): array
    {
        $defaults = $this->loadYaml(base_path('services/always-motd/config.example.yml'));
        $current = $this->loadYaml($this->getSourceConfigPath());
        $config = array_replace_recursive($defaults, $current);

        $config['detection']['nestNames'] = $this->normalizeStringList(
            data_get($config, 'detection.nestNames', ['Minecraft'])
        );
        $config['detection']['nestIds'] = $this->normalizeIntegerList(
            data_get($config, 'detection.nestIds', [])
        );
        $config['detection']['eggNames'] = $this->normalizeStringList(
            data_get($config, 'detection.eggNames', [])
        );
        $config['detection']['eggIds'] = $this->normalizeIntegerList(
            data_get($config, 'detection.eggIds', [])
        );
        $config['excludeEggs'] = $this->normalizeIntegerList(data_get($config, 'excludeEggs', []));

        $config['live']['enabled'] = (bool) data_get($config, 'live.enabled', true);
        $config['live']['syncServerIcon'] = (bool) data_get($config, 'live.syncServerIcon', true);
        $config['live']['runningDescription'] = (string) data_get($config, 'live.runningDescription', '');
        $config['live']['server-icon'] = (string) data_get($config, 'live.server-icon', './server-icon.png');

        return $config;
    }

    public function save(array $input, ?UploadedFile $icon = null): void
    {
        $config = [
            'detection' => [
                'nestNames' => $this->parseStringCsv((string) data_get($input, 'detection.nestNamesInput', '')),
                'nestIds' => $this->parseIntegerCsv((string) data_get($input, 'detection.nestIdsInput', '')),
                'eggNames' => $this->parseStringCsv((string) data_get($input, 'detection.eggNamesInput', '')),
                'eggIds' => $this->parseIntegerCsv((string) data_get($input, 'detection.eggIdsInput', '')),
            ],
            'excludeEggs' => $this->parseIntegerCsv((string) data_get($input, 'excludeEggsInput', '')),
            'live' => [
                'enabled' => filter_var(data_get($input, 'live.enabled', true), FILTER_VALIDATE_BOOLEAN),
                'syncServerIcon' => filter_var(data_get($input, 'live.syncServerIcon', true), FILTER_VALIDATE_BOOLEAN),
                'runningDescription' => rtrim((string) data_get($input, 'live.runningDescription', '')),
                'server-icon' => './server-icon.png',
            ],
        ];

        $this->ensureRuntimeDirectory();

        if ($icon instanceof UploadedFile) {
            $this->writeIconFromPath($icon->getRealPath());
        } elseif (filter_var(data_get($input, 'motd.sync_panel_logo', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->syncPanelLogoToIcon();
        }

        $yaml = Yaml::dump(
            $config,
            10,
            2,
            Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE
        );

        if (file_put_contents($this->getRuntimeConfigPath(), $yaml . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write the Minecraft MOTD config file.');
        }
    }

    public function getRuntimeConfigPath(): string
    {
        return storage_path('app/always-motd/config.yml');
    }

    public function getRuntimeIconPath(): string
    {
        return storage_path('app/always-motd/server-icon.png');
    }

    public function getLegacyConfigPath(): string
    {
        return base_path('services/always-motd/config.yml');
    }

    public function getLegacyIconPath(): string
    {
        return base_path('services/always-motd/server-icon.png');
    }

    public function getSourceConfigPath(): string
    {
        if (is_file($this->getRuntimeConfigPath())) {
            return $this->getRuntimeConfigPath();
        }

        if (is_file($this->getLegacyConfigPath())) {
            return $this->getLegacyConfigPath();
        }

        return base_path('services/always-motd/config.example.yml');
    }

    private function ensureRuntimeDirectory(): void
    {
        $directory = dirname($this->getRuntimeConfigPath());
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the Minecraft MOTD runtime directory.');
        }
    }

    private function loadYaml(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $parsed = Yaml::parseFile($path);

        return is_array($parsed) ? $parsed : [];
    }

    private function normalizeStringList(mixed $value): array
    {
        return array_values(array_filter(array_map(
            static fn ($item) => trim((string) $item),
            is_array($value) ? $value : []
        )));
    }

    private function normalizeIntegerList(mixed $value): array
    {
        return array_values(array_filter(array_map(
            static fn ($item) => is_numeric($item) ? (int) $item : null,
            is_array($value) ? $value : []
        ), static fn ($item) => !is_null($item)));
    }

    private function parseStringCsv(string $value): array
    {
        return array_values(array_filter(array_map(
            static fn ($item) => trim($item),
            preg_split('/\s*,\s*/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: []
        )));
    }

    private function parseIntegerCsv(string $value): array
    {
        return array_values(array_map(
            'intval',
            preg_split('/\s*,\s*/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: []
        ));
    }

    private function resolveConfiguredPath(string $filePath, string $configPath): string
    {
        if ($filePath === '') {
            return $this->getRuntimeIconPath();
        }

        if (str_starts_with($filePath, DIRECTORY_SEPARATOR)) {
            return $filePath;
        }

        return dirname($configPath) . DIRECTORY_SEPARATOR . $filePath;
    }

    private function syncPanelLogoToIcon(): void
    {
        $logoPath = $this->resolvePanelLogoPath();
        $this->writeIconFromPath($logoPath);
    }

    private function resolvePanelLogoPath(): string
    {
        $logo = trim((string) config('app.logo', ''));
        if ($logo === '') {
            throw new RuntimeException('No panel logo is currently configured.');
        }

        if (str_starts_with($logo, 'storage/')) {
            $storagePath = storage_path('app/public/' . ltrim(substr($logo, strlen('storage/')), '/'));
            if (is_file($storagePath)) {
                return $storagePath;
            }
        }

        $publicPath = public_path(ltrim($logo, '/'));
        if (is_file($publicPath)) {
            return $publicPath;
        }

        throw new RuntimeException('The current panel logo file could not be found on disk.');
    }

    private function writeIconFromPath(string $path): void
    {
        if (!is_file($path)) {
            throw new RuntimeException('The selected icon file could not be found on disk.');
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Unable to read the selected icon file.');
        }

        if (file_put_contents($this->getRuntimeIconPath(), $contents, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write the Minecraft MOTD icon file.');
        }
    }
}
