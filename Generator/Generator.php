<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Generator;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Module\Dir\Reader;
use Opengento\MakegentoCli\Service\CurrentModule;
use Opengento\MakegentoCli\Utils\StringTransformationTools;

/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
abstract class Generator
{
    protected const TEMPLATE_EXTENSION = '.tpl';
    protected const XML_EXTENSION = '.xml';

    public const OPENGENTO_MAKEGENTO_CLI = 'Opengento_MakegentoCli';

    /**
     * Constructor
     *
     * @param File $ioFile
     * @param Reader $reader
     */
    public function __construct(
        protected readonly File $ioFile,
        protected readonly Filesystem $filesystem,
        protected readonly Reader $reader,
        protected readonly StringTransformationTools $stringTransformationTools,
        protected readonly CurrentModule $currentModule
    ) {
    }

    /**
     * Generate
     *
     * @param string $templatePath
     * @param array $fieldsToUpdate
     * @param array $fieldsReplacement
     * @param string $newFilePathFromModuleDirectory
     * @param string $fileName
     * @return void
     * @throws LocalizedException
     */
    protected function generate(
        string $templatePath,
        array $fieldsToUpdate,
        array $fieldsReplacement,
        string $newFilePathFromModuleDirectory,
        string $fileName,
        bool $isNewModule = false,
    ): string {
        // Get template and replace content with actuel data
        try {
            $template = $this->ioFile->read($templatePath);

            $newFileContent = str_replace(
                $fieldsToUpdate,
                $fieldsReplacement,
                $template
            );
        } catch (\Exception $e) {
            throw new LocalizedException(__("Template file not found: %1", $e->getMessage()));
        }

        $newFilePath = $newFilePathFromModuleDirectory;
        if (!$isNewModule) {
            $newFilePath = $this->reader->getModuleDir(null, $this->currentModule->getModuleName())
                . '/' . $newFilePathFromModuleDirectory;
        }

        try {
            $newFilePathWithName = $newFilePath . '/' . $fileName;

            if (!$this->ioFile->fileExists($newFilePathWithName, false)) {
                $this->ioFile->mkdir($newFilePath, 0755);
            }

            $this->ioFile->write($newFilePathWithName, $newFileContent);
        } catch (\Exception $e) {
            throw new LocalizedException(__("Error while attempting to create file: %1", $e->getMessage()));
        }
        return $newFilePathWithName;
    }
}
