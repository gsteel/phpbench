<?php

namespace PhpBench\Tests\Example;

use Generator;
use PhpBench\Console\Application;
use PhpBench\Extension\CoreExtension;
use PhpBench\PhpBench;
use PhpBench\Tests\IntegrationTestCase;
use PhpBench\Tests\Util\Approval;
use RuntimeException;
use Symfony\Component\Console\Input\StringInput;

class CommandsTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        $this->workspace()->reset();
    }

    /**
     * @dataProvider provideCommand
     */
    public function testCommand(string $path): void
    {
        $this->createExample();

        $approval = Approval::create($path, 3);
        $command = trim($approval->getSection(1));

        if (0 !== strpos($command, 'phpbench')) {
            throw new RuntimeException(sprintf(
                'Command test command must start with `phpbench`, got "%s"',
                $command
            ));
        }

        $this->workspace()->put('phpbench.json', json_encode(array_merge([
            'env.enabled_providers' => [],
            CoreExtension::PARAM_CONSOLE_OUTPUT_STREAM => $this->workspace()->path('output'),
            CoreExtension::PARAM_CONSOLE_ERROR_STREAM => 'php://temp'
        ], json_decode($approval->getSection(0), true))));

        $command = substr($command, strlen('phpbench '));
        $input = new StringInput($command);
        $cwd = getcwd();
        chdir($this->workspace()->path());
        $container = PhpBench::loadContainer($input);
        $application = $container->get(Application::class);
        assert($application instanceof Application);
        $application->setAutoExit(false);
        $application->run($input);
        chdir($cwd);
        
        $output = $this->workspace()->getContents('output');
        // hack to ignore the suite dates
        $output = preg_replace('{[0-9]{4}-[0-9]{2}-[0-9]{2}}', 'xxxx-xx-xx', $output);
        $output = preg_replace('{[0-9]{2}:[0-9]{2}:[0-9]{2}}', 'xx-xx-xx', $output);
        $approval->approve($output);
    }

    /**
     * @return Generator<mixed>
     */
    public function provideCommand(): Generator
    {
        /** @phpstan-ignore-next-line */
        foreach (glob(__DIR__ . '/../../examples/Command/*') as $file) {
            yield [
                $file,
            ];
        }
    }

    private function createExample(): void
    {
        $this->workspace()->put('NothingBench.php', <<<'EOT'
<?php

class NothingBench { public function benchNothing(): void {}}
EOT
        );
    }
}
