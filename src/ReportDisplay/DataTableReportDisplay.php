<?php declare(strict_types=1);

namespace Vizion\ReportDisplay;

use Base3\Api\IAssetResolver;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use DataHawk\Api\IReportQueryService;
use DataHawk\Dto\QueryResult;

class DataTableReportDisplay implements IDisplay {

	private ?array $config = null;
	private ?QueryResult $result = null;

	public function __construct(
		private readonly IMvcView $view,
		private readonly IReportQueryService $reportqueryservice,
		private readonly IAssetResolver $assetResolver
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
		// Parameter auslesen
		$sort = $_GET['sort'] ?? null;
		$direction = strtolower($_GET['direction'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
		$pageSize = max(1, intval($_GET['pageSize'] ?? ($this->config['config']['pageSize'] ?? 10)));
		$page = max(1, intval($_GET['page'] ?? 1));
		$filters = $_GET['filter'] ?? [];

		// Felder-Definition
		$fields = $this->config['fields'] ?? [];
		$fieldDefs = [];
		foreach ($fields as $f) {
			$fieldDefs[$f['alias']] = $f['element'];
		}

		// COUNT-Abfrage
		$countQuery = $this->config;
		$countQuery['type'] = 'select';
		$countQuery['fields'] = [[
			'element' => [
				'type' => 'fn',
				'function' => 'COUNT',
				'params' => [[
					'type' => 'fld',
					'table' => $fields[0]['element']['table'] ?? '',
					'field' => $fields[0]['element']['field'] ?? 'id'
				]]
			],
			'alias' => 'total'
		]];

		// WHERE-Bedingungen: Basis + Filter
		$where = $this->config['where'] ?? null;
		$filterOps = [];

		foreach ($filters as $alias => $value) {
			if (!isset($fieldDefs[$alias]) || $value === '') continue;
			$filterOps[] = [
				'type' => 'op',
				'operator' => 'LIKE',
				'params' => [$fieldDefs[$alias], '%' . $value . '%']
			];
		}

		if ($where && $filterOps) {
			$where = [
				'type' => 'op',
				'operator' => 'AND',
				'params' => array_merge([$where], $filterOps)
			];
		} elseif ($filterOps) {
			$where = count($filterOps) === 1 ? $filterOps[0] : [
				'type' => 'op',
				'operator' => 'AND',
				'params' => $filterOps
			];
		}

		$countQuery['where'] = $where;
		$total = $this->reportqueryservice->executeQuery($countQuery)->rows[0]['total'] ?? 0;

		// Offset + Limit
		$totalPages = max(1, ceil($total / $pageSize));
		$offset = ($page - 1) * $pageSize;

		// Datenabfrage
		$dataQuery = $this->config;
		$dataQuery['type'] = 'select';
		$dataQuery['where'] = $where;
		$dataQuery['offset'] = $offset;
		$dataQuery['limit'] = $pageSize;

		// Sortierung
		if ($sort && isset($fieldDefs[$sort])) {
			$dataQuery['order_by'] = [[
				'element' => $fieldDefs[$sort],
				'direction' => $direction
			]];
		}

		$result = $this->reportqueryservice->executeQuery($dataQuery);
		$rows = $result->rows ?? [];

		// Rückgabe im bekannten Format
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

		$report = $this->config['id'] ?? 'example';
		$ajaxUrl = 'generalreportdisplay.json?report=' . urlencode($report);
		$pageSize = $this->config['config']['pageSize'] ?? 10;

		$this->view->assign('ajaxUrl', $ajaxUrl);
		$this->view->assign('columns', $columns);
		$this->view->assign('pageSize', $pageSize);

		$this->view->assign('resolve', fn($src) => $this->assetResolver->resolve($src));

		return $this->view->loadTemplate();
	}

	public function getHelp(): string {
		return "Displays data as a jQuery DataTable using the Vizion ReportDisplay system.";
	}
}

