<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Generator;

use Magento\Framework\App\Filesystem\DirectoryList;

/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
class GeneratorModule extends Generator
{

    public function getComposerStandardModuleName(string $moduleVendorName, string $moduleName): string
    {
        return $this->stringTransformationTools->getKebabCase($moduleVendorName)
            . '/'
            . $this->stringTransformationTools->getKebabCase($moduleName);
    }

    /**
     * @return void
     */
    public function generateModule(string $moduleVendorName, string $moduleName): void
    {
        $moduleVendorName = $this->stringTransformationTools
                ->getPascalCase($this->stringTransformationTools->sanitizeString($moduleVendorName));
        $moduleName = $this->stringTransformationTools
                ->getPascalCase($this->stringTransformationTools->sanitizeString($moduleName));
        $fullMagentoStandardNewModuleName = $moduleVendorName . '_' . $moduleName;

        $this->currentModule->setCurrentModule($fullMagentoStandardNewModuleName);

        $explodedPath = [
            DirectoryList::APP,
            DirectoryList::GENERATED_CODE,
            $moduleVendorName,
            $moduleName,
        ];

        $rootPath = $this->filesystem->getDirectoryRead(DirectoryList::ROOT);

        $currentWorkingPath = $rootPath->getAbsolutePath();
        foreach ($explodedPath as $key => $path) {
            $currentWorkingPath .= $path;
            $currentWorkingPath .= ($key < count($explodedPath) - 1) ? '/' : '';
            if (!$rootPath->isDirectory($currentWorkingPath)) {
                $this->ioFile->mkdir($currentWorkingPath, 0755);
            }
        }

        $etcModuleXmlTemplatePath = $this->reader->getModuleDir(null, self::OPENGENTO_MAKEGENTO_CLI)
            . '/Generator/templates/etc/module.xml.tpl';
        $composerJsonTemplatePath = $this->reader->getModuleDir(null, self::OPENGENTO_MAKEGENTO_CLI)
            . '/Generator/templates/composer.json.tpl';
        $regristrationPhpTemplatePath = $this->reader->getModuleDir(null, self::OPENGENTO_MAKEGENTO_CLI)
            . '/Generator/templates/registration.php.tpl';

        // etc/module.xml
        $this->generate(
            $etcModuleXmlTemplatePath,
            ['{{fullMagentoStandardNewModuleName}}'],
            [$fullMagentoStandardNewModuleName],
            $currentWorkingPath . '/' . DirectoryList::CONFIG,
            'module.xml',
            true,
        );

        // composer.json
        $this->generate(
            $composerJsonTemplatePath,
            [
                '{{fullComposerStandardNewModuleName}}',
                '{{vendorName}}',
                '{{moduleName}}',
            ],
            [
                $this->getComposerStandardModuleName($moduleVendorName, $moduleName),
                $moduleVendorName,
                $moduleName,
            ],
            $currentWorkingPath,
            'composer.json',
            true,
        );

        // registration.php
        $this->generate(
            $regristrationPhpTemplatePath,
            ['{{fullMagentoStandardNewModuleName}}'],
            [$fullMagentoStandardNewModuleName],
            $currentWorkingPath,
            'registration.php',
            true,
        );
    }
}
