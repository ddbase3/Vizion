<?php declare(strict_types=1);

namespace Vizion\Test;

use PHPUnit\Framework\TestCase;
use Vizion\VizionPlugin;
use Base3\Api\IContainer;
use Vizion\Api\IReportConfigProvider;
use Vizion\Api\IReportDisplay;
use Vizion\Api\IReportFilterService;

final class VizionPluginTest extends TestCase {

	public function testGetNameReturnsExpectedValue(): void {
		$this->assertSame('vizionplugin', VizionPlugin::getName());
	}

	public function testInitRegistersPluginAndServices(): void {
		$calls = [];

		$container = $this->createStub(IContainer::class);
		$container->method('set')
			->willReturnCallback(function(string $name, $definition, $flags) use (&$calls, $container) {
				$calls[] = [
					'name' => $name,
					'definition' => $definition,
					'flags' => (int)$flags,
				];
				return $container;
			});

		$plugin = new VizionPlugin($container);
		$plugin->init();

		$this->assertTrue($this->hasRegistration($calls, VizionPlugin::getName()), 'Plugin itself was not registered');

		$this->assertRegistration(
			$calls,
			VizionPlugin::getName(),
			IContainer::SHARED,
			function($definition) use ($plugin): void {
				$this->assertSame($plugin, $definition);
			}
		);

		$this->assertRegistration(
			$calls,
			IReportConfigProvider::class,
			IContainer::SHARED | IContainer::NOOVERWRITE,
			function($definition): void {
				$this->assertIsCallable($definition);
			}
		);

		$this->assertRegistration(
			$calls,
			IReportFilterService::class,
			IContainer::SHARED | IContainer::NOOVERWRITE,
			function($definition): void {
				$this->assertIsCallable($definition);
			}
		);

		$this->assertRegistration(
			$calls,
			IReportDisplay::class,
			IContainer::SHARED | IContainer::NOOVERWRITE,
			function($definition): void {
				$this->assertIsCallable($definition);
			}
		);
	}

	public function testCheckDependenciesReturnsOkWhenInstalled(): void {
		$container = $this->createStub(IContainer::class);
		$container->method('get')->willReturnCallback(function(string $name) {
			return match ($name) {
				'datahawkplugin',
				'clientstackplugin',
				'resourcefoundationplugin' => new \stdClass(),
				default => null,
			};
		});

		$plugin = new VizionPlugin($container);
		$result = $plugin->checkDependencies();

		$this->assertSame('Ok', $result['datahawkplugin_installed'] ?? null);
		$this->assertSame('Ok', $result['clientstackplugin_installed'] ?? null);
		$this->assertSame('Ok', $result['resourcefoundationplugin_installed'] ?? null);
	}

	public function testCheckDependenciesReturnsErrorsWhenMissing(): void {
		$container = $this->createStub(IContainer::class);
		$container->method('get')->willReturn(null);

		$plugin = new VizionPlugin($container);
		$result = $plugin->checkDependencies();

		$this->assertSame('datahawkplugin not installed', $result['datahawkplugin_installed'] ?? null);
		$this->assertSame('clientstackplugin not installed', $result['clientstackplugin_installed'] ?? null);
		$this->assertSame('resourcefoundationplugin not installed', $result['resourcefoundationplugin_installed'] ?? null);
	}

	private function hasRegistration(array $calls, string $name): bool {
		foreach ($calls as $call) {
			if (($call['name'] ?? null) === $name) return true;
		}
		return false;
	}

	private function assertRegistration(array $calls, string $name, int $flags, callable $assertDefinition): void {
		foreach ($calls as $call) {
			if (($call['name'] ?? null) !== $name) continue;

			$this->assertSame($flags, $call['flags'] ?? null, "Unexpected flags for {$name}");
			$assertDefinition($call['definition'] ?? null);
			return;
		}

		$this->fail("Missing container registration for {$name}");
	}
}
