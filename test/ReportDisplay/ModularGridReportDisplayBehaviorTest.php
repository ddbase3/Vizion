<?php declare(strict_types=1);

namespace Vizion\Test\ReportDisplay;

use Base3\Api\IAssetResolver;
use Base3\Api\IClassMap;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Logger\Api\ILogger;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Api\IReportExporter;
use ResourceFoundation\Dto\QueryResult;
use Vizion\Api\IReportCellRendererService;
use Vizion\Api\IReportDataService;
use Vizion\Api\IReportFilterService;
use Vizion\Api\IReportTreeFilterService;
use Vizion\Export\CsvReportExporter;
use Vizion\ReportDisplay\ModularGridReportDisplay;
use Vizion\Service\ModularGridReportQueryBuilder;

final class ModularGridReportDisplayBehaviorTest extends TestCase {

	public function testJsonDelegatesToReportDataServiceAndRendersOnlyReturnedAliases(): void {
		$rendered = [];
		$data = $this->createStub(IReportDataService::class);
		$data->method('executeConfig')->willReturn([
			'ok' => true, 'data' => [['id' => 1, 'name' => 'Alice']],
			'fields' => ['name'], 'report' => 'test', 'total' => 1
		]);
		$renderer = $this->createStub(IReportCellRendererService::class);
		$renderer->method('renderGridRows')->willReturnCallback(static function(array $rows, array $fields) use (&$rendered): array {
			$rendered = $fields;
			return [['id' => 1, 'name' => '<span>Alice</span>']];
		});
		$display = $this->display([], $data, null, null, $renderer);
		$display->setData($this->config());
		$result = $this->json($display, 'json');
		$this->assertTrue($result['ok']);
		$this->assertSame('<span>Alice</span>', $result['data'][0]['name']);
		$this->assertSame(['name'], array_column($rendered, 'alias'));
		$this->assertArrayNotHasKey('fields', $result);
		$this->assertArrayNotHasKey('report', $result);
		$this->assertSame(1, $result['total']);
	}

	public function testJsonServiceExceptionReturnsStableErrorContract(): void {
		$data = $this->createStub(IReportDataService::class);
		$data->method('executeConfig')->willReturnCallback(static function(): array { throw new \RuntimeException('backend failed'); });
		$display = $this->display([], $data);
		$display->setData($this->config());
		$result = $this->json($display, 'json');
		$this->assertFalse($result['ok']);
		$this->assertSame('backend failed', $result['error']);
		$this->assertSame([], $result['data']);
		$this->assertSame([], $result['groups']);
		$this->assertSame(0, $result['total']);
		$this->assertNull($result['nextCursor']);
	}

	public function testTreeModeRequiresKeyAndReturnsNodesFromExistingDataService(): void {
		$data = $this->createStub(IReportDataService::class);
		$data->method('loadTreeConfig')->willReturn([['id' => 'a', 'label' => 'Node A']]);
		$missing = $this->display([], $data);
		$missing->setData($this->config());
		$this->assertFalse($this->json($missing, 'tree')['ok']);
		$this->assertSame([], $this->json($missing, 'tree')['nodes']);
		$valid = $this->display(['key' => 'cat'], $data);
		$valid->setData($this->config());
		$result = $this->json($valid, 'tree');
		$this->assertTrue($result['ok']);
		$this->assertSame([['id' => 'a', 'label' => 'Node A']], $result['nodes']);
	}

	public function testTreeServiceErrorProducesErrorWithoutNodes(): void {
		$data = $this->createStub(IReportDataService::class);
		$data->method('loadTreeConfig')->willReturnCallback(static function(): array { throw new \RuntimeException('tree unavailable'); });
		$display = $this->display(['key' => 'category'], $data);
		$result = $this->json($display, 'tree');
		$this->assertFalse($result['ok']);
		$this->assertSame('tree unavailable', $result['error']);
		$this->assertSame([], $result['nodes']);
	}

	public function testExportRefusesUnconfiguredOrUnknownExporter(): void {
		$display = $this->display(['exporter' => 'csvreportexporter']);
		$display->setData(['table' => 'users', 'export' => ['exporters' => [], 'fields' => ['name']]]);
		$this->assertStringContainsString('not configured', $display->getOutput('export'));
		$display = $this->display(['exporter' => 'jsonreportexporter']);
		$display->setData($this->config());
		$this->assertStringContainsString('not configured', $display->getOutput('export'));
		$display = $this->display(['exporter' => 'csvreportexporter']);
		$display->setData($this->config());
		$this->assertStringContainsString('not available', $display->getOutput('export'));
	}

	public function testExportRejectsInvalidScopeMissingFieldsAndEmptySelectedRows(): void {
		$map = $this->csvMap();
		$invalidScope = $this->display(['exporter' => 'csvreportexporter', 'scope' => 'everything'], null, null, $map);
		$invalidScope->setData($this->config());
		$this->assertStringContainsString('scope is invalid', $invalidScope->getOutput('export'));
		$emptyFieldRequest = $this->display(['exporter' => 'csvreportexporter', 'fields' => ['password']], null, null, $map);
		$emptyFieldRequest->setData($this->config());
		$this->assertStringContainsString('at least one field', $emptyFieldRequest->getOutput('export'));
		$missingRows = $this->display(['exporter' => 'csvreportexporter', 'scope' => 'selected', 'selectedRowIds' => []], null, null, $map);
		$missingRows->setData($this->config());
		$this->assertStringContainsString('at least one row', $missingRows->getOutput('export'));
	}

	public function testFilteredCsvExportRespectsFieldWhitelistAndPreservesRawData(): void {
		$queries = [];
		$query = $this->createStub(IQueryService::class);
		$query->method('executeQuery')->willReturnCallback(static function(array $definition) use (&$queries): QueryResult {
			$queries[] = $definition;
			return new QueryResult([
				['name' => 'name', 'label' => 'Name'], ['name' => 'password', 'label' => 'Secret']
			], [['name' => 'Alice', 'password' => 'never-export']]);
		});
		$display = $this->display(['exporter' => 'csvreportexporter', 'scope' => 'filtered', 'fields' => ['name']], null, $query, $this->csvMap());
		$display->setData($this->config());
		$content = $display->getOutput('export');
		$this->assertStringContainsString('Alice', $content);
		$this->assertStringNotContainsString('never-export', $content);
		$this->assertStringNotContainsString('password', $content);
		$this->assertSame(['name'], array_column($queries[0]['fields'], 'alias'));
		$this->assertArrayNotHasKey('limit', $queries[0]);
	}

	public function testSelectedRowsWithRowKeyFilterAtQueryBoundary(): void {
		$queries = [];
		$query = $this->createStub(IQueryService::class);
		$query->method('executeQuery')->willReturnCallback(static function(array $definition) use (&$queries): QueryResult {
			$queries[] = $definition;
			return new QueryResult([], [['id' => 3, 'name' => 'Chosen']]);
		});
		$config = $this->config();
		$config['rowKey'] = ['id'];
		$builder = new ModularGridReportQueryBuilder($this->createStub(IReportFilterService::class), $this->createStub(IReportTreeFilterService::class));
		$rowKey = $builder->buildRowKey($config, ['id' => 3]);
		$display = $this->display(['exporter' => 'csvreportexporter', 'scope' => 'selected', 'selectedRowIds' => [$rowKey], 'fields' => ['name']], null, $query, $this->csvMap());
		$display->setData($config);
		$this->assertStringContainsString('Chosen', $display->getOutput('export'));
		$this->assertSame('=', $queries[0]['where']['operator']);
		$this->assertSame(3, $queries[0]['where']['params'][1]);
		$this->assertSame(['name'], array_column($queries[0]['fields'], 'alias'));
	}

	public function testSelectedRowsRejectForgedStableIdentifiers(): void {
		$config = $this->config();
		$config['rowKey'] = ['id'];
		$display = $this->display(['exporter' => 'csvreportexporter', 'scope' => 'selected', 'selectedRowIds' => ['arbitrary-row-id']], null, null, $this->csvMap());
		$display->setData($config);
		$this->assertStringContainsString('selected row identifiers are invalid', $display->getOutput('export'));
	}

	public function testHtmlWiresGridColumnFiltersAndExportOptions(): void {
		$assigned = [];
		$view = $this->createStub(IMvcView::class);
		$view->method('assign')->willReturnCallback(static function(string $key, mixed $value) use (&$assigned): void { $assigned[$key] = $value; });
		$view->method('loadTemplate')->willReturn('grid template');
		$renderer = $this->createStub(IReportCellRendererService::class);
		$renderer->method('buildGridColumns')->willReturn([['key' => 'name']]);
		$renderer->method('stripInternalGridColumnMetadata')->willReturn([['key' => 'name']]);
		$filter = $this->createStub(IReportFilterService::class);
		$filter->method('buildGridFilterFields')->willReturn([['key' => 'name']]);
		$filter->method('buildInitialFilterValues')->willReturn(['name' => '']);
		$tree = $this->createStub(IReportTreeFilterService::class);
		$tree->method('buildGridTreeFilters')->willReturn([]);
		$assets = $this->createStub(IAssetResolver::class);
		$assets->method('resolve')->willReturnCallback(static fn(string $path): string => '/assets/' . basename($path));
		$links = $this->createStub(ILinkTargetService::class);
		$links->method('getLink')->willReturn('/generalreport');
		$display = new ModularGridReportDisplay($view, $this->createStub(IRequest::class), $this->createStub(IQueryService::class), $this->createStub(ILogger::class), $links, $assets, $this->csvMap(), $filter, $tree, $this->createStub(IReportDataService::class), $renderer, new ModularGridReportQueryBuilder($filter, $tree));
		$display->setData($this->config());
		$this->assertSame('grid template', $display->getOutput('html'));
		$this->assertSame('/generalreport', $assigned['ajaxUrl']);
		$this->assertSame('/generalreport', $assigned['exportUrl']);
		$this->assertSame('/generalreport', $assigned['treeUrl']);
		$this->assertSame('csvreportexporter', $assigned['exportOptions']['defaultExporter']);
		$this->assertSame(['name'], $assigned['exportOptions']['defaultFields']);
		$this->assertSame(['name', 'id'], array_column($assigned['exportOptions']['fields'], 'key'));
		$this->assertSame([['key' => 'name']], $assigned['columns']);
		$this->assertSame('/assets/index.js', $assigned['modulargridJsUrl']);
	}


	public function testLegacySelectedExportKeepsOnlyMatchingContentDerivedRows(): void {
		$rows = [
			['id' => 3, 'name' => 'Chosen', 'password' => 'private'],
			['id' => 4, 'name' => 'Other', 'password' => 'not-for-export']
		];
		$queries = [];
		$query = $this->createStub(IQueryService::class);
		$query->method('executeQuery')->willReturnCallback(static function(array $definition) use (&$queries, $rows): QueryResult {
			$queries[] = $definition;
			return new QueryResult([], $rows);
		});
		$rowId = 'report-row-' . substr(sha1(json_encode($rows[0], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 0, 20);
		$display = $this->display([
			'exporter' => 'csvreportexporter', 'scope' => 'selected',
			'selectedRowIds' => [null, '', $rowId, $rowId, []], 'fields' => ['name']
		], null, $query, $this->csvMap());
		$display->setData($this->config());
		$content = $display->getOutput('export');
		$this->assertStringContainsString('Chosen', $content);
		$this->assertStringNotContainsString('Other', $content);
		$this->assertStringNotContainsString('private', $content);
		$this->assertStringNotContainsString('not-for-export', $content);
		$this->assertSame(['id', 'name', 'password'], array_column($queries[0]['fields'], 'alias'));
	}

	public function testAllExportIgnoresClientSideSearchWhileFilteredExportAppliesIt(): void {
		$queries = [];
		$query = $this->createStub(IQueryService::class);
		$query->method('executeQuery')->willReturnCallback(static function(array $definition) use (&$queries): QueryResult {
			$queries[] = $definition;
			return new QueryResult([], [['name' => 'Alice']]);
		});
		$all = $this->display(['exporter' => 'csvreportexporter', 'scope' => 'all', 'request' => ['search' => 'Alice'], 'fields' => ['name']], null, $query, $this->csvMap());
		$all->setData($this->config());
		$this->assertStringContainsString('Alice', $all->getOutput('export'));
		$this->assertArrayNotHasKey('where', $queries[0]);
		$filtered = $this->display(['exporter' => 'csvreportexporter', 'scope' => 'filtered', 'request' => ['search' => 'Alice'], 'fields' => ['name']], null, $query, $this->csvMap());
		$filtered->setData($this->config());
		$this->assertStringContainsString('Alice', $filtered->getOutput('export'));
		$this->assertSame('OR', $queries[1]['where']['operator']);
	}

	private function csvMap(): IClassMap {
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstanceByInterfaceName')->willReturnCallback(static fn(string $interface, string $name): ?IReportExporter => $name === 'csvreportexporter' ? new CsvReportExporter() : null);
		return $map;
	}

	private function config(): array {
		return [
			'report' => 'people', 'table' => 'users', 'config' => ['pageSize' => 20],
			'fields' => [
				['alias' => 'id', 'element' => ['type' => 'fld', 'table' => 'users', 'field' => 'id'], 'config' => ['label' => 'ID']],
				['alias' => 'name', 'element' => ['type' => 'fld', 'table' => 'users', 'field' => 'name'], 'config' => ['label' => 'Name']],
				['alias' => 'password', 'element' => ['type' => 'fld', 'table' => 'users', 'field' => 'password'], 'config' => ['label' => 'Secret']]
			],
			'export' => ['exporters' => ['csvreportexporter'], 'fields' => ['name', 'id'], 'defaultFields' => ['name']]
		];
	}

	private function display(array $payload, ?IReportDataService $data = null, ?IQueryService $query = null, ?IClassMap $map = null, ?IReportCellRendererService $renderer = null): ModularGridReportDisplay {
		$request = $this->createStub(IRequest::class);
		$request->method('getJsonBody')->willReturn($payload);
		$filters = $this->createStub(IReportFilterService::class);
		$filters->method('normalizeFilters')->willReturn([]);
		$trees = $this->createStub(IReportTreeFilterService::class);
		$trees->method('normalizeTreeFilters')->willReturn([]);
		return new ModularGridReportDisplay(
			$this->createStub(IMvcView::class), $request,
			$query ?? $this->createStub(IQueryService::class), $this->createStub(ILogger::class),
			$this->createStub(ILinkTargetService::class), $this->createStub(IAssetResolver::class),
			$map ?? $this->createStub(IClassMap::class), $filters, $trees,
			$data ?? $this->createStub(IReportDataService::class), $renderer ?? $this->createStub(IReportCellRendererService::class),
			new ModularGridReportQueryBuilder($filters, $trees)
		);
	}

	private function json(ModularGridReportDisplay $display, string $mode): array {
		return json_decode($display->getOutput($mode), true, 512, JSON_THROW_ON_ERROR);
	}
}
