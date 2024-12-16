<?php

namespace Opengento\MakegentoCli\Service\Database;

use Magento\Framework\App\ResourceConnection;
use Opengento\MakegentoCli\Service\CommandIoProvider;
use Symfony\Component\Console\Question\Question;

class DataTableAutoCompletion
{

    private bool $dataTableInitialized = false;

    private array $dataTables = [];

    private array $tableFields = [];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly DbSchemaParser $dbSchemaParser,
        private readonly CommandIoProvider $commandIoProvider
    )
    {
    }

    /**
     * Returns all the tables in the database to be able to manage autocomplete for foreign key reference table.
     *
     * @return array
     */
    public function getAllTables(): array
    {
        if (!$this->dataTableInitialized) {
            $connection = $this->resourceConnection->getConnection();
            $tables = $connection->getTables();
            foreach ($tables as $table) {
                $this->dataTables[] = $table;
            }
        }
        return $this->dataTables;
    }

    /**
     * @param string $tableName
     * @return void
     */
    public function addDataTable(string $tableName): void
    {
        $this->dataTables[] = $tableName;
    }

    /**
     * Permits to select a table from the data tables of a module.
     *
     * @param string $selectedModule
     * @return mixed
     */
    public function tableSelector(string $selectedModule): mixed
    {
        $dataTables = $this->dbSchemaParser->getModuleDataTables($selectedModule);

        $tableSelection = new Question('Choose the table: ' . PHP_EOL);
        $tableSelection->setAutocompleterValues(array_keys($dataTables));
        $this->commandIoProvider->getOutput()->writeln('<info>Table in db_schema.xml</info>');
        foreach (array_keys($dataTables) as $entity) {
            $this->commandIoProvider->getOutput()->writeln($entity);
        }
        return $this->commandIoProvider->getQuestionHelper()->ask($this->commandIoProvider->getInput(), $this->commandIoProvider->getOutput(), $tableSelection);
    }

    /**
     * Returns all the fields of a table to be able to manage autocomplete for foreign key reference field.
     *
     * @param string $tableName
     * @return array
     */
    public function getTableFields(string $tableName): array
    {
        if (!isset($this->tableFields[$tableName])) {
            $connection = $this->resourceConnection->getConnection();
            $table = $connection->describeTable($tableName);
            $fields = [];
            $primaryKey = '';
            foreach ($table as $name => $field) {
                if ($field['PRIMARY'] === true) {
                    $primaryKey = $name;
                }
                $fields[] = $name;
            }
            $this->addTableFields($tableName, $fields, $primaryKey);
        }
        return $this->tableFields[$tableName];
    }

    /**
     * Adds a table to table fields array
     * @param string $tableName
     * @param array $fields
     * @param string $primaryKey
     */
    public function addTableFields(string $tableName, array $fields, string $primaryKey): void
    {
        $this->tableFields[$tableName] = [
            'fields' => $fields,
            'identity' => $primaryKey
        ];
    }

    public function addFieldToTable(string $tableName, string $fieldName, $isPrimary = false): void
    {
        if (!isset($this->tableFields[$tableName])) {
            $this->tableFields[$tableName] = [
                'fields' => [],
                'identity' => ''
            ];
        }
        $this->tableFields[$tableName]['fields'][] = $fieldName;
        if ($isPrimary) {
            $this->tableFields[$tableName]['identity'] = $fieldName;
        }
    }
}
