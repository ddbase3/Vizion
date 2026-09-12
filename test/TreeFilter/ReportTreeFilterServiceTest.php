<?php declare(strict_types=1);

namespace Vizion\Test\TreeFilter;

use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Dto\QueryResult;
use RuntimeException;
use Vizion\TreeFilter\ReportTreeFilterService;

final class ReportTreeFilterServiceTest extends TestCase {

	public function testLoadTreeUsesConfiguredSourceAndReturnsGenericNodes(): void {
		$queryService = $this->createMock(IQueryService::class);
		$queryService->expects($this->once())
			->method('executeQuery')
			->with($this->callback(function(array $query): bool {
				return ($query['schema'] ?? null) === 'reporting'
					&& ($query['table'] ?? null) === 'category_catalog'
					&& ($query['where']['operator'] ?? null) === '='
					&& ($query['order_by'][0]['direction'] ?? null) === 'ASC';
			}))
			->willReturn(new QueryResult([], [
				['id' => 10, 'parent_id' => 0, 'label' => 'Root', 'depth' => 1],
				['id' => 11, 'parent_id' => 10, 'label' => 'Child', 'depth' => 2],
			]));

		$service = new ReportTreeFilterService($queryService);
		$nodes = $service->loadTree($this->config(), 'category');

		$this->assertSame([
			['id' => '10', 'parentId' => '0', 'label' => 'Root', 'depth' => 1],
			['id' => '11', 'parentId' => '10', 'label' => 'Child', 'depth' => 2],
		], $nodes);
	}

	public function testGridTreeFilterKeepsConfiguredPresentationValues(): void {
		$queryService = $this->createMock(IQueryService::class);
		$service = new ReportTreeFilterService($queryService);
		$fields = $service->buildGridTreeFilters($this->config());

		$this->assertSame('Categories', $fields[0]['label'] ?? null);
		$this->assertSame('Categories', $fields[0]['shortLabel'] ?? null);
		$this->assertSame('Not filtered', $fields[0]['emptyLabel'] ?? null);
		$this->assertSame('Clear category filter', $fields[0]['clearLabel'] ?? null);
		$this->assertSame('#6f7f55', $fields[0]['accentColor'] ?? null);
	}

	public function testNodeFilterBuildsNestedSetSubqueryFromValidatedSelection(): void {
		$queryService = $this->createMock(IQueryService::class);
		$queryService->expects($this->once())
			->method('executeQuery')
			->with($this->callback(function(array $query): bool {
				return ($query['schema'] ?? null) === 'reporting'
					&& ($query['table'] ?? null) === 'category_catalog'
					&& ($query['limit'] ?? null) === 1
					&& ($query['where']['operator'] ?? null) === 'AND';
			}))
			->willReturn(new QueryResult([], [[
				'tree' => 1,
				'lft' => 20,
				'rgt' => 31,
			]]));

		$service = new ReportTreeFilterService($queryService);
		$where = $service->buildFilterWhere(['category' => '11'], $this->config());

		$this->assertSame('IN', $where['operator'] ?? null);
		$this->assertSame('report_rows', $where['params'][0]['table'] ?? null);
		$this->assertSame('obj_id', $where['params'][0]['field'] ?? null);
		$this->assertSame('subquery', $where['params'][1]['type'] ?? null);

		$subquery = $where['params'][1]['query'] ?? [];
		$this->assertSame('category_catalog', $subquery['table'] ?? null);
		$this->assertTrue($subquery['distinct'] ?? false);
		$this->assertSame('object_id', $subquery['fields'][0]['element']['field'] ?? null);
		$this->assertSame('AND', $subquery['where']['operator'] ?? null);
		$this->assertCount(3, $subquery['where']['params'] ?? []);
		$this->assertSame(1, $subquery['where']['params'][0]['params'][1] ?? null);
		$this->assertSame(20, $subquery['where']['params'][1]['params'][1] ?? null);
		$this->assertSame(31, $subquery['where']['params'][2]['params'][1] ?? null);
	}

	public function testNodeFilterUsesSourceIdWhenNoValueElementIsConfigured(): void {
		$queryService = $this->createMock(IQueryService::class);
		$queryService->expects($this->once())
			->method('executeQuery')
			->willReturn(new QueryResult([], [[
				'tree' => 1,
				'lft' => 20,
				'rgt' => 31,
			]]));

		$config = $this->config();
		unset($config['treeFilters'][0]['filter']['value']);

		$service = new ReportTreeFilterService($queryService);
		$where = $service->buildFilterWhere(['category' => '11'], $config);
		$subquery = $where['params'][1]['query'] ?? [];

		$this->assertSame('id', $subquery['fields'][0]['element']['field'] ?? null);
	}

	public function testPathMembershipFilterUsesMaterializedParentChainPath(): void {
		$queryService = $this->createMock(IQueryService::class);
		$queryService->expects($this->once())
			->method('executeQuery')
			->willReturn(new QueryResult([], [[
				'tree' => 1,
				'lft' => 0,
				'rgt' => 0,
			]]));

		$config = $this->config();
		$config['treeFilters'][0]['filter'] = [
			'type' => 'path_membership',
			'element' => $this->field('report_rows', 'obj_id'),
			'source' => [
				'schema' => 'reporting',
				'table' => 'repository_paths',
				'value' => $this->field('repository_paths', 'obj_id'),
				'path' => $this->field('repository_paths', 'repository_path'),
				'delimiter' => ',',
			],
		];

		$service = new ReportTreeFilterService($queryService);
		$where = $service->buildFilterWhere(['category' => '11'], $config);

		$this->assertSame('IN', $where['operator'] ?? null);
		$this->assertSame('obj_id', $where['params'][0]['field'] ?? null);
		$subquery = $where['params'][1]['query'] ?? [];
		$this->assertSame('repository_paths', $subquery['table'] ?? null);
		$this->assertTrue($subquery['distinct'] ?? false);
		$this->assertSame('obj_id', $subquery['fields'][0]['element']['field'] ?? null);
		$this->assertSame('LIKE', $subquery['where']['operator'] ?? null);
		$this->assertSame('repository_path', $subquery['where']['params'][0]['field'] ?? null);
		$this->assertSame('%,11,%', $subquery['where']['params'][1] ?? null);
	}

	public function testRangeFilterUsesSelectedNestedSetBoundsDirectly(): void {
		$queryService = $this->createMock(IQueryService::class);
		$queryService->expects($this->once())
			->method('executeQuery')
			->willReturn(new QueryResult([], [[
				'tree' => 4,
				'lft' => 7,
				'rgt' => 18,
			]]));

		$config = $this->config();
		$config['treeFilters'][0]['filter'] = [
			'type' => 'range',
			'tree' => $this->field('report_rows', 'tree'),
			'left' => $this->field('report_rows', 'lft'),
			'right' => $this->field('report_rows', 'rgt'),
		];

		$service = new ReportTreeFilterService($queryService);
		$where = $service->buildFilterWhere(['category' => '11'], $config);

		$this->assertSame('AND', $where['operator'] ?? null);
		$this->assertSame(4, $where['params'][0]['params'][1] ?? null);
		$this->assertSame(7, $where['params'][1]['params'][1] ?? null);
		$this->assertSame(18, $where['params'][2]['params'][1] ?? null);
	}

	public function testMembershipFilterUsesIndexedMembershipRangeSource(): void {
		$queryService = $this->createMock(IQueryService::class);
		$queryService->expects($this->once())
			->method('executeQuery')
			->willReturn(new QueryResult([], [[
				'tree' => 2,
				'lft' => 13,
				'rgt' => 27,
			]]));

		$config = $this->config();
		$config['treeFilters'][0]['filter'] = [
			'type' => 'membership',
			'element' => $this->field('report_rows', 'usr_id'),
			'source' => [
				'schema' => 'reporting',
				'table' => 'orgunit_assignments',
				'value' => $this->field('orgunit_assignments', 'usr_id'),
				'tree' => $this->field('orgunit_assignments', 'tree'),
				'left' => $this->field('orgunit_assignments', 'lft'),
				'right' => $this->field('orgunit_assignments', 'rgt'),
			],
		];

		$service = new ReportTreeFilterService($queryService);
		$where = $service->buildFilterWhere(['category' => '11'], $config);

		$this->assertSame('IN', $where['operator'] ?? null);
		$this->assertSame('usr_id', $where['params'][0]['field'] ?? null);
		$subquery = $where['params'][1]['query'] ?? [];
		$this->assertSame('orgunit_assignments', $subquery['table'] ?? null);
		$this->assertTrue($subquery['distinct'] ?? false);
		$this->assertSame('usr_id', $subquery['fields'][0]['element']['field'] ?? null);
		$this->assertSame(2, $subquery['where']['params'][0]['params'][1] ?? null);
		$this->assertSame(13, $subquery['where']['params'][1]['params'][1] ?? null);
		$this->assertSame(27, $subquery['where']['params'][2]['params'][1] ?? null);
	}

	public function testUnavailableSelectionFailsInsteadOfBroadeningReport(): void {
		$queryService = $this->createMock(IQueryService::class);
		$queryService->method('executeQuery')->willReturn(new QueryResult([], []));

		$service = new ReportTreeFilterService($queryService);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Tree filter selection is not available for category.');
		$service->buildFilterWhere(['category' => '999'], $this->config());
	}

	/** @return array<string,mixed> */
	private function config(): array {
		return [
			'treeFilters' => [[
				'key' => 'category',
				'label' => 'Categories',
				'shortLabel' => 'Categories',
				'emptyLabel' => 'Not filtered',
				'clearLabel' => 'Clear category filter',
				'accentColor' => '#6f7f55',
				'source' => [
					'schema' => 'reporting',
					'table' => 'category_catalog',
					'id' => $this->field('category_catalog', 'id'),
					'parentId' => $this->field('category_catalog', 'parent_id'),
					'label' => $this->field('category_catalog', 'label'),
					'tree' => $this->field('category_catalog', 'tree'),
					'left' => $this->field('category_catalog', 'lft'),
					'right' => $this->field('category_catalog', 'rgt'),
					'depth' => $this->field('category_catalog', 'depth'),
					'where' => [
						'type' => 'op',
						'operator' => '=',
						'params' => [$this->field('category_catalog', 'active'), 1],
					],
				],
				'filter' => [
					'type' => 'node',
					'element' => $this->field('report_rows', 'obj_id'),
					'value' => $this->field('category_catalog', 'object_id'),
				],
			]],
		];
	}

	/** @return array<string,string> */
	private function field(string $table, string $field): array {
		return [
			'type' => 'fld',
			'table' => $table,
			'field' => $field,
		];
	}
}
