<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Maker;

use Magento\Framework\Console\QuestionPerformer\YesNo;
use Opengento\MakegentoCli\Service\DbSchemaService;
use Opengento\MakegentoCli\Utils\ConsoleStyle;
use Opengento\MakegentoCli\Api\MakerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method string getCommandDescription()
 */
abstract class AbstractMaker implements MakerInterface
{
    protected InputInterface $input;

    protected OutputInterface $output;

    public function __construct(
        private readonly ConsoleStyle    $consoleStyle
    ) {
    }

    /**
     * @return void
     */
    public function interact(InputInterface $input, Command $command)
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
