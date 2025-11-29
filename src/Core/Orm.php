<?php

namespace MintyPHP\Core;

use MintyPHP\Core\DB;

/**
 * ORM (Object-Relational Mapping) layer for MintyPHP
 * 
 * Provides a simple interface for CRUD operations on database tables.
 */
class Orm
{
    /**
     * Database instance for executing queries.
     */
    private DB $db;

    /**
     * Constructor for the Orm class.
     * @param DB $db Database instance for executing queries.
     */
    public function __construct(DB $db)
    {
        $this->db = $db;
    }

    /**
     * Inserts a new record into the specified table.
     * 
     * @param string $tableName The name of the table to insert into.
     * @param array<string, int|string|float|null> $object An associative array representing the record to insert, where keys are column names and values are the corresponding values.
     * @return int The ID of the newly inserted record. If no fields are provided, returns 0.
     */
    public function insert(string $tableName, array $object): int
    {
        if (count($object) == 0) {
            return 0;
        }
        $fields = [];
        $qmarks = [];
        $params = [];
        foreach ($object as $key => $value) {
            $fields[] = $key;
            $qmarks[] = '?';
            $params[] = $value;
        }
        $fields = implode('`,`', $fields);
        $qmarks = implode(',', $qmarks);
        $sql = "INSERT INTO `$tableName` (`$fields`) VALUES ($qmarks)";
        return $this->db->insert($sql, $params);
    }

    /**
     * Updates an existing record in the specified table by its ID.
     * 
     * @param string $tableName The name of the table to update.
     * @param array<string, int|string|float|null> $object An associative array representing the record to insert, where keys are column names and values are the corresponding values.
     * @param string|int $id The ID of the record to update.
     * @param string $idField The name of the ID field (default is 'id').
     * @return bool Returns true if the update was successful, false otherwise.
     */
    public function update(string $tableName, array $object, string|int $id, string $idField = 'id'): bool
    {
        if (isset($object[$idField])) {
            unset($object[$idField]);
        }
        if (count($object) == 0) {
            return false;
        }
        $fields = [];
        $qmarks = [];
        $params = [];
        foreach ($object as $key => $value) {
            if (!in_array(gettype($value), ['integer', 'double', 'string', 'NULL'])) {
                throw new \InvalidArgumentException("Invalid value type for $key: " . gettype($value));
            }
            $fields[] = $key;
            $params[] = $value;
        }
        $fields = implode('` = ?, `', $fields);
        $qmarks = implode(',', $qmarks);
        $sql = "UPDATE `$tableName` SET `$fields` = ? WHERE `$idField` = ?";
        $params[] = $id;
        return $this->db->update($sql, $params) ? true : false;
    }

    /**
     * Selects a record from the specified table by its ID.
     * 
     * @param string $tableName The name of the table to select from.
     * @param string|int $id The ID of the record to select.
     * @param string $idField The name of the ID field (default is 'id').
     * @return array<string, mixed> $object An associative array representing the selected record, where keys are column names and values are the corresponding values. If no record is found, an empty array is returned.
     */
    public function select(string $tableName, string|int $id, string $idField = 'id'): array
    {
        $sql = "SELECT * FROM `$tableName` WHERE `$idField` = ?";
        return $this->db->selectOne($sql, $id)[$tableName] ?? [];
    }

    /**
     * Deletes a record from the specified table by its ID.
     *
     * @param string $tableName The name of the table.
     * @param string|int $id The ID of the record to delete.
     * @param string $idField The name of the ID field (default is 'id').
     * @return bool Returns true if the deletion was successful, false otherwise.
     */
    public function delete(string $tableName, string|int $id, string $idField = 'id'): bool
    {
        $sql = "DELETE FROM `$tableName` WHERE `$idField` = ?";
        return $this->db->delete($sql, $id) ? true : false;
    }
}
