<?php

namespace PaulhenriL\PhpPackageTemplate;

use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InstallCommand extends Command
{
    protected static $defaultName = "install";

    /** @var InputInterface */
    protected $input;

    /** @var OutputInterface */
    protected $output;

    protected function configure()
    {
        $this->setDescription('Install the template');

        $this->addArgument(
            'project-name',
            InputArgument::OPTIONAL,
            'the project name to use in stubs',
            $this->inferProjectName()
        );

        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'overwrite existing files'
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        $this->createDirectories();
        $this->copyStubs();
        $this->output->writeln("<info>Done 🚀</info>");

        return 0;
    }

    protected function createDirectories(): void
    {
        $stubsIterator = $this->getStubsIterator();
        $this->output->writeln("<info>Creating directories</info>");

        foreach ($stubsIterator as $file) {
            if (in_array($file->getBasename(), ['..'])) {
                continue;
            }

            if (!$file->isDir()) {
                continue;
            }

            $target = $this->findTargetPath($file);

            if (is_dir($target)) {
                $this->output->writeln("<comment> - $target already exists</comment>");
                continue;
            }

            $this->output->writeln("<comment> - $target</comment>");
            mkdir($target, 0755, true);
        }
    }

    protected function copyStubs(): void
    {
        $stubsIterator = $this->getStubsIterator();
        $this->output->writeln("<info>Copying stubs</info>");

        foreach ($stubsIterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            $target = $this->findTargetPath($file);

            if (file_exists($target) && !$this->input->getOption('force')) {
                $this->output->writeln("<comment> - $target already exists</comment>");
                continue;
            }

            $this->copyStub($file, $target);
            $this->output->writeln("<comment> - $target</comment>");
        }
    }

    protected function getStubsIterator(): RecursiveIteratorIterator
    {
        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->getStubsDir())
        );
    }

    protected function copyStub(\SplFileInfo $fileInfo, string $target): void
    {
        $contents = $this->replacePlaceholders(
            file_get_contents($fileInfo->getPathname())
        );

        file_put_contents($target, $contents);
    }

    protected function replacePlaceholders(string $contents): string
    {
        $contents = str_replace(
            'stub-current-year',
            date('Y'),
            $contents
        );

        $contents = str_replace(
            'StubProjectName',
            Str::studly($this->input->getArgument('project-name')),
            $contents
        );

        return str_replace(
            'stub-project-name',
            $this->input->getArgument('project-name'),
            $contents
        );
    }

    protected function findTargetPath(\SplFileInfo $fileInfo): string
    {
        return getcwd() . str_replace($this->getStubsDir(), '', $fileInfo->getRealPath());
    }

    protected function getStubsDir(): string
    {
        return realpath(__DIR__ . '/../stubs');
    }

    protected function inferProjectName(): string
    {
        $projectName = explode('/', getcwd());
        $projectName = array_pop($projectName);

        return $projectName;
    }
}
