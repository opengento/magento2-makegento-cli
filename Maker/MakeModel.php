<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Maker;

use Opengento\MakegentoCli\Generator\Generator;
use Opengento\MakegentoCli\Utils\InputConfiguration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;

class MakeModel extends AbstractMaker
{
    public function generate(InputInterface $input, Output $output, Generator $generator)
    {
        // TODO: Implement generate() method.
    }

    /**
     * @return void
     */
    public function interact(InputInterface $input, Command $command)
    {
        // TODO: Implement interact() method.
    }
}
