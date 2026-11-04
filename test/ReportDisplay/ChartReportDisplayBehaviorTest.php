<?php declare(strict_types=1);

namespace Vizion\Test\ReportDisplay;

use Base3\Api\IAssetResolver;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Dto\QueryResult;
use Vizion\Api\IReportChartService;
use Vizion\Api\IReportFilterService;
use Vizion\ReportDisplay\ChartReportDisplay;

final class ChartReportDisplayBehaviorTest extends TestCase {

	public function testJsonRequestNormalizesFiltersAndKeepsAppliedFilters(): void {
		$capturedQuery = [];
		$capturedFilters = [];
		$fields = [['alias' => 'year', 'config' => ['type' => 'date']]];
		$input = $this->createStub(IRequest::class);
		$input->method('getJsonBody')->willReturn(['filters' => ['year' => '2026']]);
		$filters = $this->createStub(IReportFilterService::class);
		$filters->method('normalizeFilters')->willReturnCallback(static function(mixed $values, array $definitions) use ($fields): array {
			if($definitions !== $fields) throw new \RuntimeException('wrong field definitions');
			return ['year' => trim((string)$values['year'])];
		});
		$chart = $this->createStub(IReportChartService::class);
		$chart->method('buildQuery')->willReturnCallback(static function(array $config, array $definitions, array $values) use (&$capturedFilters): array {
			$capturedFilters = $values;
			return ['type' => 'select', 'table' => $config['table'], 'fields' => $definitions];
		});
		$chart->method('buildPayload')->willReturn(['ok' => true, 'mode' => 'chart', 'labels' => ['2026'], 'datasets' => [['data' => [4]]]]);
		$query = $this->createStub(IQueryService::class);
		$query->method('executeQuery')->willReturnCallback(static function(array $definition) use (&$capturedQuery): QueryResult {
			$capturedQuery = $definition;
			return new QueryResult([], []);
		});
		$display = $this->display($input, $query, $filters, $chart);
		$display->setData(['table' => 'events', 'fields' => $fields]);
		$response = json_decode($display->getOutput('JSON'), true, 512, JSON_THROW_ON_ERROR);
		$this->assertTrue($response['ok']);
		$this->assertSame(['2026'], $response['labels']);
		$this->assertSame(['year' => '2026'], $response['appliedFilters']);
		$this->assertSame(['year' => '2026'], $capturedFilters);
		$this->assertSame('events', $capturedQuery['table']);
		$this->assertSame($fields, $capturedQuery['fields']);
	}

	public function testJsonQueryFailureHasStableErrorPayload(): void {
		$input = $this->createStub(IRequest::class);
		$filters = $this->createStub(IReportFilterService::class);
		$filters->method('normalizeFilters')->willReturn([]);
		$chart = $this->createStub(IReportChartService::class);
		$chart->method('buildQuery')->willReturn(['type' => 'select', 'table' => 'missing']);
		$query = $this->createStub(IQueryService::class);
		$query->method('executeQuery')->willReturnCallback(static function(array $query): QueryResult { throw new \RuntimeException('query failed'); });
		$display = $this->display($input, $query, $filters, $chart);
		$display->setData([]);
		$response = json_decode($display->getOutput('json'), true, 512, JSON_THROW_ON_ERROR);
		$this->assertFalse($response['ok']);
		$this->assertSame('query failed', $response['error']);
		$this->assertSame('chart', $response['mode']);
		$this->assertSame([], $response['labels']);
		$this->assertSame([], $response['datasets']);
		$this->assertSame(0, $response['total']);
	}

	public function testHtmlModeAssignsChartConfigFiltersAndAssets(): void {
		$assigned = [];
		$view = $this->createStub(IMvcView::class);
		$view->method('assign')->willReturnCallback(static function(string $name, mixed $value) use (&$assigned): void { $assigned[$name] = $value; });
		$view->method('loadTemplate')->willReturn('rendered chart');
		$filter = $this->createStub(IReportFilterService::class);
		$filter->method('buildGridFilterFields')->willReturn([['key' => 'year']]);
		$filter->method('buildInitialFilterValues')->willReturn(['year' => 2026]);
		$chart = $this->createStub(IReportChartService::class);
		$chart->method('buildClientConfig')->willReturn(['type' => 'line']);
		$links = $this->createStub(ILinkTargetService::class);
		$links->method('getLink')->willReturn('/reports/chart');
		$assets = $this->createStub(IAssetResolver::class);
		$assets->method('resolve')->willReturnCallback(static fn(string $path): string => '/assets/' . basename($path));
		$display = new ChartReportDisplay($view, $this->createStub(IRequest::class), $this->createStub(IQueryService::class), $links, $assets, $filter, $chart);
		$display->setData(['report' => 'mychart', 'fields' => [['alias' => 'year']]]);
		$this->assertSame('rendered chart', $display->getOutput('html'));
		$this->assertSame('/reports/chart', $assigned['ajaxUrl']);
		$this->assertSame([['key' => 'year']], $assigned['filterFields']);
		$this->assertSame(['year' => 2026], $assigned['filterInitialValues']);
		$this->assertSame(['type' => 'line'], $assigned['chartConfig']);
		$this->assertSame('/assets/chart.js', $assigned['chartJsUrl']);
		$this->assertSame('/assets/vizion-report-chart.js', $assigned['chartToolsJsUrl']);
	}

	public function testHelpUsesProvidedTranslation(): void {
		$view = $this->createStub(IMvcView::class);
		$view->method('getBricks')->willReturn(['help_chart' => 'Translated chart help']);
		$display = new ChartReportDisplay($view, $this->createStub(IRequest::class), $this->createStub(IQueryService::class), $this->createStub(ILinkTargetService::class), $this->createStub(IAssetResolver::class), $this->createStub(IReportFilterService::class), $this->createStub(IReportChartService::class));
		$this->assertSame('Translated chart help', $display->getHelp());
		$this->assertSame('chartreportdisplay', ChartReportDisplay::getName());
	}

	private function display(IRequest $request, IQueryService $query, IReportFilterService $filters, IReportChartService $chart): ChartReportDisplay {
		return new ChartReportDisplay($this->createStub(IMvcView::class), $request, $query, $this->createStub(ILinkTargetService::class), $this->createStub(IAssetResolver::class), $filters, $chart);
	}
}
