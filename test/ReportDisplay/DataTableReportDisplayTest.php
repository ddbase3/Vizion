<?php declare(strict_types=1);

namespace Vizion\Test\ReportDisplay;

use PHPUnit\Framework\TestCase;
use Vizion\ReportDisplay\DataTableReportDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IAssetResolver;
use Base3\Logger\Api\ILogger;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Dto\QueryResult;
use ResourceFoundation\Dto\TableMetadata;

if (!defined('DIR_PLUGIN')) {
	define('DIR_PLUGIN', '/plugins/');
}

final class DataTableReportDisplayTest extends TestCase {

	public function testGetNameReturnsExpectedValue(): void {
		$this->assertSame('datatablereportdisplay', DataTableReportDisplay::getName());
	}

	public function testGetHelpReturnsExpectedText(): void {
		$display = $this->createDisplay();
		$this->assertSame(
			'Displays data as a jQuery DataTable using the Vizion ReportDisplay system.',
			$display->getHelp()
		);
	}

	public function testGetOutputJsonBuildsQueriesAndReturnsExpectedJson(): void {
		$view = new FakeMvcView();

		$countResult = $this->makeQueryResult(
			[['__total__' => 15]],
			'COUNT SQL'
		);

		$dataResult = $this->makeQueryResult(
			[
				['name' => 'Alice'],
				['name' => 'Bob'],
			],
			'DATA SQL'
		);

		$queryService = new FakeQueryService([$countResult, $dataResult]);
		$assetResolver = new FakeAssetResolver();
		$logger = new FakeLogger();

		$display = new DataTableReportDisplay($view, $queryService, $assetResolver, $logger);

		$backupGet = $_GET ?? [];
		$_GET = [
			'sort' => 'name',
			'direction' => 'desc',
			'pageSize' => '2',
			'page' => '2',
			'filter' => [
				'name' => 'Al',
			],
		];

		$config = [
			'fields' => [
				[
					'alias' => 'name',
					'element' => [
						'type' => 'fld',
						'table' => 'users',
						'field' => 'name',
					],
					'config' => [
						'label' => 'Name',
						'visible' => true,
					],
				],
			],
			'config' => [
				'pageSize' => 5,
			],
			'where' => [
				'type' => 'op',
				'operator' => '=',
				'params' => ['1', '1'],
			],
		];

		$display->setData($config);
		$json = $display->getOutput('json');

		$_GET = $backupGet;

		$data = json_decode($json, true);
		$this->assertIsArray($data);

		$this->assertSame(15, $data['total']);
		$this->assertSame(2, $data['page']);
		$this->assertSame(2, $data['pageSize']);
		$this->assertSame(8, $data['totalPages']);

		$this->assertSame(
			[
				['name' => 'Alice'],
				['name' => 'Bob'],
			],
			$data['data']
		);

		$this->assertCount(2, $queryService->queries);

		$countQuery = $queryService->queries[0];
		$dataQuery = $queryService->queries[1];

		$this->assertArrayHasKey('fields', $countQuery);
		$lastField = end($countQuery['fields']);
		$this->assertSame('__total__', $lastField['alias']);
		$this->assertArrayNotHasKey('limit', $countQuery);
		$this->assertArrayNotHasKey('offset', $countQuery);

		$this->assertSame(2, $dataQuery['limit']);
		$this->assertSame(2, $dataQuery['offset']);

		$this->assertArrayHasKey('order_by', $dataQuery);
		$this->assertSame('DESC', $dataQuery['order_by'][0]['direction']);

		$this->assertCount(2, $logger->legacyLogs);
		$this->assertSame('Vizion', $logger->legacyLogs[0]['scope']);
		$this->assertStringContainsString('CNT |', $logger->legacyLogs[0]['message']);
		$this->assertStringContainsString('COUNT SQL', $logger->legacyLogs[0]['message']);

		$this->assertSame('Vizion', $logger->legacyLogs[1]['scope']);
		$this->assertStringContainsString('RES |', $logger->legacyLogs[1]['message']);
		$this->assertStringContainsString('DATA SQL', $logger->legacyLogs[1]['message']);
	}

	public function testGetOutputHtmlAssignsViewVariablesAndUsesAssetResolver(): void {
		$view = new FakeMvcView();
		$view->output = 'TEMPLATE OUTPUT';

		$queryService = new FakeQueryService();
		$assetResolver = new FakeAssetResolver();
		$logger = new FakeLogger();

		$display = new DataTableReportDisplay($view, $queryService, $assetResolver, $logger);

		$config = [
			'report' => 'my_report',
			'fields' => [
				[
					'alias' => 'name',
					'element' => [
						'type' => 'fld',
						'table' => 'users',
						'field' => 'name',
					],
					'config' => [
						'label' => 'User Name',
						'visible' => false,
					],
				],
			],
			'config' => [
				'pageSize' => 10,
			],
		];

		$display->setData($config);
		$html = $display->getOutput('html');

		$this->assertSame('TEMPLATE OUTPUT', $html);

		$this->assertSame(DIR_PLUGIN . 'Vizion', $view->path);
		$this->assertSame('ReportDisplay/DataTableReportDisplay.php', $view->template);

		$this->assertSame(
			'?name=generalreportdisplay&out=json&report=' . urlencode('my_report'),
			$view->assigned['ajaxUrl'] ?? null
		);

		$columns = $view->assigned['columns'] ?? null;
		$this->assertIsArray($columns);
		$this->assertSame('name', $columns[0]['key']);
		$this->assertSame('User Name', $columns[0]['label']);
		$this->assertFalse($columns[0]['visible']);

		$this->assertSame($config, $view->assigned['config'] ?? null);

		$resolve = $view->assigned['resolve'] ?? null;
		$this->assertIsCallable($resolve);

		$resultPath = $resolve('css/style.css');
		$this->assertSame('/assets/css/style.css', $resultPath);
		$this->assertSame(['css/style.css'], $assetResolver->resolved);
	}

	public function testGetOutputDefaultsToHtml(): void {
		$view = new FakeMvcView();
		$view->output = 'DEFAULT HTML';

		$queryService = new FakeQueryService();
		$assetResolver = new FakeAssetResolver();
		$logger = new FakeLogger();

		$display = new DataTableReportDisplay($view, $queryService, $assetResolver, $logger);
		$display->setData([]);

		$this->assertSame('DEFAULT HTML', $display->getOutput());
	}

	private function createDisplay(): DataTableReportDisplay {
		return new DataTableReportDisplay(
			new FakeMvcView(),
			new FakeQueryService(),
			new FakeAssetResolver(),
			new FakeLogger()
		);
	}

	private function makeQueryResult(array $rows, string $debugSql): QueryResult {
		$ref = new \ReflectionClass(QueryResult::class);
		/** @var QueryResult $qr */
		$qr = $ref->newInstanceWithoutConstructor();
		$qr->rows = $rows;
		$qr->debugSql = $debugSql;
		return $qr;
	}
}

class FakeMvcView implements IMvcView {

	public string $path = '.';
	public string $template = 'default';
	public array $assigned = [];
	public string $output = '';
	public array $bricks = [];

	public function setPath(string $path = '.'): void {
		$this->path = $path;
	}

	public function assign(string $key, $value): void {
		$this->assigned[$key] = $value;
	}

	public function setTemplate(string $template = 'default'): void {
		$this->template = $template;
	}

	public function loadTemplate(): string {
		return $this->output;
	}

	public function loadBricks(string $set, string $language = ''): void {
		$this->bricks[$set] = $this->bricks[$set] ?? [];
	}

	public function getBricks(string $set): ?array {
		return $this->bricks[$set] ?? null;
	}
}

class FakeQueryService implements IQueryService {

	public array $results;
	public array $queries = [];

	public function __construct(array $results = []) {
		$this->results = $results;
	}

	public function listTables(): array {
		return [];
	}

	public function getTable(string $tableName): ?TableMetadata {
		return null;
	}

	public function executeQuery(array $queryJson): QueryResult {
		$this->queries[] = $queryJson;

		if (count($this->results) > 0) {
			$result = array_shift($this->results);
			if ($result instanceof QueryResult) return $result;
		}

		$ref = new \ReflectionClass(QueryResult::class);
		/** @var QueryResult $fallback */
		$fallback = $ref->newInstanceWithoutConstructor();
		$fallback->rows = [];
		$fallback->debugSql = '';
		return $fallback;
	}

	public function listDomains(): array {
		return [];
	}

	public function listCategories(): array {
		return [];
	}

	public function listTags(): array {
		return [];
	}
}

class FakeAssetResolver implements IAssetResolver {

	public array $resolved = [];

	public function resolve(string $path): string {
		$this->resolved[] = $path;
		return '/assets/' . $path;
	}
}

class FakeLogger implements ILogger {

	public array $legacyLogs = [];

	public function emergency(string|\Stringable $message, array $context = []): void {}
	public function alert(string|\Stringable $message, array $context = []): void {}
	public function critical(string|\Stringable $message, array $context = []): void {}
	public function error(string|\Stringable $message, array $context = []): void {}
	public function warning(string|\Stringable $message, array $context = []): void {}
	public function notice(string|\Stringable $message, array $context = []): void {}
	public function info(string|\Stringable $message, array $context = []): void {}
	public function debug(string|\Stringable $message, array $context = []): void {}
	public function logLevel(string $level, string|\Stringable $message, array $context = []): void {}

	public function log(string $scope, string $log, ?int $timestamp = null): bool {
		$this->legacyLogs[] = [
			'scope' => $scope,
			'message' => $log,
			'timestamp' => $timestamp,
		];
		return true;
	}

	public function getScopes(): array {
		return [];
	}

	public function getNumOfScopes() {
		return 0;
	}

	public function getLogs(string $scope, int $num = 50, bool $reverse = true): array {
		return [];
	}
}
