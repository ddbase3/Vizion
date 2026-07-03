<?php declare(strict_types=1);

namespace Vizion\ReportDisplay;

use Base3\Api\IAssetResolver;
use Base3\Api\IClassMap;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use ResourceFoundation\Api\IQueryService;
use Vizion\Api\IReportConfigProvider;
use Vizion\Api\IReportFilterService;
use Throwable;

final class MatrixReportDisplay implements IDisplay {

	private ?string $report = null;

	/** @var array<string,mixed>|null */
	private ?array $config = null;

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly IAssetResolver $assetResolver,
		private readonly IQueryService $queryService,
		private readonly IReportConfigProvider $configProvider,
		private readonly IClassMap $classmap,
		private readonly ILinkTargetService $linkTargetService,
		private readonly IReportFilterService $reportFilterService
	) {}

	public static function getName(): string {
		return 'matrixreportdisplay';
	}

	public function setData($data): void {
		if(is_string($data)) {
			$this->report = $data;
			$this->config = null;
			return;
		}

		if(is_array($data)) {
			$this->config = $data;
			$this->report = isset($data['report']) && is_scalar($data['report']) ? (string) $data['report'] : $this->report;
		}
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		return strtolower($out) === 'json'
			? $this->getJsonOutput($final)
			: $this->getHtmlOutput();
	}

	private function getHtmlOutput(): string {
		$config = $this->getConfig();
		$report = $this->getReportName();

		$this->view->setPath(DIR_PLUGIN . 'Vizion');
		$this->view->setTemplate('ReportDisplay/MatrixReportDisplay.php');
		$this->view->assign('ajaxUrl', $this->linkTargetService->getLink([
			'name' => self::getName(),
			'out' => 'json'
		], [
			'report' => $report
		]));
		$this->view->assign('columns', $this->buildColumns($this->getFields()));
		$this->view->assign('filterFields', $this->reportFilterService->buildGridFilterFields($this->getFields()));
		$this->view->assign('filterInitialValues', $this->reportFilterService->buildInitialFilterValues($this->getFields()));
		$this->view->assign('config', $config);
		$this->view->assign('modulargridCssUrl', $this->assetResolver->resolve('plugin/ClientStack/assets/modulargrid/styles/modulargrid.css'));
		$this->view->assign('modulargridJsUrl', $this->assetResolver->resolve('plugin/ClientStack/assets/modulargrid/index.js'));

		return $this->view->loadTemplate();
	}

	private function getJsonOutput(bool $final = false): string {
		$response = $this->buildJsonResponse();

		if($final && !headers_sent()) {
			header('Content-Type: application/json; charset=utf-8');
		}

		return (string) json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	/** @return array<string,mixed> */
	private function buildJsonResponse(): array {
		try {
			$payload = $this->request->getJsonBody();
			$payload = is_array($payload) ? $payload : [];
			$mode = isset($payload['mode']) && is_scalar($payload['mode']) ? (string) $payload['mode'] : 'page';

			return ($mode === 'detail' || $mode === 'matrix-detail')
				? $this->buildDetailResponse($payload)
				: $this->buildPageResponse($payload);
		}
		catch(Throwable $exception) {
			return [
				'ok' => false,
				'error' => $exception->getMessage(),
				'data' => [],
				'total' => 0,
				'page' => 1,
				'pageSize' => 0
			];
		}
	}

	/** @param array<string,mixed> $payload @return array<string,mixed> */
	private function buildPageResponse(array $payload): array {
		$request = $this->normalizeRequest($payload);
		$fields = $this->getFields();
		$fieldDefs = $this->buildFieldDefs($fields);
		$where = $this->buildWhere($request['search'], $request['filters'], $fieldDefs);
		$total = $this->loadTotal($where);
		$page = (int) $request['page'];
		$pageSize = (int) $request['pageSize'];
		$offset = max(0, ($page - 1) * $pageSize);
		$totalPages = $pageSize > 0 ? (int) ceil($total / $pageSize) : 0;

		$query = [
			'type' => 'select',
			'schema' => (string) ($this->getConfig()['schema'] ?? ''),
			'table' => (string) ($this->getConfig()['table'] ?? ''),
			'fields' => $this->buildQueryFields($fields),
			'limit' => $pageSize,
			'offset' => $offset
		];

		if($where !== null) {
			$query['where'] = $where;
		}

		$sort = $request['sort'];
		$sortKey = (string) ($sort['key'] ?? '');
		if($sortKey !== '' && isset($fieldDefs[$sortKey])) {
			$query['order_by'] = [[
				'element' => $fieldDefs[$sortKey],
				'direction' => strtoupper((string) ($sort['dir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC'
			]];
		}
		elseif(isset($this->getConfig()['order_by']) && is_array($this->getConfig()['order_by'])) {
			$query['order_by'] = $this->getConfig()['order_by'];
		}

		$result = $this->queryService->executeQuery($query);
		$rows = [];

		foreach($result->rows ?? [] as $index => $row) {
			if(!is_array($row)) {
				continue;
			}

			$row['__row_key'] = $this->buildRowKey($row, $offset + $index + 1);
			$rows[] = $row;
		}

		return [
			'ok' => true,
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
			'appliedGroup' => []
		];
	}

	/** @param array<string,mixed> $payload @return array<string,mixed> */
	private function buildDetailResponse(array $payload): array {
		$config = $this->getConfig();
		$detail = isset($config['detail']) && is_array($config['detail']) ? $config['detail'] : [];
		$displayName = isset($detail['display']) && is_scalar($detail['display']) ? (string) $detail['display'] : MatrixTableReportDisplay::getName();
		$display = $this->classmap->getInstanceByInterfaceName(IDisplay::class, $displayName);

		if(!$display instanceof IDisplay) {
			throw new \RuntimeException('Invalid matrix subreport display: ' . $displayName);
		}

		$display->setData([
			'config' => $config,
			'detail' => $detail,
			'payload' => $payload
		]);

		$response = json_decode($display->getOutput('json'), true);
		if(!is_array($response)) {
			throw new \RuntimeException('Matrix subreport returned invalid JSON.');
		}

		return $response;
	}

	/** @param array<string,mixed> $payload @return array<string,mixed> */
	private function normalizeRequest(array $payload): array {
		$page = max(1, (int) ($payload['page'] ?? 1));
		$pageSize = max(1, min(250, (int) ($payload['pageSize'] ?? ($this->getConfig()['config']['pageSize'] ?? 50))));
		$search = isset($payload['search']) && is_scalar($payload['search']) ? trim((string) $payload['search']) : '';

		return [
			'page' => $page,
			'pageSize' => $pageSize,
			'search' => $search,
			'sort' => $this->normalizeSort($payload['sort'] ?? null),
			'filters' => $this->reportFilterService->normalizeFilters($payload['filters'] ?? null, $this->getFields())
		];
	}

	/** @param mixed $sortPayload @return array<string,string> */
	private function normalizeSort(mixed $sortPayload): array {
		$fields = $this->getFields();
		$fieldDefs = $this->buildFieldDefs($fields);
		$defaultKey = (string) ($this->getConfig()['config']['sortColumn'] ?? ($fields[0]['alias'] ?? ''));
		$defaultDirection = strtolower((string) ($this->getConfig()['config']['sortDirection'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

		if($defaultKey === '' || !isset($fieldDefs[$defaultKey])) {
			$defaultKey = (string) ($fields[0]['alias'] ?? '');
		}

		$sort = [
			'key' => $defaultKey,
			'dir' => $defaultDirection,
			'type' => $this->getFieldType($defaultKey)
		];

		if(!is_array($sortPayload) || count($sortPayload) === 0) {
			return $sort;
		}

		$first = reset($sortPayload);
		if(!is_array($first)) {
			return $sort;
		}

		$key = isset($first['key']) ? (string) $first['key'] : $defaultKey;
		$key = isset($fieldDefs[$key]) ? $key : $defaultKey;
		$dir = isset($first['dir']) && strtolower((string) $first['dir']) === 'desc' ? 'desc' : 'asc';

		return [
			'key' => $key,
			'dir' => $dir,
			'type' => $this->getFieldType($key)
		];
	}

	/** @param array<string,mixed> $filters @param array<string,mixed> $fieldDefs @return array<string,mixed>|null */
	private function buildWhere(string $search, array $filters, array $fieldDefs): ?array {
		$where = [];
		$config = $this->getConfig();

		if(isset($config['where']) && is_array($config['where'])) {
			$where[] = $config['where'];
		}

		if($search !== '') {
			$searchParts = [];
			foreach($this->getFields() as $field) {
				$alias = (string) ($field['alias'] ?? '');
				$fieldConfig = isset($field['config']) && is_array($field['config']) ? $field['config'] : [];
				if($alias === '' || !isset($fieldDefs[$alias]) || ($fieldConfig['search'] ?? true) === false) {
					continue;
				}
				$searchParts[] = [
					'type' => 'op',
					'operator' => 'LIKE',
					'params' => [$fieldDefs[$alias], '%' . $search . '%']
				];
			}
			if(count($searchParts) === 1) {
				$where[] = $searchParts[0];
			}
			elseif(count($searchParts) > 1) {
				$where[] = ['type' => 'op', 'operator' => 'OR', 'params' => $searchParts];
			}
		}

		$filterWhere = $this->reportFilterService->buildFilterWhere($filters, $this->getFields(), $fieldDefs);

		if($filterWhere !== null) {
			$where[] = $filterWhere;
		}

		if(count($where) === 0) {
			return null;
		}

		return count($where) === 1 ? $where[0] : ['type' => 'op', 'operator' => 'AND', 'params' => $where];
	}

	/** @param array<string,mixed>|null $where */
	private function loadTotal(?array $where): int {
		$query = [
			'type' => 'select',
			'schema' => (string) ($this->getConfig()['schema'] ?? ''),
			'table' => (string) ($this->getConfig()['table'] ?? ''),
			'fields' => [[
				'element' => ['type' => 'fn', 'function' => 'COUNT', 'params' => [['type' => 'fld', 'field' => '*']]],
				'alias' => '__total__'
			]]
		];

		if($where !== null) {
			$query['where'] = $where;
		}

		$result = $this->queryService->executeQuery($query);

		return (int) ($result->rows[0]['__total__'] ?? 0);
	}

	/** @return array<string,mixed> */
	private function getConfig(): array {
		if($this->config !== null) {
			return $this->config;
		}

		$report = $this->getReportName();
		$this->config = $this->configProvider->getConfig($report);
		$this->config['report'] = $report;

		return $this->config;
	}

	private function getReportName(): string {
		if($this->report !== null && trim($this->report) !== '') {
			return trim($this->report);
		}

		$report = $this->request->get('report');
		if(is_scalar($report) && trim((string) $report) !== '') {
			$this->report = trim((string) $report);
			return $this->report;
		}

		throw new \InvalidArgumentException('Missing matrix report identifier.');
	}

	/** @return array<int,array<string,mixed>> */
	private function getFields(): array {
		$fields = $this->getConfig()['fields'] ?? [];

		return is_array($fields) ? $fields : [];
	}

	/** @param array<int,array<string,mixed>> $fields @return array<string,mixed> */
	private function buildFieldDefs(array $fields): array {
		$result = [];
		foreach($fields as $field) {
			if(isset($field['alias'], $field['element'])) {
				$result[(string) $field['alias']] = $field['element'];
			}
		}
		return $result;
	}

	/** @param array<int,array<string,mixed>> $fields @return array<int,array<string,mixed>> */
	private function buildQueryFields(array $fields): array {
		$result = [];
		foreach($fields as $field) {
			if(isset($field['alias'], $field['element'])) {
				$result[] = ['element' => $field['element'], 'alias' => (string) $field['alias']];
			}
		}
		return $result;
	}

	/** @param array<int,array<string,mixed>> $fields @return array<int,array<string,mixed>> */
	private function buildColumns(array $fields): array {
		$columns = [];
		foreach($fields as $field) {
			if(!isset($field['alias'])) {
				continue;
			}
			$fieldConfig = isset($field['config']) && is_array($field['config']) ? $field['config'] : [];
			$alias = (string) $field['alias'];
			$column = [
				'key' => $alias,
				'label' => (string) ($fieldConfig['label'] ?? $alias),
				'visible' => $fieldConfig['visible'] ?? true,
				'type' => (string) ($fieldConfig['type'] ?? 'string')
			];
			if(isset($fieldConfig['width']) && is_numeric($fieldConfig['width'])) {
				$column['width'] = (int) $fieldConfig['width'];
			}
			$columns[] = $column;
		}
		return $columns;
	}

	/** @return array<string,mixed> */
	private function getField(string $alias): array {
		foreach($this->getFields() as $field) {
			if((string) ($field['alias'] ?? '') === $alias) {
				return $field;
			}
		}
		return [];
	}

	private function getFieldType(string $alias): string {
		$field = $this->getField($alias);
		$config = isset($field['config']) && is_array($field['config']) ? $field['config'] : [];
		return (string) ($config['type'] ?? 'string');
	}

	/** @param array<string,mixed> $row */
	private function buildRowKey(array $row, int $fallback): string {
		$config = $this->getConfig()['detail'] ?? [];
		$key = is_array($config) && isset($config['parameter']) && is_scalar($config['parameter']) ? (string) $config['parameter'] : 'id';
		$value = $row[$key] ?? ($row['id'] ?? $fallback);

		return 'matrix-row-' . (string) $value;
	}

	public function getHelp(): string {
		return 'Displays a matrix master report with Ajax-loaded Vizion subreports in the row detail area.';
	}
}
