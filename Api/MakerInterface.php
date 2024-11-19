<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Api;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
interface MakerInterface
{

    /**
     * Called after normal code generation: allows you to do anything.
     */
    public function generate(InputInterface $input, OutputInterface $output, string $selectedModule);
}
