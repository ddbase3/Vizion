<?php declare(strict_types=1);

namespace Vizion\Test\Service;

use PHPUnit\Framework\TestCase;
use Vizion\Api\IReportFilterService;
use Vizion\Service\ModularGridReportQueryBuilder;

final class ModularGridReportQueryBuilderTest extends TestCase {

	public function testBuildDataQueryUsesSameNormalizedSearchFilterAndSortState(): void {
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

		$builder = new ModularGridReportQueryBuilder($filters);
		$config = $this->config();
		$request = $builder->normalizeRequest([
			'page' => 2,
			'pageSize' => 50,
			'search' => 'Alice',
			'sort' => [['key' => 'name', 'dir' => 'desc']],
			'filters' => ['status' => 'active']
		], $config);
		$base = $builder->buildBaseQuery($config, $request);
		$query = $builder->buildDataQuery($config, $base, $request);

		$this->assertSame(50, $query['offset'] ?? null);
		$this->assertSame(50, $query['limit'] ?? null);
		$this->assertSame('DESC', $query['order_by'][0]['direction'] ?? null);
		$this->assertSame('AND', $query['where']['operator'] ?? null);
	}

	public function testUnpagedExportQueryRemovesLimitAndProjectsRequestedFields(): void {
		$filters = $this->createStub(IReportFilterService::class);
		$filters->method('normalizeFilters')->willReturn([]);
		$filters->method('buildFilterWhere')->willReturn(null);

		$builder = new ModularGridReportQueryBuilder($filters);
		$config = $this->config();
		$request = $builder->normalizeRequest([], $config);
		$base = $builder->buildBaseQuery($config, $request);
		$query = $builder->buildDataQuery($config, $base, $request, ['status'], false);

		$this->assertArrayNotHasKey('limit', $query);
		$this->assertArrayNotHasKey('offset', $query);
		$this->assertSame(['status'], array_column($query['fields'] ?? [], 'alias'));
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
