<?php declare(strict_types=1);

namespace Vizion\Test\Service;

use Base3\Api\IModuleRegistry;
use PHPUnit\Framework\TestCase;
use Vizion\Service\FileReportConfigProvider;

final class FileReportConfigProviderTest extends TestCase {

	private string $tempRoot;
	private array $modulePaths = [];

	protected function setUp(): void {
		parent::setUp();
		$this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'base3_vizion_' . uniqid('', true);
		mkdir($this->tempRoot, 0777, true);
	}

	protected function tearDown(): void {
		$this->rmDirRecursive($this->tempRoot);
		parent::tearDown();
	}

	public function testGetConfigThrowsWhenReportIdentifierIsDuplicatedAcrossPlugins(): void {
		$report = 'sales';
		$pluginA = $this->makePluginName('TestPluginA');
		$pluginB = $this->makePluginName('TestPluginB');

		$this->writeReportJson($pluginA, $report, ['from' => 'A']);
		$this->writeReportJson($pluginB, $report, ['from' => 'B']);

		$provider = new FileReportConfigProvider($this->createModuleRegistry());

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Report identifier is not unique: sales');
		$provider->getConfig($report);
	}

	public function testGetConfigFindsReportInLaterPluginIfEarlierDoesNotHaveIt(): void {
		$report = 'inventory';
		$pluginA = $this->makePluginName('TestPluginA');
		$pluginB = $this->makePluginName('TestPluginB');

		$this->ensurePluginDir($pluginA);
		$this->writeReportJson($pluginB, $report, ['ok' => true]);

		$provider = new FileReportConfigProvider($this->createModuleRegistry());
		$this->assertSame(['ok' => true], $provider->getConfig($report));
	}

	public function testGetConfigRejectsInvalidReportIdentifier(): void {
		$provider = new FileReportConfigProvider($this->createModuleRegistry());

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid report identifier');
		$provider->getConfig('../sales');
	}

	public function testGetConfigThrowsWhenReportNotFound(): void {
		$this->ensurePluginDir($this->makePluginName('TestPluginA'));
		$this->ensurePluginDir($this->makePluginName('TestPluginB'));

		$provider = new FileReportConfigProvider($this->createModuleRegistry());

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Report not found: missing_report');
		$provider->getConfig('missing_report');
	}

	private function createModuleRegistry(): IModuleRegistry {
		$paths = $this->modulePaths;
		return new class($paths) implements IModuleRegistry {
			/** @param array<string,string> $paths */
			public function __construct(private readonly array $paths) {}
			public function getModuleNames(): array { return array_keys($this->paths); }
			public function getModulePath(string $name): ?string { return $this->paths[$name] ?? null; }
			public function requireModulePath(string $name): string {
				$path = $this->getModulePath($name);
				if ($path === null) throw new \RuntimeException('Module not found: ' . $name);
				return $path;
			}
		};
	}

	private function makePluginName(string $prefix): string {
		return $prefix . '_' . str_replace('.', '_', uniqid('', true));
	}

	private function ensurePluginDir(string $pluginName): string {
		$dir = $this->tempRoot . DIRECTORY_SEPARATOR . $pluginName;
		if (!is_dir($dir)) mkdir($dir, 0777, true);
		$this->modulePaths[$pluginName] = $dir;
		return $dir;
	}

	private function writeReportJson(string $pluginName, string $report, array $data): void {
		$pluginDir = $this->ensurePluginDir($pluginName);
		$dir = $pluginDir . DIRECTORY_SEPARATOR . 'local' . DIRECTORY_SEPARATOR . 'Vizion';
		if (!is_dir($dir)) mkdir($dir, 0777, true);
		file_put_contents($dir . DIRECTORY_SEPARATOR . $report . '.json', json_encode($data, JSON_UNESCAPED_SLASHES));
	}

	private function rmDirRecursive(string $dir): void {
		if (!is_dir($dir)) return;
		$items = scandir($dir);
		if ($items === false) return;

		foreach ($items as $item) {
			if ($item === '.' || $item === '..') continue;
			$path = $dir . DIRECTORY_SEPARATOR . $item;
			if (is_dir($path)) $this->rmDirRecursive($path);
			else @unlink($path);
		}
		@rmdir($dir);
	}
}
