<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Maker;

use Opengento\MakegentoCli\Api\ConsoleStyle;
use Opengento\MakegentoCli\Api\DependencyBuilder;
use Opengento\MakegentoCli\Api\Generator;
use Opengento\MakegentoCli\Api\InputConfiguration;
use Opengento\MakegentoCli\Api\MakerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @method string getCommandDescription()
 */
abstract class AbstractMaker implements MakerInterface
{
    /**
     * @return void
     */
    public function interact(InputInterface $input, ConsoleStyle $io, Command $command)
    {
    }

    /**
     * @return void
     */
    protected function writeSuccessMessage(ConsoleStyle $io)
    {
        $io->newLine();
        $io->writeln(' <bg=green;fg=white>          </>');
        $io->writeln(' <bg=green;fg=white> Success! </>');
        $io->writeln(' <bg=green;fg=white>          </>');
        $io->newLine();
    }

    /** @param array<class-string, string> $dependencies */
    protected function addDependencies(array $dependencies, ?string $message = null): string
    {
        $dependencyBuilder = new DependencyBuilder();

        foreach ($dependencies as $class => $name) {
            $dependencyBuilder->addClassDependency($class, $name);
        }

        return $dependencyBuilder->getMissingPackagesMessage(
            static::getCommandName(),
            $message
        );
    }

    /**
     * Get the help file contents needed for "setHelp()" of a maker.
     *
     * @param string $helpFileName the filename (omit path) of the help file located in config/help/
     *                             e.g. MakeController.txt
     *
     * @internal
     */
    final protected function getHelpFileContents(string $helpFileName): string
    {
        return file_get_contents(\sprintf('%s/config/help/%s', \dirname(__DIR__, 2), $helpFileName));
    }
}
