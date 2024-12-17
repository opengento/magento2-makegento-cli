<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Generator;

use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader;
use Opengento\MakegentoCli\Exception\ExistingClassException;
use Opengento\MakegentoCli\Service\CurrentModule;
use Opengento\MakegentoCli\Utils\StringTransformationTools;

/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
class GeneratorCrud extends Generator
{
    private const ROUTES_XML = 'routes.xml';
    private const MENU_XML = 'menu.xml';
    private const ACL_XML = 'acl.xml';
    private const LISTING = 'listing';

    /** @var null|string $entityName */
    private ?string $entityName = null;

    public function __construct(
        File $ioFile,
        Filesystem $filesystem,
        Reader $reader,
        StringTransformationTools $stringTransformationTools,
        CurrentModule $currentModule
    )
    {
        parent::__construct($ioFile, $filesystem, $reader, $stringTransformationTools, $currentModule);
    }

    public function setEntityName(string $entityName): self
    {
        $this->entityName = $this->stringTransformationTools->getPascalCase($entityName);
        return $this;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    /**
     * @return string
     * @throws ExistingClassException
     * @throws LocalizedException
     */
    public function generateRoutes(): string
    {
        $moduleNameSnakeCase = $this->currentModule->getModuleNameSnakeCase();

        if ($this->ioFile->fileExists(Dir::MODULE_ETC_DIR . '/' . Area::AREA_ADMINHTML . '/' . self::ROUTES_XML)) {
            throw new ExistingClassException("Routes already exists", Dir::MODULE_ETC_DIR . '/' . Area::AREA_ADMINHTML . '/' . self::ROUTES_XML);
        }

        $templatePath = $this->reader->getModuleDir(null, self::OPENGENTO_MAKEGENTO_CLI)
            . '/Generator/templates/etc/adminhtml/routes.xml.tpl';

        $fieldsToUpdate = ['{{id}}', '{{frontName}}', '{{name}}'];
        $fieldsReplacement = [$moduleNameSnakeCase, $moduleNameSnakeCase, $this->currentModule->getModuleName()];

        $this->generate(
            $templatePath,
            $fieldsToUpdate,
            $fieldsReplacement,
            Dir::MODULE_ETC_DIR . '/' . Area::AREA_ADMINHTML,
            self::ROUTES_XML,
        );
        return $moduleNameSnakeCase;
    }

    /**
     * Generate menu.xml file
     *
     * @return void
     * @throws LocalizedException
     */
    public function generateMenuEntry(string $route, string $listingControllerRoute): void
    {
        $templatePath = $this->reader->getModuleDir(null, self::OPENGENTO_MAKEGENTO_CLI)
            . '/Generator/templates/etc/adminhtml/menu.xml.tpl';

        list($controller, $action) = explode('/', $listingControllerRoute);

        $fieldsToUpdate = [
            '{{module_name}}',
            '{{menuEntryTitle}}',
            '{{order}}',
            '{{frontName}}',
            '{{controller}}',
            '{{action}}'
        ];
        $fieldsReplacement = [
            $this->currentModule->getModuleName(),
            str_replace('_', ' ', $this->currentModule->getModuleName()),
            '42',
            $route,
            $controller,
            $action
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
     * @return string
     * @throws LocalizedException
     */
    public function generateListingLayout(): string
    {
        $templatePath = $this->reader->getModuleDir(null, self::OPENGENTO_MAKEGENTO_CLI)
            . '/Generator/templates/view/adminhtml/layout/listing.xml.tpl';

        $layoutName = $this->currentModule->getModuleNameSnakeCase()
            . '_'
            . strtolower($this->getEntityName()) . '_' . self::LISTING;

        $layoutUiComponentName = strtolower($this->currentModule->getModuleNameWithoutVendor())
            . '_' . strtolower($this->getEntityName()) . '_' . self::LISTING;

        $layoutFileName = $layoutName . self::XML_EXTENSION;

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
        return $layoutUiComponentName;
    }

    /**
     * Generate ACL file
     *
     * @return void
     * @throws LocalizedException
     */
    public function generateAcl(): void
    {
        $templatePath = $this->reader->getModuleDir(null, self::OPENGENTO_MAKEGENTO_CLI)
            . '/Generator/templates/etc/acl.xml.tpl';

        $fieldsToUpdate = [
            '{{module_name}}',
            '{{aclTitleView}}',
            '{{aclTitleManage}}',
        ];
        $fieldsReplacement = [
            $this->currentModule->getModuleName(),
            str_replace('_', ' ', $this->currentModule->getModuleName()) . ' View',
            str_replace('_', ' ', $this->currentModule->getModuleName()) . ' Manage',
        ];

        $this->generate(
            $templatePath,
            $fieldsToUpdate,
            $fieldsReplacement,
            Dir::MODULE_ETC_DIR,
            self::ACL_XML,
        );
    }
}
