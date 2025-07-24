<?php declare(strict_types=1);

namespace Vizion\ReportDisplay;

use Base3\Api\IAssetResolver;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Logger\Api\ILogger;
use DataHawk\Api\IReportQueryService;
use DataHawk\Dto\QueryResult;

class DataTableReportDisplay implements IDisplay {

	private ?array $config = null;
	private ?QueryResult $result = null;

	private $logSql = false;

	public function __construct(
		private readonly IMvcView $view,
		private readonly IReportQueryService $reportqueryservice,
		private readonly IAssetResolver $assetResolver,
		private readonly ILogger $logger
	) {}

	public static function getName(): string {
		return "datatablereportdisplay";
	}

	public function setData($data): void {
		$this->config = is_array($data) ? $data : [];
		if (isset($data['result']) && $data['result'] instanceof QueryResult) {
			$this->result = $data['result'];
		}
	}

	public function getOutput($out = "html") {
		return $out === "json"
			? $this->getJsonOutput()
			: $this->getHtmlOutput();
	}

	private function getJsonOutput(): string {
		// Read params
		$sort = $_GET['sort'] ?? null;
		$direction = strtolower($_GET['direction'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
		$pageSize = max(1, intval($_GET['pageSize'] ?? ($this->config['config']['pageSize'] ?? 10)));
		$page = max(1, intval($_GET['page'] ?? 1));
		$filters = $_GET['filter'] ?? [];

		// Field defintions
		$fields = $this->config['fields'] ?? [];
		$fieldDefs = [];
		foreach ($fields as $f) {
			$fieldDefs[$f['alias']] = $f['element'];
		}

		// Base query (with type=select)
		$baseQuery = $this->config;
		$baseQuery['type'] = 'select';

		// WHERE condition (base + filter)
		$where = $this->config['where'] ?? null;
		$whereParams = $where ? [$where] : [];

		foreach ($filters as $alias => $value) {
			if (!isset($fieldDefs[$alias]) || $value === '') continue;
			$whereParams[] = [
				'type' => 'op',
				'operator' => 'LIKE',
				'params' => [$fieldDefs[$alias], '%' . $value . '%']
			];
		}

		if (count($whereParams) === 1) {
			$baseQuery['where'] = $whereParams[0];
		} elseif (count($whereParams) > 1) {
			$baseQuery['where'] = [
				'type' => 'op',
				'operator' => 'AND',
				'params' => $whereParams
			];
		}

		// ==========================
		// COUNT QUERY
		// ==========================
		$countQuery = $baseQuery;

		// Fields: all real fields (for JOIN creation)
		$countQuery['fields'] = [];
		foreach ($fields as $f) {
			$countQuery['fields'][] = [
				'element' => $f['element'],
				'alias' => $f['alias']
			];
		}

		// Additional count field
		$countQuery['fields'][] = [
			'element' => [
				'type' => 'fn',
				'function' => 'COUNT',
				'params' => [[
					'type' => 'fld',
					'table' => $fields[0]['element']['table'] ?? '',
					'field' => $fields[0]['element']['field'] ?? 'id'
				]]
			],
			'alias' => '__total__'
		];

		// GROUP BY und HAVING übernehmen, falls vorhanden
		if (isset($this->config['group'])) {
			$countQuery['group'] = $this->config['group'];
		}
		if (isset($this->config['having'])) {
			$countQuery['having'] = $this->config['having'];
		}

		// No limit/offset/sorting for counting
		unset($countQuery['limit'], $countQuery['offset'], $countQuery['order_by']);

		// Execute 
		$totalResult = $this->reportqueryservice->executeQuery($countQuery);
		$total = $totalResult->rows[0]['__total__'] ?? 0;

		// ==========================
		// DATENQUERY
		// ==========================
		$dataQuery = $baseQuery;

		// Set fields
		$dataQuery['fields'] = [];
		foreach ($fields as $f) {
			$dataQuery['fields'][] = [
				'element' => $f['element'],
				'alias' => $f['alias']
			];
		}

		// Sorting
		if ($sort && isset($fieldDefs[$sort])) {
			$dataQuery['order_by'] = [[
				'element' => $fieldDefs[$sort],
				'direction' => $direction
			]];
		}

		// Pagination
		$totalPages = max(1, ceil($total / $pageSize));
		$offset = ($page - 1) * $pageSize;
		$dataQuery['offset'] = $offset;
		$dataQuery['limit'] = $pageSize;

		// GROUP BY and HAVING also here
		if (isset($this->config['group'])) {
			$dataQuery['group'] = $this->config['group'];
		}
		if (isset($this->config['having'])) {
			$dataQuery['having'] = $this->config['having'];
		}

		// Execute
		$result = $this->reportqueryservice->executeQuery($dataQuery);
		$rows = $result->rows ?? [];

		// Logging
		if ($this->logSql) $this->logger->log('Vizion', $result->debugSql);

		// Return
		return json_encode([
			'total' => $total,
			'page' => $page,
			'pageSize' => $pageSize,
			'totalPages' => $totalPages,
			'data' => $rows
		], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	}

	private function getHtmlOutput(): string {
		$this->view->setPath(DIR_PLUGIN . 'Vizion');
		$this->view->setTemplate('ReportDisplay/DataTableReportDisplay.php');

		$fields = $this->config['fields'] ?? [];
		$columns = array_map(fn($f) => [
			'key' => $f['alias'],
			'label' => $f['config']['label'] ?? $f['alias'],
			'visible' => $f['config']['visible'] ?? true
		], $fields);

		$report = $this->config['report'] ?? '';
		$ajaxUrl = '?name=generalreportdisplay&out=json&report=' . urlencode($report);

		$this->view->assign('ajaxUrl', $ajaxUrl);
		$this->view->assign('columns', $columns);
		$this->view->assign('config', $this->config);

		$this->view->assign('resolve', fn($src) => $this->assetResolver->resolve($src));

		return $this->view->loadTemplate();
	}

	public function getHelp(): string {
		return "Displays data as a jQuery DataTable using the Vizion ReportDisplay system.";
	}
}

