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

final class ReportDataServiceTest extends TestCase {

	public function testExecuteReportUsesModularGridSemanticsAndKeepsRowsRaw(): void {
		$configProvider = $this->createStub(IReportConfigProvider::class);
		$configProvider->method('getConfig')->with('ilias:course_report_rows')->willReturn($this->config());

		$filterService = $this->createStub(IReportFilterService::class);
		$filterService->method('normalizeFilters')->willReturn(['status' => 'active']);
		$filterService->method('buildFilterWhere')->willReturn([
			'type' => 'op',
			'operator' => '=',
			'params' => [
				['type' => 'fld', 'table' => 'course_report_rows', 'field' => 'status'],
				'active'
			]
		]);

		$treeService = $this->createStub(IReportTreeFilterService::class);
		$treeService->method('normalizeTreeFilters')->willReturn(['category' => '42']);
		$treeService->method('buildFilterWhere')->willReturn([
			'type' => 'op',
			'operator' => 'IN',
			'params' => [
				['type' => 'fld', 'table' => 'course_report_rows', 'field' => 'obj_id'],
				42
			]
		]);

		$queryService = $this->createMock(IQueryService::class);
		$queryService->expects($this->exactly(2))
			->method('executeQuery')
			->willReturnCallback(function(array $query): QueryResult {
				$aliases = array_column($query['fields'] ?? [], 'alias');
				if(in_array('__total__', $aliases, true)) {
					return new QueryResult([], [['__total__' => 3]], 'COUNT SQL');
				}

				$this->assertSame(['course_title'], $aliases);
				$this->assertSame('AND', $query['where']['operator'] ?? null);
				return new QueryResult(
					[['name' => 'course_title', 'type' => 'string']],
					[['course_title' => 'Course A']],
					'DATA SQL'
				);
			});

		$builder = new ModularGridReportQueryBuilder($filterService, $treeService);
		$service = new ReportDataService(
			$this->createStub(IClassMap::class),
			$this->createStub(IReportingScopeRegistry::class),
			$configProvider,
			$queryService,
			$filterService,
			$treeService,
			$builder,
			$this->createStub(ILogger::class)
		);

		$result = $service->executeReport('ilias:course_report_rows', [
			'filters' => ['status' => 'active'],
			'treeFilters' => ['category' => '42'],
			'fields' => ['course_title'],
			'page' => 1,
			'pageSize' => 50,
		]);

		$this->assertSame(3, $result['total']);
		$this->assertSame(['course_title'], $result['fields']);
		$this->assertSame('Course A', $result['data'][0]['course_title'] ?? null);
		$this->assertArrayHasKey('__row_key', $result['data'][0]);
		$this->assertSame(['status' => 'active'], $result['appliedFilters']);
		$this->assertSame(['category' => '42'], $result['appliedTreeFilters']);
	}

	public function testSearchTreeUsesAndSearchAcrossBreadcrumbPath(): void {
		$configProvider = $this->createStub(IReportConfigProvider::class);
		$configProvider->method('getConfig')->willReturn($this->config());

		$filterService = $this->createStub(IReportFilterService::class);
		$treeService = $this->createStub(IReportTreeFilterService::class);
		$treeService->method('loadTree')->willReturn([
			['id' => '1', 'parentId' => '', 'label' => 'Organisation', 'depth' => 1],
			['id' => '2', 'parentId' => '1', 'label' => 'North', 'depth' => 2],
			['id' => '3', 'parentId' => '2', 'label' => 'Sales', 'depth' => 3],
			['id' => '4', 'parentId' => '1', 'label' => 'South', 'depth' => 2],
		]);

		$service = new ReportDataService(
			$this->createStub(IClassMap::class),
			$this->createStub(IReportingScopeRegistry::class),
			$configProvider,
			$this->createStub(IQueryService::class),
			$filterService,
			$treeService,
			new ModularGridReportQueryBuilder($filterService, $treeService),
			$this->createStub(ILogger::class)
		);

		$result = $service->searchTree('ilias:course_report_rows', 'category', 'north sales', 20);

		$this->assertSame(1, $result['matchCount']);
		$this->assertSame('3', $result['nodes'][0]['id'] ?? null);
		$this->assertSame('Organisation / North / Sales', $result['nodes'][0]['pathLabel'] ?? null);
	}

	/** @return array<string,mixed> */
	private function config(): array {
		return [
			'display' => 'modulargridreportdisplay',
			'table' => 'course_report_rows',
			'config' => [
				'pageSize' => 25,
				'sortColumn' => 'course_title',
				'sortDirection' => 'asc'
			],
			'fields' => [
				[
					'alias' => 'course_title',
					'element' => ['type' => 'fld', 'table' => 'course_report_rows', 'field' => 'course_title'],
					'config' => ['label' => 'Course', 'sortable' => true]
				],
				[
					'alias' => 'status',
					'element' => ['type' => 'fld', 'table' => 'course_report_rows', 'field' => 'status'],
					'config' => [
						'label' => 'Status',
						'filter' => ['enabled' => true, 'type' => 'select', 'match' => 'equals']
					]
				]
			],
			'treeFilters' => [[
				'key' => 'category',
				'label' => 'Categories',
				'description' => 'Repository category subtree.'
			]]
		];
	}
}
