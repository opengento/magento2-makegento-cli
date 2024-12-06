<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Utils;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\State;
use Magento\Framework\Console\QuestionPerformer\YesNo;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Module\Dir\Reader;
use SimpleXMLElement;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestionFactory;
use Symfony\Component\Console\Question\QuestionFactory;

/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
class ConsoleCrudEntitySelector
{
    private const MODULE_ARGUMENT = 'module';

    public function __construct(
        private readonly File $file,
        private readonly QuestionFactory $questionFactory,
        private readonly ReadFactory $readFactory,
        private readonly DirectoryList $directoryList,
        private readonly YesNo $yesNoQuestionPerformer,
        private readonly StringTransformationTools $stringTransformationTools,
    ) {
    }

    public function execute(
        InputInterface $input,
        OutputInterface $output,
        HelperInterface $commandHelper,
        string $moduleName
    ): string|int {
        // Get all the entites in db_schemas from Module
        $path = $this->getModuleDbSchemaPath($moduleName);

        $fieldList = [];
        if (!$path) {
            $output->writeln("<error> db_schema.xml file not found in module $moduleName. "
                . "Please enter Entity name manually.</error>");
        } else {
            try {
                $fieldList = $this->extractFieldList($path);
                $output->writeln("<info>Clés primaires trouvées :</info>");
                foreach ($fieldList as $primaryKey) {
                    $output->writeln($primaryKey);
                }
            } catch (FileSystemException $e) {
                $output->writeln("<error>Could not read file: {$e->getMessage()}</error>");
            }
        }

        $question = $this->questionFactory->create([
            'question' => '<info>Please enter the name of the entity</info>' . PHP_EOL,
        ]);
        if ($fieldList) {
            $question->setAutocompleterValues($fieldList);
        }

        $fieldName = $commandHelper->ask($input, $output, $question);
        $fieldName = $this->stringTransformationTools->sanitizeString($fieldName);

        if ($this->yesNoQuestionPerformer->execute(['Tranform to Camelcase  [y/n]'], $input, $output)) {
            $fieldName = $this->stringTransformationTools->getCamelCase($fieldName);
        }

        return $fieldName;
    }

    private function getModuleDbSchemaPath(string $moduleName): ?string
    {
        [$vendor, $module] = explode('_', $moduleName);
        $modulePath = "app/code/$vendor/$module/etc/db_schema.xml";

        $read = $this->readFactory->create($this->directoryList->getRoot());
        return $read->isExist($modulePath) ? $read->getAbsolutePath($modulePath) : null;
    }

    private function extractFieldList(string $filePath): array
    {
        $fieldList = [];
        $xml = new SimpleXMLElement($this->file->read($filePath));

        $columns = $xml->xpath("table/column/@name");
        foreach ($columns as $column) {
            $fieldList[] = (string) $column[0];
        }

        return $fieldList;
    }
}
