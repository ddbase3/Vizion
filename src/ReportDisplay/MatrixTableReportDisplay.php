<?php declare(strict_types=1);

namespace Vizion\ReportDisplay;

use Base3\Api\IDisplay;
use ResourceFoundation\Api\IQueryService;
use Throwable;

final class MatrixTableReportDisplay implements IDisplay {

	/** @var array<string,mixed> */
	private array $config = [];

	/** @var array<string,mixed> */
	private array $detail = [];

	/** @var array<string,mixed> */
	private array $payload = [];

	public function __construct(
		private readonly IQueryService $queryService
	) {}

	public static function getName(): string {
		return 'matrixtablereportdisplay';
	}

	public function setData($data): void {
		if(!is_array($data)) {
			return;
		}

		$this->config = isset($data['config']) && is_array($data['config']) ? $data['config'] : [];
		$this->detail = isset($data['detail']) && is_array($data['detail']) ? $data['detail'] : [];
		$this->payload = isset($data['payload']) && is_array($data['payload']) ? $data['payload'] : [];
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		return (string) json_encode($this->buildResponse(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	/** @return array<string,mixed> */
	private function buildResponse(): array {
		try {
			$mainId = $this->getMainId();

			if($mainId <= 0) {
				return ['ok' => false, 'found' => false, 'error' => 'Missing matrix detail parameter.'];
			}

			$headline = $this->loadHeadline($mainId);

			if($headline === null) {
				return ['ok' => true, 'found' => false, 'error' => 'Matrix headline row not found.'];
			}

			$columns = $this->loadColumns($mainId);
			$rows = $this->loadRows($mainId);
			$cells = $this->loadCells($mainId);
			$cellMap = $this->buildCellMap($cells);
			$detailRows = [];

			foreach($rows as $row) {
				$rowKey = (int) ($row[$this->getRowsRowKey()] ?? 0);
				$detailRow = $this->buildFixedRow($row);

				foreach($columns as $column) {
					$columnKey = (int) ($column[$this->getColumnsColumnKey()] ?? 0);
					$cell = $cellMap[$rowKey][$columnKey] ?? null;
					$detailRow[$this->buildDynamicColumnKey($columnKey)] = $this->buildCellValue(is_array($cell) ? $cell : null);
				}

				$detailRows[] = $detailRow;
			}

			return [
				'ok' => true,
				'found' => true,
				'detail' => [
					'kind' => 'vizion-matrix-table-subreport',
					'headline' => $this->buildHeadlineTitle($headline),
					'summary' => $this->buildSummary($headline, count($rows), count($columns)),
					'columns' => $this->buildDetailColumns($columns),
					'rows' => $detailRows,
					'headlineRow' => $headline
				]
			];
		}
		catch(Throwable $exception) {
			return ['ok' => false, 'found' => false, 'error' => $exception->getMessage()];
		}
	}

	private function getMainId(): int {
		$parameter = isset($this->detail['parameter']) && is_scalar($this->detail['parameter']) ? (string) $this->detail['parameter'] : 'id';
		$aliases = [$parameter, 'mainCourseObjId', 'main_course_obj_id', 'id'];

		foreach($aliases as $alias) {
			if(isset($this->payload[$alias]) && is_scalar($this->payload[$alias])) {
				return (int) $this->payload[$alias];
			}
		}

		return 0;
	}

	/** @return array<string,mixed>|null */
	private function loadHeadline(int $mainId): ?array {
		$source = $this->getSource('headline');
		$key = (string) ($source['key'] ?? ($this->detail['parameter'] ?? 'id'));
		$result = $this->queryService->executeQuery([
			'type' => 'select',
			'schema' => (string) ($source['schema'] ?? ($this->config['schema'] ?? '')),
			'table' => (string) ($source['table'] ?? ($this->config['table'] ?? '')),
			'fields' => $this->buildSourceFields($source),
			'where' => ['type' => 'op', 'operator' => '=', 'params' => [$this->field((string) ($source['table'] ?? ($this->config['table'] ?? '')), $key), $mainId]],
			'limit' => 1
		]);

		$row = $result->rows[0] ?? null;
		return is_array($row) ? $row : null;
	}

	/** @return array<int,array<string,mixed>> */
	private function loadColumns(int $mainId): array {
		$source = $this->getSource('columns');
		return $this->loadSourceRows($source, $mainId);
	}

	/** @return array<int,array<string,mixed>> */
	private function loadRows(int $mainId): array {
		$source = $this->getSource('rows');
		return $this->loadSourceRows($source, $mainId);
	}

	/** @return array<int,array<string,mixed>> */
	private function loadCells(int $mainId): array {
		$source = $this->getSource('cells');
		return $this->loadSourceRows($source, $mainId);
	}

	/** @param array<string,mixed> $source @return array<int,array<string,mixed>> */
	private function loadSourceRows(array $source, int $mainId): array {
		$table = (string) ($source['table'] ?? '');
		$key = (string) ($source['key'] ?? ($this->detail['parameter'] ?? 'id'));
		$query = [
			'type' => 'select',
			'schema' => (string) ($source['schema'] ?? ($this->config['schema'] ?? '')),
			'table' => $table,
			'fields' => $this->buildSourceFields($source),
			'where' => ['type' => 'op', 'operator' => '=', 'params' => [$this->field($table, $key), $mainId]]
		];

		if(isset($source['order_by']) && is_array($source['order_by'])) {
			$query['order_by'] = $source['order_by'];
		}

		$result = $this->queryService->executeQuery($query);
		$rows = [];

		foreach($result->rows ?? [] as $row) {
			if(is_array($row)) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	/** @param array<string,mixed> $source @return array<int,array<string,mixed>> */
	private function buildSourceFields(array $source): array {
		$fields = isset($source['fields']) && is_array($source['fields']) ? $source['fields'] : [];
		$table = (string) ($source['table'] ?? '');
		$result = [];

		foreach($fields as $field) {
			if(is_string($field)) {
				$result[] = ['element' => $this->field($table, $field), 'alias' => $field];
				continue;
			}

			if(is_array($field) && isset($field['alias'], $field['element'])) {
				$result[] = ['element' => $field['element'], 'alias' => (string) $field['alias']];
			}
		}

		return $result;
	}

	/** @param array<int,array<string,mixed>> $cells @return array<int,array<int,array<string,mixed>>> */
	private function buildCellMap(array $cells): array {
		$map = [];
		$rowKey = $this->getCellsRowKey();
		$columnKey = $this->getCellsColumnKey();

		foreach($cells as $cell) {
			$rk = (int) ($cell[$rowKey] ?? 0);
			$ck = (int) ($cell[$columnKey] ?? 0);
			if($rk <= 0 || $ck <= 0) {
				continue;
			}
			$map[$rk][$ck] = $cell;
		}

		return $map;
	}

	/** @param array<string,mixed> $row @return array<string,mixed> */
	private function buildFixedRow(array $row): array {
		$result = [];
		$table = $this->getTableConfig();
		$fixedColumns = isset($table['fixedColumns']) && is_array($table['fixedColumns']) ? $table['fixedColumns'] : [];

		foreach($fixedColumns as $column) {
			if(!is_array($column)) {
				continue;
			}
			$key = (string) ($column['key'] ?? '');
			$field = (string) ($column['field'] ?? $key);
			if($key !== '') {
				$result[$key] = $row[$field] ?? '';
			}
		}

		return $result;
	}

	/** @param array<int,array<string,mixed>> $dynamicColumns @return array<int,array<string,mixed>> */
	private function buildDetailColumns(array $dynamicColumns): array {
		$table = $this->getTableConfig();
		$fixedColumns = isset($table['fixedColumns']) && is_array($table['fixedColumns']) ? $table['fixedColumns'] : [];
		$columns = [];

		foreach($fixedColumns as $column) {
			if(is_array($column)) {
				$entry = [
					'key' => (string) ($column['key'] ?? ''),
					'label' => (string) ($column['label'] ?? ($column['key'] ?? '')),
					'type' => (string) ($column['type'] ?? 'text')
				];

				if(isset($column['statusKey']) && is_scalar($column['statusKey'])) {
					$entry['statusKey'] = (string) $column['statusKey'];
				}

				$columns[] = $entry;
			}
		}

		$columnKey = $this->getColumnsColumnKey();
		$labelField = $this->getColumnsLabelField();
		$type = (string) ($table['dynamicColumnType'] ?? 'status');

		foreach($dynamicColumns as $column) {
			$key = (int) ($column[$columnKey] ?? 0);
			if($key <= 0) {
				continue;
			}
			$columns[] = [
				'key' => $this->buildDynamicColumnKey($key),
				'label' => (string) ($column[$labelField] ?? $key),
				'type' => $type
			];
		}

		return $columns;
	}

	/** @param array<string,mixed>|null $cell @return array<string,mixed> */
	private function buildCellValue(?array $cell): array {
		$table = $this->getTableConfig();
		$cellConfig = isset($table['cell']) && is_array($table['cell']) ? $table['cell'] : [];
		$statusField = (string) ($cellConfig['statusField'] ?? 'status');
		$labelField = (string) ($cellConfig['labelField'] ?? $statusField);
		$percentageField = (string) ($cellConfig['percentageField'] ?? '');
		$markField = (string) ($cellConfig['markField'] ?? '');
		$defaultStatus = (string) ($cellConfig['defaultStatus'] ?? '');
		$status = $cell !== null ? (string) ($cell[$statusField] ?? $defaultStatus) : $defaultStatus;
		$label = $cell !== null ? (string) ($cell[$labelField] ?? $status) : $status;

		return [
			'label' => $this->translateStatus($label),
			'status' => $status,
			'percentage' => $cell !== null && $percentageField !== '' ? $this->formatPercentage($cell[$percentageField] ?? null) : '',
			'mark' => $cell !== null && $markField !== '' ? (string) ($cell[$markField] ?? '') : ''
		];
	}

	/** @param array<string,mixed> $headline */
	private function buildHeadlineTitle(array $headline): string {
		$source = $this->getSource('headline');
		$titleField = (string) ($source['titleField'] ?? 'title');
		return (string) ($headline[$titleField] ?? 'Matrix');
	}

	/** @param array<string,mixed> $headline */
	private function buildSummary(array $headline, int $rowCount, int $columnCount): string {
		$template = (string) ($this->detail['summary'] ?? '{rows} rows, {columns} columns');
		$values = [
			'{rows}' => (string) $rowCount,
			'{columns}' => (string) $columnCount
		];
		foreach($headline as $key => $value) {
			if(is_scalar($value) || $value === null) {
				$values['{' . (string) $key . '}'] = (string) $value;
			}
		}
		return strtr($template, $values);
	}

	private function buildDynamicColumnKey(int $columnKey): string {
		$table = $this->getTableConfig();
		return (string) ($table['dynamicColumnPrefix'] ?? 'col_') . (string) $columnKey;
	}

	/** @return array<string,mixed> */
	private function getSource(string $name): array {
		$value = $this->detail[$name] ?? [];
		return is_array($value) ? $value : [];
	}

	/** @return array<string,mixed> */
	private function getTableConfig(): array {
		$value = $this->detail['table'] ?? [];
		return is_array($value) ? $value : [];
	}

	private function getRowsRowKey(): string {
		$source = $this->getSource('rows');
		return (string) ($source['rowKey'] ?? 'id');
	}

	private function getColumnsColumnKey(): string {
		$source = $this->getSource('columns');
		return (string) ($source['columnKey'] ?? 'id');
	}

	private function getColumnsLabelField(): string {
		$source = $this->getSource('columns');
		return (string) ($source['labelField'] ?? $this->getColumnsColumnKey());
	}

	private function getCellsRowKey(): string {
		$source = $this->getSource('cells');
		return (string) ($source['rowKey'] ?? $this->getRowsRowKey());
	}

	private function getCellsColumnKey(): string {
		$source = $this->getSource('cells');
		return (string) ($source['columnKey'] ?? $this->getColumnsColumnKey());
	}

	/** @return array<string,mixed> */
	private function field(string $table, string $field): array {
		return ['type' => 'fld', 'table' => $table, 'field' => $field];
	}

	private function translateStatus(string $status): string {
		$labels = isset($this->detail['statusLabels']) && is_array($this->detail['statusLabels']) ? $this->detail['statusLabels'] : [];
		return (string) ($labels[$status] ?? ($labels[strtoupper($status)] ?? $status));
	}

	private function formatPercentage(mixed $value): string {
		if($value === null || $value === '') {
			return '';
		}
		if(!is_numeric($value)) {
			return (string) $value;
		}
		return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') . ' %';
	}

	public function getHelp(): string {
		return 'Renders a matrix detail subreport as a JSON payload for a parent matrix report.';
	}
}
