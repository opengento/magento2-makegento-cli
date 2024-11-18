<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Utils;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Filesystem;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Module\ModuleListInterface;
use Symfony\Component\Console\Helper\HelperInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestionFactory;
use Symfony\Component\Console\Question\QuestionFactory;

/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
class ConsoleModuleSelector
{
    public const MODULE_REGISTRATION_PATTERN = 'app/code/*/*/registration.php';
    private $rootDir;

    private array $modulePaths = [];

    public function __construct(
        private readonly Filesystem          $filesystem,
        private readonly ModuleListInterface $moduleList,
        private readonly Reader              $moduleReader,
        private readonly QuestionFactory     $questionFactory,
        private readonly ComponentRegistrar $componentRegistrar
    ) {
        $this->rootDir = $this->filesystem->getDirectoryRead(DirectoryList::ROOT);
    }

    public function execute(InputInterface $input, OutputInterface $output, HelperInterface $commandHelper, $includeVendor = false): string
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

        return $commandHelper->ask($input, $output, $question);
    }

    private function getInstalledModules($includeVendor = false): array
    {
        $modules = $this->moduleList->getNames();

        foreach ($modules as $module){
            $dir = $this->moduleReader->getModuleDir(null, $module);

            if (!$includeVendor && !str_contains($dir, 'app/code')) {
                continue;
            }
            $this->modulePaths[] = $module;
        }

        return $this->modulePaths;
    }

    public function getModulePath(string $moduleName): string
    {
        return $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $moduleName);
    }
}
