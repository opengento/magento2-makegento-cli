<?php

namespace Opengento\MakegentoCli\Service;

class DbSchemaService
{
    private array $fieldTypes = [
        'int',
        'smallint',
        'varchar',
        'boolean',
        'date',
        'datetime',
        'timestamp',
        'float',
        'blob',
        'decimal',
        'json',
        'real',
        'text',
        'varbinary'
    ];

    public function getFieldTypes(): array
    {
        return $this->fieldTypes;
    }

    public function moduleHasDbSchema(string $modulePath): bool
    {
        $modulePath .= '/etc/db_schema.xml';
        return file_exists($modulePath);
    }

    public function parseDbSchema(string $modulePath): array
    {
        $modulePath .= '/etc/db_schema.xml';
        $xml = simplexml_load_file($modulePath);
        /**
         * let's try to parse the xml file to find table names, their fields and attributes of the fields
         */
        $tables = [];
        foreach ($xml->table as $table) {
            $tableName = (string) $table['name'];
            $columns = [];
            foreach ($table->column as $column) {
                $columnName = (string) $column['name'];
                $attributes = [];
                foreach ($column->attributes() as $attrName => $attrValue) {
                    $attributes[$attrName] = (string) $attrValue;
                }
                $columns[$columnName] = $attributes;
            }
            $tables[$tableName] = $columns;
        }
        return $tables;
    }

    public function createDbSchema(string $modulePath, array $dataTables): void
    {
        $modulePath .= '/etc/db_schema.xml';
        $xml = new \SimpleXMLElement('<schema xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:Setup/Declaration/Schema/etc/schema.xsd"></schema>');
        foreach ($dataTables as $tableName => $tableFields) {
            $table = $xml->addChild('table');
            $table->addAttribute('name', $tableName);
            foreach ($tableFields as $fieldName => $fieldAttributes) {
                $column = $table->addChild('column');
                $column->addAttribute('name', $fieldName);
                foreach ($fieldAttributes as $attrName => $attrValue) {
                    $column->addAttribute($attrName, $attrValue);
                }
            }
        }
        $xml->asXML($modulePath);
    }
}
