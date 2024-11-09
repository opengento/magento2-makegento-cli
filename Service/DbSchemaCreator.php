<?php

namespace Opengento\MakegentoCli\Service;

class DbSchemaCreator
{
    private array $fieldTypes = [
        'blob',
        'boolean',
        'date',
        'datetime',
        'decimal',
        'float',
        'int',
        'json',
        'real',
        'smallint',
        'text',
        'timestamp',
        'varbinary',
        'varchar'
    ];

    public function getFieldTypes(): array
    {
        return $this->fieldTypes;
    }
}
