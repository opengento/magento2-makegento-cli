<?php
/**
 * Opengento_MakegentoCli
 *
 * @package    Opengento_MakegentoCli
 */

namespace Opengento\MakegentoCli\Console\Command;

use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\CacheInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Command class.
 */
class HelperToolsCommand extends Command
{
    private string $_EOL = PHP_EOL.' > ';
    private array $_stepList = [
        'dbschema_indexes'=>[
            'column' => 'Which column do you want to add to the index?',
            'type' => 'Which index type ?',
            'next' => '<red>next</red> Do you want to add another indexes ? <comment>(y/N)</comment>',
        ],
        'dbschema_constraints_u'=>[
            'column'=>'Which columns are in the constrains ?',
            'next'=>'<red>next</red> Do you want to add another constraints <comment>(y/N)</comment>',
        ],
        'dbschema_constraints_fk'=>[
            'column_loc'=> 'What\'s the name of the link <comment>(it will be used for the name of the field)</comment>',
            'table'=>'Witch table will you refer to?', // TODO: Ajouter une rechercher de table ?
            'column_fk'=>'Witch Column do you want to add from this table %s ?', // Checking matching type
            'next'=>'<red>next</red> Do you want to add another foreign constraints <comment>(y/N)</comment>',
        ],
        'dbschema_columns'=>[
            'name'=>'What\'s the name of the column n°%s ?',
            'type'=>'What\'s the type of the column n°%s ?',
            'dbschema_options'=>'Do you want to add option ? <comment>(y/N)</comment>',
            'default'=>'What\'s the default value of the column n°%s ? <comment>(empty make it nullable)</comment>',
            'comment'=>'What\'s the comment of the column n°%s',
            'next'=>'<red>next</red> Do you want to add another column ? <comment>(y/N)</comment>',
        ],
        'dbschema_options'=>[
            'key'=>'Which option do you want add ?',
            'value'=>'Witch value <comment>(%s)</comment>',
            'next'=>'<red>next</red> Do you want to add another option ? <comment>(y/N)</comment>'
        ]
    ];
    private array $_customOptionList = [
        'column',
        'index',
        'constraint_u',
        'constraint_fk',
    ];
    private array $_type = [
        'decimal'=>['scale','precision','unsigned'],
        'float'=>['scale','precision','unsigned'],
        'double'=>['scale','precision','unsigned'],
        'int' =>['padding','unsigned'],
        'bigint'=>['padding','unsigned'],
        'smallint'=>['padding','unsigned'],
        'tinyint'=>['padding','unsigned'],
        'text'=>[],
        'longtext'=>[],
        'mediumtext'=>[],
        'varchar'=>['length'],
        'char'=>['length'],
        'json'=>[],
        'blob'=>[],
        'mediumblob'=>[],
        'longblob'=>[],
        'varbinary'=>['length'],
        'timestamp'=>[],
        'datetime'=>[],
        'date'=>[],
        'boolean'=>[]
    ];
    private array $_type_option=[
        'precision' => [
            'pattern'=>'/^([1-9]|[1-5][0-9]|6[0-5])$/',
            'text'=>'The scale must be a number between 1 and 65'
        ],
        'scale' => [
            'pattern'=>'/^([1-9]|[1-2][0-9]|30)$/',
            'text'=>'The precision must be a number between 1 and 30'
        ],
        'length' => [
            'pattern'=>'/^([1-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/',
            'text'=>'The length must be a number between 1 and 255'
        ],
        'unsigned' => [
            'choices'=>['true','false']
        ]
    ];
    private array $_type_index = ['btree','fulltext','hash'];
    private array $_options_fk = ['DATA_TYPE','UNSIGNED','LENGTH','PRECISION','SCALE'];
    private string $_separatorOption = '|';
    private string $_separatorElem =  '/';
    private string $_separatorItem =  ',';

    public function __construct(
        private ObjectManagerInterface $objectManager,
        private ResourceConnection $resource,
        private CacheInterface $cache
    ) {
        parent::__construct();
    }

    protected function configure() :void {
        $this->setName('make:tools');
        $this->setDescription('Helper pour les fonctions de make:tools');

        $this->addOption('column', 'c', null, 'Column Mode');
        $this->addOption('index', 'i', null, 'Index Mode');
        $this->addOption('constraint_u', 'k', null, 'Unique Constraint Mode');
        $this->addOption('constraint_fk', 'f', null, 'Foreign Key Constraint Mode');

        $this->setHidden(true);
        $this->addArgument('token', null, 'Token', '');
        parent::configure();
    }

    protected function interact(InputInterface $input, OutputInterface $output) :void {
        $helperCommande = $this->getHelper('question');
        $options = $input->getOptions();
        if (!empty($this->getTypeCommand($options))){
            return;
        } else {
            foreach($this->_customOptionList as $option){
                $question = $this->objectManager->create(
                    '\Symfony\Component\Console\Question\ConfirmationQuestion',
                    [
                        'question' => '<info>Wanna add ' . $option . ' parameter ? <comment>(y/N)</comment></info>' . $this->_EOL,
                        'default' => false
                    ]
                );
                if ($helperCommande->ask($input, $output, $question)) {
                    $input->setOption($option, '1');
                }
            }
        }
    }
    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int {
        // Initialisation des variables
        $type_commands = $columnList = [];
        $idx = $cst_u = $cst_fk = $col = []; // Tableau de retour
        $columnLine = $indexLine = $constraintULine = $constraintFKLine = '';

        $helperCommande = $this->getHelper('question');

        // Add a color to the command
        $output->getFormatter()->setStyle('red', new OutputFormatterStyle('red'));
        $output->getFormatter()->setStyle('br', new OutputFormatterStyle('red', null, ['bold']));

        // Command
        if ($input->getOption('column')) $type_commands[] = 'column';
        if ($input->getOption('index')) $type_commands[] = 'index';
        if ($input->getOption('constraint_u')) $type_commands[] = 'constraint_u';
        if ($input->getOption('constraint_fk')) $type_commands[] = 'constraint_fk';

        $output->writeln('<info>Vous entrez dans le générateur de de ligne pour le createur de dbschema</info>');
        $output->writeln('Options choisies : <red>' . implode('/', $type_commands) . '</red>');

        foreach ($type_commands as $type_command) {
            $cpt = 0;
            $continue = true;

            // Mode Colonne
            if ($type_command == 'column') {
                do {
                    $col[$cpt] = array();
                    foreach ($this->_stepList['dbschema_columns'] as $key => $step) {
                        $question = null;
                        switch ($key) {
                            case 'type':
                                $question = $this->_objectManager->create(
                                    '\Symfony\Component\Console\Question\ChoiceQuestion',
                                    [
                                        'question' => '<info>' . sprintf($step, $cpt + 1) . '</info>',
                                        'choices' => array_keys($this->_type),
                                        'default' => false
                                    ]
                                );
                                break;
                            case 'default':
                            case 'name':
                            case 'comment':
                                $question = $this->_objectManager->create(
                                    '\Symfony\Component\Console\Question\Question',
                                    [
                                        'question' => '<info>' . sprintf($step, $cpt + 1) . '</info>' . $this->_EOL,
                                        'default' => false
                                    ]
                                );
                                break;
                            case 'next':
                            case 'dbschema_options':
                                if ($key == 'dbschema_options' && empty($this->_type[$col[$cpt]['type']])) continue 2;
                                $question = $this->_objectManager->create(
                                    '\Symfony\Component\Console\Question\ConfirmationQuestion',
                                    [
                                        'question' => '<info>' . sprintf($step) . '</info>' . $this->_EOL,
                                        'default' => false
                                    ]
                                );
                                break;
                            default:
                                $output->writeln('<br>Error: </br><red>Unknown</red>');
                                break;
                        }
                        $answer = $helperCommande->ask($input, $output, $question);

                        switch ($key) {
                            case 'name':
                                if (in_array($answer, $columnList)) {
                                    $output->writeln('<br>Error:</br> <red>The column already exist you cannot use the same name</red>');
                                    $cpt--;
                                    break 2;
                                }
                                $columnList[] = $answer;
                            case 'default':
                            case 'comment':
                            case 'type':
                                $col[$cpt][$key] = $answer;
                                break;
                            case 'dbschema_options':
                                $continueSub = true;
                                $options = [];
                                do {
                                    $subans = [];
                                    foreach ($this->_stepList['dbschema_options'] as $subKey => $optionStep) {
                                        $subQuestion = null;
                                        switch ($subKey) {
                                            case 'key':
                                                $subQuestion = $this->_objectManager->create(
                                                    '\Symfony\Component\Console\Question\ChoiceQuestion',
                                                    [
                                                        'question' => '<info>' . $optionStep . '</info>',
                                                        'choices' => $this->_type[$col[$cpt]['type']],
                                                        'default' => false
                                                    ]
                                                );
                                                break;
                                            case 'value':
                                                if (isset($this->_type_option[$subans['key']]['choices'])) {
                                                    $subQuestion = $this->_objectManager->create(
                                                        '\Symfony\Component\Console\Question\ChoiceQuestion',
                                                        [
                                                            'question' => '<info>' . sprintf($optionStep, $subans['key']) . '</info>' . $this->_EOL,
                                                            'choices' => $this->_type_option[$subans['key']]['choices'],
                                                            'default' => false
                                                        ]
                                                    );
                                                } else {
                                                    $subQuestion = $this->_objectManager->create(
                                                        '\Symfony\Component\Console\Question\Question',
                                                        [
                                                            'question' => '<info>' . sprintf($optionStep, $subans['key']) . '</info>' . $this->_EOL,
                                                            'default' => false
                                                        ]
                                                    );
                                                }
                                                break;
                                            case 'next':
                                                $subQuestion = $this->_objectManager->create(
                                                    '\Symfony\Component\Console\Question\ConfirmationQuestion',
                                                    [
                                                        'question' => '<info>' . $optionStep . '</info>' . $this->_EOL,
                                                        'default' => false
                                                    ]
                                                );
                                                break;
                                        }
                                        $subAnswer = $helperCommande->ask($input, $output, $subQuestion);

                                        switch ($subKey) {
                                            case 'key':
                                            case 'value':
                                                if ($subKey == 'value' && isset($this->_type_option[$subans['key']]['pattern']) && !preg_match($this->_type_option[$subans['key']]['pattern'], $subAnswer))
                                                    $output->writeln('<br>ERROR:</br> <red>' . ($this->_type_option[$subans['key']]['text'] ?? 'Unknown') . '</red>');
                                                $subans[$subKey] = $subAnswer;
                                                break;
                                            case 'next':
                                                $options[$subans['key']] = $subans['value'];
                                                if (!$subAnswer) {
                                                    $continueSub = false;
                                                }
                                                break;
                                            default:
                                                break;
                                        }
                                    }
                                    $col[$cpt][$key] = $options;
                                } while ($continueSub == true);
                                $answer = $options;
                                $col[$cpt][$key] = $answer;
                                break;
                            case 'next':
                                if (!$answer) $continue = false;
                                break;
                            default:
                                $output->writeln('<br>Error:</br><red>Unknown</red>');
                                break;
                        }
                    }
                    $output->writeln(PHP_EOL . '<comment>' . str_replace(',', PHP_EOL, $this->translateAnswerToLine($col)) . '</comment>' . PHP_EOL);
                    $cpt++;
                } while ($continue);
                $columnLine = $this->translateAnswerToLine($col);
            }

            // Mode Index
            if ($type_command == 'index') {
                do {
                    $idx[$cpt] = [];
                    foreach ($this->_stepList['dbschema_indexes'] as $key => $step) {
                        $question = null;
                        switch ($key) {
                            case 'column':
                            case 'type':
                                $choices = [];
                                if ($key == 'column' && !empty($columnList)) $choices = $columnList;
                                elseif ($key == 'type') $choices = $this->_type_index;

                                if (!empty($choices)) {
                                    $question = $this->_objectManager->create(
                                        '\Symfony\Component\Console\Question\ChoiceQuestion',
                                        [
                                            'question' => '<info>' . $step . '</info>',
                                            'choices' => $choices,
                                            'default' => 0
                                        ]
                                    );
                                } else {
                                    $question = $this->_objectManager->create(
                                        '\Symfony\Component\Console\Question\Question',
                                        [
                                            'question' => '<info>' . $step . '</info>' . $this->_EOL,
                                            'default' => false
                                        ]
                                    );
                                }
                                break;
                            case 'next':
                                $question = $this->_objectManager->create(
                                    '\Symfony\Component\Console\Question\Question',
                                    [
                                        'question' => '<info>' . $step . '</info>' . $this->_EOL,
                                        'default' => false
                                    ]
                                );
                                break;
                            default:
                                $output->writeln('<br>Error:</br><red> Unknown</red>');
                                break;
                        }
                        $answer = $helperCommande->ask($input, $output, $question);

                        if ($key != 'next') {
                            $idx[$cpt][$key] = $answer;
                        } elseif (!$answer) {
                            $continue = false;
                        }
                    }
                    $cpt++;
                } while ($continue);
                $indexLine = $this->translateAnswerToLine($idx);
            }

            // Mode Contrainte Unique
            if ($type_command == 'constraint_u') {
                do {
                    $cst_u[$cpt] = [];
                    foreach ($this->_stepList['dbschema_constraints_u'] as $key => $step) {
                        $subContinue = true;
                        $choices = [];
                        $cpt_u = 0;
                        switch ($key) {
                            case 'column':
                                if (!empty($columnList)) $choices = array_merge($columnList, ['<red>(end)</red>']);
                                do {
                                    if (!empty($columnList)) {
                                        $question = $this->_objectManager->create(
                                            '\Symfony\Component\Console\Question\ChoiceQuestion',
                                            [
                                                'question' => '<info>' . $step . '</info>',
                                                'choices' => $choices,
                                                'default' => false
                                            ]
                                        );
                                    } else {
                                        $question = $this->_objectManager->create(
                                            '\Symfony\Component\Console\Question\Question',
                                            [
                                                'question' => '<info>' . $step . '</info>' . ($cpt_u++ == 0 ? '' : '<comment>(empty to stop)</comment>') . $this->_EOL,
                                                'default' => false
                                            ]
                                        );
                                    }
                                    $subAnswer = $helperCommande->ask($input, $output, $question);
                                    if (!empty($choices)) $choices = array_diff($choices, [$subAnswer]);
                                    if (strpos($subAnswer, '(end)') || $subAnswer == '') {
                                        $subContinue = false;
                                    } else {
                                        $cst_u[$cpt][] = $subAnswer;
                                    }
                                } while ($subContinue);
                                break;
                            case 'next':
                                $question = $this->_objectManager->create(
                                    '\Symfony\Component\Console\Question\ConfirmationQuestion',
                                    [
                                        'question' => '<info>' . $step . '</info>' . $this->_EOL,
                                        'default' => false
                                    ]
                                );
                                $answer = $helperCommande->ask($input, $output, $question);
                                if (!$answer) {
                                    $continue = false;
                                }
                                break;
                            default:
                                break;
                        }
                    }
                    $cpt++;
                } while ($continue);
                $constraintULine = $this->translateAnswerToLine($cst_u);
            }

            // Mode Contrainte Etrangère
            if ($type_command == 'constraint_fk') {
                do {
                    foreach ($this->_stepList['dbschema_constraints_fk'] as $key => $step) {
                        $question = null;
                        switch ($key) {
                            case 'column_fk':
                                $data = $this->_ressource->getConnection()->showTableStatus($cst_fk[$cpt]['table']);
                                if ($data) {
                                    $attribute = $this->_ressource->getConnection()->describeTable($cst_fk[$cpt]['table']);
                                    $choices = array_keys($attribute);
                                    $question = $this->_objectManager->create(
                                        '\Symfony\Component\Console\Question\ChoiceQuestion',
                                        [
                                            'question' => '<info>' . $step . '</info>',
                                            'choices' => $choices,
                                            'default' => false
                                        ]
                                    );
                                } else {
                                    $output->writeln('<br>ERROR: </br><red>La table n\'existe pas, veuillez recommencer</red>');
                                    unset($cst_fk[$cpt]);
                                    break 2;
                                }
                                break;
                            case 'table':
                            case 'column_loc':
                                $question = $this->_objectManager->create(
                                    '\Symfony\Component\Console\Question\Question',
                                    [
                                        'question' => '<info>' . $step . '</info>' . $this->_EOL,
                                        'default' => false
                                    ]
                                );
                                break;
                            case 'next':
                                $question = $this->_objectManager->create(
                                    '\Symfony\Component\Console\Question\ConfirmationQuestion',
                                    [
                                        'question' => '<info>' . $step . '</info>' . $this->_EOL,
                                        'default' => false
                                    ]
                                );
                                break;
                            default:
                                $output->writeln('<br>Error:</br> Unknown');
                                break;
                        }
                        $answer = $helperCommande->ask($input, $output, $question);

                        if ($key != 'next') {
                            $cst_fk[$cpt][$key] = $answer;
                            if ($key == 'column_fk') {
                                foreach ($this->_options_fk as $opFK) {
                                    if ($attribute[$answer][$opFK] != '') $cst_fk[$cpt][$opFK] = strtolower($opFK) . $this->_separatorOption . $attribute[$answer][$opFK];
                                }
                            }
                        } else {
                            if (!$answer) {
                                $continue = false;
                            }
                        }
                    }
                } while ($continue);
                $constraintFKLine = $this->translateAnswerToLine($cst_fk);
            }
        }

        // Set the return for cache purposes (if the command is call by another one)
        $return = [];
        if ($columnLine) {
            $output->writeln('Column: "<red>' . $columnLine . '</red>"');
            $return['column'] = $columnLine;
        }
        if ($indexLine != '') {
            $output->writeln('Index : "<red>' . $indexLine . '</red>"');
            $return['index'] = $indexLine;
        }
        if ($constraintULine != '') {
            $output->writeln('Unique : "<red>' . $constraintULine . '</red>"');
            $return['constraint_u'] = $constraintULine;
        }
        if ($constraintFKLine != '') {
            $output->writeln('Foreign Key : "<red>' . $constraintFKLine . '</red>"');
            $return['constraint_fk'] = $constraintFKLine;
        }

        // Stock the answer in the cache for 10 minutes
        if ($token = $input->getArgument('token')) {
            $this->_cache->save(serialize($return), $token, [], '600');
        }

        return 0;
    }

    /**
     * Translate the Answer to a formated line for the genception command
     *
     * @param array $ans
     * @return string
     */
    private function translateAnswerToLine(array $ans): string {
        $columnLine = [];

        // Line Creation
        foreach ($ans as $answerList) {
            $column = [];
            foreach ($answerList as $answerType => $answerVal) {
                $val = null;
                switch ($answerType) {
                    case 'name':
                        $name = strtolower($answerVal);
                        $val = $name;
                        break;
                    case 'type':
                        $type = strtolower($answerVal);
                        $val = $type;
                        break;
                    case 'default':
                        if ($answerVal != '') {
                            $default = 'default' . $this->_separatorOption . $answerVal;
                            $val = $default;
                        } else {
                            $default = 'nullable';
                            $val = $default;
                        }
                        break;
                    case 'comment':
                        if ($answerVal != '') {
                            $comment = 'comment' . $this->_separatorOption . $answerVal;
                            $val = $comment;
                        }
                        break;
                    case 'dbschema_options':
                        $options = [];
                        foreach ($answerVal as $optionKey => $optionVal) {
                            $options[] = $optionKey . $this->_separatorOption . $optionVal;
                        }
                        if ($options) {
                            $val = implode($this->_separatorElem, $options);
                        }
                        break;
                    default:
                        $val = $answerVal;
                        break;
                }
                if ($val != null) {
                    $column[] = $val;
                }
            }
            $columnLine[] = implode($this->_separatorElem, $column);
        }

        return implode($this->_separatorItem, $columnLine);
    }

    /**
     * Get the types of the commands
     *
     * @param $options
     * @return array
     */
    private function getTypeCommand($options): array {
        $type_commands = [];

        if ($options['column'] == 1) {
            $type_commands[] = 'column';
        }
        if ($options['index'] == 1) {
            $type_commands[] = 'index';
        }
        if ($options['constraint_u'] == 1) {
            $type_commands[] = 'constraint_u';
        }
        if ($options['constraint_fk'] == 1) {
            $type_commands[] = 'constraint_fk';
        }

        return $type_commands;
    }
}
