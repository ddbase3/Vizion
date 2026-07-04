<?php declare(strict_types=1);

namespace Vizion\Chart;

use Base3\Api\IClassMap;
use ResourceFoundation\Dto\QueryResult;
use Vizion\Api\IReportCellRendererService;
use Vizion\Api\IReportChartService;
use Vizion\Api\IReportFilterService;

final class ReportChartService implements IReportChartService {

	private ReportChartRendererRegistry $rendererRegistry;

	public function __construct(
		IClassMap $classMap,
		private readonly IReportFilterService $reportFilterService,
		private readonly IReportCellRendererService $reportCellRendererService
	) {
		$this->rendererRegistry = new ReportChartRendererRegistry($classMap);
	}

	public function buildClientConfig(array $config, array $fields): array {
		$chartConfig = $this->getChartConfig($config);
		$dimension = $this->normalizeDimension($chartConfig, $fields);
		$measures = $this->normalizeMeasures($chartConfig, $fields);
		$type = (string) ($chartConfig['chartType'] ?? $chartConfig['type'] ?? 'bar');
		if(strtolower($type) === 'chartjs' || strtolower($type) === 'chart') {
			$type = 'bar';
		}
		$renderer = $this->rendererRegistry->getRenderer($type);
		$fieldColumns = $this->buildFieldColumnsByAlias($fields);

		if(isset($fieldColumns[$dimension['field']])) {
			$dimension['formatter'] = $fieldColumns[$dimension['field']]['formatter'] ?? ['type' => 'text'];
		}

		foreach($measures as $index => $measure) {
			$field = (string) ($measure['field'] ?? '');
			if($field !== '' && isset($fieldColumns[$field])) {
				$measures[$index]['formatter'] = $fieldColumns[$field]['formatter'] ?? ['type' => 'number'];
			}
		}

		return $renderer->buildClientConfig($chartConfig, $measures, $dimension);
	}

	public function buildQuery(array $config, array $fields, array $filters): array {
		$chartConfig = $this->getChartConfig($config);
		$dimension = $this->normalizeDimension($chartConfig, $fields);
		$measures = $this->normalizeMeasures($chartConfig, $fields);
		$fieldDefs = $this->buildFieldDefs($fields);
		$dimensionAlias = (string) $dimension['field'];
		$preparedMode = $this->isPreparedDataMode($chartConfig);

		$dimensionElement = $this->buildDimensionElement($dimension, $fieldDefs, $config);

		$query = [
			'type' => 'select',
			'schema' => (string) ($config['schema'] ?? ''),
			'table' => (string) ($config['table'] ?? ''),
			'fields' => [[
				'element' => $dimensionElement,
				'alias' => '__dimension__'
			]]
		];

		if(!$preparedMode) {
			$query['group_by'] = [$dimensionElement];
		}

		foreach($measures as $measure) {
			$query['fields'][] = [
				'element' => $preparedMode ? $this->buildPreparedMeasureElement($measure, $fieldDefs, $config) : $this->buildMeasureElement($measure, $fieldDefs, $config),
				'alias' => (string) $measure['alias']
			];
		}

		$where = $this->buildWhere($config, $fields, $fieldDefs, $filters, $chartConfig);
		if($where !== null) {
			$query['where'] = $where;
		}

		$orderBy = $this->buildOrderBy($chartConfig, $dimension, $measures, $fieldDefs, $config, $preparedMode);
		if($orderBy !== []) {
			$query['order_by'] = $orderBy;
		}

		$limit = $chartConfig['limit'] ?? null;
		if(is_numeric($limit) && (int) $limit > 0) {
			$query['limit'] = (int) $limit;
		}

		return $query;
	}

	public function buildPayload(array $config, QueryResult $result): array {
		$chartConfig = $this->getChartConfig($config);
		$fields = $this->getFields($config);
		$clientConfig = $this->buildClientConfig($config, $fields);
		$measures = $clientConfig['measures'] ?? [];
		$buckets = $this->buildDimensionBuckets($clientConfig, $chartConfig);
		$datasets = [];

		foreach($measures as $measure) {
			$datasets[(string) ($measure['alias'] ?? '')] = [
				'label' => (string) ($measure['label'] ?? $measure['alias'] ?? ''),
				'data' => [],
				'vizionMeasure' => $measure
			];
		}

		foreach($result->rows ?? [] as $row) {
			if(!is_array($row)) {
				continue;
			}

			$dimensionValue = $row['__dimension__'] ?? '';
			$key = $this->buildBucketKey($dimensionValue);

			if(!isset($buckets[$key])) {
				$buckets[$key] = [
					'value' => $dimensionValue,
					'row' => [
						'__dimension__' => $dimensionValue
					]
				];
			}

			foreach($measures as $measure) {
				$alias = (string) ($measure['alias'] ?? '');
				$value = $row[$alias] ?? 0;
				$buckets[$key]['row'][$alias] = is_numeric($value) ? (float) $value : 0.0;
			}
		}

		$buckets = $this->normalizeIntervalBuckets($buckets, $clientConfig, $chartConfig);

		$labels = [];
		$rows = [];

		foreach($buckets as $bucket) {
			$row = isset($bucket['row']) && is_array($bucket['row']) ? $bucket['row'] : [];
			$labels[] = $bucket['value'] ?? '';

			foreach($measures as $measure) {
				$alias = (string) ($measure['alias'] ?? '');
				$value = $row[$alias] ?? 0;
				$number = is_numeric($value) ? (float) $value : 0.0;
				$datasets[$alias]['data'][] = $number;
				$row[$alias] = $number;
			}

			$row['__dimension__'] = $bucket['value'] ?? '';
			$rows[] = $row;
		}

		return [
			'ok' => true,
			'mode' => 'chart',
			'chart' => $clientConfig,
			'labels' => $labels,
			'datasets' => array_values($datasets),
			'rows' => $rows,
			'total' => count($labels),
			'appliedChart' => $chartConfig
		];
	}

	/** @return array<string,array<string,mixed>> */
	private function buildDimensionBuckets(array $clientConfig, array $chartConfig): array {
		$values = $this->getConfiguredBucketValues($clientConfig, $chartConfig);
		$buckets = [];

		foreach($values as $value) {
			$key = $this->buildBucketKey($value);

			if(isset($buckets[$key])) {
				continue;
			}

			$buckets[$key] = [
				'value' => $value,
				'row' => [
					'__dimension__' => $value
				]
			];
		}

		return $buckets;
	}

	/** @return array<int,mixed> */
	private function getConfiguredBucketValues(array $clientConfig, array $chartConfig): array {
		$dimension = isset($clientConfig['dimension']) && is_array($clientConfig['dimension']) ? $clientConfig['dimension'] : [];
		$chartBuckets = $chartConfig['buckets'] ?? $chartConfig['dimensionBuckets'] ?? null;

		if(is_array($chartBuckets)) {
			return $this->extractBucketValues($chartBuckets);
		}

		$dimensionBuckets = $dimension['buckets'] ?? null;
		if(is_array($dimensionBuckets)) {
			return $this->extractBucketValues($dimensionBuckets);
		}

		$formatter = isset($dimension['formatter']) && is_array($dimension['formatter']) ? $dimension['formatter'] : [];
		$options = $formatter['options'] ?? null;

		return is_array($options) ? $this->extractBucketValues($options) : [];
	}

	/** @param array<int|string,mixed> $items @return array<int,mixed> */
	private function extractBucketValues(array $items): array {
		$values = [];

		foreach($items as $key => $item) {
			if(is_array($item) && array_key_exists('value', $item)) {
				$values[] = $item['value'];
				continue;
			}

			if(is_scalar($item) || $item === null) {
				$values[] = $item;
				continue;
			}

			if(is_string($key) || is_int($key)) {
				$values[] = $key;
			}
		}

		return $values;
	}

	private function buildBucketKey(mixed $value): string {
		if(is_bool($value)) {
			return $value ? 'bool:true' : 'bool:false';
		}

		if($value === null) {
			return 'null';
		}

		if(is_int($value) || is_float($value) || is_string($value)) {
			return 'scalar:' . (string) $value;
		}

		return md5(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: serialize($value));
	}

	/** @param array<string,array<string,mixed>> $buckets @return array<string,array<string,mixed>> */
	private function normalizeIntervalBuckets(array $buckets, array $clientConfig, array $chartConfig): array {
		$dimension = isset($clientConfig['dimension']) && is_array($clientConfig['dimension']) ? $clientConfig['dimension'] : [];
		$interval = strtolower((string) ($dimension['bucketInterval'] ?? $dimension['interval'] ?? $chartConfig['bucketInterval'] ?? $chartConfig['interval'] ?? ''));

		if(!in_array($interval, ['month', 'day'], true) || count($buckets) === 0) {
			return $buckets;
		}

		$dates = [];
		foreach($buckets as $bucket) {
			$date = $this->parseBucketDate($bucket['value'] ?? null);
			if($date !== null) {
				$dates[] = $date;
			}
		}

		if($dates === []) {
			return $buckets;
		}

		usort($dates, static fn(\DateTimeImmutable $left, \DateTimeImmutable $right): int => $left->getTimestamp() <=> $right->getTimestamp());
		$start = $interval === 'month' ? $dates[0]->modify('first day of this month') : $dates[0];
		$end = $interval === 'month' ? $dates[count($dates) - 1]->modify('first day of this month') : $dates[count($dates) - 1];
		$valueFormat = $this->getBucketValueFormat($dimension, $chartConfig, $interval);
		$step = $interval === 'month' ? '+1 month' : '+1 day';
		$result = [];

		for($date = $start; $date <= $end; $date = $date->modify($step)) {
			$value = $this->formatBucketDate($date, $interval, $valueFormat);
			$key = $this->buildBucketKey($value);

			$result[$key] = $buckets[$key] ?? [
				'value' => $value,
				'row' => [
					'__dimension__' => $value
				]
			];
		}

		foreach($buckets as $key => $bucket) {
			if(!isset($result[$key])) {
				$result[$key] = $bucket;
			}
		}

		return $result;
	}

	private function parseBucketDate(mixed $value): ?\DateTimeImmutable {
		if(!is_scalar($value)) {
			return null;
		}

		$text = trim((string) $value);
		if(preg_match('/^(\d{4})-(\d{2})$/', $text, $matches) === 1) {
			return \DateTimeImmutable::createFromFormat('!Y-m-d', $matches[1] . '-' . $matches[2] . '-01') ?: null;
		}

		if(preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text) === 1) {
			return \DateTimeImmutable::createFromFormat('!Y-m-d', $text) ?: null;
		}

		return null;
	}

	private function getBucketValueFormat(array $dimension, array $chartConfig, string $interval): string {
		$formatter = isset($dimension['formatter']) && is_array($dimension['formatter']) ? $dimension['formatter'] : [];
		$format = (string) ($dimension['valueFormat'] ?? $formatter['valueFormat'] ?? $chartConfig['valueFormat'] ?? '');

		if($format !== '') {
			return $format;
		}

		return $interval === 'month' ? 'YYYY-MM' : 'YYYY-MM-DD';
	}

	private function formatBucketDate(\DateTimeImmutable $date, string $interval, string $valueFormat): string {
		if(str_contains($valueFormat, 'YYYY-MM-DD')) {
			return $date->format('Y-m-d');
		}

		if(str_contains($valueFormat, 'YYYY-MM')) {
			return $date->format('Y-m');
		}

		return $interval === 'month' ? $date->format('Y-m') : $date->format('Y-m-d');
	}

	/** @return array<string,mixed> */
	private function getChartConfig(array $config): array {
		$chart = isset($config['chart']) && is_array($config['chart']) ? $config['chart'] : [];
		$presentation = isset($config['presentation']) && is_array($config['presentation']) ? $config['presentation'] : [];

		return array_merge($presentation, $chart);
	}

	/** @param array<int,array<string,mixed>> $fields @return array<string,mixed> */
	private function normalizeDimension(array $chartConfig, array $fields): array {
		$dimension = $chartConfig['dimension'] ?? $chartConfig['x'] ?? $chartConfig['category'] ?? null;

		if(is_array($dimension)) {
			$field = isset($dimension['field']) && is_scalar($dimension['field']) ? (string) $dimension['field'] : '';
			$label = isset($dimension['label']) && is_scalar($dimension['label']) ? (string) $dimension['label'] : $this->getFieldLabel($fields, $field);

			return array_merge($dimension, [
				'field' => $field,
				'label' => $label
			]);
		}

		$field = is_scalar($dimension) && trim((string) $dimension) !== ''
			? (string) $dimension
			: (string) ($fields[0]['alias'] ?? '');

		return [
			'field' => $field,
			'label' => $this->getFieldLabel($fields, $field)
		];
	}

	private function isPreparedDataMode(array $chartConfig): bool {
		$dataMode = strtolower((string) ($chartConfig['dataMode'] ?? $chartConfig['mode'] ?? 'grouped'));

		return in_array($dataMode, ['prepared', 'rows', 'direct'], true);
	}

	/** @param array<int,array<string,mixed>> $fields @return array<int,array<string,mixed>> */
	private function normalizeMeasures(array $chartConfig, array $fields): array {
		$rawMeasures = [];

		if(isset($chartConfig['measures']) && is_array($chartConfig['measures'])) {
			$rawMeasures = $chartConfig['measures'];
		}
		elseif(isset($chartConfig['measure'])) {
			$rawMeasures = [$chartConfig['measure']];
		}

		if($rawMeasures === []) {
			$rawMeasures = [[
				'aggregation' => 'count',
				'alias' => 'row_count',
				'label' => 'Anzahl'
			]];
		}

		$result = [];

		foreach($rawMeasures as $index => $measure) {
			if(is_string($measure)) {
				$measure = ['field' => $measure, 'aggregation' => 'sum'];
			}

			if(!is_array($measure)) {
				continue;
			}

			$aggregation = strtoupper((string) ($measure['aggregation'] ?? $measure['type'] ?? 'count'));
			$aggregation = in_array($aggregation, ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX'], true) ? $aggregation : 'COUNT';
			$field = isset($measure['field']) && is_scalar($measure['field']) ? (string) $measure['field'] : '';
			$alias = isset($measure['alias']) && is_scalar($measure['alias']) && trim((string) $measure['alias']) !== ''
				? (string) $measure['alias']
				: strtolower($aggregation) . ($field !== '' ? '_' . $field : '_' . (string) ($index + 1));
			$label = isset($measure['label']) && is_scalar($measure['label'])
				? (string) $measure['label']
				: ($field !== '' ? $this->getFieldLabel($fields, $field) : ucfirst(strtolower($aggregation)));

			$result[] = array_merge($measure, [
				'aggregation' => strtolower($aggregation),
				'function' => $aggregation,
				'field' => $field,
				'alias' => $alias,
				'label' => $label,
				'formatter' => isset($measure['formatter']) && is_array($measure['formatter']) ? $measure['formatter'] : ['type' => 'number']
			]);
		}

		return $result;
	}

	/** @param array<string,mixed> $dimension @param array<string,mixed> $fieldDefs @return array<string,mixed> */
	private function buildDimensionElement(array $dimension, array $fieldDefs, array $config): array {
		$element = $dimension['element'] ?? null;

		if(is_array($element)) {
			return $element;
		}

		$field = (string) ($dimension['field'] ?? '');
		if($field !== '') {
			$element = $this->resolveFieldElement($field, $fieldDefs, $config);
			if($element !== null) {
				return $element;
			}
		}

		throw new \RuntimeException('Invalid chart dimension: ' . $field);
	}

	/** @param array<string,mixed> $measure @param array<string,mixed> $fieldDefs @return array<string,mixed> */
	private function buildMeasureElement(array $measure, array $fieldDefs, array $config): array {
		$function = (string) ($measure['function'] ?? 'COUNT');
		$field = (string) ($measure['field'] ?? '');

		if($function === 'COUNT' && $field === '') {
			$params = ['*'];
		}
		else {
			if($field === '') {
				throw new \RuntimeException('Invalid chart measure field: ' . $field);
			}

			$element = $this->resolveFieldElement($field, $fieldDefs, $config);
			if($element === null) {
				throw new \RuntimeException('Invalid chart measure field: ' . $field);
			}

			$params = [$element];
		}

		return [
			'type' => 'fn',
			'function' => $function,
			'params' => $params
		];
	}

	/** @param array<string,mixed> $measure @param array<string,mixed> $fieldDefs @return array<string,mixed> */
	private function buildPreparedMeasureElement(array $measure, array $fieldDefs, array $config): array {
		$element = $measure['element'] ?? null;
		if(is_array($element)) {
			return $element;
		}

		$field = (string) ($measure['field'] ?? '');
		if($field !== '') {
			$element = $this->resolveFieldElement($field, $fieldDefs, $config);
			if($element !== null) {
				return $element;
			}
		}

		throw new \RuntimeException('Prepared chart measure requires a valid field or element.');
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

	/** @param array<int,array<string,mixed>> $fields @return array<string,array<string,mixed>> */
	private function buildFieldColumnsByAlias(array $fields): array {
		$result = [];

		foreach($this->reportCellRendererService->buildGridColumns($fields) as $column) {
			if(isset($column['key'])) {
				$result[(string) $column['key']] = $column;
			}
		}

		return $result;
	}

	/** @return array<int,array<string,mixed>> */
	private function getFields(array $config): array {
		$fields = $config['fields'] ?? [];
		return is_array($fields) ? $fields : [];
	}

	/** @param array<int,array<string,mixed>> $fields */
	private function getFieldLabel(array $fields, string $alias): string {
		foreach($fields as $field) {
			if((string) ($field['alias'] ?? '') !== $alias) {
				continue;
			}

			$config = isset($field['config']) && is_array($field['config']) ? $field['config'] : [];
			return (string) ($config['label'] ?? $alias);
		}

		return $alias;
	}

	/** @param array<int,array<string,mixed>> $fields @param array<string,mixed> $fieldDefs @param array<string,mixed> $filters */
	private function buildWhere(array $config, array $fields, array $fieldDefs, array $filters, array $chartConfig): ?array {
		$whereParams = [];

		if(isset($config['where']) && is_array($config['where'])) {
			$whereParams[] = $config['where'];
		}

		if(isset($chartConfig['where']) && is_array($chartConfig['where'])) {
			$whereParams[] = $chartConfig['where'];
		}

		$filterWhere = $this->reportFilterService->buildFilterWhere($filters, $fields, $fieldDefs);

		if($filterWhere !== null) {
			$whereParams[] = $filterWhere;
		}

		if(count($whereParams) === 0) {
			return null;
		}

		return count($whereParams) === 1
			? $whereParams[0]
			: [
				'type' => 'op',
				'operator' => 'AND',
				'params' => $whereParams
			];
	}

	/** @param array<string,mixed> $chartConfig @param array<string,mixed> $dimension @param array<int,array<string,mixed>> $measures @param array<string,mixed> $fieldDefs @return array<int,array<string,mixed>> */
	private function buildOrderBy(array $chartConfig, array $dimension, array $measures, array $fieldDefs, array $config, bool $preparedMode = false): array {
		if(isset($chartConfig['order_by']) && is_array($chartConfig['order_by'])) {
			return $chartConfig['order_by'];
		}

		if(isset($chartConfig['sort']) && is_array($chartConfig['sort'])) {
			$by = strtolower((string) ($chartConfig['sort']['by'] ?? 'label'));
			$direction = strtoupper((string) ($chartConfig['sort']['direction'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

			if($by === 'label' || $by === 'dimension') {
				return $this->buildDimensionOrderBy($dimension, $fieldDefs, $config, $direction);
			}

			foreach($measures as $measure) {
				if(strtolower((string) ($measure['alias'] ?? '')) === $by) {
					return [[
						'element' => $preparedMode ? $this->buildPreparedMeasureElement($measure, $fieldDefs, $config) : $this->buildMeasureElement($measure, $fieldDefs, $config),
						'direction' => $direction
					]];
				}
			}

			$fieldOrderBy = $this->buildFieldOrderBy($by, $fieldDefs, $config, $direction);
			if($fieldOrderBy !== []) {
				return $fieldOrderBy;
			}
		}

		$order = isset($chartConfig['order']) && is_scalar($chartConfig['order']) ? strtolower((string) $chartConfig['order']) : 'label-asc';
		$direction = str_ends_with($order, 'desc') ? 'DESC' : 'ASC';
		$sortByValue = str_starts_with($order, 'value') || str_starts_with($order, 'measure');

		if($sortByValue && isset($measures[0])) {
			return [[
				'element' => $preparedMode ? $this->buildPreparedMeasureElement($measures[0], $fieldDefs, $config) : $this->buildMeasureElement($measures[0], $fieldDefs, $config),
				'direction' => $direction
			]];
		}

		return $this->buildDimensionOrderBy($dimension, $fieldDefs, $config, $direction);
	}

	/** @param array<string,mixed> $fieldDefs @return array<int,array<string,mixed>> */
	private function buildFieldOrderBy(string $fieldAlias, array $fieldDefs, array $config, string $direction): array {
		$element = $this->resolveFieldElement($fieldAlias, $fieldDefs, $config);
		if($element === null) {
			return [];
		}

		return [[
			'element' => $element,
			'direction' => $direction
		]];
	}

	/** @param array<string,mixed> $dimension @param array<string,mixed> $fieldDefs @return array<int,array<string,mixed>> */
	private function buildDimensionOrderBy(array $dimension, array $fieldDefs, array $config, string $direction): array {
		try {
			return [[
				'element' => $this->buildDimensionElement($dimension, $fieldDefs, $config),
				'direction' => $direction
			]];
		} catch(\RuntimeException) {
			return [];
		}
	}

	/** @param array<string,mixed> $fieldDefs @return array<string,mixed>|null */
	private function resolveFieldElement(string $fieldAlias, array $fieldDefs, array $config): ?array {
		$fieldAlias = trim($fieldAlias);
		if($fieldAlias === '') {
			return null;
		}

		foreach($fieldDefs as $alias => $element) {
			if(strtolower((string) $alias) === strtolower($fieldAlias) && is_array($element)) {
				return $element;
			}
		}

		$table = trim((string) ($config['table'] ?? ''));
		if($table === '') {
			return null;
		}

		return [
			'type' => 'fld',
			'table' => $table,
			'field' => $fieldAlias
		];
	}
}
