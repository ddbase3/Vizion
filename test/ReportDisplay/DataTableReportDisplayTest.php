<?php declare(strict_types=1);

namespace Vizion\Test\ReportDisplay;

use PHPUnit\Framework\TestCase;
use Vizion\ReportDisplay\DataTableReportDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IAssetResolver;
use Base3\Configuration\Api\IConfiguration;
use Base3\Logger\Api\ILogger;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Dto\QueryResult;

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
		$viewState = $this->makeViewState();
		$view = $this->createMvcViewStub($viewState);

		$countResult = $this->makeQueryResult([['__total__' => 15]], 'COUNT SQL');
		$dataResult = $this->makeQueryResult(
			[
				['name' => 'Alice'],
				['name' => 'Bob'],
			],
			'DATA SQL'
		);

		$queryState = $this->makeQueryState([$countResult, $dataResult]);
		$queryService = $this->createQueryServiceStub($queryState);

		$assetState = ['resolved' => []];
		$assetResolver = $this->createAssetResolverStub($assetState);

		$logState = ['legacyLogs' => []];
		$logger = $this->createLoggerStub($logState);

		$configState = ['base' => ['endpoint' => '']];
		$configuration = $this->createConfigurationStub($configState);

		$display = new DataTableReportDisplay($view, $queryService, $assetResolver, $logger, $configuration);

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

		$this->assertCount(2, $queryState['queries']);

		$countQuery = $queryState['queries'][0];
		$dataQuery = $queryState['queries'][1];

		$this->assertSame('select', $countQuery['type'] ?? null);
		$this->assertArrayHasKey('fields', $countQuery);

		$lastField = end($countQuery['fields']);
		$this->assertSame('__total__', $lastField['alias'] ?? null);
		$this->assertSame('windowfn', $lastField['element']['type'] ?? null);
		$this->assertSame('COUNT', $lastField['element']['function'] ?? null);

		$this->assertArrayNotHasKey('limit', $countQuery);
		$this->assertArrayNotHasKey('offset', $countQuery);
		$this->assertArrayNotHasKey('order_by', $countQuery);

		$this->assertSame(2, $dataQuery['limit']);
		$this->assertSame(2, $dataQuery['offset']);

		$this->assertArrayHasKey('order_by', $dataQuery);
		$this->assertSame('DESC', $dataQuery['order_by'][0]['direction']);

		$this->assertCount(2, $logState['legacyLogs']);
		$this->assertSame('Vizion', $logState['legacyLogs'][0]['scope']);
		$this->assertStringContainsString('CNT |', $logState['legacyLogs'][0]['message']);
		$this->assertStringContainsString('COUNT SQL', $logState['legacyLogs'][0]['message']);

		$this->assertSame('Vizion', $logState['legacyLogs'][1]['scope']);
		$this->assertStringContainsString('RES |', $logState['legacyLogs'][1]['message']);
		$this->assertStringContainsString('DATA SQL', $logState['legacyLogs'][1]['message']);
	}

	public function testGetOutputJsonCopiesGroupAndHavingToBothQueries(): void {
		$viewState = $this->makeViewState();
		$view = $this->createMvcViewStub($viewState);

		$countResult = $this->makeQueryResult([['__total__' => 1]], 'COUNT SQL');
		$dataResult = $this->makeQueryResult([['x' => 1]], 'DATA SQL');

		$queryState = $this->makeQueryState([$countResult, $dataResult]);
		$queryService = $this->createQueryServiceStub($queryState);

		$assetResolver = $this->createStub(IAssetResolver::class);
		$logger = $this->createStub(ILogger::class);

		$configState = ['base' => ['endpoint' => '']];
		$configuration = $this->createConfigurationStub($configState);

		$display = new DataTableReportDisplay($view, $queryService, $assetResolver, $logger, $configuration);

		$backupGet = $_GET ?? [];
		$_GET = [];

		$config = [
			'fields' => [
				[
					'alias' => 'x',
					'element' => [
						'type' => 'fld',
						'table' => 't',
						'field' => 'x',
					],
					'config' => [],
				],
			],
			'group' => [
				['type' => 'fld', 'table' => 't', 'field' => 'x'],
			],
			'having' => [
				'type' => 'op',
				'operator' => '>',
				'params' => [
					['type' => 'fld', 'table' => 't', 'field' => 'x'],
					0,
				],
			],
		];

		$display->setData($config);
		$display->getOutput('json');

		$_GET = $backupGet;

		$this->assertCount(2, $queryState['queries']);

		$countQuery = $queryState['queries'][0];
		$dataQuery = $queryState['queries'][1];

		$this->assertSame($config['group'], $countQuery['group'] ?? null);
		$this->assertSame($config['having'], $countQuery['having'] ?? null);

		$this->assertSame($config['group'], $dataQuery['group'] ?? null);
		$this->assertSame($config['having'], $dataQuery['having'] ?? null);
	}

	public function testGetOutputJsonDoesNotSetOrderByWhenSortAliasIsUnknown(): void {
		$viewState = $this->makeViewState();
		$view = $this->createMvcViewStub($viewState);

		$countResult = $this->makeQueryResult([['__total__' => 1]], 'COUNT SQL');
		$dataResult = $this->makeQueryResult([['name' => 'Alice']], 'DATA SQL');

		$queryState = $this->makeQueryState([$countResult, $dataResult]);
		$queryService = $this->createQueryServiceStub($queryState);

		$assetResolver = $this->createStub(IAssetResolver::class);
		$logger = $this->createStub(ILogger::class);

		$configState = ['base' => ['endpoint' => '']];
		$configuration = $this->createConfigurationStub($configState);

		$display = new DataTableReportDisplay($view, $queryService, $assetResolver, $logger, $configuration);

		$backupGet = $_GET ?? [];
		$_GET = [
			'sort' => 'does_not_exist',
			'direction' => 'desc',
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
					'config' => [],
				],
			],
		];

		$display->setData($config);
		$display->getOutput('json');

		$_GET = $backupGet;

		$dataQuery = $queryState['queries'][1];
		$this->assertArrayNotHasKey('order_by', $dataQuery);
	}

	public function testGetOutputHtmlAssignsViewVariablesAndUsesAssetResolver(): void {
		$viewState = $this->makeViewState();
		$viewState['output'] = 'TEMPLATE OUTPUT';
		$view = $this->createMvcViewStub($viewState);

		$queryState = $this->makeQueryState([]);
		$queryService = $this->createQueryServiceStub($queryState);

		$assetState = ['resolved' => []];
		$assetResolver = $this->createAssetResolverStub($assetState);

		$logger = $this->createStub(ILogger::class);

		$configState = ['base' => ['endpoint' => '']];
		$configuration = $this->createConfigurationStub($configState);

		$display = new DataTableReportDisplay($view, $queryService, $assetResolver, $logger, $configuration);

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

		$this->assertSame(DIR_PLUGIN . 'Vizion', $viewState['path']);
		$this->assertSame('ReportDisplay/DataTableReportDisplay.php', $viewState['template']);

		$this->assertSame(
			'?name=generalreportdisplay&out=json&report=' . urlencode('my_report'),
			$viewState['assigned']['ajaxUrl'] ?? null
		);

		$columns = $viewState['assigned']['columns'] ?? null;
		$this->assertIsArray($columns);
		$this->assertSame('name', $columns[0]['key']);
		$this->assertSame('User Name', $columns[0]['label']);
		$this->assertFalse($columns[0]['visible']);

		$this->assertSame($config, $viewState['assigned']['config'] ?? null);

		$resolve = $viewState['assigned']['resolve'] ?? null;
		$this->assertIsCallable($resolve);

		$resultPath = $resolve('css/style.css');
		$this->assertSame('/assets/css/style.css', $resultPath);
		$this->assertSame(['css/style.css'], $assetState['resolved']);
	}

	public function testGetOutputDefaultsToHtml(): void {
		$viewState = $this->makeViewState();
		$viewState['output'] = 'DEFAULT HTML';
		$view = $this->createMvcViewStub($viewState);

		$queryState = $this->makeQueryState([]);
		$queryService = $this->createQueryServiceStub($queryState);

		$assetResolver = $this->createStub(IAssetResolver::class);
		$logger = $this->createStub(ILogger::class);

		$configState = ['base' => ['endpoint' => '']];
		$configuration = $this->createConfigurationStub($configState);

		$display = new DataTableReportDisplay($view, $queryService, $assetResolver, $logger, $configuration);
		$display->setData([]);

		$this->assertSame('DEFAULT HTML', $display->getOutput());
	}

	private function createDisplay(): DataTableReportDisplay {
		$viewState = $this->makeViewState();
		$queryState = $this->makeQueryState([]);
		$assetState = ['resolved' => []];
		$logState = ['legacyLogs' => []];
		$configState = ['base' => ['endpoint' => '']];

		return new DataTableReportDisplay(
			$this->createMvcViewStub($viewState),
			$this->createQueryServiceStub($queryState),
			$this->createAssetResolverStub($assetState),
			$this->createLoggerStub($logState),
			$this->createConfigurationStub($configState)
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

	private function makeViewState(): array {
		return [
			'path' => '.',
			'template' => 'default',
			'assigned' => [],
			'output' => '',
			'bricks' => [],
		];
	}

	private function makeQueryState(array $results): array {
		return [
			'results' => $results,
			'queries' => [],
		];
	}

	private function createMvcViewStub(array &$state): IMvcView {
		$view = $this->createStub(IMvcView::class);

		$view->method('setPath')->willReturnCallback(function(string $path = '.') use (&$state): void {
			$state['path'] = $path;
		});

		$view->method('assign')->willReturnCallback(function(string $key, $value) use (&$state): void {
			$state['assigned'][$key] = $value;
		});

		$view->method('setTemplate')->willReturnCallback(function(string $template = 'default') use (&$state): void {
			$state['template'] = $template;
		});

		$view->method('loadTemplate')->willReturnCallback(function() use (&$state): string {
			return (string)$state['output'];
		});

		return $view;
	}

	private function createQueryServiceStub(array &$state): IQueryService {
		$svc = $this->createStub(IQueryService::class);

		$svc->method('executeQuery')->willReturnCallback(function(array $queryJson) use (&$state): QueryResult {
			$state['queries'][] = $queryJson;

			if (count($state['results']) > 0) {
				$next = array_shift($state['results']);
				if ($next instanceof QueryResult) {
					return $next;
				}
			}

			$ref = new \ReflectionClass(QueryResult::class);
			/** @var QueryResult $fallback */
			$fallback = $ref->newInstanceWithoutConstructor();
			$fallback->rows = [];
			$fallback->debugSql = '';
			return $fallback;
		});

		return $svc;
	}

	private function createAssetResolverStub(array &$state): IAssetResolver {
		$ar = $this->createStub(IAssetResolver::class);

		$ar->method('resolve')->willReturnCallback(function(string $path) use (&$state): string {
			$state['resolved'][] = $path;
			return '/assets/' . $path;
		});

		return $ar;
	}

	private function createLoggerStub(array &$state): ILogger {
		$logger = $this->createStub(ILogger::class);

		$logger->method('log')->willReturnCallback(function(string $scope, string $log, ?int $timestamp = null) use (&$state): bool {
			$state['legacyLogs'][] = [
				'scope' => $scope,
				'message' => $log,
				'timestamp' => $timestamp,
			];
			return true;
		});

		return $logger;
	}

	private function createConfigurationStub(array &$state): IConfiguration {
		$cfg = $this->createStub(IConfiguration::class);

		$cfg->method('get')->willReturnCallback(function($configuration = "") use (&$state) {
			if ($configuration === "" || $configuration === null) {
				return $state;
			}
			if (is_string($configuration) && array_key_exists($configuration, $state)) {
				return $state[$configuration];
			}
			return null;
		});

		return $cfg;
	}
}
