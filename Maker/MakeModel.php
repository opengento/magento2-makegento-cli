<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Maker;

use Opengento\MakegentoCli\Generator\Generator;
use Opengento\MakegentoCli\Utils\InputConfiguration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;

class MakeModel extends AbstractMaker
{


    /**
     * @return void
     */
    public function interact(InputInterface $input, Command $command)
    {
        // TODO: Implement interact() method.
    }

    public static function getCommandName(): string
    {
        // TODO: Implement getCommandName() method.
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig)
    {
        // TODO: Implement configureCommand() method.
    }

    public function generate(InputInterface $input, OutputInterface $output, string $selectedModule)
    {
        // TODO: Implement generate() method.
    }
}
