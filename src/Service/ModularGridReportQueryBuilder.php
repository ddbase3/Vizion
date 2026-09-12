<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of Vizion for BASE3 Framework.
 *
 * Vizion extends the BASE3 framework with modular, visual display
 * components for reports and structured data.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/vizion
 * https://github.com/ddbase3/Vizion
 **********************************************************************/

namespace Vizion\Service;

use Vizion\Api\IReportFilterService;
use Vizion\Api\IReportTreeFilterService;

/**
 * Builds the structured queries used by ModularGrid reports.
 *
 * Paging and exports intentionally share this class so search, filters,
 * sorting, grouping and static report conditions have one implementation.
 */
final class ModularGridReportQueryBuilder {

	public function __construct(
		private readonly IReportFilterService $reportFilterService,
		private readonly IReportTreeFilterService $reportTreeFilterService
	) {}

	/**
	 * @param array<string, mixed> $payload
	 * @param array<string, mixed> $config
	 * @return array<string, mixed>
	 */
	public function normalizeRequest(array $payload, array $config): array {
		$page = isset($payload['page']) ? (int)$payload['page'] : 1;
		$page = max(1, $page);

		$pageSize = isset($payload['pageSize'])
			? (int)$payload['pageSize']
			: (int)($config['config']['pageSize'] ?? 25);
		$pageSize = max(1, min(250, $pageSize));

		$search = '';
		if(isset($payload['search']) && is_scalar($payload['search'])) {
			$search = trim((string)$payload['search']);
		}

		$fields = $this->getFields($config);
		$sort = $this->normalizeSort($payload['sort'] ?? null, $config, $fields);
		$filters = $this->reportFilterService->normalizeFilters($payload['filters'] ?? null, $fields);
		$treeFilters = $this->reportTreeFilterService->normalizeTreeFilters($payload['treeFilters'] ?? null, $config);

		return [
			'page' => $page,
			'pageSize' => $pageSize,
			'search' => $search,
			'sort' => $sort,
			'filters' => $filters,
			'treeFilters' => $treeFilters,
		];
	}

	/**
	 * @param array<string, mixed> $config
	 * @param array<string, mixed> $request
	 * @return array<string, mixed>
	 */
	public function buildBaseQuery(array $config, array $request): array {
		$fields = $this->getFields($config);
		$fieldDefs = $this->buildFieldDefs($fields);
		$baseQuery = $config;
		$baseQuery['type'] = 'select';

		$where = $config['where'] ?? null;
		$whereParams = $where ? [$where] : [];
		$search = (string)($request['search'] ?? '');

		if($search !== '') {
			$searchParams = [];

			foreach($this->getSearchFieldDefs($fields) as $element) {
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

		$filters = is_array($request['filters'] ?? null) ? $request['filters'] : [];
		$filterWhere = $this->reportFilterService->buildFilterWhere($filters, $fields, $fieldDefs);

		if($filterWhere !== null) {
			$whereParams[] = $filterWhere;
		}

		$treeFilters = is_array($request['treeFilters'] ?? null) ? $request['treeFilters'] : [];
		$treeFilterWhere = $this->reportTreeFilterService->buildFilterWhere($treeFilters, $config);

		if($treeFilterWhere !== null) {
			$whereParams[] = $treeFilterWhere;
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
	 * @param array<string, mixed> $config
	 * @param array<string, mixed> $baseQuery
	 * @return array<string, mixed>
	 */
	public function buildCountQuery(array $config, array $baseQuery): array {
		$countQuery = $baseQuery;
		$countQuery['fields'] = [];

		foreach($this->getFields($config) as $field) {
			if(!isset($field['element'], $field['alias'])) {
				continue;
			}

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

		if(isset($config['group_by'])) {
			$countQuery['group_by'] = $config['group_by'];
		}

		if(isset($config['having'])) {
			$countQuery['having'] = $config['having'];
		}

		unset($countQuery['limit'], $countQuery['offset'], $countQuery['order_by']);
		return $countQuery;
	}

	/**
	 * @param array<string, mixed> $config
	 * @param array<string, mixed> $baseQuery
	 * @param array<string, mixed> $request
	 * @param array<int, string>|null $fieldAliases
	 * @return array<string, mixed>
	 */
	public function buildDataQuery(
		array $config,
		array $baseQuery,
		array $request,
		?array $fieldAliases = null,
		bool $paged = true
	): array {
		$fields = $this->getFields($config);
		$fieldDefs = $this->buildFieldDefs($fields);
		$selectedAliases = $fieldAliases === null
			? array_keys($fieldDefs)
			: $this->normalizeFieldAliases($fieldAliases, $fieldDefs);

		$dataQuery = $baseQuery;
		$dataQuery['fields'] = [];

		foreach($fields as $field) {
			$alias = (string)($field['alias'] ?? '');
			if($alias === '' || !in_array($alias, $selectedAliases, true) || !isset($field['element'])) {
				continue;
			}

			$dataQuery['fields'][] = [
				'element' => $field['element'],
				'alias' => $alias
			];
		}

		$sort = is_array($request['sort'] ?? null) ? $request['sort'] : [];
		$sortKey = (string)($sort['key'] ?? '');

		if($sortKey !== '' && isset($fieldDefs[$sortKey])) {
			$dataQuery['order_by'] = [[
				'element' => $fieldDefs[$sortKey],
				'direction' => strtoupper((string)($sort['dir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC'
			]];
		}
		elseif(isset($config['order_by'])) {
			$dataQuery['order_by'] = $config['order_by'];
		}
		else {
			unset($dataQuery['order_by']);
		}

		if(isset($config['group_by'])) {
			$dataQuery['group_by'] = $config['group_by'];
		}

		if(isset($config['having'])) {
			$dataQuery['having'] = $config['having'];
		}

		if($paged) {
			$page = max(1, (int)($request['page'] ?? 1));
			$pageSize = max(1, (int)($request['pageSize'] ?? 25));
			$dataQuery['offset'] = ($page - 1) * $pageSize;
			$dataQuery['limit'] = $pageSize;
		}
		else {
			unset($dataQuery['offset'], $dataQuery['limit']);
		}

		return $dataQuery;
	}

	/**
	 * @param array<string, mixed> $config
	 * @return array<int, array<string, mixed>>
	 */
	public function getFields(array $config): array {
		$fields = $config['fields'] ?? [];
		return is_array($fields) ? array_values(array_filter($fields, 'is_array')) : [];
	}

	/**
	 * @param array<int, array<string, mixed>> $fields
	 * @return array<string, mixed>
	 */
	public function buildFieldDefs(array $fields): array {
		$fieldDefs = [];

		foreach($fields as $field) {
			if(!isset($field['alias'], $field['element'])) {
				continue;
			}

			$fieldDefs[(string)$field['alias']] = $field['element'];
		}

		return $fieldDefs;
	}

	/**
	 * @param array<int, string> $aliases
	 * @param array<string, mixed> $fieldDefs
	 * @return array<int, string>
	 */
	public function normalizeFieldAliases(array $aliases, array $fieldDefs): array {
		$result = [];

		foreach($aliases as $alias) {
			$alias = trim((string)$alias);
			if($alias === '' || !array_key_exists($alias, $fieldDefs) || in_array($alias, $result, true)) {
				continue;
			}

			$result[] = $alias;
		}

		return $result;
	}

	/**
	 * @param mixed $sortPayload
	 * @param array<string, mixed> $config
	 * @param array<int, array<string, mixed>> $fields
	 * @return array<string, string>
	 */
	private function normalizeSort(mixed $sortPayload, array $config, array $fields): array {
		$fieldDefs = $this->buildFieldDefs($fields);
		$defaultKey = (string)($config['config']['sortColumn'] ?? ($fields[0]['alias'] ?? ''));
		$defaultDirection = strtolower((string)($config['config']['sortDirection'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

		if($defaultKey === '' || !isset($fieldDefs[$defaultKey])) {
			$defaultKey = (string)($fields[0]['alias'] ?? '');
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

		$key = isset($first['key']) ? (string)$first['key'] : $defaultKey;
		if(!isset($fieldDefs[$key])) {
			$key = $defaultKey;
		}

		$dir = isset($first['dir']) ? strtolower((string)$first['dir']) : $defaultDirection;
		$dir = $dir === 'desc' ? 'desc' : 'asc';

		return [
			'key' => $key,
			'dir' => $dir,
			'type' => $this->getFieldSortType($fields, $key),
		];
	}

	/**
	 * @param array<int, array<string, mixed>> $fields
	 * @return array<string, mixed>
	 */
	private function getSearchFieldDefs(array $fields): array {
		$fieldDefs = [];

		foreach($fields as $field) {
			if(!isset($field['alias'], $field['element'])) {
				continue;
			}

			$fieldConfig = isset($field['config']) && is_array($field['config']) ? $field['config'] : [];
			if(array_key_exists('search', $fieldConfig) && !$fieldConfig['search']) {
				continue;
			}

			$fieldDefs[(string)$field['alias']] = $field['element'];
		}

		return $fieldDefs;
	}

	/**
	 * @param array<int, array<string, mixed>> $fields
	 */
	private function getFieldSortType(array $fields, string $alias): string {
		foreach($fields as $field) {
			if((string)($field['alias'] ?? '') !== $alias) {
				continue;
			}

			$fieldConfig = isset($field['config']) && is_array($field['config']) ? $field['config'] : [];
			return (string)($fieldConfig['type'] ?? 'string');
		}

		return 'string';
	}
}
