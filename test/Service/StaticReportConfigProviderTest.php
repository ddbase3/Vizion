<?php declare(strict_types=1);

namespace Vizion\Test\Service;

use PHPUnit\Framework\TestCase;
use Vizion\Service\StaticReportConfigProvider;

final class StaticReportConfigProviderTest extends TestCase {

	public function testGetConfigReturnsExpectedConfigForExampleReport(): void {
		$provider = new StaticReportConfigProvider();

		$config = $provider->getConfig('example');

		$this->assertIsArray($config);

		$this->assertSame('datatablereportdisplay', $config['display'] ?? null);

		$this->assertIsArray($config['config'] ?? null);
		$this->assertTrue($config['config']['paging'] ?? false);
		$this->assertTrue($config['config']['columnSelector'] ?? false);
		$this->assertSame('repository_name', $config['config']['sortColumn'] ?? null);
		$this->assertSame('asc', $config['config']['sortDirection'] ?? null);

		$this->assertSame('git_repository', $config['table'] ?? null);

		$this->assertIsArray($config['fields'] ?? null);
		$this->assertGreaterThanOrEqual(1, count($config['fields']));

		$firstField = $config['fields'][0] ?? null;
		$this->assertIsArray($firstField);
		$this->assertSame('repository_name', $firstField['alias'] ?? null);

		$this->assertIsArray($config['where'] ?? null);
		$this->assertSame('op', $config['where']['type'] ?? null);
		$this->assertSame('=', $config['where']['operator'] ?? null);
		$this->assertIsArray($config['where']['params'] ?? null);
	}

	public function testGetConfigThrowsWhenReportIsNotExample(): void {
		$provider = new StaticReportConfigProvider();

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Report not found: nope');

		$provider->getConfig('nope');
	}
}
