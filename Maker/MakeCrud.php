<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Maker;

use Opengento\MakegentoCli\Api\ConsoleStyle;
use Opengento\MakegentoCli\Api\DependencyBuilder;
use Opengento\MakegentoCli\Api\InputConfiguration;
use Opengento\MakegentoCli\Generator\GeneratorCrud;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeCrud extends AbstractMaker
{
    public function __construct(
        private readonly GeneratorCrud $generatorCrud,
    ) {
    }

    public function generate(
        InputInterface $input,
        OutputInterface $output,
        string $selectedModule,
    ): void {
    }

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
        $output->writeln('<info>Creating fucking ui-component</info>');
    }
}
