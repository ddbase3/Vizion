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
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Dto\QueryResult;
use Throwable;

/**
 * Displays a compact row of metric cards backed by Resource/DataHawk queries.
 *
 * Metric reports are intended for dashboard-style headline numbers. Each card
 * owns one structured query. The display does not assemble SQL strings; it
 * either executes the explicit ResourceFoundation query from the vdef or builds
 * a small select query from the metric shorthand keys.
 */
final class MetricReportDisplay implements IDisplay {

	/** @var array<string,mixed>|null */
	private ?array $config = null;

	public function __construct(
		private readonly IMvcView $view,
		private readonly IQueryService $queryService,
		private readonly IAssetResolver $assetResolver
	) {}

	public static function getName(): string {
		return 'metricreportdisplay';
	}

	public function setData($data): void {
		$this->config = is_array($data) ? $data : [];
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$payload = $this->buildPayload();

		if(strtolower($out) === 'json') {
			if($final && !headers_sent()) {
				header('Content-Type: application/json; charset=utf-8');
			}

			return (string) json_encode(
				$payload,
				JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			);
		}

		$this->view->setPath(DIR_PLUGIN . 'Vizion');
		$this->view->setTemplate('ReportDisplay/MetricReportDisplay.php');
		$this->view->assign('config', $this->getConfig());
		$this->view->assign('metrics', $payload['metrics']);
		$this->view->assign('metricCssUrl', $this->assetResolver->resolve('plugin/Vizion/assets/css/vizion-metric-report.css'));

		return $this->view->loadTemplate();
	}

	/** @return array<string,mixed> */
	private function buildPayload(): array {
		$metrics = [];

		foreach($this->getMetricDefinitions() as $metric) {
			$metrics[] = $this->loadMetric($metric);
		}

		return [
			'ok' => true,
			'mode' => 'metrics',
			'metrics' => $metrics,
			'total' => count($metrics)
		];
	}

	/** @param array<string,mixed> $metric @return array<string,mixed> */
	private function loadMetric(array $metric): array {
		$key = trim((string) ($metric['key'] ?? $metric['alias'] ?? ''));
		$label = trim((string) ($metric['label'] ?? $key));

		if($key === '') {
			$key = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $label) ?: 'metric');
		}

		try {
			$query = $this->buildMetricQuery($metric);
			$result = $this->queryService->executeQuery($query);
			$value = $this->extractMetricValue($metric, $result);

			return [
				'ok' => true,
				'key' => $key,
				'label' => $label,
				'description' => (string) ($metric['description'] ?? ''),
				'value' => $value,
				'formattedValue' => $this->formatMetricValue($metric, $value),
				'debugSql' => $result->debugSql
			];
		}
		catch(Throwable $exception) {
			return [
				'ok' => false,
				'key' => $key,
				'label' => $label,
				'description' => (string) ($metric['description'] ?? ''),
				'value' => null,
				'formattedValue' => '—',
				'error' => $exception->getMessage()
			];
		}
	}

	/** @param array<string,mixed> $metric @return array<string,mixed> */
	private function buildMetricQuery(array $metric): array {
		$query = isset($metric['query']) && is_array($metric['query']) ? $metric['query'] : [];
		$config = $this->getConfig();
		$table = trim((string) ($query['table'] ?? $metric['table'] ?? $config['table'] ?? ''));
		$schema = trim((string) ($query['schema'] ?? $metric['schema'] ?? $config['schema'] ?? ''));

		if($table === '') {
			throw new \RuntimeException('Metric query requires a table.');
		}

		$query['type'] = (string) ($query['type'] ?? 'select');
		$query['schema'] = $schema;
		$query['table'] = $table;

		if(isset($metric['distinct']) && !array_key_exists('distinct', $query)) {
			$query['distinct'] = (bool) $metric['distinct'];
		}

		if(isset($metric['where']) && is_array($metric['where']) && !isset($query['where'])) {
			$query['where'] = $metric['where'];
		}

		if(!isset($query['fields']) || !is_array($query['fields']) || count($query['fields']) === 0) {
			$query['fields'] = [$this->buildDefaultMetricField($metric, $table)];
		}

		return $query;
	}

	/** @param array<string,mixed> $metric @return array<string,mixed> */
	private function buildDefaultMetricField(array $metric, string $table): array {
		$element = $metric['element'] ?? null;

		if(is_array($element)) {
			return [
				'element' => $element,
				'alias' => (string) ($metric['valueField'] ?? $metric['field'] ?? 'value')
			];
		}

		$field = trim((string) ($metric['field'] ?? ''));

		if($field !== '') {
			return [
				'element' => [
					'type' => 'fld',
					'table' => $table,
					'field' => $field
				],
				'alias' => $field
			];
		}

		return [
			'element' => [
				'type' => 'fn',
				'function' => 'COUNT',
				'params' => ['*']
			],
			'alias' => (string) ($metric['valueField'] ?? 'value')
		];
	}

	/** @param array<string,mixed> $metric */
	private function extractMetricValue(array $metric, QueryResult $result): int|float|string|null {
		$mode = strtolower((string) ($metric['valueMode'] ?? $metric['mode'] ?? 'first_value'));

		if(in_array($mode, ['row_count', 'rows', 'count_rows'], true)) {
			return count($result->rows ?? []);
		}

		if(in_array($mode, ['sum', 'sum_rows'], true)) {
			$field = $this->getValueField($metric, $result);
			$sum = 0.0;

			foreach($result->rows ?? [] as $row) {
				if(is_array($row) && isset($row[$field]) && is_numeric($row[$field])) {
					$sum += (float) $row[$field];
				}
			}

			return $sum;
		}

		$firstRow = $result->rows[0] ?? [];

		if(!is_array($firstRow) || count($firstRow) === 0) {
			return 0;
		}

		$field = $this->getValueField($metric, $result);

		if($field !== '' && array_key_exists($field, $firstRow)) {
			$value = $firstRow[$field];
			return is_scalar($value) || $value === null ? $value : null;
		}

		foreach($firstRow as $value) {
			if(is_scalar($value) || $value === null) {
				return $value;
			}
		}

		return null;
	}

	/** @param array<string,mixed> $metric */
	private function getValueField(array $metric, QueryResult $result): string {
		if(isset($metric['valueField']) && is_scalar($metric['valueField'])) {
			return (string) $metric['valueField'];
		}

		if(isset($metric['field']) && is_scalar($metric['field'])) {
			return (string) $metric['field'];
		}

		if(isset($metric['key']) && is_scalar($metric['key'])) {
			$key = (string) $metric['key'];
			$firstRow = $result->rows[0] ?? [];
			if(is_array($firstRow) && array_key_exists($key, $firstRow)) {
				return $key;
			}
		}

		return 'value';
	}

	/** @param array<string,mixed> $metric */
	private function formatMetricValue(array $metric, int|float|string|null $value): string {
		$format = $metric['format'] ?? [];
		$format = is_array($format) ? $format : ['type' => (string) $format];
		$type = strtolower((string) ($format['type'] ?? 'number'));
		$prefix = (string) ($format['prefix'] ?? '');
		$suffix = (string) ($format['suffix'] ?? '');

		if($value === null) {
			return '—';
		}

		if($type === 'raw') {
			return $prefix . (string) $value . $suffix;
		}

		if(is_numeric($value)) {
			$decimals = max(0, min(6, (int) ($format['decimals'] ?? 0)));
			return $prefix . number_format((float) $value, $decimals, ',', '.') . $suffix;
		}

		return $prefix . (string) $value . $suffix;
	}

	/** @return array<int,array<string,mixed>> */
	private function getMetricDefinitions(): array {
		$config = $this->getConfig();
		$metrics = $config['metrics'] ?? [];

		if(isset($config['config']) && is_array($config['config']) && isset($config['config']['metrics'])) {
			$metrics = $config['config']['metrics'];
		}

		if(!is_array($metrics)) {
			return [];
		}

		return array_values(array_filter($metrics, static fn($metric): bool => is_array($metric)));
	}

	/** @return array<string,mixed> */
	private function getConfig(): array {
		return $this->config ?? [];
	}

	public function getHelp(): string {
		return 'Displays a compact row of metric cards backed by structured Resource/DataHawk queries.';
	}
}
