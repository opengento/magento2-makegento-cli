<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Utils;

use Magento\Framework\Exception\FileSystemException;
use Opengento\MakegentoCli\Exception\CommandIoNotInitializedException;
use Opengento\MakegentoCli\Exception\TableDefinitionException;
use Opengento\MakegentoCli\Service\CommandIoProvider;
use Opengento\MakegentoCli\Service\CurrentModule;
use Opengento\MakegentoCli\Service\Database\DataTableAutoCompletion;
use Opengento\MakegentoCli\Service\Database\DbSchemaPath;
use Opengento\MakegentoCli\Service\Php\DefaultClassNameGetter;
use Symfony\Component\Console\Question\Question;

/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
class ConsoleCrudEntitySelector
{

    public function __construct(
        private readonly DbSchemaPath $dbSchemaPath,
        private readonly DataTableAutoCompletion $dataTableAutoCompletion,
        private readonly DefaultClassNameGetter $defaultClassNameGetter,
        private readonly CommandIoProvider $commandIoProvider,
        private readonly CurrentModule           $currentModule
    ) {
    }

    /**
     * @throws FileSystemException
     * @throws TableDefinitionException
     * @throws CommandIoNotInitializedException
     */
    public function execute(
    ): string|int {
        $moduleName = $this->currentModule->getModuleName();
        $path = $this->dbSchemaPath->get($moduleName);

        if (!$path) {
            throw new TableDefinitionException("The module $moduleName does not have a db_schema.xml file. Please run the makegento:db-schema:entity command first.");
        }


        $tableName = $this->dataTableAutoCompletion->tableSelector($moduleName);

        $modelClassName = $this->defaultClassNameGetter->get($tableName, $moduleName);

        return $this->commandIoProvider->getQuestionHelper()->ask(
            $this->commandIoProvider->getInput(),
            $this->commandIoProvider->getOutput(),
            new Question('Enter the class name <info>[default : '. $modelClassName .']</info>: ', $modelClassName)
        );
    }
}
