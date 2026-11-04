<?php declare(strict_types=1);

namespace Vizion\Test\ReportDisplay;

use Base3\Translation\Api\ITranslation;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Dto\QueryResult;
use Vizion\Api\IReportCellRendererService;
use Vizion\ReportDisplay\MatrixTableReportDisplay;

final class MatrixTableReportDisplayBehaviorTest extends TestCase {

	public function testMissingDetailIdDoesNotQueryDatabase(): void {
		$display = $this->display(static function(array $query): QueryResult { throw new \RuntimeException('unexpected query'); });
		$this->assertSame(['ok' => false, 'found' => false, 'error' => 'Missing matrix detail parameter.'], $this->json($display));
		$display->setData(['payload' => ['mainCourseObjId' => 'not a number']]);
		$this->assertFalse(json_decode($display->getOutput('json'), true, 512, JSON_THROW_ON_ERROR)['found']);
	}

	public function testMissingHeadlineReturnsNotFoundWithoutLoadingOtherSources(): void {
		$queries = [];
		$display = $this->display(static function(array $query) use (&$queries): QueryResult {
			$queries[] = $query;
			return new QueryResult([], []);
		});
		$display->setData(['config' => ['table' => 'headlines'], 'payload' => ['mainCourseObjId' => 41]]);
		$response = json_decode($display->getOutput('json'), true, 512, JSON_THROW_ON_ERROR);
		$this->assertTrue($response['ok']);
		$this->assertFalse($response['found']);
		$this->assertCount(1, $queries);
		$this->assertSame('headlines', $queries[0]['table']);
		$this->assertSame(41, $queries[0]['where']['params'][1]);
	}

	public function testBuildsMatrixCellsAndMissingColumnPlaceholders(): void {
		$queries = [];
		$results = [
			'headlines' => [['id' => 9, 'title' => 'Quarterly Review']],
			'columns' => [['id' => 10, 'label' => 'January'], ['id' => 11, 'label' => 'February']],
			'rows' => [['id' => 20, 'person' => 'Alice'], ['id' => 21, 'person' => 'Bob']],
			'cells' => [
				['row_id' => 20, 'column_id' => 10, 'status' => 'complete', 'percentage' => '96.500', 'mark' => 'A'],
				['row_id' => 20, 'column_id' => 11, 'status' => 'partial', 'percentage' => '12.34', 'mark' => 'B'],
				['row_id' => 0, 'column_id' => 11, 'status' => 'ignored']
			]
		];
		$display = $this->display(static function(array $query) use (&$queries, $results): QueryResult {
			$queries[] = $query;
			return new QueryResult([], $results[$query['table']] ?? []);
		});
		$display->setData(['config' => ['schema' => 'academy'], 'detail' => [
			'parameter' => 'key', 'summary' => '{title}: {rows} by {columns}',
			'headline' => ['table' => 'headlines', 'key' => 'id', 'fields' => ['id', 'title']],
			'columns' => ['table' => 'columns', 'key' => 'report_id', 'columnKey' => 'id', 'labelField' => 'label', 'fields' => ['id', 'label']],
			'rows' => ['table' => 'rows', 'key' => 'report_id', 'rowKey' => 'id', 'fields' => ['id', 'person']],
			'cells' => ['table' => 'cells', 'key' => 'report_id', 'rowKey' => 'row_id', 'columnKey' => 'column_id'],
			'statusLabels' => ['COMPLETE' => 'Finished', 'partial' => 'In progress', 'missing' => 'Not started'],
			'table' => [
				'fixedColumns' => [['key' => 'name', 'field' => 'person', 'label' => 'Person']],
				'cell' => ['statusField' => 'status', 'percentageField' => 'percentage', 'markField' => 'mark', 'defaultStatus' => 'missing']
			]
		], 'payload' => ['key' => 9]]);
		$response = json_decode($display->getOutput('json'), true, 512, JSON_THROW_ON_ERROR);
		$this->assertTrue($response['ok']);
		$this->assertTrue($response['found']);
		$this->assertSame('vizion-matrix-table-subreport', $response['detail']['kind']);
		$this->assertSame('Quarterly Review', $response['detail']['headline']);
		$this->assertSame('Quarterly Review: 2 by 2', $response['detail']['summary']);
		$this->assertSame(['name', 'col_10', 'col_11'], array_column($response['detail']['columns'], 'key'));
		$this->assertSame('Alice', $response['detail']['rows'][0]['name']);
		$this->assertSame('Finished', $response['detail']['rows'][0]['col_10']['label']);
		$this->assertSame('96.5 %', $response['detail']['rows'][0]['col_10']['percentage']);
		$this->assertSame('A', $response['detail']['rows'][0]['col_10']['mark']);
		$this->assertSame('In progress', $response['detail']['rows'][0]['col_11']['label']);
		$this->assertSame('Not started', $response['detail']['rows'][1]['col_10']['label']);
		$this->assertSame('', $response['detail']['rows'][1]['col_10']['percentage']);
		$this->assertSame('academy', $queries[0]['schema']);
		$this->assertSame(1, $queries[0]['limit']);
		$this->assertSame('report_id', $queries[1]['where']['params'][0]['field']);
	}

	public function testConfiguredValueRendererIsAppliedToFixedAndDynamicCells(): void {
		$renderCalls = [];
		$cellRenderer = $this->createStub(IReportCellRendererService::class);
		$cellRenderer->method('renderGridRows')->willReturnCallback(static function(array $rows, array $fields) use (&$renderCalls): array {
			$renderCalls[] = [$rows, $fields];
			return [['__matrix_rendered_value' => '<b>safe</b>']];
		});
		$results = [
			'headline' => [['id' => 1, 'title' => 'Title']],
			'columns' => [['id' => 2]],
			'rows' => [['id' => 3, 'status' => 'ok']],
			'cells' => [['row_id' => 3, 'column_id' => 2, 'status' => 'ok']]
		];
		$display = $this->display(static fn(array $query): QueryResult => new QueryResult([], $results[$query['table']] ?? []), $cellRenderer);
		$display->setData(['detail' => [
			'headline' => ['table' => 'headline'], 'columns' => ['table' => 'columns'], 'rows' => ['table' => 'rows'],
			'cells' => ['table' => 'cells', 'rowKey' => 'row_id', 'columnKey' => 'column_id'],
			'table' => ['fixedColumns' => [['key' => 'fixed', 'field' => 'status', 'valueRenderer' => ['name' => 'text']]],
				'cell' => ['valueRenderer' => ['name' => 'text']]]
		], 'payload' => ['id' => 1]]);
		$response = json_decode($display->getOutput('json'), true, 512, JSON_THROW_ON_ERROR);
		$this->assertSame('<b>safe</b>', $response['detail']['rows'][0]['fixed']['html']);
		$this->assertSame('<b>safe</b>', $response['detail']['rows'][0]['col_2']['html']);
		$this->assertCount(2, $renderCalls);
		$this->assertSame('ok', $renderCalls[0][0][0]['__matrix_rendered_value']);
	}

	public function testBackendExceptionProducesFailurePayload(): void {
		$display = $this->display(static function(array $query): QueryResult { throw new \RuntimeException('read failed'); });
		$display->setData(['payload' => ['id' => 1]]);
		$response = json_decode($display->getOutput('json'), true, 512, JSON_THROW_ON_ERROR);
		$this->assertFalse($response['ok']);
		$this->assertFalse($response['found']);
		$this->assertSame('read failed', $response['error']);
	}

	private function display(callable $execute, ?IReportCellRendererService $renderer = null): MatrixTableReportDisplay {
		$query = $this->createStub(IQueryService::class);
		$query->method('executeQuery')->willReturnCallback($execute);
		$translation = $this->createStub(ITranslation::class);
		$translation->method('translate')->willReturnCallback(static fn(string $module, string $section, string $key, string $fallback): string => $fallback);
		return new MatrixTableReportDisplay($query, $translation, $renderer ?? $this->createStub(IReportCellRendererService::class));
	}

	private function json(MatrixTableReportDisplay $display): array {
		return json_decode($display->getOutput('json'), true, 512, JSON_THROW_ON_ERROR);
	}
}
