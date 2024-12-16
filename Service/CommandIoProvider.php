<?php

namespace Opengento\MakegentoCli\Service;

use Opengento\MakegentoCli\Exception\CommandIoNotInitializedException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Provide a shared instance of InputInterface, OutputInterface and QuestionHelper
 */
class CommandIoProvider
{
    private ?InputInterface $input = null;

    private ?OutputInterface $output = null;

    private ?QuestionHelper $questionHelper = null;

    public function init(InputInterface $input, OutputInterface $output, QuestionHelper $questionHelper): void
    {
        $this->input = $input;
        $this->output = $output;
        $this->questionHelper = $questionHelper;
    }

    /**
     * @throws CommandIoNotInitializedException
     */
    public function getInput(): InputInterface
    {
        if (null === $this->input) {
            throw new CommandIoNotInitializedException('InputInterface is not initialized');
        }
        return $this->input;
    }

    /**
     * @throws CommandIoNotInitializedException
     */
    public function getOutput(): OutputInterface
    {
        if (null === $this->output) {
            throw new CommandIoNotInitializedException('OutputInterface is not initialized');
        }
        return $this->output;
    }

    /**
     * @throws CommandIoNotInitializedException
     */
    public function getQuestionHelper(): QuestionHelper
    {
        if (null === $this->questionHelper) {
            throw new CommandIoNotInitializedException('QuestionHelper is not initialized');
        }
        return $this->questionHelper;
    }
}
