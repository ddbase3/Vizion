<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of Vizion for BASE3 Framework.
 *
 * Vizion extends the BASE3 framework with modular, visual display
 * components for reports and structured data. It provides flexible
 * renderers such as interactive tables and charts, driven by
 * declarative configuration and seamlessly integrated into BASE3 pages.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/vizion
 * https://github.com/ddbase3/Vizion
 **********************************************************************/

namespace Vizion\ReportDisplay;

use Base3\Api\IAssetResolver;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Logger\Api\ILogger;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Dto\QueryResult;
use Vizion\Api\IReportCellRendererService;
use Vizion\Api\IReportFilterService;

class ModularGridReportDisplay implements IDisplay {

	private ?array $config = null;
	private ?QueryResult $result = null;

	private bool $logSql = true;

	public function __construct(
		private readonly IMvcView $view,
		private readonly IRequest $request,
		private readonly IQueryService $reportqueryservice,
		private readonly ILogger $logger,
		private readonly ILinkTargetService $linkTargetService,
		private readonly IAssetResolver $assetResolver,
		private readonly IReportFilterService $reportFilterService,
		private readonly IReportCellRendererService $reportCellRendererService
	) {}

	public static function getName(): string {
		return 'modulargridreportdisplay';
	}

	public function setData($data): void {
		$this->config = is_array($data) ? $data : [];

		if(isset($data['result']) && $data['result'] instanceof QueryResult) {
			$this->result = $data['result'];
		}
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		return strtolower($out) === 'json'
			? $this->getJsonOutput($final)
			: $this->getHtmlOutput();
	}

	private function getJsonOutput(bool $final = false): string {
		try {
			$response = $this->buildJsonResponse();
		}
		catch(\Throwable $exception) {
			$response = [
				'ok' => false,
				'error' => $exception->getMessage(),
				'data' => [],
				'groups' => [],
				'page' => 1,
				'pageSize' => 0,
				'total' => 0,
				'totalPages' => 0,
				'hasMore' => false,
				'nextCursor' => null
			];
		}

		if($final && !headers_sent()) {
			header('Content-Type: application/json; charset=utf-8');
		}

		return (string) json_encode(
			$response,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildJsonResponse(): array {
		$payload = $this->request->getJsonBody();

		if(!is_array($payload)) {
			$payload = [];
		}

		$request = $this->normalizeRequest($payload);
		$fields = $this->getFields();
		$fieldDefs = $this->buildFieldDefs($fields);

		$baseQuery = $this->buildBaseQuery($fieldDefs, $request['search'], $request['filters']);
		$total = $this->loadTotal($baseQuery, $fields);
		$pageSize = $request['pageSize'];
		$page = $request['page'];
		$totalPages = $pageSize > 0 ? (int) ceil($total / $pageSize) : 0;

		$dataQuery = $this->buildDataQuery($baseQuery, $fields, $fieldDefs, $request);
		$result = $this->reportqueryservice->executeQuery($dataQuery);

		if($this->logSql) {
			$this->logger->log('Vizion', 'MODULARGRID RES | ' . $result->debugSql);
		}

		$rows = [];
		$offset = (($page - 1) * $pageSize);

		foreach(($result->rows ?? []) as $index => $row) {
			if(!is_array($row)) {
				continue;
			}

			$row['__row_key'] = $this->buildRowKey($row, $offset + $index + 1);
			$rows[] = $row;
		}

		return [
			'mode' => 'page',
			'data' => $rows,
			'groups' => [],
			'page' => $page,
			'pageSize' => $pageSize,
			'total' => $total,
			'totalPages' => $totalPages,
			'hasMore' => ($offset + $pageSize) < $total,
			'nextCursor' => null,
			'appliedSearch' => $request['search'],
			'appliedSort' => [$request['sort']],
			'appliedFilters' => $request['filters'],
			'appliedGroup' => [],
		];
	}


	/**
	 * @param array<string, mixed> $row
	 */
	private function buildRowKey(array $row, int $fallback): string {
		$normalized = [];

		foreach($row as $key => $value) {
			$key = (string) $key;

			if(str_starts_with($key, '__')) {
				continue;
			}

			if(is_scalar($value) || $value === null) {
				$normalized[$key] = $value;
				continue;
			}

			$normalized[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		ksort($normalized);
		$encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if(!is_string($encoded) || $encoded === '') {
			$encoded = 'fallback-' . (string) $fallback;
		}

		return 'report-row-' . substr(sha1($encoded), 0, 20);
	}

	private function getHtmlOutput(): string {
		$this->view->setPath(DIR_PLUGIN . 'Vizion');
		$this->view->setTemplate('ReportDisplay/ModularGridReportDisplay.php');

		$fields = $this->getFields();
		$columns = $this->reportCellRendererService->buildGridColumns($fields);
		$filterFields = $this->reportFilterService->buildGridFilterFields($fields);
		$filterInitialValues = $this->reportFilterService->buildInitialFilterValues($fields);
		$report = $this->config['report'] ?? '';
		$ajaxUrl = $this->linkTargetService->getLink(
			[
				'name' => 'generalreportdisplay',
				'out' => 'json'
			],
			[
				'report' => $report
			]
		);

		$this->view->assign('ajaxUrl', $ajaxUrl);
		$this->view->assign('columns', $columns);
		$this->view->assign('filterFields', $filterFields);
		$this->view->assign('filterInitialValues', $filterInitialValues);
		$this->view->assign('config', $this->config ?? []);
		$this->view->assign('modulargridCssUrl', $this->assetResolver->resolve('plugin/ClientStack/assets/modulargrid/styles/modulargrid.css'));
		$this->view->assign('modulargridJsUrl', $this->assetResolver->resolve('plugin/ClientStack/assets/modulargrid/index.js'));
		$this->view->assign('chronoPickerCssUrl', $this->assetResolver->resolve('plugin/ClientStack/assets/chronopicker/styles/chronopicker.css'));
		$this->view->assign('chronoPickerJsUrl', $this->assetResolver->resolve('plugin/ClientStack/assets/chronopicker/index.js'));
		$this->view->assign('filterControlsJsUrl', $this->assetResolver->resolve('plugin/Vizion/assets/js/vizion-report-filter-controls.js'));
		$this->view->assign('cellRenderersJsUrl', $this->assetResolver->resolve('plugin/Vizion/assets/js/vizion-report-cell-renderers.js'));

		return $this->view->loadTemplate();
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function normalizeRequest(array $payload): array {
		$page = isset($payload['page']) ? (int) $payload['page'] : 1;
		$page = max(1, $page);

		$pageSize = isset($payload['pageSize'])
			? (int) $payload['pageSize']
			: (int) ($this->config['config']['pageSize'] ?? 25);
		$pageSize = max(1, min(250, $pageSize));

		$search = '';
		if(isset($payload['search']) && is_scalar($payload['search'])) {
			$search = trim((string) $payload['search']);
		}

		$sort = $this->normalizeSort($payload['sort'] ?? null);
		$filters = $this->reportFilterService->normalizeFilters($payload['filters'] ?? null, $this->getFields());

		return [
			'page' => $page,
			'pageSize' => $pageSize,
			'search' => $search,
			'sort' => $sort,
			'filters' => $filters,
		];
	}

	/**
	 * @param mixed $sortPayload
	 * @return array<string, string>
	 */
	private function normalizeSort(mixed $sortPayload): array {
		$fields = $this->getFields();
		$fieldDefs = $this->buildFieldDefs($fields);
		$defaultKey = (string) ($this->config['config']['sortColumn'] ?? ($fields[0]['alias'] ?? ''));
		$defaultDirection = strtolower((string) ($this->config['config']['sortDirection'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

		if($defaultKey === '' || !isset($fieldDefs[$defaultKey])) {
			$defaultKey = (string) ($fields[0]['alias'] ?? '');
		}

		$sort = [
			'key' => $defaultKey,
			'dir' => $defaultDirection,
			'type' => 'string',
		];

		if(!is_array($sortPayload) || count($sortPayload) === 0) {
			return $sort;
		}

		$first = reset($sortPayload);

		if(!is_array($first)) {
			return $sort;
		}

		$key = isset($first['key']) ? (string) $first['key'] : $defaultKey;
		if(!isset($fieldDefs[$key])) {
			$key = $defaultKey;
		}

		$dir = isset($first['dir']) ? strtolower((string) $first['dir']) : $defaultDirection;
		$dir = $dir === 'desc' ? 'desc' : 'asc';

		return [
			'key' => $key,
			'dir' => $dir,
			'type' => $this->getFieldSortType($key),
		];
	}

	/**
	 * @param array<string, mixed> $fieldDefs
	 * @param array<string, mixed> $filters
	 * @return array<string, mixed>
	 */
	private function buildBaseQuery(array $fieldDefs, string $search, array $filters): array {
		$baseQuery = $this->config ?? [];
		$baseQuery['type'] = 'select';

		$where = $this->config['where'] ?? null;
		$whereParams = $where ? [$where] : [];

		if($search !== '') {
			$searchParams = [];

			foreach($fieldDefs as $element) {
				$searchParams[] = [
					'type' => 'op',
					'operator' => 'LIKE',
					'params' => [$element, '%' . $search . '%']
				];
			}

			if(count($searchParams) === 1) {
				$whereParams[] = $searchParams[0];
			}
			elseif(count($searchParams) > 1) {
				$whereParams[] = [
					'type' => 'op',
					'operator' => 'OR',
					'params' => $searchParams
				];
			}
		}

		$filterWhere = $this->reportFilterService->buildFilterWhere($filters, $this->getFields(), $fieldDefs);

		if($filterWhere !== null) {
			$whereParams[] = $filterWhere;
		}

		if(count($whereParams) === 1) {
			$baseQuery['where'] = $whereParams[0];
		}
		elseif(count($whereParams) > 1) {
			$baseQuery['where'] = [
				'type' => 'op',
				'operator' => 'AND',
				'params' => $whereParams
			];
		}
		else {
			unset($baseQuery['where']);
		}

		return $baseQuery;
	}

	/**
	 * @param array<string, mixed> $baseQuery
	 * @param array<int, array<string, mixed>> $fields
	 */
	private function loadTotal(array $baseQuery, array $fields): int {
		$countQuery = $baseQuery;
		$countQuery['fields'] = [];

		foreach($fields as $field) {
			$countQuery['fields'][] = [
				'element' => $field['element'],
				'alias' => $field['alias']
			];
		}

		$countQuery['fields'][] = [
			'element' => [
				'type' => 'windowfn',
				'function' => 'COUNT',
				'params' => ['*'],
				'over' => []
			],
			'alias' => '__total__'
		];

		if(isset($this->config['group'])) {
			$countQuery['group'] = $this->config['group'];
		}

		if(isset($this->config['having'])) {
			$countQuery['having'] = $this->config['having'];
		}

		unset($countQuery['limit'], $countQuery['offset'], $countQuery['order_by']);

		$result = $this->reportqueryservice->executeQuery($countQuery);
		$total = (int) ($result->rows[0]['__total__'] ?? 0);

		if($this->logSql) {
			$this->logger->log('Vizion', 'MODULARGRID CNT | ' . $result->debugSql);
		}

		return $total;
	}

	/**
	 * @param array<string, mixed> $baseQuery
	 * @param array<int, array<string, mixed>> $fields
	 * @param array<string, mixed> $fieldDefs
	 * @param array<string, mixed> $request
	 * @return array<string, mixed>
	 */
	private function buildDataQuery(array $baseQuery, array $fields, array $fieldDefs, array $request): array {
		$dataQuery = $baseQuery;
		$dataQuery['fields'] = [];

		foreach($fields as $field) {
			$dataQuery['fields'][] = [
				'element' => $field['element'],
				'alias' => $field['alias']
			];
		}

		$sort = $request['sort'];
		$sortKey = $sort['key'] ?? '';

		if($sortKey !== '' && isset($fieldDefs[$sortKey])) {
			$dataQuery['order_by'] = [[
				'element' => $fieldDefs[$sortKey],
				'direction' => strtoupper($sort['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC'
			]];
		}
		elseif(isset($this->config['order_by'])) {
			$dataQuery['order_by'] = $this->config['order_by'];
		}

		if(isset($this->config['group'])) {
			$dataQuery['group'] = $this->config['group'];
		}

		if(isset($this->config['having'])) {
			$dataQuery['having'] = $this->config['having'];
		}

		$page = (int) $request['page'];
		$pageSize = (int) $request['pageSize'];

		$dataQuery['offset'] = ($page - 1) * $pageSize;
		$dataQuery['limit'] = $pageSize;

		return $dataQuery;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function getFields(): array {
		$fields = $this->config['fields'] ?? [];

		return is_array($fields) ? $fields : [];
	}

	/**
	 * @param array<int, array<string, mixed>> $fields
	 * @return array<string, mixed>
	 */
	private function buildFieldDefs(array $fields): array {
		$fieldDefs = [];

		foreach($fields as $field) {
			if(!isset($field['alias'], $field['element'])) {
				continue;
			}

			$fieldDefs[(string) $field['alias']] = $field['element'];
		}

		return $fieldDefs;
	}

	private function getFieldSortType(string $alias): string {
		foreach($this->getFields() as $field) {
			if((string) ($field['alias'] ?? '') !== $alias) {
				continue;
			}

			$config = isset($field['config']) && is_array($field['config']) ? $field['config'] : [];

			return (string) ($config['type'] ?? 'string');
		}

		return 'string';
	}

	public function getHelp(): string {
		return 'Displays DataHawk query results as a ModularGrid table using the Vizion ReportDisplay system.';
	}
}
