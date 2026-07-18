<?php
declare(strict_types=1);

final class MenuStore
{
    private string $runtimeDirectory;
    private string $menuPath;
    private string $seedPath;
    private string $lockPath;
    private string $backupDirectory;

    public function __construct(string $basePath)
    {
        $this->runtimeDirectory = $basePath . '/runtime';
        $this->menuPath = $this->runtimeDirectory . '/menu.json';
        $this->seedPath = $basePath . '/data/menu.seed.json';
        $this->lockPath = $this->runtimeDirectory . '/menu.lock';
        $this->backupDirectory = $this->runtimeDirectory . '/backups';
        $this->ensureInitialized();
    }

    public function data(): array
    {
        $handle = $this->openLock();

        try {
            if (!flock($handle, LOCK_SH)) {
                throw new RuntimeException('Unable to read the menu right now.');
            }

            return $this->readData($this->menuPath);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function items(?string $type = null, bool $availableOnly = false): array
    {
        $items = $this->data()['items'];

        $items = array_values(array_filter($items, static function (array $item) use ($type, $availableOnly): bool {
            if ($type !== null && ($item['type'] ?? '') !== $type) {
                return false;
            }

            return !$availableOnly || !empty($item['is_available']);
        }));

        usort($items, static function (array $a, array $b): int {
            $categoryOrder = strcmp((string) ($a['category'] ?? ''), (string) ($b['category'] ?? ''));
            $positionOrder = ((int) ($a['display_order'] ?? 0)) <=> ((int) ($b['display_order'] ?? 0));

            return $categoryOrder !== 0 ? $categoryOrder : ($positionOrder !== 0 ? $positionOrder : strcasecmp((string) $a['title'], (string) $b['title']));
        });

        return $items;
    }

    public function find(string $id): ?array
    {
        foreach ($this->data()['items'] as $item) {
            if (($item['id'] ?? '') === $id) {
                return $item;
            }
        }

        return null;
    }

    public function upsert(array $item): void
    {
        $this->mutate(static function (array $data) use ($item): array {
            $found = false;

            foreach ($data['items'] as $index => $existing) {
                if (($existing['id'] ?? '') === $item['id']) {
                    $data['items'][$index] = $item;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $data['items'][] = $item;
            }

            return $data;
        });
    }

    public function delete(string $id): ?array
    {
        $deleted = null;

        $this->mutate(static function (array $data) use ($id, &$deleted): array {
            $data['items'] = array_values(array_filter($data['items'], static function (array $item) use ($id, &$deleted): bool {
                if (($item['id'] ?? '') === $id) {
                    $deleted = $item;
                    return false;
                }

                return true;
            }));

            return $data;
        });

        return $deleted;
    }

    public function rawJson(): string
    {
        $data = $this->data();
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function mutate(callable $callback): void
    {
        $handle = $this->openLock();

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to update the menu right now.');
            }

            $current = $this->readData($this->menuPath);
            $updated = $callback($current);
            $updated['version'] = 1;
            $updated['updated_at'] = gmdate('c');
            $this->validateData($updated);
            $this->createBackup($current);
            $this->atomicWrite($updated);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function ensureInitialized(): void
    {
        $this->ensureDirectory($this->runtimeDirectory, 0750);
        $this->ensureDirectory($this->backupDirectory, 0750);

        if (is_file($this->menuPath)) {
            return;
        }

        if (!is_file($this->seedPath)) {
            throw new RuntimeException('The menu seed file is missing.');
        }

        $handle = $this->openLock();

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to initialize the menu.');
            }

            if (!is_file($this->menuPath)) {
                $seed = $this->readData($this->seedPath);
                $this->atomicWrite($seed);
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function openLock()
    {
        $handle = fopen($this->lockPath, 'c+');

        if ($handle === false) {
            throw new RuntimeException('The menu storage is not writable.');
        }

        @chmod($this->lockPath, 0640);
        return $handle;
    }

    private function readData(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Unable to read menu data.');
        }

        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Menu data is invalid.', 0, $exception);
        }

        if (!is_array($data)) {
            throw new RuntimeException('Menu data is invalid.');
        }

        $this->validateData($data);
        return $data;
    }

    private function validateData(array $data): void
    {
        if (!isset($data['items']) || !is_array($data['items'])) {
            throw new RuntimeException('Menu items are missing.');
        }

        $ids = [];

        foreach ($data['items'] as $item) {
            if (!is_array($item) || empty($item['id']) || empty($item['title'])) {
                throw new RuntimeException('A menu item is invalid.');
            }

            $id = (string) $item['id'];
            if (isset($ids[$id])) {
                throw new RuntimeException('Duplicate menu item identifiers were found.');
            }

            if (category_type((string) ($item['category'] ?? '')) === null) {
                throw new RuntimeException('A menu item has an unknown category.');
            }

            $expectedType = category_type((string) $item['category']);
            if (($item['type'] ?? '') !== $expectedType) {
                throw new RuntimeException('A menu item has an invalid menu type.');
            }

            if (!is_int($item['price'] ?? null) || $item['price'] < 0 || $item['price'] > 100_000_000) {
                throw new RuntimeException('A menu item has an invalid price.');
            }

            foreach (['image', 'thumb'] as $imageField) {
                $path = (string) ($item[$imageField] ?? '');
                $validRoot = str_starts_with($path, 'images/') || str_starts_with($path, 'uploads/menu/');

                if (!$validRoot || str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, "\0")) {
                    throw new RuntimeException('A menu item has an invalid image path.');
                }
            }

            $ids[$id] = true;
        }
    }

    private function atomicWrite(array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
        $temporary = tempnam($this->runtimeDirectory, 'menu-');

        if ($temporary === false || file_put_contents($temporary, $json, LOCK_EX) === false) {
            throw new RuntimeException('Unable to save menu data.');
        }

        @chmod($temporary, 0640);

        if (!rename($temporary, $this->menuPath)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to publish menu data.');
        }
    }

    private function createBackup(array $data): void
    {
        $filename = $this->backupDirectory . '/menu-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;

        if (file_put_contents($filename, $json, LOCK_EX) !== false) {
            @chmod($filename, 0640);
        }

        $backups = glob($this->backupDirectory . '/menu-*.json') ?: [];
        rsort($backups, SORT_STRING);

        foreach (array_slice($backups, 30) as $oldBackup) {
            @unlink($oldBackup);
        }
    }

    private function ensureDirectory(string $path, int $permissions): void
    {
        if (!is_dir($path) && !mkdir($path, $permissions, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to create application storage.');
        }

        @chmod($path, $permissions);
    }
}
