<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Generator;

use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\App\Filesystem\DirectoryList;

/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
class GeneratorCrud
{
    private const ROUTES_XML = 'routes.xml';
    private const MENU_XML = 'menu.xml';
    private const OPENGENTO_MAKEGENTO_CLI = 'Opengento_MakegentoCli';

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly File $ioFile,
        private readonly Reader $reader,
    ) {
    }

    /**
     * @param string $moduleName
     *
     * @return void
     */
    public function generateRoutes(string $moduleName): void
    {
        $templatePath = $this->reader->getModuleDir(null, self::OPENGENTO_MAKEGENTO_CLI)
            . '/Generator/templates/etc/adminhtml/routes.tpl.xml';

        try {
            $template = $this->ioFile->read($templatePath);

            $moduleNameCamelCase = $this->camelToSnake(explode('_', $moduleName)[1]);
            $newFileContent = str_replace(
                ['{{id}}', '{{frontName}}', '{{name}}'],
                [$moduleNameCamelCase, $moduleNameCamelCase, $moduleName, ],
                $template
            );
        } catch (\Exception $e) {
            throw new LocalizedException(__("File not found: %1", $e->getMessage()));
        }

        $newFilePath = $this->reader->getModuleDir(Dir::MODULE_ETC_DIR, $moduleName);
        $newFilePath .= '/' . Area::AREA_ADMINHTML;

        try {
            if (!$this->ioFile->fileExists($newFilePath . '/' . self::ROUTES_XML, false)) {
                $this->ioFile->mkdir($newFilePath, 0755);
            }

            $this->ioFile->write($newFilePath . '/' . self::ROUTES_XML, $newFileContent);

        } catch (\Exception $e) {
            throw new LocalizedException(__("Error while attempting to create file: %1", $e->getMessage()));
        }
    }

    /**
     * @param string $moduleName
     *
     * @return void
     */
    public function generateMenuEntry(string $moduleName): void
    {
        $templatePath = $this->reader->getModuleDir(null, self::OPENGENTO_MAKEGENTO_CLI)
            . '/Generator/templates/etc/adminhtml/menu.tpl.xml';

        try {
            $template = $this->ioFile->read($templatePath);

            $controllerName = explode('_', $moduleName)[1];
            $moduleNameCamelCase = $this->camelToSnake(explode('_', $moduleName)[1]);
            $newFileContent = str_replace([
                '{{module_name}}',
                '{{menuEntryTitle}}',
                '{{module_name}}',
                '{{order}}',
                '{{frontName}}',
                '{{controllerName}}',
                '{{module_name}}'
            ],
                [$moduleName, 'title', $moduleName, '123', $moduleNameCamelCase, $moduleNameCamelCase, $moduleName],
                $template
            );
        } catch (\Exception $e) {
            throw new LocalizedException(__("File not found: %1", $e->getMessage()));
        }

        $newFilePath = $this->reader->getModuleDir(Dir::MODULE_ETC_DIR, $moduleName);
        $newFilePath .= '/' . Area::AREA_ADMINHTML;

        try {
            if (!$this->ioFile->fileExists($newFilePath . '/' . self::MENU_XML, false)) {
                $this->ioFile->mkdir($newFilePath, 0755);
            }

            $this->ioFile->write($newFilePath . '/' . self::MENU_XML, $newFileContent);

        } catch (\Exception $e) {
            throw new LocalizedException(__("Error while attempting to create file: %1", $e->getMessage()));
        }
    }

    /**
     * Return snake case formatted string to camel case
     *
     * @param string $camelCase
     * @return string
     */
    private function camelToSnake(string $camelCase)
    {
        $result = '';

        for ($i = 0, $iMax = strlen($camelCase); $i < $iMax; $i++) {
            $char = $camelCase[$i];

            if (ctype_upper($char)) {
                $result .= '_' . strtolower($char);
            } else {
                $result .= $char;
            }
        }

        return ltrim($result, '_');
    }
}
