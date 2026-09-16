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

use Base3\Api\IClassMap;
use Base3\Logger\Api\ILogger;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Api\IReportConfigDefinitionProvider;
use ResourceFoundation\Api\IReportingScopeRegistry;
use RuntimeException;
use Vizion\Api\IReportConfigProvider;
use Vizion\Api\IReportDataService;
use Vizion\Api\IReportFilterService;
use Vizion\Api\IReportTreeFilterService;

/**
 * Executes curated Vizion reports without going through a display or HTTP
 * endpoint.
 *
 * The first supported executable display is ModularGrid. Additional display
 * types should join this service only when their data semantics are extracted
 * from their displays into reusable services.
 */
final class ReportDataService implements IReportDataService {

	private const SUPPORTED_DISPLAY = 'modulargridreportdisplay';
	private const DEFAULT_LIST_LIMIT = 20;
	private const MAX_LIST_LIMIT = 100;
	private const DEFAULT_TREE_LIMIT = 20;
	private const MAX_TREE_LIMIT = 100;

	public function __construct(
		private readonly IClassMap $classMap,
		private readonly IReportingScopeRegistry $reportingScopeRegistry,
		private readonly IReportConfigProvider $reportConfigProvider,
		private readonly IQueryService $queryService,
		private readonly IReportFilterService $reportFilterService,
		private readonly IReportTreeFilterService $reportTreeFilterService,
		private readonly ModularGridReportQueryBuilder $queryBuilder,
		private readonly ILogger $logger
	) {}

	public function listReports(string $reportingScope, string $search = '', int $limit = self::DEFAULT_LIST_LIMIT): array {
		$reportingScope = trim($reportingScope);
		if($reportingScope === '') {
			throw new \InvalidArgumentException('Missing reporting scope.');
		}

		$scope = $this->reportingScopeRegistry->get($reportingScope);
		if($scope === null) {
			throw new RuntimeException('Reporting scope not found: ' . $reportingScope);
		}

		$limit = max(1, min(self::MAX_LIST_LIMIT, $limit));
		$search = trim($search);
		$definitions = $this->getDefinitionsForProviderScopes($scope->reportScopes);
		$ranked = [];

		foreach($definitions as $name => $definition) {
			if(!$this->isSupportedConfig($definition)) {
				continue;
			}

			$score = $this->scoreReport($name, $definition, $search);
			if($score <= 0) {
				continue;
			}

			$ranked[] = [
				'score' => $score,
				'report' => $this->buildReportSummary($reportingScope, $name, $definition)
			];
		}

		usort($ranked, function(array $a, array $b): int {
			if($a['score'] === $b['score']) {
				return strcmp((string)$a['report']['id'], (string)$b['report']['id']);
			}
			return $b['score'] <=> $a['score'];
		});

		$total = count($ranked);
		$reports = [];
		foreach(array_slice($ranked, 0, $limit) as $entry) {
			$reports[] = $entry['report'];
		}

		return [
			'reportingScope' => $reportingScope,
			'label' => $scope->label,
			'search' => $search,
			'matchCount' => $total,
			'truncated' => $total > count($reports),
			'reports' => $reports,
		];
	}

	public function describeReport(string $report): array {
		$config = $this->loadExecutableConfig($report);
		$fields = $this->queryBuilder->getFields($config);
		$fieldDescriptions = [];
		$filters = [];

		foreach($fields as $field) {
			$alias = trim((string)($field['alias'] ?? ''));
			if($alias === '') {
				continue;
			}

			$fieldConfig = isset($field['config']) && is_array($field['config']) ? $field['config'] : [];
			$fieldDescription = [
				'key' => $alias,
				'label' => (string)($fieldConfig['label'] ?? $alias),
				'type' => (string)($fieldConfig['type'] ?? 'string'),
				'sortable' => (bool)($fieldConfig['sortable'] ?? false),
				'searchable' => !array_key_exists('search', $fieldConfig) || (bool)$fieldConfig['search'],
			];

			if(isset($fieldConfig['description']) && is_scalar($fieldConfig['description'])) {
				$fieldDescription['description'] = trim((string)$fieldConfig['description']);
			}

			$fieldDescriptions[] = $fieldDescription;

		}

		foreach($this->reportFilterService->buildGridFilterFields($fields) as $filter) {
			if(!is_array($filter)) {
				continue;
			}

			$key = trim((string)($filter['key'] ?? ''));
			if($key === '') {
				continue;
			}

			$filterDescription = [
				'key' => $key,
				'label' => (string)($filter['label'] ?? $key),
				'type' => (string)($filter['type'] ?? 'text'),
				'match' => (string)($filter['match'] ?? ''),
				'visibility' => (string)($filter['visibility'] ?? 'always'),
			];

			if(isset($filter['options']) && is_array($filter['options'])) {
				$filterDescription['options'] = $filter['options'];
			}

			if(array_key_exists('initialValue', $filter)) {
				$filterDescription['initialValue'] = $filter['initialValue'];
			}

			$filters[] = $filterDescription;
		}

		$treeFilters = [];
		foreach($this->getTreeFilterDefinitions($config) as $definition) {
			$key = trim((string)($definition['key'] ?? ''));
			if($key === '') {
				continue;
			}

			$treeFilters[] = [
				'key' => $key,
				'label' => (string)($definition['label'] ?? $key),
				'description' => trim((string)($definition['description'] ?? '')),
				'emptyLabel' => (string)($definition['emptyLabel'] ?? 'Not filtered'),
			];
		}

		$reportConfig = isset($config['config']) && is_array($config['config']) ? $config['config'] : [];

		return [
			'id' => $report,
			'label' => (string)($reportConfig['title'] ?? $report),
			'description' => trim((string)($config['description'] ?? '')),
			'display' => (string)($config['display'] ?? ''),
			'fields' => $fieldDescriptions,
			'filters' => $filters,
			'treeFilters' => $treeFilters,
			'defaults' => [
				'pageSize' => (int)($reportConfig['pageSize'] ?? 25),
				'sortColumn' => (string)($reportConfig['sortColumn'] ?? ''),
				'sortDirection' => (string)($reportConfig['sortDirection'] ?? 'asc'),
			],
		];
	}

	public function executeReport(string $report, array $payload = []): array {
		$config = $this->loadExecutableConfig($report);
		$config['report'] = $report;

		return $this->executeConfig($config, $payload);
	}

	public function executeConfig(array $config, array $payload = []): array {
		$this->assertSupportedConfig($config);
		$report = trim((string)($config['report'] ?? ''));
		$request = $this->queryBuilder->normalizeRequest($payload, $config);
		$fields = $this->queryBuilder->getFields($config);
		$fieldAliases = $this->resolveRequestedFieldAliases($payload['fields'] ?? null, $fields);
		$baseQuery = $this->queryBuilder->buildBaseQuery($config, $request);
		$total = $this->loadTotal($config, $baseQuery);
		$pageSize = (int)$request['pageSize'];
		$page = (int)$request['page'];
		$totalPages = $pageSize > 0 ? (int)ceil($total / $pageSize) : 0;
		$dataQuery = $this->queryBuilder->buildDataQuery($config, $baseQuery, $request, $fieldAliases);
		$result = $this->queryService->executeQuery($dataQuery);

		$this->logger->log('Vizion', 'REPORTDATA RES | ' . $result->debugSql);

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
			'report' => $report,
			'mode' => 'page',
			'data' => $rows,
			'groups' => [],
			'page' => $page,
			'pageSize' => $pageSize,
			'total' => $total,
			'totalPages' => $totalPages,
			'hasMore' => ($offset + $pageSize) < $total,
			'nextCursor' => null,
			'fields' => $fieldAliases ?? array_values(array_filter(array_map(
				fn(array $field): string => trim((string)($field['alias'] ?? '')),
				$fields
			))),
			'appliedSearch' => $request['search'],
			'appliedSort' => [$request['sort']],
			'appliedFilters' => $request['filters'],
			'appliedTreeFilters' => $request['treeFilters'],
			'appliedGroup' => [],
		];
	}

	public function loadTree(string $report, string $key): array {
		$config = $this->loadExecutableConfig($report);
		$config['report'] = $report;

		return $this->loadTreeConfig($config, $key);
	}

	public function loadTreeConfig(array $config, string $key): array {
		$this->assertSupportedConfig($config);
		$key = trim($key);
		if($key === '') {
			throw new \InvalidArgumentException('Missing tree filter key.');
		}

		return $this->reportTreeFilterService->loadTree($config, $key);
	}

	public function searchTree(string $report, string $key, string $search = '', int $limit = self::DEFAULT_TREE_LIMIT): array {
		$nodes = $this->loadTree($report, $key);
		$limit = max(1, min(self::MAX_TREE_LIMIT, $limit));
		$tokens = $this->searchTokens($search);
		$nodesById = [];

		foreach($nodes as $node) {
			$id = trim((string)($node['id'] ?? ''));
			if($id !== '') {
				$nodesById[$id] = $node;
			}
		}

		$matches = [];
		foreach($nodes as $node) {
			$id = trim((string)($node['id'] ?? ''));
			if($id === '') {
				continue;
			}

			$path = $this->buildNodePath($id, $nodesById);
			$pathText = implode(' / ', array_map(
				fn(array $part): string => (string)$part['label'],
				$path
			));
			$haystack = $this->lower((string)($node['label'] ?? '') . ' ' . $pathText);

			if(!$this->matchesAllTokens($haystack, $tokens)) {
				continue;
			}

			$matches[] = [
				'id' => $id,
				'label' => (string)($node['label'] ?? $id),
				'parentId' => (string)($node['parentId'] ?? ''),
				'depth' => $node['depth'] ?? null,
				'path' => $path,
				'pathLabel' => $pathText,
			];
		}

		usort($matches, function(array $a, array $b): int {
			$pathCompare = strcmp((string)$a['pathLabel'], (string)$b['pathLabel']);
			return $pathCompare !== 0 ? $pathCompare : strcmp((string)$a['id'], (string)$b['id']);
		});

		$total = count($matches);

		return [
			'report' => $report,
			'treeFilter' => $key,
			'search' => trim($search),
			'matchCount' => $total,
			'truncated' => $total > $limit,
			'nodes' => array_slice($matches, 0, $limit),
		];
	}

	/**
	 * @param array<string,mixed> $config
	 * @param array<string,mixed> $baseQuery
	 */
	private function loadTotal(array $config, array $baseQuery): int {
		$countQuery = $this->queryBuilder->buildCountQuery($config, $baseQuery);
		$result = $this->queryService->executeQuery($countQuery);
		$this->logger->log('Vizion', 'REPORTDATA CNT | ' . $result->debugSql);

		return (int)($result->rows[0]['__total__'] ?? 0);
	}

	/**
	 * @param array<int,array<string,mixed>> $fields
	 * @return array<int,string>|null
	 */
	private function resolveRequestedFieldAliases(mixed $payload, array $fields): ?array {
		if($payload === null) {
			return null;
		}
		if(!is_array($payload)) {
			throw new \InvalidArgumentException('Report fields must be an array of configured field aliases.');
		}
		if($payload === []) {
			return null;
		}

		$fieldDefs = $this->queryBuilder->buildFieldDefs($fields);
		$aliases = $this->queryBuilder->normalizeFieldAliases($payload, $fieldDefs);
		if($aliases === []) {
			throw new \InvalidArgumentException('None of the requested report fields are configured for this report.');
		}

		return $aliases;
	}

	/**
	 * @param array<string,mixed> $row
	 */
	private function buildRowKey(array $row, int $fallback): string {
		$normalized = [];

		foreach($row as $key => $value) {
			$key = (string)$key;
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
			$encoded = 'fallback-' . (string)$fallback;
		}

		return 'report-row-' . substr(sha1($encoded), 0, 20);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function loadExecutableConfig(string $report): array {
		$report = trim($report);
		if($report === '') {
			throw new \InvalidArgumentException('Missing report identifier.');
		}

		$config = $this->reportConfigProvider->getConfig($report);
		$this->assertSupportedConfig($config);

		return $config;
	}

	/** @param array<string,mixed> $config */
	private function assertSupportedConfig(array $config): void {
		if(!$this->isSupportedConfig($config)) {
			throw new RuntimeException(
				'Report display is not supported by headless Vizion report execution: ' . (string)($config['display'] ?? '')
			);
		}
	}

	/**
	 * @param string[] $providerScopes
	 * @return array<string,array<string,mixed>>
	 */
	private function getDefinitionsForProviderScopes(array $providerScopes): array {
		$providers = $this->getProviders();
		$definitions = [];

		foreach($providerScopes as $providerScope) {
			$provider = $providers[$providerScope] ?? null;
			if(!$provider instanceof IReportConfigDefinitionProvider) {
				continue;
			}

			foreach($provider->getDefinitions() as $name => $dataset) {
				$name = trim((string)$name);
				if($name === '' || !is_array($dataset) || !$this->isEnabled($dataset)) {
					continue;
				}

				$definition = $dataset['definition'] ?? null;
				if(!is_array($definition)) {
					throw new RuntimeException('Report definition dataset must contain a definition array: ' . $providerScope . '/' . $name);
				}
				if(isset($definitions[$name])) {
					throw new RuntimeException('Report identifier is not unique inside reporting scope: ' . $name);
				}

				$definitions[$name] = $definition;
			}
		}

		return $definitions;
	}

	/** @return array<string,IReportConfigDefinitionProvider> */
	private function getProviders(): array {
		$providers = [];

		foreach($this->classMap->getInstancesByInterface(IReportConfigDefinitionProvider::class) as $provider) {
			if(!$provider instanceof IReportConfigDefinitionProvider) {
				continue;
			}

			$scope = trim($provider->getScope());
			if($scope === '') {
				throw new RuntimeException('Report definition provider has an empty scope: ' . $provider::getName());
			}
			if(isset($providers[$scope])) {
				throw new RuntimeException('Report definition scope is not unique: ' . $scope);
			}

			$providers[$scope] = $provider;
		}

		return $providers;
	}

	/** @param array<string,mixed> $dataset */
	private function isEnabled(array $dataset): bool {
		if(!array_key_exists('enabled', $dataset)) {
			return true;
		}

		$value = $dataset['enabled'];
		if(is_bool($value)) {
			return $value;
		}
		if(is_numeric($value)) {
			return (int)$value !== 0;
		}
		if(is_string($value)) {
			$value = strtolower(trim($value));
			if(in_array($value, ['1', 'true', 'yes', 'on', 'enabled'], true)) {
				return true;
			}
			if(in_array($value, ['0', 'false', 'no', 'off', 'disabled'], true)) {
				return false;
			}
		}

		return true;
	}

	/** @param array<string,mixed> $config */
	private function isSupportedConfig(array $config): bool {
		return strtolower(trim((string)($config['display'] ?? ''))) === self::SUPPORTED_DISPLAY;
	}

	/**
	 * @param array<string,mixed> $config
	 * @return array<string,mixed>
	 */
	private function buildReportSummary(string $reportingScope, string $name, array $config): array {
		$reportConfig = isset($config['config']) && is_array($config['config']) ? $config['config'] : [];
		$treeFilters = [];

		foreach($this->getTreeFilterDefinitions($config) as $definition) {
			$key = trim((string)($definition['key'] ?? ''));
			if($key !== '') {
				$treeFilters[] = [
					'key' => $key,
					'label' => (string)($definition['label'] ?? $key)
				];
			}
		}

		return [
			'id' => $name,
			'qualifiedId' => $reportingScope . ':' . $name,
			'label' => (string)($reportConfig['title'] ?? $name),
			'description' => trim((string)($config['description'] ?? '')),
			'display' => (string)($config['display'] ?? ''),
			'fieldCount' => count($this->queryBuilder->getFields($config)),
			'treeFilters' => $treeFilters,
		];
	}

	/** @param array<string,mixed> $config */
	private function scoreReport(string $name, array $config, string $search): int {
		if(trim($search) === '') {
			return 1;
		}

		$parts = [
			$name,
			(string)($config['description'] ?? ''),
		];
		$reportConfig = isset($config['config']) && is_array($config['config']) ? $config['config'] : [];
		$parts[] = (string)($reportConfig['title'] ?? '');

		foreach($this->queryBuilder->getFields($config) as $field) {
			$parts[] = (string)($field['alias'] ?? '');
			$fieldConfig = isset($field['config']) && is_array($field['config']) ? $field['config'] : [];
			$parts[] = (string)($fieldConfig['label'] ?? '');
		}

		foreach($this->getTreeFilterDefinitions($config) as $definition) {
			$parts[] = (string)($definition['key'] ?? '');
			$parts[] = (string)($definition['label'] ?? '');
			$parts[] = (string)($definition['description'] ?? '');
		}

		$haystack = $this->lower(implode(' ', $parts));
		$searchLower = $this->lower(trim($search));
		$score = str_contains($haystack, $searchLower) ? 100 : 0;

		foreach($this->searchTokens($search) as $token) {
			if(str_contains($haystack, $token)) {
				$score += 15;
			}
		}

		return $score;
	}

	/**
	 * @param array<string,mixed> $config
	 * @return array<int,array<string,mixed>>
	 */
	private function getTreeFilterDefinitions(array $config): array {
		$definitions = $config['treeFilters'] ?? [];
		return is_array($definitions) ? array_values(array_filter($definitions, 'is_array')) : [];
	}

	/**
	 * @param array<string,array<string,mixed>> $nodesById
	 * @return array<int,array{id:string,label:string}>
	 */
	private function buildNodePath(string $id, array $nodesById): array {
		$path = [];
		$seen = [];
		$currentId = $id;

		while($currentId !== '' && isset($nodesById[$currentId]) && !isset($seen[$currentId])) {
			$seen[$currentId] = true;
			$node = $nodesById[$currentId];
			array_unshift($path, [
				'id' => $currentId,
				'label' => (string)($node['label'] ?? $currentId),
			]);
			$currentId = trim((string)($node['parentId'] ?? ''));
		}

		return $path;
	}

	/** @return string[] */
	private function searchTokens(string $search): array {
		$search = trim($this->lower($search));
		if($search === '') {
			return [];
		}

		$tokens = preg_split('/\s+/u', $search) ?: [];
		return array_values(array_filter(array_unique($tokens), fn(string $token): bool => $token !== ''));
	}

	/** @param string[] $tokens */
	private function matchesAllTokens(string $haystack, array $tokens): bool {
		foreach($tokens as $token) {
			if(!str_contains($haystack, $token)) {
				return false;
			}
		}

		return true;
	}

	private function lower(string $value): string {
		return function_exists('mb_strtolower')
			? mb_strtolower($value, 'UTF-8')
			: strtolower($value);
	}
}
