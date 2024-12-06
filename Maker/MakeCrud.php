<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Maker;

use Magento\Framework\Exception\LocalizedException;
use Opengento\MakegentoCli\Generator\GeneratorCrud;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeCrud extends AbstractMaker
{
    public function __construct(
        private readonly GeneratorCrud $generatorCrud
    )
    {
    }

    /**
     * @throws LocalizedException
     */
    public function generate(
        InputInterface $input,
        OutputInterface $output,
        string $selectedModule,
        string $entityName = ''
    ): void {
        if (empty($entityName)) {
            throw new LocalizedException(__('Entity name is required'));
        }
        $this->generateCrud($input, $output, $selectedModule, $entityName);
    }

    /**
     * @throws LocalizedException
     */
    public function generateCrud(
        InputInterface $input,
        OutputInterface $output,
        string $selectedModule,
        string $entityName,
    ): void {
        $this->generatorCrud->setCurrentModuleName($selectedModule);
        $this->generatorCrud->setEntityName($entityName);

        // Generate route
        $output->writeln('<info>Creating routes.xml</info>');
        $this->generatorCrud->generateRoutes();
        // Generate route
        $output->writeln('<info>Creating menu.xml</info>');
        $this->generatorCrud->generateMenuEntry();
        // Generate acl
        $output->writeln('<info>Creating acl.xml</info>');
        $this->generatorCrud->generateAcl();
        // Generate controller
        $output->writeln('<info>Creating adminhtml controller</info>');
        $this->generatorCrud->generateListingController();
        // Generate layout
        $output->writeln('<info>Creating layout</info>');
        $this->generatorCrud->generateListingLayout();

        // Generate fucking ui components
        $output->writeln('<info>Creating ui-component</info>');
    }
}
