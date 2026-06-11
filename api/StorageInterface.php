<?php
/**
 * StorageInterface
 *
 * Abstraction contract for all persistence operations.
 * Implement this interface to swap JSON file storage for a database
 * (MySQL, SQLite, MongoDB…) without touching any other layer.
 *
 * @package VolunteerContracts\Storage
 */
interface StorageInterface
{
    /**
     * Persist a record. Creates if no id, updates otherwise.
     *
     * @param  string $collection  Logical collection name (e.g. "workers", "contracts")
     * @param  array  $data        Associative array of the record's fields
     * @return array               The saved record (with id and timestamps set)
     * @throws RuntimeException    On write failure
     */
    public function save(string $collection, array $data): array;

    /**
     * Retrieve a single record by its id.
     *
     * @param  string $collection
     * @param  string $id
     * @return array|null          The record, or null if not found
     */
    public function find(string $collection, string $id): ?array;

    /**
     * Retrieve all records in a collection, with optional filtering.
     *
     * @param  string        $collection
     * @param  callable|null $filter     fn(array $record): bool — return true to include
     * @return array[]                   List of matching records
     */
    public function findAll(string $collection, ?callable $filter = null): array;

    /**
     * Remove a record by id.
     *
     * @param  string $collection
     * @param  string $id
     * @return bool                True on success, false if record did not exist
     */
    public function delete(string $collection, string $id): bool;

    /**
     * Check whether a record exists.
     *
     * @param  string $collection
     * @param  string $id
     * @return bool
     */
    public function exists(string $collection, string $id): bool;
}
