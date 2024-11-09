<?php

namespace Opengento\MakegentoCli\Service;

use DirectoryIterator;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;

class ModulesService
{
    private array $modules;
    private array $modulePaths;

    private bool $initialized = false;

    /**
     * @return array
     * @throws DirectoryNotFoundException
     * @throws \Exception
     */
    public function getAppModuleList(): array
    {
        $this->initializeModules();
        return $this->modules;
    }

    public function getModulePaths(): array
    {
        $this->initializeModules();
        return $this->modulePaths;
    }

    public function getModulePath(string $module): string
    {
        $this->initializeModules();
        return $this->modulePaths[$module];
    }

    private function initializeModules()
    {
        if ($this->initialized) {
            return;
        }
        $path = BP . '/app/code';

        if (!is_dir($path)) {
            throw new DirectoryNotFoundException("app/code directory doesn't exist.");
        }

        foreach (new DirectoryIterator($path) as $vendorDir) {
            if ($vendorDir->isDir() && !$vendorDir->isDot()) {
                foreach (new DirectoryIterator($vendorDir->getPathname()) as $moduleDir) {
                    if ($moduleDir->isDir() && !$moduleDir->isDot()) {
                        $this->modulePaths[$vendorDir->getFilename() . "_" . $moduleDir->getFilename()] = $moduleDir->getPathname();
                        $this->modules[] = $vendorDir->getFilename() . "_" . $moduleDir->getFilename();
                    }
                }
            }
        }
        sort($this->modules);
    }
}
