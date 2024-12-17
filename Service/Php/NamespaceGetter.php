<?php

namespace Opengento\MakegentoCli\Service\Php;

use Opengento\MakegentoCli\Exception\CommandIoNotInitializedException;
use Opengento\MakegentoCli\Service\CommandIoProvider;
use Symfony\Component\Console\Question\Question;

class NamespaceGetter
{
    public function __construct(
        private readonly CommandIoProvider $commandIoProvider,
    ) {
    }


    /**
     * Strip the module path from everything before app/code/ or vendor/ to return the namespace
     *
     * @param string $modulePath
     * @param string $path
     * @param string $selectedModule
     * @param string $namespace
     * @return string
     */
    public function getNamespace(string $modulePath, string $path, string $selectedModule, string $namespace = ''): string
    {
        if (empty($namespace)) {
            $namespace = $this->getNamespaceFromPath($modulePath, $selectedModule);
        }
        return str_replace('/', '\\', $namespace . $path);
    }

    /**
     * Get the namespace from the path
     *
     * @param string $modulePath
     * @param string|null $selectedModule
     * @return string
     * @throws CommandIoNotInitializedException
     */
    public function getNamespaceFromPath(string $modulePath, string $selectedModule = null): string
    {
        if (str_contains($modulePath, 'app/code')) {
            $namespace = preg_replace('~.*app/code/~', '', $modulePath);
        } elseif (str_contains($modulePath, 'vendor')) {
            $namespace = preg_replace('~.*vendor/~', '', $modulePath);
        } else {
            $output = $this->commandIoProvider->getOutput();
            $input = $this->commandIoProvider->getInput();
            $questionHelper = $this->commandIoProvider->getQuestionHelper();
            $proposedNamespace = str_replace('_', '\\', $selectedModule);
            $output->writeln('<info>Namespace not found, please set it manually</info>');
            $namespaceQuestion = new Question('Enter the namespace <info>[default : ' . $proposedNamespace . '] : ', $proposedNamespace);
            $inputNamespace = $questionHelper->ask($input, $output, $namespaceQuestion);
            $namespace = $this->validateNamespaceInput($inputNamespace);
            if ($inputNamespace !== $namespace) {
                $output->writeln('<info>Namespace corrected to ' . $namespace . '</info>');
            }
        }
        return $namespace;
    }

    /**
     * Removes spaces, replaces / by \, removes trailing \ and removes leading \
     *
     * @param string $namespace
     * @return string
     */
    private function validateNamespaceInput(string $namespace): string
    {
        $namespace = str_replace('/', '\\', $namespace);
        $namespace = str_replace(' ', '', $namespace);
        $namespace = trim($namespace, '\\');
        return $namespace;
    }
}
