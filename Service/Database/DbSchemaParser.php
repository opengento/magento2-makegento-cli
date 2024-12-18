<?php

namespace Opengento\MakegentoCli\Service\Database;

use Magento\Framework\Exception\FileSystemException;
use Opengento\MakegentoCli\Exception\TableDefinitionException;

class DbSchemaParser
{
    private array $dataTables;

    public function __construct(
        private readonly DbSchemaPath $dbSchemaPath
    )
    {
    }

    /**
     * Returns the data tables of a module. If the data tables are already parsed, it will return them. In case the module
     * does not have a db_schema.xml file, it will throw a FileSystemException.
     *
     * @param string $selectedModule
     * @return array
     * @throws TableDefinitionException
     */
    public function getModuleDataTables(string $selectedModule): array
    {
        if (!isset($this->dataTables[$selectedModule])) {
            $this->dataTables[$selectedModule] = $this->parseDbSchema($selectedModule);
        }
        return $this->dataTables[$selectedModule];
    }

    /**
     * Parses the db_schema.xml file of a module and returns an array with the tables, fields, constraints and indexes.
     *
     * @param string $selectedModule
     * @return array
     * @throws TableDefinitionException
     */
    private function parseDbSchema(string $selectedModule): array
    {
        $dbSchemaPath = $this->dbSchemaPath->get($selectedModule);
        $xml = simplexml_load_file($dbSchemaPath);
        /**
         * let's try to parse the xml file to find table names, their fields and attributes of the fields
         */
        $tables = [];
        foreach ($xml->table as $table) {
            $primary = null;
            $tableName = (string)$table['name'];
            $tableAttributes = $this->manageAttributes($table);
            $columns = [];
            foreach ($table->column as $column) {
                $columnName = (string)$column['name'];
                $type = (string)$column->attributes('xsi', true)['type'];
                $columns[$columnName] = $this->manageAttributes($column);
                $columns[$columnName]['type'] = $type;
            }
            $domPrimary = $table->xpath('constraint[@xsi:type="primary"]');
            if ($domPrimary) {
                $primary = (string)$domPrimary[0]->column['name'];
            }
            $constraints = $this->parseConstraints($table);

            $indexes = $this->parseIndexes($table);

            $tables[$tableName] =
                [
                    'fields' => $columns,
                    'constraints' => $constraints,
                    'primary' => $primary,
                    'indexes' => $indexes,
                    'table_attr' => $tableAttributes
                ];
        }
        return $tables;
    }

    /**
     * @param \SimpleXMLElement $node
     * @return array
     */
    private function manageAttributes(\SimpleXMLElement $node): array
    {
        $attributes = [];
        foreach ($node->attributes() as $attrName => $attrValue) {
            if ($attrName === 'name') {
                continue;
            }
            $attributes[$attrName] = (string)$attrValue;
        }
        return $attributes;
    }

    /**
     * Parses the constraints of a table.
     *
     * @param \SimpleXMLElement $table
     * @return array
     */
    private function parseConstraints(\SimpleXMLElement $table): array
    {
        if (!isset($table->constraint)) {
            return [];
        }
        $constraints = [];
        foreach ($table->constraint as $constraint) {
            /** @var \SimpleXMLElement $constraint */
            $constraintName = (string)$constraint['name'];
            $type = (string)$constraint->attributes('xsi', true)['type'];
            $constraintAttributes = [
                'type' => $type
            ];
            foreach ($constraint->attributes() as $attrName => $attrValue) {
                if ($attrName === 'referenceId' && (string)$attrValue === 'PRIMARY') {
                    continue 2;
                }
                $constraintAttributes[$attrName] = (string)$attrValue;
            }
            foreach ($constraint->column ?? [] as $column) {
                if (!isset($constraintAttributes['columns'])) {
                    $constraintAttributes['columns'] = [];
                }
                $constraintAttributes['columns'][] = $column;
            }
            $constraints[$constraintName] = $constraintAttributes;
        }
        return $constraints;
    }

    /**
     * @param \SimpleXMLElement $table
     * @return array
     */
    private function parseIndexes(\SimpleXMLElement $table): array
    {
        if (!isset($table->index)) {
            return [];
        }
        $indexes = [];
        foreach ($table->index as $index) {
            $indexName = (string)$index['referenceId'];
            $indexType = (string)$index['indexType'];
            $fields = [];
            foreach ($index->column as $field) {
                $fields[] = (string)$field['name'];
            }
            $indexes[$indexName] = [
                'type' => $indexType,
                'fields' => $fields
            ];
        }
        return $indexes;
    }
}
