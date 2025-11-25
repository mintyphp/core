<?php

namespace MintyPHP;

use MintyPHP\Core\Orm as CoreOrm;

/**
 * Static class for ORM operations using a singleton pattern.
 */
class Orm
{
    /**
     * The ORM instance
     * @var ?CoreOrm
     */
    private static ?CoreOrm $instance = null;

    /**
     * Get the ORM instance
     * @return CoreOrm
     */
    public static function getInstance(): CoreOrm
    {
        return self::$instance ??= new CoreOrm();
    }

    /**
     * Set the ORM instance to use
     * @param CoreOrm $orm
     * @return void
     */
    public static function setInstance(CoreOrm $orm): void
    {
        self::$instance = $orm;
    }

    /**
     * Inserts a new record into the specified table.
     * 
     * @param string $tableName The name of the table to insert into.
     * @param array<string, ?string> $object An associative array representing the record to insert, where keys are column names and values are the corresponding values.
     * @return int The ID of the newly inserted record. If no fields are provided, returns 0.
     */
    public static function insert(string $tableName, array $object): int
    {
        $orm = self::getInstance();
        return $orm->insert($tableName, $object);
    }

    /**
     * Updates an existing record in the specified table by its ID.
     * 
     * @param string $tableName The name of the table to update.
     * @param array<string, ?string> $object An associative array representing the record to insert, where keys are column names and values are the corresponding values.
     * @param string|int $id The ID of the record to update.
     * @param string $idField The name of the ID field (default is 'id').
     * @return bool Returns true if the update was successful, false otherwise.
     */
    public static function update(string $tableName, array $object, string|int $id, string $idField = 'id'): bool
    {
        $orm = self::getInstance();
        return $orm->update($tableName, $object, $id, $idField);
    }

    /**
     * Selects a record from the specified table by its ID.
     * 
     * @param string $tableName The name of the table to select from.
     * @param string|int $id The ID of the record to select.
     * @param string $idField The name of the ID field (default is 'id').
     * @return array<string, mixed> $object An associative array representing the selected record, where keys are column names and values are the corresponding values. If no record is found, an empty array is returned.
     */
    public static function select(string $tableName, string|int $id, string $idField = 'id'): array
    {
        $orm = self::getInstance();
        return $orm->select($tableName, $id, $idField);
    }

    /**
     * Deletes a record from the specified table by its ID.
     *
     * @param string $tableName The name of the table.
     * @param string|int $id The ID of the record to delete.
     * @param string $idField The name of the ID field (default is 'id').
     * @return bool Returns true if the deletion was successful, false otherwise.
     */
    public static function delete(string $tableName, string|int $id, string $idField = 'id'): bool
    {
        $orm = self::getInstance();
        return $orm->delete($tableName, $id, $idField);
    }
}
