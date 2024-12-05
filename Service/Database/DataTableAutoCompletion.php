<?php

namespace Opengento\MakegentoCli\Service\Database;

use Magento\Framework\App\ResourceConnection;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class DataTableAutoCompletion
{

    private bool $dataTableInitialized = false;

    private bool $tableFieldInitialized = false;

    private array $dataTables = [];

    private array $tableFields = [];

    public function __construct(
        private readonly QuestionHelper $questionHelper,
        private readonly ResourceConnection $resourceConnection,
        private readonly DbSchemaParser $dbSchemaParser
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
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param string $selectedModule
     * @return mixed
     */
    public function tableSelector(InputInterface $input, OutputInterface $output, string $selectedModule): mixed
    {
        $dataTables = $this->dbSchemaParser->getModuleDataTables($selectedModule);

        $tableSelection = new Question('Choose the table: ' . PHP_EOL);
        $tableSelection->setAutocompleterValues(array_keys($dataTables));
        return $this->questionHelper->ask($input, $output, $tableSelection);
    }

    /**
     * Returns all the fields of a table to be able to manage autocomplete for foreign key reference field.
     *
     * @param string $tableName
     * @return array
     */
    public function getTableFields(string $tableName): array
    {
        if (!$this->tableFieldInitialized && !isset($this->tableFields[$tableName])) {
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
}
