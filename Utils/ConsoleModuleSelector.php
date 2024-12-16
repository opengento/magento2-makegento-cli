<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Utils;

use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Module\ModuleListInterface;
use Opengento\MakegentoCli\Service\CommandIoProvider;
use Opengento\MakegentoCli\Service\CurrentModule;
use Symfony\Component\Console\Question\QuestionFactory;

/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
class ConsoleModuleSelector
{

    private array $modulePaths = [];

    public function __construct(
        private readonly ModuleListInterface $moduleList,
        private readonly Reader              $moduleReader,
        private readonly QuestionFactory     $questionFactory,
        private readonly CommandIoProvider   $commandIoProvider,
        private readonly CurrentModule       $currentModule
    ) {
    }

    /**
     * @param bool $includeVendor
     * @return string
     * @throws \InvalidArgumentException
     */
    public function execute(bool $includeVendor = false): void
    {
        // Get all the modules
        $modules = $this->getInstalledModules($includeVendor);

        // Ask for which module you want ot generate stuff
        $question = $this->questionFactory->create([
                'question' => '<info>Please enter the name of a module</info> (begin to type for completion)' . PHP_EOL,
                'default' => 1
            ]
        );

        $question->setAutocompleterValues($modules);

        $moduleName = $this->commandIoProvider->getQuestionHelper()->ask(
            $this->commandIoProvider->getInput(),
            $this->commandIoProvider->getOutput(),
            $question
        );

        if (!is_string($moduleName)) {
            throw new \InvalidArgumentException('You did not choose any module');
        }

        $modulePath = $this->getModulePath($moduleName);

        $this->currentModule->setCurrentModule($moduleName, $modulePath);
    }

    /**
     * Get installed modules
     *
     * @param bool $includeVendor
     * @return array
     */
    private function getInstalledModules($includeVendor = false): array
    {
        $modules = $this->moduleList->getNames();

        foreach ($modules as $module){
            $dir = $this->moduleReader->getModuleDir(null, $module);

            if (!$includeVendor && !str_contains($dir, 'app/code')) {
                continue;
            }
            $this->modulePaths[$module] = $dir;
        }

        return array_keys($this->modulePaths);
    }

    /**
     * @param string $moduleName
     * @return string
     * @throws \InvalidArgumentException
     */
    private function getModulePath(string $moduleName): string
    {
        if (!isset($this->modulePaths[$moduleName])) {
            throw new \InvalidArgumentException('The module ' . $moduleName . ' does not exist');
        }
        return $this->modulePaths[$moduleName];
    }
}
