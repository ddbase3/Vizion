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

namespace Vizion\TreeFilter;

use ResourceFoundation\Api\IQueryService;
use RuntimeException;
use Vizion\Api\IReportTreeFilterService;

final class ReportTreeFilterService implements IReportTreeFilterService {

	public function __construct(
		private readonly IQueryService $queryService
	) {}

	public function buildGridTreeFilters(array $config): array {
		$result = [];

		foreach($this->getDefinitions($config) as $definition) {
			$key = trim((string)($definition['key'] ?? ''));
			if($key === '') {
				continue;
			}

			$field = [
				'key' => $key,
				'label' => (string)($definition['label'] ?? $key),
				'shortLabel' => (string)($definition['shortLabel'] ?? ($definition['label'] ?? $key)),
				'emptyLabel' => (string)($definition['emptyLabel'] ?? ($definition['label'] ?? $key)),
				'clearLabel' => (string)($definition['clearLabel'] ?? ($definition['emptyLabel'] ?? ($definition['label'] ?? $key))),
				'accentColor' => (string)($definition['accentColor'] ?? ''),
				'searchPlaceholder' => (string)($definition['searchPlaceholder'] ?? 'Search'),
				'defaultValue' => $this->normalizeSelection($definition['defaultValue'] ?? ''),
				'initialValue' => $this->normalizeSelection($definition['initialValue'] ?? ($definition['defaultValue'] ?? '')),
			];

			foreach(['width', 'minWidth', 'maxWidth'] as $dimension) {
				if(isset($definition[$dimension]) && is_numeric($definition[$dimension])) {
					$field[$dimension] = (int)$definition[$dimension];
				}
			}

			$result[] = $field;
		}

		return $result;
	}

	public function normalizeTreeFilters(mixed $payload, array $config): array {
		if(!is_array($payload)) {
			return [];
		}

		$definitions = $this->getDefinitionsByKey($config);
		$result = [];

		foreach($payload as $key => $value) {
			if(!is_string($key) || !isset($definitions[$key])) {
				continue;
			}

			$selection = $this->normalizeSelection($value);
			if($selection === '') {
				continue;
			}

			$result[$key] = $selection;
		}

		return $result;
	}

	public function loadTree(array $config, string $key): array {
		$definition = $this->getDefinition($config, $key);
		$source = $this->getSource($definition, $key);

		$query = [
			'type' => 'select',
			'schema' => $source['schema'],
			'table' => $source['table'],
			'fields' => [
				['element' => $source['id'], 'alias' => 'id'],
				['element' => $source['parentId'], 'alias' => 'parent_id'],
				['element' => $source['label'], 'alias' => 'label'],
				['element' => $source['depth'], 'alias' => 'depth'],
			]
		];

		if(isset($source['where']) && is_array($source['where'])) {
			$query['where'] = $source['where'];
		}

		$query['order_by'] = $this->getSourceOrderBy($source);
		$result = $this->queryService->executeQuery($query);
		$nodes = [];

		foreach($result->rows as $row) {
			if(!is_array($row)) {
				continue;
			}

			$id = $this->normalizeSelection($row['id'] ?? '');
			if($id === '') {
				continue;
			}

			$nodes[] = [
				'id' => $id,
				'parentId' => $this->normalizeSelection($row['parent_id'] ?? ''),
				'label' => (string)($row['label'] ?? $id),
				'depth' => isset($row['depth']) && is_numeric($row['depth']) ? (int)$row['depth'] : null,
			];
		}

		return $nodes;
	}

	public function buildFilterWhere(array $treeFilters, array $config): ?array {
		$definitions = $this->getDefinitionsByKey($config);
		$conditions = [];

		foreach($treeFilters as $key => $selection) {
			if(!isset($definitions[$key]) || $selection === '') {
				continue;
			}

			$definition = $definitions[$key];
			$source = $this->getSource($definition, $key);
			$selectedNode = $this->loadSelectedNode($source, $selection, $key);
			$filter = isset($definition['filter']) && is_array($definition['filter']) ? $definition['filter'] : [];
			$type = strtolower(trim((string)($filter['type'] ?? '')));

			$condition = match($type) {
				'range' => $this->buildRangeCondition($filter, $selectedNode, $key),
				'node' => $this->buildNodeCondition($source, $filter, $selectedNode, $key),
				'membership' => $this->buildMembershipCondition($filter, $selectedNode, $key),
				'path_membership' => $this->buildPathMembershipCondition($filter, $selection, $key),
				default => throw new RuntimeException('Unsupported tree filter type for ' . $key . ': ' . $type),
			};

			$conditions[] = $condition;
		}

		if($conditions === []) {
			return null;
		}

		return count($conditions) === 1
			? $conditions[0]
			: $this->op('AND', ...$conditions);
	}

	/** @return array<int,array<string,mixed>> */
	private function getDefinitions(array $config): array {
		$definitions = $config['treeFilters'] ?? [];
		return is_array($definitions) ? array_values(array_filter($definitions, 'is_array')) : [];
	}

	/** @return array<string,array<string,mixed>> */
	private function getDefinitionsByKey(array $config): array {
		$result = [];

		foreach($this->getDefinitions($config) as $definition) {
			$key = trim((string)($definition['key'] ?? ''));
			if($key === '') {
				continue;
			}
			if(isset($result[$key])) {
				throw new RuntimeException('Tree filter key is not unique: ' . $key);
			}
			$result[$key] = $definition;
		}

		return $result;
	}

	/** @return array<string,mixed> */
	private function getDefinition(array $config, string $key): array {
		$definitions = $this->getDefinitionsByKey($config);
		if(!isset($definitions[$key])) {
			throw new RuntimeException('Tree filter is not configured: ' . $key);
		}
		return $definitions[$key];
	}

	/** @return array<string,mixed> */
	private function getSource(array $definition, string $key): array {
		$source = isset($definition['source']) && is_array($definition['source']) ? $definition['source'] : [];
		$required = ['schema', 'table', 'id', 'parentId', 'label', 'tree', 'left', 'right', 'depth'];

		foreach($required as $name) {
			if(!array_key_exists($name, $source) || $source[$name] === '' || $source[$name] === null) {
				throw new RuntimeException('Tree filter source ' . $key . ' is missing ' . $name . '.');
			}
		}

		return $source;
	}

	/** @return array<int,array<string,mixed>> */
	private function getSourceOrderBy(array $source): array {
		if(isset($source['orderBy']) && is_array($source['orderBy'])) {
			return array_values(array_filter($source['orderBy'], 'is_array'));
		}

		return [
			['element' => $source['tree'], 'direction' => 'ASC'],
			['element' => $source['left'], 'direction' => 'ASC'],
		];
	}

	/** @return array{tree:mixed,left:mixed,right:mixed} */
	private function loadSelectedNode(array $source, string $selection, string $key): array {
		$where = $this->op('=', $source['id'], $selection);
		if(isset($source['where']) && is_array($source['where'])) {
			$where = $this->op('AND', $source['where'], $where);
		}

		$query = [
			'type' => 'select',
			'schema' => $source['schema'],
			'table' => $source['table'],
			'fields' => [
				['element' => $source['tree'], 'alias' => 'tree'],
				['element' => $source['left'], 'alias' => 'lft'],
				['element' => $source['right'], 'alias' => 'rgt'],
			],
			'where' => $where,
			'limit' => 1,
		];
		$result = $this->queryService->executeQuery($query);
		$row = $result->rows[0] ?? null;

		if(!is_array($row) || !array_key_exists('tree', $row) || !array_key_exists('lft', $row) || !array_key_exists('rgt', $row)) {
			throw new RuntimeException('Tree filter selection is not available for ' . $key . '.');
		}

		return [
			'tree' => $row['tree'],
			'left' => $row['lft'],
			'right' => $row['rgt'],
		];
	}

	/** @return array<string,mixed> */
	private function buildRangeCondition(array $filter, array $selectedNode, string $key): array {
		$tree = $filter['tree'] ?? null;
		$left = $filter['left'] ?? null;
		$right = $filter['right'] ?? null;
		if(!is_array($tree) || !is_array($left) || !is_array($right)) {
			throw new RuntimeException('Range tree filter ' . $key . ' requires tree, left and right elements.');
		}

		return $this->op(
			'AND',
			$this->op('=', $tree, $selectedNode['tree']),
			$this->op('>=', $left, $selectedNode['left']),
			$this->op('<=', $right, $selectedNode['right'])
		);
	}

	/** @return array<string,mixed> */
	private function buildNodeCondition(array $source, array $filter, array $selectedNode, string $key): array {
		$element = $filter['element'] ?? null;
		$value = $filter['value'] ?? $source['id'];
		if(!is_array($element)) {
			throw new RuntimeException('Node tree filter ' . $key . ' requires an element.');
		}
		if(!is_array($value)) {
			throw new RuntimeException('Node tree filter ' . $key . ' requires a value element.');
		}

		$where = $this->buildNestedSetWhere(
			$source['tree'],
			$source['left'],
			$source['right'],
			$selectedNode,
			isset($filter['where']) && is_array($filter['where']) ? $filter['where'] : null
		);
		$query = [
			'type' => 'select',
			'schema' => $source['schema'],
			'table' => $source['table'],
			'distinct' => true,
			'fields' => [[
				'element' => $value,
				'alias' => 'tree_filter_value'
			]],
			'where' => $where,
		];

		return $this->op('IN', $element, $this->subquery($query));
	}

	/** @return array<string,mixed> */
	private function buildPathMembershipCondition(array $filter, string $selection, string $key): array {
		$element = $filter['element'] ?? null;
		$source = isset($filter['source']) && is_array($filter['source']) ? $filter['source'] : [];
		if(!is_array($element)) {
			throw new RuntimeException('Path membership tree filter ' . $key . ' requires an element.');
		}

		foreach(['schema', 'table', 'value', 'path'] as $name) {
			if(!array_key_exists($name, $source) || $source[$name] === '' || $source[$name] === null) {
				throw new RuntimeException('Path membership tree filter ' . $key . ' is missing source ' . $name . '.');
			}
		}
		if(!is_array($source['value']) || !is_array($source['path'])) {
			throw new RuntimeException('Path membership tree filter ' . $key . ' requires value and path elements.');
		}

		$delimiter = (string)($source['delimiter'] ?? ',');
		if($delimiter === '') {
			throw new RuntimeException('Path membership tree filter ' . $key . ' requires a non-empty delimiter.');
		}

		$where = $this->op(
			'LIKE',
			$source['path'],
			'%' . $delimiter . $selection . $delimiter . '%'
		);
		if(isset($source['where']) && is_array($source['where'])) {
			$where = $this->op('AND', $where, $source['where']);
		}

		$query = [
			'type' => 'select',
			'schema' => (string)$source['schema'],
			'table' => (string)$source['table'],
			'distinct' => true,
			'fields' => [[
				'element' => $source['value'],
				'alias' => 'tree_filter_value'
			]],
			'where' => $where,
		];

		return $this->op('IN', $element, $this->subquery($query));
	}

	/** @return array<string,mixed> */
	private function buildMembershipCondition(array $filter, array $selectedNode, string $key): array {
		$element = $filter['element'] ?? null;
		$source = isset($filter['source']) && is_array($filter['source']) ? $filter['source'] : [];
		if(!is_array($element)) {
			throw new RuntimeException('Membership tree filter ' . $key . ' requires an element.');
		}

		foreach(['schema', 'table', 'value', 'tree', 'left', 'right'] as $name) {
			if(!array_key_exists($name, $source) || $source[$name] === '' || $source[$name] === null) {
				throw new RuntimeException('Membership tree filter ' . $key . ' is missing source ' . $name . '.');
			}
		}

		$where = $this->buildNestedSetWhere(
			$source['tree'],
			$source['left'],
			$source['right'],
			$selectedNode,
			isset($source['where']) && is_array($source['where']) ? $source['where'] : null
		);
		$query = [
			'type' => 'select',
			'schema' => $source['schema'],
			'table' => $source['table'],
			'distinct' => true,
			'fields' => [[
				'element' => $source['value'],
				'alias' => 'tree_filter_value'
			]],
			'where' => $where,
		];

		return $this->op('IN', $element, $this->subquery($query));
	}

	/** @return array<string,mixed> */
	private function buildNestedSetWhere(array $tree, array $left, array $right, array $selectedNode, ?array $extraWhere): array {
		$params = [
			$this->op('=', $tree, $selectedNode['tree']),
			$this->op('>=', $left, $selectedNode['left']),
			$this->op('<=', $right, $selectedNode['right']),
		];

		if($extraWhere !== null) {
			$params[] = $extraWhere;
		}

		return $this->op('AND', ...$params);
	}

	private function normalizeSelection(mixed $value): string {
		return is_scalar($value) ? trim((string)$value) : '';
	}

	/** @return array<string,mixed> */
	private function subquery(array $query): array {
		return ['type' => 'subquery', 'query' => $query];
	}

	/** @return array<string,mixed> */
	private function op(string $operator, mixed ...$params): array {
		return ['type' => 'op', 'operator' => $operator, 'params' => $params];
	}
}
