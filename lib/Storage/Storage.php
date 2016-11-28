<?php

namespace Phpactor\Storage;

use Phactor\Knowledge\Reflection\ClassHierarchy;
use Phactor\Knowledge\Reflection\ClassReflection;
use BetterReflection\Reflection\ReflectionClass;
use Doctrine\DBAL\Connection;

class Storage
{
    private $connection;
    private $queue = [];

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function persistClass(ReflectionClass $classReflection)
    {
        do {
            $this->queue[$classReflection->getName()] = $classReflection;
        } while ($classReflection = $classReflection->getParentClass());
    }

    public function flush()
    {
        $this->connection->beginTransaction();

        foreach ($this->queue as $reflection) {
            $this->insertOrReplace('classes', [
                'namespace' => $reflection->getNamespaceName(), 
                'name' => $reflection->getShortName(),
                'file' => $reflection->getFileName(),
                'doc' => $reflection->getDocComment()
            ], [ 'namespace' , 'name' ]);
        }

        $this->connection->commit();
        $this->queue = [];
    }

    private function insertOrReplace($tableName, $data, array $keys)
    {
        $values = [];
        $criterias = [];
        foreach ($keys as $key) {
            $values[$key] = $data[$key];
            $criterias[] = $key . ' = :' . $key;
        }

        $stmt = $this->connection->prepare(
            'SELECT id FROM ' . $tableName . ' WHERE ' . implode(' AND ', $criterias)
        );

        foreach ($values as $key => $value) {
            $stmt->bindParam($key, $value);
        }

        $result = $stmt->execute($values);
        $id = $stmt->fetchColumn(0);

        if ($id) {
            return $id;
        }

        $this->connection->insert($tableName, $data);

        return $this->connection->lastInsertId();
    }
}
