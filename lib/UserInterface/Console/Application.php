<?php

namespace Phpactor\UserInterface\Console;

use Symfony\Component\Console\Application as SymfonyApplication;
use PhpBench\DependencyInjection\Container;
use Phpactor\Container\CoreExtension;
use XdgBaseDir\Xdg;
use Webmozart\PathUtil\Path;
use Symfony\Component\Yaml\Yaml;
use Phpactor\Container\CodeTransformExtension;
use Phpactor\Phpactor;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

class Application extends SymfonyApplication
{
    /**
     * @var Container
     */
    private $container;

    public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN')
    {
        parent::__construct('Phpactor', '0.1');
    }

    public function initialize()
    {
        $this->container = new Container([
            CoreExtension::class,
            CodeTransformExtension::class,
        ], Phpactor::loadConfig());
        $this->container->init();

        foreach ($this->container->getServiceIdsForTag('ui.console.command') as $commandId => $attrs) {
            $this->add($this->container->get($commandId));
        }
    }

    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->setCatchExceptions(false);

        try {
            return parent::doRun($input, $output);
        } catch (\Exception $e) {
            if ($input->hasOption('format') && $input->getOption('format')) {
                return $this->handleException($output, $input->getOption('format'), $e);
            }

            if ($output instanceof ConsoleOutputInterface) {
                $this->renderException($e, $output->getErrorOutput());
            }

            return 255;
        }
    }

    private function handleException(OutputInterface $output, string $dumper, \Exception $e)
    {
        $errors = [
            'error' => $this->serializeException($e),
            'previous' => [
            ],
        ];

        while ($e = $e->getPrevious()) {
            $errors['previous'][] = $this->serializeException($e);
        }

        $this->container->get('console.dumper_registry')->get($dumper)->dump($output, $errors);

        return 64;
    }

    private function serializeException(\Exception $e)
    {
        return [
            'class' => get_class($e),
            'code' => $e->getCode(),
            'message' => $e->getMessage(),
        ];
    }
}
