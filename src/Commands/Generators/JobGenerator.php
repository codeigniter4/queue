<?php

declare(strict_types=1);

namespace CodeIgniter\Queue\Commands\Generators;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\GeneratorTrait;

/**
 * Generates a skeleton Job file.
 */
class JobGenerator extends BaseCommand
{
    use GeneratorTrait;

    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'Queue';

    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'queue:job';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Generates a new job file.';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'queue:job <name> [options]';

    /**
     * The Command's Arguments
     *
     * @var array
     */
    protected $arguments = [
        'name' => 'The job class name.',
    ];

    /**
     * The Command's Options
     *
     * @var array
     */
    protected $options = [
        '--namespace' => 'Set root namespace. Default: "APP_NAMESPACE".',
        '--suffix'    => 'Append the component title to the class name (e.g. Email => EmailJob).',
        '--force'     => 'Force overwrite existing file.',
    ];

    /**
     * Actually execute a command.
     */
    public function run(array $params)
    {
        $this->component = 'Job';
        $this->directory = 'Jobs';
        $this->template  = 'job.tpl.php';

        $this->classNameLang = 'Queue.generator.className.job';

        $this->generateClass($params);

        return EXIT_SUCCESS;
    }
}
