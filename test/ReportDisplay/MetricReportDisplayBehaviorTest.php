<?php declare(strict_types=1);

namespace Vizion\Test\ReportDisplay;

use Base3\Api\IAssetResolver;
use Base3\Api\IMvcView;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Dto\QueryResult;
use Vizion\ReportDisplay\MetricReportDisplay;

final class MetricReportDisplayBehaviorTest extends TestCase {

	public function testMissingOrMalformedMetricDefinitionsReturnEmptyPayload(): void {
		$display = $this->display(static fn(array $query): QueryResult => new QueryResult([], []));
		$this->assertSame(['ok' => true, 'mode' => 'metrics', 'metrics' => [], 'total' => 0], $this->json($display, ['metrics' => 'invalid']));
		$this->assertSame(0, $this->json($display, ['metrics' => [], 'config' => ['metrics' => 'invalid']])['total']);
	}

	public function testDefaultCountQueryAndNumericFormatting(): void {
		$queries = [];
		$display = $this->display(static function(array $query) use (&$queries): QueryResult {
			$queries[] = $query;
			return new QueryResult([], [['value' => 1234.5]], 'SELECT count');
		});
		$response = $this->json($display, ['table' => 'enrollments', 'schema' => 'school', 'metrics' => [[
			'key' => 'total', 'label' => 'Total', 'format' => ['decimals' => 2, 'prefix' => '€', 'suffix' => ' EUR']
		]]]);
		$this->assertSame(1, $response['total']);
		$this->assertSame('1.234,50', substr($response['metrics'][0]['formattedValue'], strlen('€'), 8));
		$this->assertSame('€1.234,50 EUR', $response['metrics'][0]['formattedValue']);
		$this->assertSame('SELECT count', $response['metrics'][0]['debugSql']);
		$this->assertSame('school', $queries[0]['schema']);
		$this->assertSame('enrollments', $queries[0]['table']);
		$this->assertSame('COUNT', $queries[0]['fields'][0]['element']['function']);
		$this->assertSame('value', $queries[0]['fields'][0]['alias']);
	}

	public function testMetricFieldAndCustomElementBuildExpectedQueries(): void {
		$queries = [];
		$display = $this->display(static function(array $query) use (&$queries): QueryResult {
			$queries[] = $query;
			return new QueryResult([], [['amount' => 42]]);
		});
		$response = $this->json($display, ['table' => 'base', 'metrics' => [
			['label' => 'Per user', 'table' => 'users', 'field' => 'amount', 'distinct' => true, 'where' => ['type' => 'op', 'operator' => '>', 'params' => [1, 0]]],
			['key' => 'custom', 'query' => ['table' => 'custom', 'fields' => [['alias' => 'amount', 'element' => ['type' => 'fn', 'function' => 'SUM', 'params' => ['amount']]]]], 'valueField' => 'amount'],
			['key' => 'by_element', 'element' => ['type' => 'fn', 'function' => 'AVG', 'params' => ['age']], 'valueField' => 'amount']
		]]);
		$this->assertSame(3, $response['total']);
		$this->assertSame('per_user', $response['metrics'][0]['key']);
		$this->assertSame('users', $queries[0]['table']);
		$this->assertTrue($queries[0]['distinct']);
		$this->assertSame('amount', $queries[0]['fields'][0]['element']['field']);
		$this->assertSame('>', $queries[0]['where']['operator']);
		$this->assertSame('custom', $queries[1]['table']);
		$this->assertSame('SUM', $queries[1]['fields'][0]['element']['function']);
		$this->assertSame('AVG', $queries[2]['fields'][0]['element']['function']);
	}

	public function testRowCountAndSumModesSkipNonNumericValues(): void {
		$display = $this->display(static fn(array $query): QueryResult => new QueryResult([], [
			['amount' => 10], ['amount' => '2.5'], ['amount' => 'not-a-number'], ['amount' => null]
		]));
		$response = $this->json($display, ['table' => 'grades', 'metrics' => [
			['key' => 'rows', 'valueMode' => 'row_count'],
			['key' => 'sum', 'valueMode' => 'sum', 'valueField' => 'amount', 'format' => ['type' => 'number', 'decimals' => 1]],
			['key' => 'original', 'field' => 'amount', 'format' => 'raw']
		]]);
		$this->assertSame(4, $response['metrics'][0]['value']);
		$this->assertSame(12.5, $response['metrics'][1]['value']);
		$this->assertSame('12,5', $response['metrics'][1]['formattedValue']);
		$this->assertSame('10', $response['metrics'][2]['formattedValue']);
	}

	public function testMissingRowAndNullValuesHaveDistinctRepresentations(): void {
		$display = $this->display(static fn(array $query): QueryResult => new QueryResult([], []));
		$response = $this->json($display, ['table' => 'none', 'metrics' => [['key' => 'empty']]]);
		$this->assertSame(0, $response['metrics'][0]['value']);
		$this->assertSame('0', $response['metrics'][0]['formattedValue']);
		$nullDisplay = $this->display(static fn(array $query): QueryResult => new QueryResult([], [['value' => null]]));
		$response = $this->json($nullDisplay, ['table' => 'none', 'metrics' => [['key' => 'nullable']]]);
		$this->assertNull($response['metrics'][0]['value']);
		$this->assertSame('—', $response['metrics'][0]['formattedValue']);
	}

	public function testMissingTableFailsPerMetricWithoutBreakingOtherMetrics(): void {
		$display = $this->display(static fn(array $query): QueryResult => new QueryResult([], [['value' => 2]]));
		$response = $this->json($display, ['metrics' => [
			['key' => 'missing'],
			['key' => 'valid', 'table' => 'items']
		]]);
		$this->assertTrue($response['ok']);
		$this->assertFalse($response['metrics'][0]['ok']);
		$this->assertStringContainsString('requires a table', $response['metrics'][0]['error']);
		$this->assertTrue($response['metrics'][1]['ok']);
		$this->assertSame(2, $response['metrics'][1]['value']);
	}

	public function testQueryExceptionReturnsErrorCardInsteadOfCrashing(): void {
		$display = $this->display(static function(array $query): QueryResult { throw new \RuntimeException('database unavailable'); });
		$response = $this->json($display, ['table' => 'test', 'metrics' => [['key' => 'error']]]);
		$this->assertFalse($response['metrics'][0]['ok']);
		$this->assertSame('database unavailable', $response['metrics'][0]['error']);
		$this->assertSame('—', $response['metrics'][0]['formattedValue']);
		$this->assertSame(1, $response['total']);
	}

	public function testHtmlAssignsMetricPayloadAndAssetUrl(): void {
		$assigned = [];
		$view = $this->createStub(IMvcView::class);
		$view->method('assign')->willReturnCallback(static function(string $key, mixed $value) use (&$assigned): void { $assigned[$key] = $value; });
		$view->method('loadTemplate')->willReturn('<metric-template/>');
		$assets = $this->createStub(IAssetResolver::class);
		$assets->method('resolve')->willReturn('/assets/metrics.css');
		$database = $this->createStub(IQueryService::class);
		$database->method('executeQuery')->willReturn(new QueryResult([], [['value' => 4]]));
		$display = new MetricReportDisplay($view, $database, $assets);
		$display->setData(['table' => 'values', 'metrics' => [['key' => 'all']]]);
		$this->assertSame('<metric-template/>', $display->getOutput('html'));
		$this->assertSame('all', $assigned['config']['metrics'][0]['key']);
		$this->assertSame(4, $assigned['metrics'][0]['value']);
		$this->assertSame('/assets/metrics.css', $assigned['metricCssUrl']);
	}

	private function display(callable $run): MetricReportDisplay {
		$query = $this->createStub(IQueryService::class);
		$query->method('executeQuery')->willReturnCallback($run);
		return new MetricReportDisplay($this->createStub(IMvcView::class), $query, $this->createStub(IAssetResolver::class));
	}

	private function json(MetricReportDisplay $display, array $config): array {
		$display->setData($config);
		return json_decode($display->getOutput('json'), true, 512, JSON_THROW_ON_ERROR);
	}
}
