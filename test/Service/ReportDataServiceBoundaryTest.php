<?php declare(strict_types=1);

namespace Vizion\Test\Service;

use Base3\Api\IClassMap;
use Base3\Logger\Api\ILogger;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Api\IReportingScopeRegistry;
use ResourceFoundation\Dto\QueryResult;
use Vizion\Api\IReportConfigProvider;
use Vizion\Api\IReportFilterService;
use Vizion\Api\IReportTreeFilterService;
use Vizion\Service\ModularGridReportQueryBuilder;
use Vizion\Service\ReportDataService;

final class ReportDataServiceBoundaryTest extends TestCase {

	public function testDataProjectionRetainsIdentityAndRendererLinkDependencies(): void {
		$queries = [];
		$service = $this->service(static function(array $query) use (&$queries): QueryResult {
			$queries[] = $query;
			return in_array('__total__', array_column($query['fields'], 'alias'), true)
				? new QueryResult([], [['__total__' => 2]])
				: new QueryResult([], [['id' => 7, 'name' => 'Alice', 'slug' => 'alice']]);
		});
		$config = $this->config();
		$config['rowKey'] = ['id'];
		$config['fields'][1]['config']['valueRenderer'] = ['name' => 'link', 'href' => '/users/{slug}'];
		$response = $service->executeConfig($config, ['fields' => ['name'], 'page' => 1, 'pageSize' => 1]);
		$this->assertSame(['name', 'id', 'slug'], $response['fields']);
		$this->assertSame(['id', 'name', 'slug'], array_column($queries[1]['fields'], 'alias'));
		$this->assertSame(2, $response['total']);
		$this->assertSame(2, $response['totalPages']);
		$this->assertTrue($response['hasMore']);
		$this->assertSame('Alice', $response['data'][0]['name']);
		$this->assertTrue(str_starts_with($response['data'][0]['__row_key'], 'report-key-'));
	}

	public function testUnsupportedDisplayIsRejectedBeforeExecutingQuery(): void {
		$service = $this->service(static function(array $query): QueryResult { throw new \RuntimeException('unexpected database query'); });
		foreach(['metricreportdisplay', '', 'matrixreportdisplay'] as $display) {
			try {
				$service->executeConfig(['display' => $display]);
				$this->fail('Unsupported Vizion report was executed.');
			} catch(\RuntimeException $exception) {
				$this->assertStringContainsString('not supported', $exception->getMessage());
			}
		}
	}

	public function testMalformedFieldSelectionsFailClosed(): void {
		$service = $this->service(static function(array $query): QueryResult { throw new \RuntimeException('unexpected database query'); });
		foreach(['name', ['unconfigured']] as $fields) {
			try {
				$service->executeConfig($this->config(), ['fields' => $fields]);
				$this->fail('Invalid fields should not cause a database request.');
			} catch(\InvalidArgumentException $exception) {
				$this->assertStringContainsString('field', $exception->getMessage());
			}
		}
	}

	public function testLegacyRowKeysIgnoreInternalColumnsAndFieldOrder(): void {
		$queries = [];
		$service = $this->service(static function(array $query) use (&$queries): QueryResult {
			$queries[] = $query;
			if(in_array('__total__', array_column($query['fields'], 'alias'), true)) {
				return new QueryResult([], [['__total__' => 2]]);
			}
			return new QueryResult([], [
				['id' => 1, 'name' => 'Alice', '__private' => 'one'],
				['name' => 'Alice', 'id' => 1, '__private' => 'two']
			]);
		});
		$response = $service->executeConfig($this->config());
		$this->assertCount(2, $response['data']);
		$this->assertSame($response['data'][0]['__row_key'], $response['data'][1]['__row_key']);
		$this->assertTrue(str_starts_with($response['data'][0]['__row_key'], 'report-row-'));
	}

	public function testTreeConfigRequiresSupportedReportAndNonemptyKey(): void {
		$trees = $this->createStub(IReportTreeFilterService::class);
		$trees->method('loadTree')->willReturn([['id' => '10', 'label' => 'Top']]);
		$service = $this->service(static fn(array $query): QueryResult => new QueryResult([], []), $trees);
		$this->assertSame([['id' => '10', 'label' => 'Top']], $service->loadTreeConfig($this->config(), ' category '));
		try {
			$service->loadTreeConfig($this->config(), ' ');
			$this->fail('Blank tree filters should not be accepted.');
		} catch(\InvalidArgumentException $exception) {
			$this->assertStringContainsString('Missing tree filter key', $exception->getMessage());
		}
		try {
			$service->loadTreeConfig(['display' => 'metricreportdisplay'], 'category');
			$this->fail('Unsupported report must not load trees.');
		} catch(\RuntimeException $exception) {
			$this->assertStringContainsString('not supported', $exception->getMessage());
		}
	}

	public function testTreeSearchMatchesBreadcrumbsAndEnforcesPageLimits(): void {
		$trees = $this->createStub(IReportTreeFilterService::class);
		$trees->method('loadTree')->willReturn([
			['id' => '1', 'label' => 'North', 'parentId' => ''],
			['id' => '2', 'label' => 'Support', 'parentId' => '1'],
			['id' => '3', 'label' => 'Sales', 'parentId' => '1'],
			['id' => '4', 'label' => 'South', 'parentId' => ''],
			['id' => '5', 'label' => 'Support', 'parentId' => '4']
		]);
		$provider = $this->createStub(IReportConfigProvider::class);
		$provider->method('getConfig')->willReturn($this->config());
		$service = $this->service(static fn(array $query): QueryResult => new QueryResult([], []), $trees, $provider);
		$results = $service->searchTree('scope:report', 'tree', 'north support', 20);
		$this->assertSame(1, $results['matchCount']);
		$this->assertSame('2', $results['nodes'][0]['id']);
		$this->assertSame('North / Support', $results['nodes'][0]['pathLabel']);
		$limited = $service->searchTree('scope:report', 'tree', '', 1);
		$this->assertSame(5, $limited['matchCount']);
		$this->assertTrue($limited['truncated']);
		$this->assertCount(1, $limited['nodes']);
	}

	private function config(): array {
		return ['report' => 'report', 'display' => 'modulargridreportdisplay', 'table' => 'users',
			'fields' => [
				['alias' => 'id', 'element' => ['type' => 'fld', 'table' => 'users', 'field' => 'id']],
				['alias' => 'name', 'element' => ['type' => 'fld', 'table' => 'users', 'field' => 'name']],
				['alias' => 'slug', 'element' => ['type' => 'fld', 'table' => 'users', 'field' => 'slug']]
			]
		];
	}

	private function service(callable $execute, ?IReportTreeFilterService $trees = null, ?IReportConfigProvider $provider = null): ReportDataService {
		$filters = $this->createStub(IReportFilterService::class);
		$filters->method('normalizeFilters')->willReturn([]);
		$trees = $trees ?? $this->createStub(IReportTreeFilterService::class);
		$trees->method('normalizeTreeFilters')->willReturn([]);
		$query = $this->createStub(IQueryService::class);
		$query->method('executeQuery')->willReturnCallback($execute);
		return new ReportDataService(
			$this->createStub(IClassMap::class), $this->createStub(IReportingScopeRegistry::class),
			$provider ?? $this->createStub(IReportConfigProvider::class), $query, $filters, $trees,
			new ModularGridReportQueryBuilder($filters, $trees), $this->createStub(ILogger::class)
		);
	}
}
