<?php

namespace MintyPHP;

class Orm
{
    /**
     * @var array<string, ?string> $object
     */
    public static function insert(string $tableName, array $object): int
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
        return DB::insert($sql, $params);
    }

    /**
     * @param array<string, ?string> $object
     */
    public static function update(string $tableName, array $object, string|int $id, string $idField = 'id'): int
    {
        if (isset($object[$idField])) {
            unset($object[$idField]);
        }
        if (count($object) == 0) {
            return 0;
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
        var_dump([$sql, $params]);
        return DB::update($sql, $params);
    }

    /**
     * @return array<string, ?string> $object
     */
    public static function select(string $tableName, string|int $id, string $idField = 'id'): array
    {
        $sql = "SELECT * FROM `$tableName` WHERE `$idField` = ?";
        return DB::selectOne($sql, [$id])[$tableName] ?? [];
    }
}
