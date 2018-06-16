<?php declare(strict_types=1);

namespace Shira\PhpExtUsage\Command;

use Shira\PhpExtUsage\Scanner\Exception\ScannerException;
use Shira\PhpExtUsage\Scanner\Result;
use Shira\PhpExtUsage\Scanner\Scanner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ScanCommand extends Command
{
    /** @var Scanner */
    private $scanner;

    function __construct(Scanner $scanner, ?string $name = null)
    {
        parent::__construct($name);

        $this->scanner = $scanner;
    }

    static function getDefaultName()
    {
        return 'scan';
    }

    protected function configure()
    {
        $this
            ->setDescription('Scan for PHP extension usage in the given paths')
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                sprintf('output type (%s)', implode(' or ', ScanOutputType::getValues())),
                ScanOutputType::TEXT
            )
            ->addOption(
                'extension',
                'e',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'file extensions to scan',
                ['php']
            )
            ->addOption(
                'progress',
                'p',
                InputOption::VALUE_NONE,
                'list file paths as they are scanned'
            )
            ->addArgument(
                'paths',
                InputArgument::IS_ARRAY,
                'directories and/or files to scan'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $outputType = ScanOutputType::fromValue($input->getOption('output'));
        $fileExtensionMap = array_flip($input->getOption('extension'));
        $progress = $input->getOption('progress');
        $paths = $input->getArgument('paths');

        if (empty($paths)) {
            throw new \InvalidArgumentException('No paths given');
        }

        if (empty($fileExtensionMap)) {
            throw new \InvalidArgumentException('No extensions given');
        }

        $results = [];
        $extensionMap = [];

        foreach ($paths as $path) {
            foreach ($this->iteratePath($path, $fileExtensionMap) as $file) {
                if ($progress) {
                    $io->getErrorStyle()->writeln($file);
                }

                try {
                    $result = $this->scanner->scan(file_get_contents($file));
                } catch (ScannerException $e) {
                    $io->getErrorStyle()->error(sprintf('Failed to scan file "%s" - %s', $file, $e->getMessage()));
                }

                $extensions = $result->getExtensions();

                if ($extensions) {
                    $results[$file] = $result;
                    $extensionMap += array_flip($extensions);
                }
            }
        }

        $extensions = array_keys($extensionMap);
        natsort($extensions);

        if ($outputType->equals(ScanOutputType::TEXT)) {
            $this->outputResultsAsText($io, $extensions, $results);
        } elseif ($outputType->equals(ScanOutputType::JSON)) {
            $this->outputResultsAsJson($output, $extensions, $results);
        } else {
            $this->outputResultsAsComposer($output, $extensions);
        }
    }

    /**
     * @param Result[] $results
     */
    private function outputResultsAsText(SymfonyStyle $io, array $extensions, array $results): void
    {
        foreach ($extensions as $extension) {
            $io->title($extension);

            $usageList = [];

            foreach ($results as $file => $result) {
                foreach ($result->getClasses($extension) as $class => $lines) {
                    $usageList[] = $this->formatUsageAsText($file, 'class', $class, $lines);
                }

                foreach ($result->getConstants($extension) as $constant => $lines) {
                    $usageList[] = $this->formatUsageAsText($file, 'constant', $constant, $lines);
                }

                foreach ($result->getFunctions($extension) as $function => $lines) {
                    $usageList[] = $this->formatUsageAsText($file, 'function', $function, $lines);
                }
            }

            if ($usageList) {
                $io->listing($usageList);
            }
        }
    }

    private function formatUsageAsText(string $file, string $type, string $elementName, array $lines): string
    {
        return sprintf('%s %s in %s @ %s', $type, $elementName, $file, implode(', ', $lines));
    }

    /**
     * @param Result[] $results
     */
    private function outputResultsAsJson(OutputInterface $output, array $extensions, array $results): void
    {
        $data = new \stdClass();

        foreach ($extensions as $extension) {
            $usageList = [];

            foreach ($results as $file => $result) {
                foreach ($result->getClasses($extension) as $class => $lines) {
                    $usageList[] = $this->getUsageDataForJson($file, 'class', $class, $lines);
                }

                foreach ($result->getConstants($extension) as $constant => $lines) {
                    $usageList[] = $this->getUsageDataForJson($file, 'constant', $constant, $lines);
                }

                foreach ($result->getFunctions($extension) as $function => $lines) {
                    $usageList[] = $this->getUsageDataForJson($file, 'function', $function, $lines);
                }
            }

            $data->{$extension}[] = $usageList;
        }

        $output->writeln(
            json_encode($data, JSON_PRETTY_PRINT),
            OutputInterface::OUTPUT_RAW
        );
    }

    private function getUsageDataForJson(string $file, string $type, string $elementName, array $lines): array
    {
        return [
            'file' => $file,
            'type' => $type,
            'name' => $elementName,
            'lines' => $lines,
        ];
    }

    /**
     * @param Result[] $results
     */
    private function outputResultsAsComposer(OutputInterface $output, array $extensions): void
    {
        $requirements = new \stdClass();

        foreach ($extensions as $extension) {
            $requirements->{sprintf('ext-%s', mb_strtolower($extension))} = '*';
        }

        $output->writeln(
            json_encode(['require' => (object) $requirements], JSON_PRETTY_PRINT),
            OutputInterface::OUTPUT_RAW
        );
    }

    private function iteratePath(string $path, array $extensionMap): iterable
    {
        if (is_file($path)) {
            yield $path;

            return;
        }

        if (!is_dir($path)) {
            throw new \InvalidArgumentException(sprintf('Path "%s" does not exist', $path));
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::CURRENT_AS_FILEINFO)
        );
        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            if (isset($extensionMap[$item->getExtension()])) {
                yield $item->getPathname();
            }
        }
    }
}
