<?php declare(strict_types=1);

namespace Vizion\Renderer;

use Base3\Api\IClassMap;
use Vizion\Api\IReportCellRendererService;

final class ReportCellRendererService implements IReportCellRendererService {

	private ReportValueFormatterRegistry $formatterRegistry;
	private ReportValueRendererRegistry $valueRendererRegistry;
	private ReportColumnRendererRegistry $columnRendererRegistry;

	public function __construct(IClassMap $classMap) {
		$this->formatterRegistry = new ReportValueFormatterRegistry($classMap);
		$this->valueRendererRegistry = new ReportValueRendererRegistry($classMap);
		$this->columnRendererRegistry = new ReportColumnRendererRegistry($classMap);
	}

	public function buildGridColumns(array $fields): array {
		$columns = [];

		foreach($fields as $field) {
			if(!isset($field['alias'])) {
				continue;
			}

			$columns[] = $this->buildGridColumn($field);
		}

		return $columns;
	}

	public function collectGridRendererAssetPaths(array $columns): array {
		$result = [];

		foreach($columns as $column) {
			$paths = $column['_rendererAssetPaths'] ?? [];

			if(!is_array($paths)) {
				continue;
			}

			foreach($paths as $path) {
				if(!is_scalar($path)) {
					continue;
				}

				$path = trim((string) $path);

				if($path !== '') {
					$result[$path] = $path;
				}
			}
		}

		return array_values($result);
	}

	public function stripInternalGridColumnMetadata(array $columns): array {
		foreach($columns as $index => $column) {
			if(!is_array($column)) {
				continue;
			}

			unset($column['_rendererAssetPaths']);
			$columns[$index] = $column;
		}

		return $columns;
	}

	/** @param array<string,mixed> $field @return array<string,mixed> */
	private function buildGridColumn(array $field): array {
		$config = isset($field['config']) && is_array($field['config']) ? $field['config'] : [];
		$alias = (string) $field['alias'];
		$type = (string) ($config['type'] ?? 'string');
		$formatterConfig = $this->normalizeConfig($config['formatter'] ?? null, $this->getDefaultFormatterType($type));
		$valueRendererConfig = $this->normalizeConfig($config['valueRenderer'] ?? null, $this->getDefaultValueRendererType($type));
		$columnRendererConfig = $this->normalizeConfig($config['columnRenderer'] ?? null, 'value');
		$formatter = $this->formatterRegistry->getFormatter((string) ($formatterConfig['type'] ?? 'text'));
		$valueRenderer = $this->valueRendererRegistry->getRenderer((string) ($valueRendererConfig['type'] ?? 'text'));
		$columnRenderer = $this->columnRendererRegistry->getRenderer((string) ($columnRendererConfig['type'] ?? 'value'));
		$assetPaths = array_merge(
			$valueRenderer->getAssetPaths($valueRendererConfig),
			$columnRenderer->getAssetPaths($columnRendererConfig)
		);

		$column = [
			'key' => $alias,
			'label' => (string) ($config['label'] ?? $alias),
			'visible' => $config['visible'] ?? true,
			'type' => $type,
			'formatter' => $formatter->buildClientConfig($field, $formatterConfig),
			'valueRendererKey' => $valueRenderer->getClientRendererKey($valueRendererConfig),
			'valueRendererType' => (string) ($valueRendererConfig['type'] ?? $valueRenderer->getRendererType()),
			'valueRendererConfig' => $this->stripType($valueRendererConfig),
			'columnRendererKey' => $columnRenderer->getClientRendererKey($columnRendererConfig),
			'columnRendererType' => (string) ($columnRendererConfig['type'] ?? $columnRenderer->getRendererType()),
			'columnRendererConfig' => $this->stripType($columnRendererConfig),
			'_rendererAssetPaths' => array_values(array_unique(array_filter($assetPaths, static fn($path): bool => is_string($path) && trim($path) !== '')))
		];

		if(isset($config['width']) && is_numeric($config['width'])) {
			$column['width'] = (int) $config['width'];
		}

		if(isset($config['lines']) && is_numeric($config['lines'])) {
			$column['lines'] = max(1, (int) $config['lines']);
		}

		if(in_array(strtolower($type), ['json', 'code'], true)) {
			$column['monospace'] = true;
		}

		$column = $valueRenderer->configureValue($column, $field, $valueRendererConfig);
		$column = $columnRenderer->configureColumn($column, $field, $columnRendererConfig);

		return $column;
	}

	/** @return array<string,mixed> */
	private function normalizeConfig(mixed $config, string $defaultType): array {
		if(is_array($config)) {
			$result = $config;
		} elseif(is_string($config) && trim($config) !== '') {
			$result = ['type' => trim($config)];
		} else {
			$result = ['type' => $defaultType];
		}

		if(!isset($result['type']) || !is_scalar($result['type']) || trim((string) $result['type']) === '') {
			$result['type'] = $defaultType;
		}

		$result['type'] = strtolower(trim((string) $result['type']));

		return $result;
	}

	/** @param array<string,mixed> $config @return array<string,mixed> */
	private function stripType(array $config): array {
		unset($config['type']);

		return $config;
	}

	private function getDefaultFormatterType(string $fieldType): string {
		return match(strtolower($fieldType)) {
			'int', 'integer', 'float', 'decimal', 'number' => 'number',
			'date' => 'date',
			'datetime' => 'datetime',
			default => 'text'
		};
	}

	private function getDefaultValueRendererType(string $fieldType): string {
		return match(strtolower($fieldType)) {
			default => 'text'
		};
	}
}
