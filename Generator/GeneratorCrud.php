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
class GeneratorCrud extends Generator
{
    private const ROUTES_XML = 'routes.xml';
    private const MENU_XML = 'menu.xml';
    private const ACL_XML = 'acl.xml';
    private const LISTING = 'listing';

    /** @var null|string $currentModule */
    private ?string $currentModule = null;

    /** @var null|string $moduleNameSnakeCase */
    private ?string $moduleNameSnakeCase = null;

    /** @var null|string $entityName */
    private ?string $entityName = null;

    public function setCurrentModuleName(string $currentModule): self
    {
        $this->currentModule = $currentModule;
        $this->moduleNameSnakeCase = $this->stringTransformationTools->getSnakeCase(explode('_', $currentModule)[1]);
        return $this;
    }

    public function getCurrentModuleName(): string
    {
        return $this->currentModule;
    }

    public function getModuleName(): string
    {
        return $this->getCurrentModuleName();
    }

    public function getModuleNameSnakeCase(): string
    {
        return $this->moduleNameSnakeCase;
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
        $controllerPath = mb_ucfirst(
            mb_strtolower($this->stringTransformationTools->getCamelCase($this->getEntityName()), 'UTF-8'),
            'UTF-8'
        );

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
            mb_ucfirst(self::LISTING, 'UTF-8'),
            $this->getModuleName(),
            mb_ucfirst(mb_strtolower($this->getModuleName(), 'UTF-8'), 'UTF-8'),
            str_replace('_', ' ', $this->getModuleName()),
        ];

        $this->generate(
            $templatePath,
            $fieldsToUpdate,
            $fieldsReplacement,
            Dir::MODULE_CONTROLLER_DIR . '/' . mb_ucfirst(Area::AREA_ADMINHTML . '/' . $controllerPath, 'UTF-8'),
            mb_ucfirst(self::LISTING, 'UTF-8') . '.php',
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
                mb_strtolower($this->stringTransformationTools->getCamelCase($this->getEntityName()), 'UTF-8')
                . '/' . self::LISTING,
            $this->getModuleName(),
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
        $templatePath = $this->reader->getModuleDir(null, self::OPENGENTO_MAKEGENTO_CLI)
            . '/Generator/templates/view/adminhtml/layout/listing.xml.tpl';

        $layoutName = $this->stringTransformationTools->getSnakeCase(explode('_', $this->getModuleName())[1])
            . '_'
            . strtolower($this->getEntityName()) . '_' . self::LISTING;

        $layoutUiComponentName = $layoutName;

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
            Dir::MODULE_ETC_DIR,
            self::ACL_XML,
        );
    }
}
