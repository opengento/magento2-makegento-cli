<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Api;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Interface that all maker commands must implement.
 *
 * @method static string getCommandDescription()
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
interface MakerInterface
{
    /**
     * If necessary, you can use this method to interactively ask the user for input.
     */
    public function interact(InputInterface $input, Command $command);

    /**
     * Called after normal code generation: allows you to do anything.
     */
    public function generate(InputInterface $input, OutputInterface $output, string $selectedModule);
}
