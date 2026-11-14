<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of Vizion for BASE3 Framework.
 *
 * Vizion provides reporting and data visualization for BASE3 projects.
 * It supports grids, interactive tables, charts and data exporters.
 * Visual components consume structured reporting and query results.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/vizion
 * https://github.com/ddbase3/Vizion
 **********************************************************************/

namespace Base3\Vizion\Test;

use PHPUnit\Framework\TestCase;
use Base3\Vizion\VizionPlugin;
use Base3\CoreFoundation\Api\IContainer;
use Base3\Vizion\Api\IReportConfigProvider;
use Base3\Vizion\Api\IReportDisplay;
use Base3\Vizion\Api\IReportFilterService;
use Base3\Vizion\Api\IReportDataService;
use Base3\Vizion\Api\IReportTreeFilterService;
use Base3\Vizion\Api\IReportCellRendererService;
use Base3\Vizion\Api\IReportChartService;
use Base3\Vizion\Service\ModularGridReportQueryBuilder;

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
			IReportTreeFilterService::class,
			IContainer::SHARED | IContainer::NOOVERWRITE,
			function($definition): void {
				$this->assertIsCallable($definition);
			}
		);


		$this->assertRegistration(
			$calls,
			IReportCellRendererService::class,
			IContainer::SHARED | IContainer::NOOVERWRITE,
			function($definition): void {
				$this->assertIsCallable($definition);
			}
		);

		$this->assertRegistration(
			$calls,
			ModularGridReportQueryBuilder::class,
			IContainer::SHARED | IContainer::NOOVERWRITE,
			function($definition): void {
				$this->assertIsCallable($definition);
			}
		);

		$this->assertRegistration(
			$calls,
			IReportDataService::class,
			IContainer::SHARED | IContainer::NOOVERWRITE,
			function($definition): void {
				$this->assertIsCallable($definition);
			}
		);

		$this->assertRegistration(
			$calls,
			IReportChartService::class,
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
				'clientstackplugin',
				'resourcefoundationplugin' => new \stdClass(),
				default => null,
			};
		});

		$plugin = new VizionPlugin($container);
		$result = $plugin->checkDependencies();

		$this->assertSame('Ok', $result['clientstackplugin_installed'] ?? null);
		$this->assertSame('Ok', $result['resourcefoundationplugin_installed'] ?? null);
	}

	public function testCheckDependenciesReturnsErrorsWhenMissing(): void {
		$container = $this->createStub(IContainer::class);
		$container->method('get')->willReturn(null);

		$plugin = new VizionPlugin($container);
		$result = $plugin->checkDependencies();

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
