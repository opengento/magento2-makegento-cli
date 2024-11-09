<?php
/**
 * Opengento_MakegentoCli
 *
 * @package   Opengento_MakegentoCli
 */

namespace Opengento\MakegentoCli\Console\Command;

use Composer\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\ObjectManagerInterface;
use Opengento\MakegentoCli\Helper\ScriptAskAnswer;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Magento\Framework\App\CacheInterface;

/**
 * Command class.
 */
class HelperCodeCommand extends Command
{
    public function __construct(
        private ObjectManagerInterface $objectManager,
        private ScriptAskAnswer $helper,
        private CacheInterface $cache
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this->setName('make:generate');
        $this->setDescription('make:generate, generate code');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $helper = $this->getHelper('question');
        $this->helper->setAction('module');

        if (($nextQuestion = $this->helper->getNextStep()) === false) {
            return Command::SUCCESS;
        }

        $question = $this->objectManager->create(ChoiceQuestion::class, [
            'question' => '<info>' . $nextQuestion['question'] . '</info>' . PHP_EOL,
            'choices' => $this->helper->getListAction(),
            'default' => 1
        ]);
        $this->helper->setAnswer($ans = $helper->ask($input, $output, $question));

        if ($ans != 'new-module') {
            $action = $ans;
            if (($nextQuestion = $this->helper->getNextStep()) === false) {
                return Command::SUCCESS;
            }
            $choices = $this->helper->getCustomModules();
            $question = $this->objectManager->create(ChoiceQuestion::class, [
                'question' => '<info>' . $nextQuestion['question'] . '</info>',
                'choices' => $choices,
                'default' => 0
            ]);
            $ans = $helper->ask($input, $output, $question);
            $this->helper->setAnswer($ans);
            $this->helper->setAction($action);

            if ($action == 'dbschema') {
                $token = random_bytes(20);
                $arguments = new ArrayInput(['command' => 'make:tools', 'token' => $token]);
                $arguments->setInteractive(true);
                $returnCmd = $this->getApplication()->find('make:tools')->run($arguments, $output);
                $responsea = [];
                if ($returnCmd == 0) {
                    $responsea = unserialize($this->cache->load($token));
                }
            }

            if ($this->helper->isModuleOutputEnabled($this->helper->getAnswer('module')[1]['val'])) {
                foreach ($this->helper->getListStepsAction() as $step) {
                    if (($nextQuestion = $this->helper->getNextStep()) === false) {
                        return Command::SUCCESS;
                    }
                    $this->helper->loadNamespaces();
                    if ($nextQuestion['choices'] !== false) {
                        if (is_array($nextQuestion['choices'])) {
                            $question = $this->objectManager->create(ChoiceQuestion::class, [
                                'question' => '<info>' . $nextQuestion['question'] . '</info>' . PHP_EOL,
                                'choices' => $nextQuestion['choices'],
                                'default' => 0
                            ]);
                        } else {
                            $property = $nextQuestion['choices'];
                            $nextQuestion['choices'] = $this->helper->$property;
                            $question = $this->objectManager->create(ChoiceQuestion::class, [
                                'question' => '<info>' . $nextQuestion['question'] . '</info>' . PHP_EOL,
                                'choices' => $nextQuestion['choices'],
                                'default' => 0
                            ]);
                        }
                    } else {
                        $default = false;
                        if ($nextQuestion['isCached'] && isset($responsea[$nextQuestion['isCached']])) {
                            $default = $responsea[$nextQuestion['isCached']];
                        }
                        $question = $this->objectManager->create(\Symfony\Component\Console\Question\Question::class, [
                            'question' => '<info>' . $nextQuestion['question'] . '</info>' . (($default === false) ? '' : $default),
                            'default' => $default
                        ]);
                    }

                    $this->helper->setAnswer($ans = $helper->ask($input, $output, $question));
                }
            } else {
                $output->writeln('Module disable veuillez l\'activer');
                return Command::SUCCESS;
            }
        } else {
            $this->helper->setAction($ans);
            if (($nextQuestion = $this->helper->getNextStep()) === false) {
                return Command::SUCCESS;
            }
            $question = $this->objectManager->create(\Symfony\Component\Console\Question\Question::class, [
                'question' => '<info>' . $nextQuestion['question'] . '</info>',
                'default' => false
            ]);
            $ans = $helper->ask($input, $output, $question);
            if ($this->helper->validatePattern('module', $ans)) {
                $this->helper->setAnswer($ans);
            } else {
                $output->writeln('Nom de module invalide');
                return $this->execute($input, $output);
            }
        }

        $storeManager = $this->objectManager->get(\Magento\Framework\Filesystem\DirectoryList::class);
        $parametersCommand = $this->helper->getAnswer(null, false);
        if ($this->helper->getAction() == 'new-module') {
            $commandLine = $storeManager->getRoot() . '/bin/magento gen:code ' . $parametersCommand[0]['val'] . ' --type=' . $this->helper->getAction();
            $output->writeln('<info>' . $commandLine . '</info>');
        } else {
            $commandLine = $storeManager->getRoot() . '/bin/magento gen:code ' . $this->helper->getAnswer('module')[1]['val'] . ' --type=' . $this->helper->getAction();
            foreach ($parametersCommand as $param) {
                if ($param['val'] === '') continue;
                $commandLine .= ' --' . $param['key'] . '="' . $param['val'] . '"';
            }
            $output->writeln('<info>' . $commandLine . '</info>');
        }

        $this->helper->setAction('module');
        $nextQuestion = 'Do you want execute the command above' . PHP_EOL;
        $question = $this->objectManager->create(\Symfony\Component\Console\Question\ConfirmationQuestion::class, [
            'question' => $nextQuestion,
            'default' => false
        ]);
        $ans = $helper->ask($input, $output, $question);
        if ($ans) {
            exec($commandLine, $outputExec);
            foreach ($outputExec as $ouputLine) {
                $output->writeln('<info>' . $ouputLine . '</info>');
            }
            return Command::SUCCESS;
        }
        return Command::SUCCESS;
    }
}
