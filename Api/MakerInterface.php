<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Api;

use Opengento\MakegentoCli\Generator\Generator;
use Opengento\MakegentoCli\Utils\InputConfiguration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;

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
     * Return the command name for your maker (e.g. make:report).
     */
    public static function getCommandName(): string;

    /**
     * Configure the command: set description, input arguments, options, etc.
     *
     * By default, all arguments will be asked interactively. If you want
     * to avoid that, use the $inputConfig->setArgumentAsNonInteractive() method.
     */
    public function configureCommand(Command $command, InputConfiguration $inputConfig);

    /**
     * If necessary, you can use this method to interactively ask the user for input.
     */
    public function interact(InputInterface $input, Command $command);

    /**
     * Called after normal code generation: allows you to do anything.
     */
    public function generate(InputInterface $input, Output $output, Generator $generator);
}
