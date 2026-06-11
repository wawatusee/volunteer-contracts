<?php
/**
 * JsonFileStorage
 *
 * Concrete implementation of StorageInterface using one JSON file per record.
 * Layout on disk:
 *   {dataDir}/{collection}/{id}.json
 *
 * Design decisions:
 *  - One file per record → easy to inspect, diff, and back up individual items.
 *  - Atomic writes via a temp-file + rename pattern to avoid corrupt JSON on crash.
 *  - All ids are UUIDs (v4) generated internally.
 *  - created_at / updated_at are managed transparently here, not in business logic.
 *
 * To migrate to a database, create a new class (e.g. PdoStorage) that
 * implements StorageInterface and swap it in the DI container (bootstrap.php).
 *
 * @package VolunteerContracts\Storage
 */
class JsonFileStorage implements StorageInterface
{
    /** @var string Absolute path to the data root directory */
    private string $dataDir;

    /**
     * @param string $dataDir  Absolute path where collection sub-folders will live
     * @throws InvalidArgumentException If the directory is not writable
     */
    public function __construct(string $dataDir)
    {
        $this->dataDir = rtrim($dataDir, '/\\');

        if (!is_dir($this->dataDir) && !mkdir($this->dataDir, 0755, true)) {
            throw new \RuntimeException("Cannot create data directory: {$this->dataDir}");
        }
    }

    // -------------------------------------------------------------------------
    // StorageInterface implementation
    // -------------------------------------------------------------------------

    /** {@inheritdoc} */
    public function save(string $collection, array $data): array
    {
        $this->ensureCollection($collection);

        $isNew = empty($data['id']);

        if ($isNew) {
            $data['id']         = $this->generateId();
            $data['created_at'] = date('c');
        }

        $data['updated_at'] = date('c');

        $path = $this->recordPath($collection, $data['id']);
        $this->writeJson($path, $data);

        return $data;
    }

    /** {@inheritdoc} */
    public function find(string $collection, string $id): ?array
    {
        $this->guardId($id);
        $path = $this->recordPath($collection, $id);

        if (!is_file($path)) {
            return null;
        }

        return $this->readJson($path);
    }

    /** {@inheritdoc} */
    public function findAll(string $collection, ?callable $filter = null): array
    {
        $dir = $this->collectionDir($collection);

        if (!is_dir($dir)) {
            return [];
        }

        $results = [];

        foreach (glob("{$dir}/*.json") as $file) {
            $record = $this->readJson($file);
            if ($record === null) {
                continue; // skip corrupt files
            }
            if ($filter === null || $filter($record)) {
                $results[] = $record;
            }
        }

        // Sort by created_at descending so newest items appear first
        usort($results, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        return $results;
    }

    /** {@inheritdoc} */
    public function delete(string $collection, string $id): bool
    {
        $this->guardId($id);
        $path = $this->recordPath($collection, $id);

        if (!is_file($path)) {
            return false;
        }

        return unlink($path);
    }

    /** {@inheritdoc} */
    public function exists(string $collection, string $id): bool
    {
        $this->guardId($id);
        return is_file($this->recordPath($collection, $id));
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Make sure the collection subdirectory exists.
     */
    private function ensureCollection(string $collection): void
    {
        $dir = $this->collectionDir($collection);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new \RuntimeException("Cannot create collection directory: {$dir}");
        }
    }

    /**
     * Build the absolute path to a collection directory.
     */
    private function collectionDir(string $collection): string
    {
        // Guard against path traversal (e.g. "../../etc")
        $safe = preg_replace('/[^a-z0-9_\-]/i', '', $collection);
        if ($safe === '') {
            throw new \InvalidArgumentException("Invalid collection name: {$collection}");
        }
        return "{$this->dataDir}/{$safe}";
    }

    /**
     * Build the absolute path to a single record file.
     */
    private function recordPath(string $collection, string $id): string
    {
        return $this->collectionDir($collection) . '/' . $this->sanitizeId($id) . '.json';
    }

    /**
     * Write data to a JSON file using an atomic temp-file+rename strategy.
     *
     * @throws RuntimeException on failure
     */
    private function writeJson(string $path, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $tmp = $path . '.tmp.' . uniqid('', true);

        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            throw new \RuntimeException("Failed to write temporary file: {$tmp}");
        }

        if (!rename($tmp, $path)) {
            unlink($tmp);
            throw new \RuntimeException("Failed to rename temp file to: {$path}");
        }
    }

    /**
     * Read and decode a JSON file.
     *
     * @return array|null  Decoded array, or null on decode error
     */
    private function readJson(string $path): ?array
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        try {
            return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            // Log corrupt file but don't crash the whole list
            error_log("Corrupt JSON file [{$path}]: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate a UUID v4.
     */
    private function generateId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); // version 4
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); // variant RFC 4122

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * Strip unsafe characters from an id before using it as a filename.
     */
    private function sanitizeId(string $id): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9\-_]/', '', $id);
        if (strlen($safe) < 4) {
            throw new \InvalidArgumentException("Invalid record id: {$id}");
        }
        return $safe;
    }

    /**
     * Throw if the id looks obviously malicious.
     */
    private function guardId(string $id): void
    {
        if (preg_match('/[\/\\\\.:]/', $id)) {
            throw new \InvalidArgumentException("Potentially unsafe id: {$id}");
        }
    }
}
