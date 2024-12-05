<?php

namespace Opengento\MakegentoCli\Maker;

use Opengento\MakegentoCli\Exception\TableDefinitionException;
use Opengento\MakegentoCli\Service\Database\ConstraintDefinition;
use Opengento\MakegentoCli\Service\Database\DataTableAutoCompletion;
use Opengento\MakegentoCli\Service\Database\DbSchemaCreator;
use Opengento\MakegentoCli\Service\Database\DbSchemaParser;
use Opengento\MakegentoCli\Service\Database\Field;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeField extends AbstractMaker
{

    public function __construct(
        private readonly DbSchemaCreator         $dbSchemaCreator,
        private readonly DataTableAutoCompletion $dataTableAutoCompletion,
        private readonly DbSchemaParser          $dbSchemaParser,
        private readonly Field                   $field,
        private readonly ConstraintDefinition    $constraintDefinition,
        protected readonly QuestionHelper        $questionHelper
    )
    {
    }

    /**
     * @throws TableDefinitionException
     */
    public function generate(InputInterface $input, OutputInterface $output, string $selectedModule): void
    {
        $tableName = $this->dataTableAutoCompletion->tableSelector($input, $output, $selectedModule);
        $dataTables = $this->dbSchemaParser->getModuleDataTables($selectedModule);
        $primary = $dataTables[$tableName]['primary'] ?? '';

        $field = $this->field->create($output, $input, $primary, $tableName);
        $existingFields = $dataTables[$tableName]['fields'];
        $dataTables[$tableName]['fields'] = array_merge($existingFields, $field);

        $constraints = $this->constraintDefinition->define($output, $input, $tableName, $dataTables[$tableName]['fields'], $this->questionHelper);
        $existingConstraints = $dataTables[$tableName]['constraints'];
        $dataTables[$tableName]['constraints'] = array_merge($existingConstraints, $constraints);

        $this->dbSchemaCreator->createDbSchema($selectedModule, $dataTables);
    }
}
