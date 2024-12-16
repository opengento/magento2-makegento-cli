<?php

namespace Opengento\MakegentoCli\Service;

use Opengento\MakegentoCli\Service\Php\NamespaceGetter;
use Opengento\MakegentoCli\Utils\ConsoleModuleSelector;
use Opengento\MakegentoCli\Utils\StringTransformationTools;

/**
 * Class CurrentModule
 *
 * This service is used to store the current module name and path
 *
 * @package Opengento\MakegentoCli\Service
 */
class CurrentModule
{
    private string $moduleName = '';

    private string $modulePath = '';

    private string $moduleNameSnaked = '';

    private string $defaultNamespace = '';


    public function __construct(
        private readonly NamespaceGetter $namespaceGetter,
        private readonly StringTransformationTools $stringTransformationTools
    )
    {
    }

    public function setCurrentModule(string $selectedModule, string $modulePath): void
    {
        $this->moduleName = $selectedModule;
        $this->modulePath = $modulePath;
        $this->moduleNameSnaked = $this->stringTransformationTools->getSnakeCase(explode('_', $selectedModule)[1]);
    }

    public function getModuleName(): string
    {
        return $this->moduleName;
    }

    public function getModuleVendor(): string
    {
        return explode('_', $this->moduleName)[0];
    }

    public function getModuleNameWithoutVendor(): string
    {
        return explode('_', $this->moduleName)[1];
    }

    public function getModulePath(): string
    {
        return $this->modulePath;
    }

    public function getModuleNamespace(string $path = ''): string
    {
        if ($this->defaultNamespace === '') {
            $this->defaultNamespace = $this->namespaceGetter->getNamespaceFromPath($this->modulePath, $this->moduleName);
        }
        return $this->namespaceGetter->getNamespace($this->modulePath, $path, $this->moduleName, $this->defaultNamespace);
    }

    public function getModuleNameSnakeCase(): string
    {
        return $this->moduleNameSnaked;
    }
}
