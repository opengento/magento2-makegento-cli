<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Maker;

use Magento\Framework\Console\QuestionPerformer\YesNo;
use Magento\Framework\Exception\LocalizedException;
use Opengento\MakegentoCli\Api\MakerInterface;
use Opengento\MakegentoCli\Exception\ExistingClassException;
use Opengento\MakegentoCli\Generator\GeneratorController;
use Opengento\MakegentoCli\Generator\GeneratorCrud;
use Opengento\MakegentoCli\Generator\GeneratorUiComponent;
use Opengento\MakegentoCli\Service\CommandIoProvider;

class MakeCrud implements MakerInterface
{
    public function __construct(
        private readonly GeneratorCrud $generatorCrud,
        private readonly GeneratorController $generatorController,
        private readonly GeneratorUiComponent $generatorUiComponent,
        private readonly YesNo                  $yesNoQuestionPerformer,
        private readonly CommandIoProvider      $commandIoProvider,
    )
    {
    }

    /**
     * @throws LocalizedException
     */
    public function generate(
        string $entityName = '',
    ): void {
        if (empty($entityName)) {
            throw new LocalizedException(__('Entity name is required'));
        }
        $this->generatorCrud->setEntityName($entityName);

        // Generate route
        $this->commandIoProvider->getOutput()->writeln('<info>Creating routes.xml</info>');
        $route = $this->generatorCrud->generateRoutes();
        // Generate route
        $this->commandIoProvider->getOutput()->writeln('<info>Creating menu.xml</info>');
        $this->generatorCrud->generateMenuEntry();
        // Generate acl
        $this->commandIoProvider->getOutput()->writeln('<info>Creating acl.xml</info>');
        $this->generatorCrud->generateAcl();
        // Generate controller
        $this->commandIoProvider->getOutput()->writeln('<info>Creating adminhtml controller</info>');
        $this->generatorController->setEntityName($entityName);
        try {
            $listingController = $this->generatorController->generateListingController();
        } catch (ExistingClassException $e) {
            $listingController = $e->getFilePath();
        }

        // Ask user if he wants to generate a form
        $generateForm = $this->yesNoQuestionPerformer->execute(
            ['Do you want to generate a form for this entity? [y/n]'],
            $this->commandIoProvider->getInput(),
            $this->commandIoProvider->getOutput()
        );
        if ($generateForm) {
            $formControllers = $this->generatorController->generateFormControllers();
        }

        // Generate layout
        $this->commandIoProvider->getOutput()->writeln('<info>Creating layout</info>');
        $listingLayoutUiComponent = $this->generatorCrud->generateListingLayout();

        // Generate ui components
        $this->commandIoProvider->getOutput()->writeln('<info>Creating ui-component</info>');
        $this->generatorUiComponent->generateListing($entityName, $listingLayoutUiComponent, $route);

        $this->commandIoProvider->getOutput()->writeln('<info>Crud for entity ' . $entityName . ' has been created</info>');
    }
}
