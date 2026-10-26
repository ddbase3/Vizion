<?php declare(strict_types=1);

namespace Vizion\Test\Service;

use PHPUnit\Framework\TestCase;
use Vizion\Api\IReportFilterService;
use Vizion\Api\IReportTreeFilterService;
use Vizion\Service\ModularGridReportQueryBuilder;

final class ModularGridReportQueryBuilderTest extends TestCase {

	public function testBuildDataQueryUsesSameNormalizedSearchFilterTreeFilterAndSortState(): void {
		$filters = $this->createMock(IReportFilterService::class);
		$filters->expects($this->once())
			->method('normalizeFilters')
			->with(['status' => 'active'], $this->isType('array'))
			->willReturn(['status' => 'active']);
		$filters->expects($this->once())
			->method('buildFilterWhere')
			->with(['status' => 'active'], $this->isType('array'), $this->isType('array'))
			->willReturn([
				'type' => 'op',
				'operator' => '=',
				'params' => [
					['type' => 'fld', 'table' => 'report_rows', 'field' => 'status'],
					'active'
				]
			]);

		$treeFilters = $this->createMock(IReportTreeFilterService::class);
		$treeFilters->expects($this->once())
			->method('normalizeTreeFilters')
			->with(['category' => '42'], $this->isType('array'))
			->willReturn(['category' => '42']);
		$treeFilters->expects($this->once())
			->method('buildFilterWhere')
			->with(['category' => '42'], $this->isType('array'))
			->willReturn([
				'type' => 'op',
				'operator' => 'IN',
				'params' => [
					['type' => 'fld', 'table' => 'report_rows', 'field' => 'category_id'],
					42
				]
			]);

		$builder = new ModularGridReportQueryBuilder($filters, $treeFilters);
		$config = $this->config();
		$request = $builder->normalizeRequest([
			'page' => 2,
			'pageSize' => 50,
			'search' => 'Alice',
			'sort' => [['key' => 'name', 'dir' => 'desc']],
			'filters' => ['status' => 'active'],
			'treeFilters' => ['category' => '42']
		], $config);
		$base = $builder->buildBaseQuery($config, $request);
		$query = $builder->buildDataQuery($config, $base, $request);

		$this->assertSame(50, $query['offset'] ?? null);
		$this->assertSame(50, $query['limit'] ?? null);
		$this->assertSame('DESC', $query['order_by'][0]['direction'] ?? null);
		$this->assertSame('AND', $query['where']['operator'] ?? null);
		$this->assertCount(3, $query['where']['params'] ?? []);
	}

	public function testUnpagedExportQueryRemovesLimitAndProjectsRequestedFields(): void {
		$filters = $this->createStub(IReportFilterService::class);
		$filters->method('normalizeFilters')->willReturn([]);
		$filters->method('buildFilterWhere')->willReturn(null);

		$treeFilters = $this->createStub(IReportTreeFilterService::class);
		$treeFilters->method('normalizeTreeFilters')->willReturn([]);
		$treeFilters->method('buildFilterWhere')->willReturn(null);

		$builder = new ModularGridReportQueryBuilder($filters, $treeFilters);
		$config = $this->config();
		$request = $builder->normalizeRequest([], $config);
		$base = $builder->buildBaseQuery($config, $request);
		$query = $builder->buildDataQuery($config, $base, $request, ['status'], false);

		$this->assertArrayNotHasKey('limit', $query);
		$this->assertArrayNotHasKey('offset', $query);
		$this->assertSame(['status'], array_column($query['fields'] ?? [], 'alias'));
	}

	public function testCountOnlyReferencesFieldsNeededByGroupingOrHaving(): void {
		$filters = $this->createStub(IReportFilterService::class);
		$trees = $this->createStub(IReportTreeFilterService::class);
		$builder = new ModularGridReportQueryBuilder($filters, $trees);
		$config = $this->config();
		$config['fields'][] = [
			'alias' => 'custom_attribute',
			'element' => ['type' => 'fld', 'table' => 'attributes', 'field' => 'value', 'tablealias' => 'a'],
			'config' => ['visible' => false]
		];
		$count = $builder->buildCountQuery($config, [
			'table' => 'report_rows',
			'fields' => $config['fields'],
			'limit' => 20,
			'order_by' => []
		]);
		$this->assertSame(['__row__', '__total__'], array_column($count['fields'], 'alias'));
		$this->assertArrayNotHasKey('order_by', $count);
		$this->assertArrayNotHasKey('limit', $count);
	}

	public function testCountRetainsAliasReferencedByHaving(): void {
		$filters = $this->createStub(IReportFilterService::class);
		$trees = $this->createStub(IReportTreeFilterService::class);
		$builder = new ModularGridReportQueryBuilder($filters, $trees);
		$config = $this->config();
		$config['having'] = ['type' => 'op', 'operator' => '>', 'params' => [
			['type' => 'fld', 'field' => 'name'], 0
		]];
		$count = $builder->buildCountQuery($config, $config);
		$this->assertSame(['__row__', 'name', '__total__'], array_column($count['fields'], 'alias'));
	}

	public function testDistinctCountRetainsProjectedFields(): void {
		$filters = $this->createStub(IReportFilterService::class);
		$trees = $this->createStub(IReportTreeFilterService::class);
		$builder = new ModularGridReportQueryBuilder($filters, $trees);
		$config = $this->config();
		$config['distinct'] = true;
		$count = $builder->buildCountQuery($config, $config);
		$this->assertSame(['__row__', 'name', 'status', '__total__'], array_column($count['fields'], 'alias'));
	}

	/**
	 * @return array<string, mixed>
	 */
	private function config(): array {
		return [
			'table' => 'report_rows',
			'config' => [
				'pageSize' => 25,
				'sortColumn' => 'name',
				'sortDirection' => 'asc'
			],
			'fields' => [
				[
					'alias' => 'name',
					'element' => ['type' => 'fld', 'table' => 'report_rows', 'field' => 'name'],
					'config' => ['type' => 'string']
				],
				[
					'alias' => 'status',
					'element' => ['type' => 'fld', 'table' => 'report_rows', 'field' => 'status'],
					'config' => ['type' => 'string', 'search' => false]
				]
			]
		];
	}
}
