<?php declare(strict_types=1);

namespace Vizion\Test\Service;

use PHPUnit\Framework\TestCase;
use Base3\Api\IClassMap;
use Vizion\Service\FileReportConfigProvider;

final class FileReportConfigProviderTest extends TestCase {

	private string $pluginRoot;
	private array $createdPluginDirs = [];

	protected function setUp(): void {
		parent::setUp();

		if (!defined('DIR_PLUGIN')) {
			$this->markTestSkipped('DIR_PLUGIN is not defined in this test environment.');
		}

		$this->pluginRoot = rtrim((string)DIR_PLUGIN, '/') . '/';

		if (!is_dir($this->pluginRoot)) {
			$this->markTestSkipped('DIR_PLUGIN does not point to an existing directory: ' . $this->pluginRoot);
		}

		if (!is_writable($this->pluginRoot)) {
			$this->markTestSkipped('DIR_PLUGIN is not writable: ' . $this->pluginRoot);
		}
	}

	protected function tearDown(): void {
		foreach ($this->createdPluginDirs as $dir) {
			$this->rmDirRecursive($dir);
		}
		$this->createdPluginDirs = [];

		parent::tearDown();
	}

	public function testGetConfigReturnsDecodedJsonFromFirstMatchingPluginFile(): void {
		$report = 'sales';

		$pluginA = $this->makePluginName('TestPluginA');
		$pluginB = $this->makePluginName('TestPluginB');

		$this->writeReportJson($pluginA, $report, ['from' => 'A']);
		$this->writeReportJson($pluginB, $report, ['from' => 'B']);

		$classmap = $this->createStub(IClassMap::class);
		$classmap->method('getPlugins')->willReturn([$pluginA, $pluginB]);

		$provider = new FileReportConfigProvider($classmap);

		$config = $provider->getConfig($report);

		$this->assertSame(['from' => 'A'], $config);
	}

	public function testGetConfigFindsReportInLaterPluginIfEarlierDoesNotHaveIt(): void {
		$report = 'inventory';

		$pluginA = $this->makePluginName('TestPluginA');
		$pluginB = $this->makePluginName('TestPluginB');

		$this->writeReportJson($pluginB, $report, ['ok' => true]);

		$classmap = $this->createStub(IClassMap::class);
		$classmap->method('getPlugins')->willReturn([$pluginA, $pluginB]);

		$provider = new FileReportConfigProvider($classmap);

		$config = $provider->getConfig($report);

		$this->assertSame(['ok' => true], $config);
	}

	public function testGetConfigThrowsWhenReportNotFound(): void {
		$pluginA = $this->makePluginName('TestPluginA');
		$pluginB = $this->makePluginName('TestPluginB');

		// Ensure plugin directories exist (but without the report file)
		$this->ensurePluginDir($pluginA);
		$this->ensurePluginDir($pluginB);

		$classmap = $this->createStub(IClassMap::class);
		$classmap->method('getPlugins')->willReturn([$pluginA, $pluginB]);

		$provider = new FileReportConfigProvider($classmap);

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Report not found: missing_report');

		$provider->getConfig('missing_report');
	}

	private function makePluginName(string $prefix): string {
		return $prefix . '_' . str_replace('.', '_', uniqid('', true));
	}

	private function ensurePluginDir(string $pluginName): string {
		$dir = $this->pluginRoot . $pluginName . '/';
		if (!is_dir($dir)) {
			mkdir($dir, 0777, true);
		}

		$this->createdPluginDirs[] = $dir;
		return $dir;
	}

	private function writeReportJson(string $pluginName, string $report, array $data): void {
		$pluginDir = $this->ensurePluginDir($pluginName);

		$dir = $pluginDir . 'local/Report/';
		if (!is_dir($dir)) {
			mkdir($dir, 0777, true);
		}

		$file = $dir . $report . '.json';
		file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES));
	}

	private function rmDirRecursive(string $dir): void {
		if (!is_dir($dir)) {
			return;
		}

		$items = scandir($dir);
		if ($items === false) {
			return;
		}

		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			$path = $dir . $item;

			if (is_dir($path)) {
				$this->rmDirRecursive($path . '/');
				continue;
			}

			@unlink($path);
		}

		@rmdir($dir);
	}
}
