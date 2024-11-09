<?php

namespace Opengento\MakegentoCli\Maker;

use Magento\Framework\Console\QuestionPerformer\YesNo;
use Magento\Framework\Filesystem;
use Magento\Framework\Module\Dir\Reader;
use Opengento\MakegentoCli\Api\ConsoleStyle;
use Opengento\MakegentoCli\Api\DependencyBuilder;
use Opengento\MakegentoCli\Api\InputConfiguration;
use Opengento\MakegentoCli\Generator\GeneratorCrud;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeCrud extends AbstractMaker
{
    public function __construct(
        private readonly Reader $reader,
        private readonly Filesystem          $filesystem,
        private readonly YesNo          $yesNoQuestionPerformer,
        private readonly QuestionHelper $questionHelper,
        private readonly GeneratorCrud  $generatorCrud,
    ) {
    }

    public function generate(InputInterface $input, OutputInterface $output, string $selectedModule): void
    {
        // générer route
        $output->writeln('<info>Creating routes.xml</info>');
        $this->generatorCrud->generateRoutes($selectedModule);
        // générer route
        $output->writeln('<info>Creating menu.xml</info>');
        $this->generatorCrud->generateMenuEntry($selectedModule);

        // générer acl
        $output->writeln('<info>Creating acl.xml</info>');
        // générer controller
        $output->writeln('<info>Creating adminhtml controller</info>');
        // générer layout
        $output->writeln('<info>Creating layout</info>');
        // générer fucking ui components
        $output->writeln('<info>Creating fucking ui-component</info>');

    }
}
