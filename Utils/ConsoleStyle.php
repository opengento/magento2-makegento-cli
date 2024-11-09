<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Utils;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ConsoleStyle extends SymfonyStyle
{
    public function __construct(
        InputInterface $input,
        private OutputInterface $output,
    ) {
        parent::__construct($input, $output);
    }

    public function success($message): void
    {
        $this->writeln('<fg=green;options=bold,underscore>OK</> '.$message);
    }

    public function comment($message): void
    {
        $this->text($message);
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    /**
     * Write Success Message
     *
     * @param OutputInterface $output
     *
     * @return void
     */
    public function writeSuccessMessage(OutputInterface $output)
    {
        $output->writeln(PHP_EOL);
        $output->writeln(' <bg=green;fg=white>          </>');
        $output->writeln(' <bg=green;fg=white> Success! </>');
        $output->writeln(' <bg=green;fg=white>          </>');
        $output->writeln(PHP_EOL);
    }

    public function writeErrorMessage(OutputInterface $output)
    {
        $output->writeln(PHP_EOL);
        $output->writeln("<error>          </error>");
        $output->writeln("<error>  Error!  </error>");
        $output->writeln("<error>          </error>");
        $output->writeln(PHP_EOL);
    }
}
