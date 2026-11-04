<?php declare(strict_types=1);

namespace Vizion\Test\ReportDisplay;

use Base3\Api\IAssetResolver;
use Base3\Api\IClassMap;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Dto\QueryResult;
use Vizion\Api\IReportCellRendererService;
use Vizion\Api\IReportConfigProvider;
use Vizion\Api\IReportFilterService;
use Vizion\ReportDisplay\MatrixReportDisplay;

final class MatrixReportDisplayBehaviorTest extends TestCase {

	public function testPagedJsonBuildsCountWhereSortAndStableRowKey(): void {
		$queries = [];
		$query = $this->createStub(IQueryService::class);
		$query->method('executeQuery')->willReturnCallback(static function(array $definition) use (&$queries): QueryResult {
			$queries[] = $definition;
			return isset($definition['limit'])
				? new QueryResult([], [['id' => 13, 'name' => 'Alice'], ['id' => 14, 'name' => 'Bob']])
				: new QueryResult([], [['__total__' => 12]]);
		});
		$input = $this->createStub(IRequest::class);
		$input->method('getJsonBody')->willReturn([
			'page' => 2, 'pageSize' => 5, 'search' => ' Alice ',
			'sort' => [['key' => 'name', 'dir' => 'desc']], 'filters' => ['status' => 'active']
		]);
		$filter = $this->createStub(IReportFilterService::class);
		$filter->method('normalizeFilters')->willReturn(['status' => 'active']);
		$filter->method('buildFilterWhere')->willReturn(['type' => 'op', 'operator' => '=', 'params' => ['status', 'active']]);
		$renderer = $this->createStub(IReportCellRendererService::class);
		$renderer->method('renderGridRows')->willReturnCallback(static fn(array $rows): array => $rows);
		$display = $this->display($input, $query, $filter, $renderer);
		$display->setData($this->config());
		$response = $this->json($display);
		$this->assertTrue($response['ok']);
		$this->assertSame(2, $response['page']);
		$this->assertSame(5, $response['pageSize']);
		$this->assertSame(12, $response['total']);
		$this->assertSame(3, $response['totalPages']);
		$this->assertTrue($response['hasMore']);
		$this->assertSame('Alice', $response['appliedSearch']);
		$this->assertSame(['status' => 'active'], $response['appliedFilters']);
		$this->assertSame('matrix-row-13', $response['data'][0]['__row_key']);
		$this->assertSame('matrix-row-14', $response['data'][1]['__row_key']);
		$this->assertSame(5, $queries[1]['offset']);
		$this->assertSame(5, $queries[1]['limit']);
		$this->assertSame('DESC', $queries[1]['order_by'][0]['direction']);
		$this->assertSame('AND', $queries[1]['where']['operator']);
		$this->assertCount(2, $queries[1]['where']['params']);
	}

	public function testUnknownSortAndInvalidPaginationAreNormalized(): void {
		$queries = [];
		$query = $this->createStub(IQueryService::class);
		$query->method('executeQuery')->willReturnCallback(static function(array $value) use (&$queries): QueryResult {
			$queries[] = $value;
			return new QueryResult([], [['__total__' => 0]]);
		});
		$input = $this->createStub(IRequest::class);
		$input->method('getJsonBody')->willReturn(['page' => -5, 'pageSize' => 900, 'sort' => [['key' => 'unconfigured', 'dir' => 'desc']]]);
		$display = $this->display($input, $query);
		$display->setData($this->config());
		$response = $this->json($display);
		$this->assertSame(1, $response['page']);
		$this->assertSame(250, $response['pageSize']);
		$this->assertSame('name', $response['appliedSort'][0]['key']);
		$this->assertSame('desc', $response['appliedSort'][0]['dir']);
		$this->assertFalse($response['hasMore']);
		$this->assertSame(250, $queries[1]['limit']);
	}

	public function testDetailModeDelegatesToExistingDisplayByInterface(): void {
		$seen = [];
		$child = $this->createStub(\Base3\Api\IDisplay::class);
		$child->method('setData')->willReturnCallback(static function($data) use (&$seen): void { $seen = $data; });
		$child->method('getOutput')->willReturn('{"ok":true,"found":true,"detail":{"id":7}}');
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstanceByInterfaceName')->willReturn($child);
		$input = $this->createStub(IRequest::class);
		$input->method('getJsonBody')->willReturn(['mode' => 'matrix-detail', 'id' => 7]);
		$display = $this->display($input, $this->createStub(IQueryService::class), null, null, $map);
		$config = $this->config();
		$config['detail'] = ['display' => 'matrixtablereportdisplay', 'parameter' => 'id'];
		$display->setData($config);
		$response = $this->json($display);
		$this->assertTrue($response['found']);
		$this->assertSame(7, $response['detail']['id']);
		$this->assertSame($config, $seen['config']);
		$this->assertSame($config['detail'], $seen['detail']);
		$this->assertSame('matrix-detail', $seen['payload']['mode']);
	}

	public function testInvalidDetailDisplayAndInvalidChildJsonBecomeErrors(): void {
		$input = $this->createStub(IRequest::class);
		$input->method('getJsonBody')->willReturn(['mode' => 'detail', 'id' => 9]);
		$missing = $this->display($input, $this->createStub(IQueryService::class));
		$missing->setData($this->config());
		$this->assertStringContainsString('Invalid matrix subreport display', $this->json($missing)['error']);
		$child = $this->createStub(\Base3\Api\IDisplay::class);
		$child->method('getOutput')->willReturn('not-json');
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstanceByInterfaceName')->willReturn($child);
		$invalid = $this->display($input, $this->createStub(IQueryService::class), null, null, $map);
		$invalid->setData($this->config());
		$this->assertStringContainsString('returned invalid JSON', $this->json($invalid)['error']);
	}

	public function testMissingReportIdProducesJsonErrorInsteadOfFatal(): void {
		$display = $this->display($this->createStub(IRequest::class), $this->createStub(IQueryService::class));
		$response = $this->json($display);
		$this->assertFalse($response['ok']);
		$this->assertSame(0, $response['total']);
		$this->assertStringContainsString('Missing matrix report identifier', $response['error']);
	}

	public function testHtmlOutputUsesConfiguredColumnAndFilterServices(): void {
		$assigned = [];
		$view = $this->createStub(IMvcView::class);
		$view->method('loadTemplate')->willReturn('<matrix/>');
		$view->method('assign')->willReturnCallback(static function(string $name, mixed $value) use (&$assigned): void { $assigned[$name] = $value; });
		$renderer = $this->createStub(IReportCellRendererService::class);
		$renderer->method('buildGridColumns')->willReturn([['key' => 'name']]);
		$renderer->method('stripInternalGridColumnMetadata')->willReturn([['key' => 'name']]);
		$filters = $this->createStub(IReportFilterService::class);
		$filters->method('buildGridFilterFields')->willReturn([['key' => 'name']]);
		$filters->method('buildInitialFilterValues')->willReturn(['name' => 'a']);
		$links = $this->createStub(ILinkTargetService::class);
		$links->method('getLink')->willReturn('/matrix?report=demo');
		$assets = $this->createStub(IAssetResolver::class);
		$assets->method('resolve')->willReturnCallback(static fn(string $path): string => '/assets/' . basename($path));
		$display = new MatrixReportDisplay($this->createStub(IRequest::class), $view, $assets, $this->createStub(IQueryService::class), $this->createStub(IReportConfigProvider::class), $this->createStub(IClassMap::class), $links, $filters, $renderer);
		$display->setData($this->config());
		$this->assertSame('<matrix/>', $display->getOutput('html'));
		$this->assertSame('/matrix?report=demo', $assigned['ajaxUrl']);
		$this->assertSame([['key' => 'name']], $assigned['columns']);
		$this->assertSame(['name' => 'a'], $assigned['filterInitialValues']);
		$this->assertSame('/assets/index.js', $assigned['modulargridJsUrl']);
		$this->assertSame('matrixreportdisplay', MatrixReportDisplay::getName());
	}

	private function display(IRequest $request, IQueryService $query, ?IReportFilterService $filters = null, ?IReportCellRendererService $renderer = null, ?IClassMap $map = null): MatrixReportDisplay {
		return new MatrixReportDisplay($request, $this->createStub(IMvcView::class), $this->createStub(IAssetResolver::class), $query, $this->createStub(IReportConfigProvider::class), $map ?? $this->createStub(IClassMap::class), $this->createStub(ILinkTargetService::class), $filters ?? $this->createStub(IReportFilterService::class), $renderer ?? $this->createStub(IReportCellRendererService::class));
	}

	private function config(): array {
		return ['report' => 'demo', 'table' => 'users', 'fields' => [
			['alias' => 'name', 'element' => ['type' => 'fld', 'table' => 'users', 'field' => 'name'], 'config' => ['type' => 'text']],
			['alias' => 'id', 'element' => ['type' => 'fld', 'table' => 'users', 'field' => 'id'], 'config' => ['type' => 'number', 'search' => false]]
		], 'config' => ['sortColumn' => 'name', 'sortDirection' => 'asc', 'pageSize' => 20]];
	}

	private function json(MatrixReportDisplay $display): array {
		return json_decode($display->getOutput('json'), true, 512, JSON_THROW_ON_ERROR);
	}
}
