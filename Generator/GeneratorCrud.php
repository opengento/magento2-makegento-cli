<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Generator;

use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader;

/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
class GeneratorCrud
{
    private const TEMPLATE_EXTENSION = '.tpl';
    private const ROUTES_XML = 'routes.xml';
    private const MENU_XML = 'menu.xml';
    private const ACL_XML = 'acl.xml';
    private const LISTING = 'listing';
    private const OPENGENTO_MAKEGENTO_CLI = 'Opengento_MakegentoCli';

    /** @var null|string $currentModule */
    private ?string $currentModule = null;

    /** @var null|string $moduleNameSnakeCase */
    private ?string $moduleNameSnakeCase = null;

    /** @var null|string $entityName */
    private ?string $entityName = null;

    /**
     * Constructor
     *
     * @param File $ioFile
     * @param Reader $reader
     */
    public function __construct(
        private readonly File $ioFile,
        private readonly Reader $reader,
    ) {
    }

    public function setCurrentModule(string $currentModule): self
    {
        $this->currentModule = $currentModule;
        $this->moduleNameSnakeCase = $this->camelToSnake(explode('_', $currentModule)[1]);
        return $this;
    }

    public function getCurrentModule(): string
    {
        return $this->currentModule;
    }

    public function getModuleName(): string
    {
        return $this->getCurrentModule();
    }

    public function getModuleNameSnakeCase(): string
    {
        return $this->moduleNameSnakeCase;
    }

    public function setEntityName(string $entityName): self
    {
        $this->entityName = mb_ucfirst($entityName);
        return $this;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    /**
     * @return void
     */
    public function generateRoutes(): void
    {
        $moduleNameSnakeCase = $this->getModuleNameSnakeCase();

        $templatePath = $this->reader->getModuleDir(null, self::OPENGENTO_MAKEGENTO_CLI)
            . '/Generator/templates/etc/adminhtml/routes.xml.tpl';

        $fieldsToUpdate = ['{{id}}', '{{frontName}}', '{{name}}'];
        $fieldsReplacement = [$moduleNameSnakeCase, $moduleNameSnakeCase, $this->getModuleName()];

        $this->generate(
            $templatePath,
            $fieldsToUpdate,
            $fieldsReplacement,
            Dir::MODULE_ETC_DIR . '/' . Area::AREA_ADMINHTML,
            self::ROUTES_XML,
        );
    }

    /**
     * Generate Listing Controller
     *
     * @return void
     * @throws LocalizedException
     */
    public function generateListingController(): void
    {
        $moduleNameSnakeCase = $this->getModuleNameSnakeCase();
        $controllerPath = mb_ucfirst($this->getEntityName());

        $templatePath = $this->reader->getModuleDir(null, self::OPENGENTO_MAKEGENTO_CLI)
            . '/Generator/templates/Controller/controller.php.tpl';

        $fieldsToUpdate = [
            '{{vendor\module}}',
            '{{controllerPath}}',
            '{{controllerName}}',
            '{{moduleName}}',
            '{{module_title}}'
        ];
        $fieldsReplacement = [
            str_replace('_', '\\', $this->getModuleName()),
            $controllerPath,
            mb_ucfirst(self::LISTING),
            $this->getModuleName(),
            $this->getModuleName(),
            str_replace('_', ' ', $this->getModuleName()),
        ];

        $this->generate(
            $templatePath,
            $fieldsToUpdate,
            $fieldsReplacement,
            Dir::MODULE_CONTROLLER_DIR . '/' . mb_ucfirst(Area::AREA_ADMINHTML . '/' . $controllerPath),
            mb_ucfirst(self::LISTING) . '.php',
        );
    }

    /**
     * Generate menu.xml file
     *
     * @return void
     * @throws LocalizedException
     */
    public function generateMenuEntry(): void
    {
        $moduleNameSnakeCase = $this->getModuleNameSnakeCase();

        $templatePath = $this->reader->getModuleDir(null, self::OPENGENTO_MAKEGENTO_CLI)
            . '/Generator/templates/etc/adminhtml/menu.xml.tpl';

        $fieldsToUpdate = [
            '{{module_name}}',
            '{{menuEntryTitle}}',
            '{{module_name}}',
            '{{order}}',
            '{{frontName}}',
            '{{controllerName}}',
            '{{module_name}}'
        ];
        $fieldsReplacement = [
            $this->getModuleName(),
            str_replace('_', ' ', $this->getModuleName()),
            $this->getModuleName(),
            '42',
            $moduleNameSnakeCase,
            $this->camelToSnake($this->getEntityName()) . '/' . self::LISTING,
            $this->getModuleName()
        ];

        $this->generate(
            $templatePath,
            $fieldsToUpdate,
            $fieldsReplacement,
            Dir::MODULE_ETC_DIR . '/' . Area::AREA_ADMINHTML,
            self::MENU_XML,
        );
    }

    /**
     * Generate Listing Layout file
     *
     * @return void
     * @throws LocalizedException
     */
    public function generateListingLayout(): void
    {
        $moduleNameSnakeCase = $this->getModuleNameSnakeCase();

        $templatePath = $this->reader->getModuleDir(null, self::OPENGENTO_MAKEGENTO_CLI)
            . '/Generator/templates/view/adminhtml/layout/listing.xml.tpl';

        $layoutUiComponentName = $this->camelToSnake(
            explode('_', $this->getModuleName())[1]
        )
        . '_'
        . $this->camelToSnake($this->getEntityName()) . '_' . self::LISTING;

        $layoutFileName = $this->camelToSnake(
            explode('_', $this->getModuleName())[1]
        )
        . '_' . strtolower($this->getEntityName()) . '_' . 'index.xml';

        $fieldsToUpdate = [
            '{{uiComponentName}}'
        ];
        $fieldsReplacement = [
            $layoutUiComponentName,
        ];

        $this->generate(
            $templatePath,
            $fieldsToUpdate,
            $fieldsReplacement,
            Dir::MODULE_VIEW_DIR . '/' . Area::AREA_ADMINHTML . '/layout',
            $layoutFileName,
        );
    }

    /**
     * Generate ACL file
     *
     * @return void
     * @throws LocalizedException
     */
    public function generateAcl(): void
    {
        $moduleNameSnakeCase = $this->getModuleNameSnakeCase();

        $templatePath = $this->reader->getModuleDir(null, self::OPENGENTO_MAKEGENTO_CLI)
            . '/Generator/templates/etc/acl.xml.tpl';

        $fieldsToUpdate = [
            '{{module_name}}',
            '{{aclTitle}}',
        ];
        $fieldsReplacement = [
            $this->getModuleName(),
            str_replace('_', ' ', $this->getModuleName()) . ' ' . self::LISTING,
        ];

        $this->generate(
            $templatePath,
            $fieldsToUpdate,
            $fieldsReplacement,
            Dir::MODULE_ETC_DIR . '/',
            self::ACL_XML,
        );
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
    private function generate(
        string $templatePath,
        array $fieldsToUpdate,
        array $fieldsReplacement,
        string $newFilePathFromModuleDirectory,
        string $fileName
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

        $newFilePath = $this->reader->getModuleDir(null, $this->getCurrentModule())
            . '/' . $newFilePathFromModuleDirectory;

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
     * Return snake case formatted string to camel case
     *
     * @param string $camelCase
     * @return string
     */
    private function camelToSnake(string $camelCase): string
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
