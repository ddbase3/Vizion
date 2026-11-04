<?php declare(strict_types=1);

namespace Vizion\Test\Chart;

use Base3\Test\Core\ClassMapStub;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Dto\QueryResult;
use Vizion\Chart\Renderer\BarChartRenderer;
use Vizion\Chart\Renderer\DoughnutChartRenderer;
use Vizion\Chart\Renderer\LineChartRenderer;
use Vizion\Chart\ReportChartService;
use Vizion\Filter\Control\NativeFilterControl;
use Vizion\Filter\ReportFilterService;
use Vizion\Filter\Type\TextFilterType;
use Vizion\Renderer\Column\ValueColumnRenderer;
use Vizion\Renderer\Formatter\NumberValueFormatter;
use Vizion\Renderer\Formatter\TextValueFormatter;
use Vizion\Renderer\ReportCellRendererService;

final class ReportChartServiceBehaviorTest extends TestCase {

	public function testDefaultClientConfigUsesFirstFieldAsDimension(): void {
		$client = $this->service()->buildClientConfig(['chart' => ['title' => 'Records']], $this->fields());
		$this->assertSame('bar', $client['type']);
		$this->assertSame('category', $client['dimension']['field']);
		$this->assertSame('Category', $client['dimension']['label']);
		$this->assertSame('row_count', $client['measures'][0]['alias']);
		$this->assertSame('COUNT', $client['measures'][0]['function']);
		$this->assertTrue($client['options']['plugins']['title']['display']);
	}

	public function testClientConfigNormalizesMeasureAndPresentationType(): void {
		$client = $this->service()->buildClientConfig([
			'presentation' => ['chartType' => 'line', 'title' => 'Default'],
			'chart' => ['title' => 'Override', 'dimension' => ['field' => 'category'], 'measures' => ['total']]
		], $this->fields());
		$this->assertSame('line', $client['type']);
		$this->assertSame('Override', $client['title']);
		$this->assertSame('sum_total', $client['measures'][0]['alias']);
		$this->assertSame('SUM', $client['measures'][0]['function']);
		$this->assertSame('Total', $client['measures'][0]['label']);
		$this->assertSame('number', $client['measures'][0]['formatter']['type']);
	}

	public function testGroupedCountQueryUsesDimensionAndCountFunction(): void {
		$query = $this->service()->buildQuery($this->config(), $this->fields(), []);
		$this->assertSame('select', $query['type']);
		$this->assertSame('records', $query['table']);
		$this->assertSame('__dimension__', $query['fields'][0]['alias']);
		$this->assertSame(['type' => 'fld', 'table' => 'records', 'field' => 'category'], $query['group_by'][0]);
		$this->assertSame('COUNT', $query['fields'][1]['element']['function']);
		$this->assertSame(['*'], $query['fields'][1]['element']['params']);
		$this->assertSame('ASC', $query['order_by'][0]['direction']);
	}

	public function testSumQueryCombinesReportChartAndFilterWhere(): void {
		$config = $this->config();
		$config['where'] = ['type' => 'op', 'operator' => '=', 'params' => ['deleted', 0]];
		$config['chart'] = [
			'dimension' => 'category', 'measures' => [['field' => 'total', 'aggregation' => 'sum']],
			'where' => ['type' => 'op', 'operator' => '<', 'params' => ['total', 100]],
			'sort' => ['by' => 'sum_total', 'direction' => 'desc'],
			'limit' => 5
		];
		$query = $this->service()->buildQuery($config, $this->fields(), []);
		$this->assertSame('SUM', $query['fields'][1]['element']['function']);
		$this->assertSame('AND', $query['where']['operator']);
		$this->assertCount(2, $query['where']['params']);
		$this->assertSame('DESC', $query['order_by'][0]['direction']);
		$this->assertSame(5, $query['limit']);
	}

	public function testPreparedModeSelectsRawMeasureWithoutGrouping(): void {
		$config = $this->config();
		$config['chart'] = [
			'dataMode' => 'prepared', 'dimension' => 'category',
			'measures' => [['field' => 'total', 'aggregation' => 'sum']],
			'sort' => ['by' => 'sum_total', 'direction' => 'desc']
		];
		$query = $this->service()->buildQuery($config, $this->fields(), []);
		$this->assertArrayNotHasKey('group_by', $query);
		$this->assertSame(['type' => 'fld', 'table' => 'records', 'field' => 'total'], $query['fields'][1]['element']);
		$this->assertSame('DESC', $query['order_by'][0]['direction']);
	}

	public function testInvalidDimensionAndPreparedMeasureRejectWithoutQueryExecution(): void {
		$config = ['chart' => ['dimension' => 'category']];
		try {
			$this->service()->buildQuery($config, [], []);
			$this->fail('Missing dimension must not be accepted.');
		} catch(\RuntimeException $exception) {
			$this->assertStringContainsString('Invalid chart dimension', $exception->getMessage());
		}
		$config['chart'] = ['dimension' => ['element' => ['type' => 'fld', 'field' => 'category']], 'dataMode' => 'prepared'];
		try {
			$this->service()->buildQuery($config, [], []);
			$this->fail('Prepared chart must specify an actual measure field.');
		} catch(\RuntimeException $exception) {
			$this->assertStringContainsString('Prepared chart measure', $exception->getMessage());
		}
	}

	public function testPayloadKeepsConfiguredBucketsAndAggregatedNumericRows(): void {
		$config = $this->config();
		$config['chart'] = ['dimension' => 'category', 'buckets' => ['open', 'pending', 'closed']];
		$result = new QueryResult([], [
			['__dimension__' => 'closed', 'row_count' => 3],
			['__dimension__' => 'open', 'row_count' => '5'],
		]);
		$payload = $this->service()->buildPayload($config, $result);
		$this->assertTrue($payload['ok']);
		$this->assertSame('chart', $payload['mode']);
		$this->assertSame(['open', 'pending', 'closed'], $payload['labels']);
		$this->assertSame([5.0, 0.0, 3.0], $payload['datasets'][0]['data']);
		$this->assertSame(3, $payload['total']);
	}

	public function testPayloadConvertsNonNumericMeasuresToZero(): void {
		$config = $this->config();
		$payload = $this->service()->buildPayload($config, new QueryResult([], [
			['__dimension__' => 'active', 'row_count' => 'not numeric'],
			['__dimension__' => 'inactive', 'row_count' => 2]
		]));
		$this->assertSame(['active', 'inactive'], $payload['labels']);
		$this->assertSame([0.0, 2.0], $payload['datasets'][0]['data']);
		$this->assertSame(0.0, $payload['rows'][0]['row_count']);
	}

	public function testMonthlyBucketsFillGapsInChronologicalOrder(): void {
		$config = $this->config();
		$config['chart'] = ['dimension' => ['field' => 'category', 'interval' => 'month']];
		$payload = $this->service()->buildPayload($config, new QueryResult([], [
			['__dimension__' => '2026-03', 'row_count' => 3],
			['__dimension__' => '2026-01', 'row_count' => 1]
		]));
		$this->assertSame(['2026-01', '2026-02', '2026-03'], $payload['labels']);
		$this->assertSame([1.0, 0.0, 3.0], $payload['datasets'][0]['data']);
	}

	private function fields(): array {
		return [
			['alias' => 'category', 'element' => ['type' => 'fld', 'table' => 'records', 'field' => 'category'], 'config' => ['label' => 'Category']],
			['alias' => 'total', 'element' => ['type' => 'fld', 'table' => 'records', 'field' => 'total'], 'config' => ['type' => 'number', 'label' => 'Total']]
		];
	}

	private function config(): array {
		return ['table' => 'records', 'fields' => $this->fields(), 'chart' => ['dimension' => 'category']];
	}

	private function service(): ReportChartService {
		$map = new ClassMapStub();
		foreach([BarChartRenderer::class, DoughnutChartRenderer::class, LineChartRenderer::class, TextFilterType::class,
			NativeFilterControl::class, TextValueFormatter::class, NumberValueFormatter::class, ValueColumnRenderer::class] as $class) {
			$map->register($class);
		}
		return new ReportChartService($map, new ReportFilterService($map), new ReportCellRendererService($map));
	}
}
