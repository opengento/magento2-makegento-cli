<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Generator;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Module\Dir\Reader;
use Opengento\MakegentoCli\Exception\InvalidArrayException;
use Opengento\MakegentoCli\Utils\StringTransformationTools;
use phpseclib3\Exception\FileNotFoundException;

/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
abstract class Generator
{
    protected const TEMPLATE_EXTENSION = '.tpl';
    protected const XML_EXTENSION = '.xml';

    protected const OPENGENTO_MAKEGENTO_CLI = 'Opengento_MakegentoCli';

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
    ): void {
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
            $newFilePath = $this->reader->getModuleDir(null, $this->getCurrentModuleName())
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
    }

    /**
     * @throws InvalidArrayException
     */
    protected function generateWithInstructions(
        string $templatePath,
        array $data,
        string $newFilePath
    ): void {
        $template = $this->ioFile->read($templatePath);
        if (!$template) {
            throw new LocalizedException(__("Template file not found: %1", $templatePath));
        }
        $newFileContent = $this->getForeachContent($template, $data);
        if (!$this->ioFile->fileExists($newFilePath, false)) {
            $this->ioFile->mkdir($newFilePath, 0755);
        }
        $this->ioFile->write($newFilePath, $newFileContent);
    }

    /**
     * @throws InvalidArrayException
     */
    protected function getForeachContent(string $template, array $data): string
    {
        $replaceContent = '';
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                /**
                 * If the value is an array, we look for the content between the "foreach" and "endforeach" instructions
                 * OR we look for the content of the {% attr $key %} instruction
                 */
                $instruction = '{% foreach ' . $key . ' %}';
                if (str_contains($template, $instruction)) {
                    echo 'getting foreach content : ' . $key . PHP_EOL;
                    /**
                     * Gets the content between the "foreach" and "endforeach" instructions
                     */
                    preg_match('/{% foreach ' . $key . ' %}(.*?){% endforeach ' . $key . ' %}/s', $template, $matches);
                    $foreachContent = $matches[1];
                    $replaceContent .= $this->getForeachContent($foreachContent, $value);
                } elseif (str_contains($template, '{% attr ' . $key . ' %}')) {
                    echo 'getting attr content : ' . $key . PHP_EOL;
                    $attributes = '';
                    foreach ($value as $attrKey => $attrValue) {
                        $attributes .= $attrKey . '="' . $attrValue . '" ';
                    }
                    $replaceContent = str_replace('{% attr ' . $key . ' %}', $attributes, $template);
                } else {
                    echo 'no content found for : ' . $key . PHP_EOL;
                    $replaceContent .= $this->getForeachContent($template, $value);
                }
                // If none of the above conditions are met, we skip this entry
            } elseif (is_string($value)) {
                echo 'replacing key : ' . $key . ' with value : ' . $value . PHP_EOL;
                echo 'template : ' . $template . PHP_EOL;
                $replaceContent = str_replace('{{' . $key . '}}', $value, $template);
                echo 'replaceContent : ' . $replaceContent . PHP_EOL;
            } elseif (is_bool($value)) {
                echo 'value is boolean : ' . $value . PHP_EOL;
                /**
                 * If the value is true, we add the content of the {% if $key %} instruction
                 */
                if ($value) {
                    $replaceContent = preg_replace(
                        '/{% if ' . $key . ' %}(.*?){% endif ' . $key . ' %}/s',
                        '$1',
                        $template
                    );
                } else {
                    $replaceContent = preg_replace(
                        '/{% if ' . $key . ' %}(.*?){% endif ' . $key . ' %}/s',
                        '',
                        $template
                    );
                }
            }
        }
        echo 'replaceContent : ' . $replaceContent . PHP_EOL;
        return $replaceContent;
    }

    /**
     * Cleans the string from remaining placeholders and instructions
     */
    protected function cleanString(string $string): string
    {
        $string = preg_replace('/{{.*?}}/', '', $string);
        return preg_replace('/{%.*?%}/', '', $string);
    }
}
