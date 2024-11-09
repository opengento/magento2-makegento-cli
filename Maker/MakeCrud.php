<?php

namespace Opengento\MakegentoCli\Maker;

use Opengento\MakegentoCli\Api\ConsoleStyle;
use Opengento\MakegentoCli\Api\DependencyBuilder;
use Opengento\MakegentoCli\Api\Generator;
use Opengento\MakegentoCli\Api\InputConfiguration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class MakeCrud extends AbstractMaker
{
    public static function getCommandName(): string
    {
        // TODO: Implement getCommandName() method.
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig)
    {
        // TODO: Implement configureCommand() method.
    }

    public function configureDependencies(DependencyBuilder $dependencies)
    {
        // TODO: Implement configureDependencies() method.
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator)
    {
        // TODO: Implement generate() method.
    }
}
