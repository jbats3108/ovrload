<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CheckPhpCoverageScriptTest extends TestCase
{
    private string $script;

    protected function setUp(): void
    {
        parent::setUp();

        $this->script = dirname(__DIR__, 2).'/scripts/check-php-coverage.php';
    }

    #[DataProvider('thresholdProvider')]
    public function test_compares_clover_statements_to_minimum(float $min, int $expectedExit): void
    {
        $clover = dirname(__DIR__).'/Fixtures/coverage/clover-60.xml';

        $command = sprintf(
            '%s %s %s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($this->script),
            escapeshellarg($clover),
            escapeshellarg((string) $min),
        );

        exec($command.' 2>&1', $output, $exitCode);

        $this->assertSame($expectedExit, $exitCode, implode("\n", $output));
        $this->assertStringContainsString('60.00%', implode("\n", $output));
    }

    /**
     * @return array<string, array{0: float, 1: int}>
     */
    public static function thresholdProvider(): array
    {
        return [
            'at floor' => [60.0, 0],
            'below floor' => [60.01, 1],
            'above coverage' => [50.0, 0],
        ];
    }

    public function test_fails_when_clover_missing(): void
    {
        $missing = dirname(__DIR__).'/Fixtures/coverage/does-not-exist.xml';

        $command = sprintf(
            '%s %s %s 50',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($this->script),
            escapeshellarg($missing),
        );

        exec($command.' 2>&1', $output, $exitCode);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('not found', implode("\n", $output));
    }
}
