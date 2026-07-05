<?php declare(strict_types=1);

namespace Vizion\Renderer;

use Base3\Api\IClassMap;
use Vizion\Api\IReportCellRendererService;
use Vizion\Api\IReportValueRenderer;

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

	public function renderGridRows(array $rows, array $fields): array {
		$renderableFields = $this->getRenderableFields($fields);

		if($renderableFields === []) {
			return $rows;
		}

		foreach($rows as $rowIndex => $row) {
			if(!is_array($row)) {
				continue;
			}

			foreach($renderableFields as $alias => $definition) {
				$row[$alias] = $definition['renderer']->renderValue(
					$row[$alias] ?? null,
					$row,
					$definition['field'],
					$definition['config']
				);
			}

			$rows[$rowIndex] = $row;
		}

		return $rows;
	}

	public function collectGridRendererAssetPaths(array $columns): array {
		return [];
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
		$alias = (string)$field['alias'];
		$type = (string)($config['type'] ?? 'string');
		$formatterConfig = $this->normalizeConfig($config['formatter'] ?? null, $this->getDefaultFormatterType($type));
		$columnRendererConfig = $this->normalizeConfig($config['columnRenderer'] ?? null, 'valuecolumnrenderer');
		$formatter = $this->formatterRegistry->getFormatter((string)($formatterConfig['type'] ?? 'textvalueformatter'));
		$columnRenderer = $this->columnRendererRegistry->getRenderer((string)($columnRendererConfig['type'] ?? 'valuecolumnrenderer'));

		$column = [
			'key' => $alias,
			'label' => (string)($config['label'] ?? $alias),
			'visible' => $config['visible'] ?? true,
			'type' => $type,
			'formatter' => $formatter->buildClientConfig($field, $formatterConfig),
			'columnRendererType' => (string)($columnRendererConfig['type'] ?? $columnRenderer::getName()),
			'columnRendererConfig' => $this->stripType($columnRendererConfig),
			'_rendererAssetPaths' => []
		];

		if(isset($config['valueRenderer']) && is_array($config['valueRenderer'])) {
			$valueRendererConfig = $this->normalizeConfig($config['valueRenderer'], 'textvaluerenderer');
			$valueRenderer = $this->valueRendererRegistry->getRenderer((string)$valueRendererConfig['type']);

			if($valueRenderer->rendersHtml()) {
				$column['html'] = true;
			}
		}

		if(isset($config['width']) && is_numeric($config['width'])) {
			$column['width'] = (int)$config['width'];
		}

		if(isset($config['lines']) && is_numeric($config['lines'])) {
			$column['lines'] = max(1, (int)$config['lines']);
		}

		if(in_array(strtolower($type), ['json', 'code'], true)) {
			$column['monospace'] = true;
		}

		$column = $columnRenderer->configureColumn($column, $field, $columnRendererConfig);

		return $column;
	}

	/**
	 * @param array<int,array<string,mixed>> $fields
	 * @return array<string,array{field:array<string,mixed>,config:array<string,mixed>,renderer:IReportValueRenderer}>
	 */
	private function getRenderableFields(array $fields): array {
		$result = [];

		foreach($fields as $field) {
			if(!isset($field['alias']) || !is_scalar($field['alias'])) {
				continue;
			}

			$config = isset($field['config']) && is_array($field['config']) ? $field['config'] : [];

			if(!isset($config['valueRenderer']) || !is_array($config['valueRenderer'])) {
				continue;
			}

			$rendererConfig = $this->normalizeConfig($config['valueRenderer'], 'textvaluerenderer');
			$renderer = $this->valueRendererRegistry->getRenderer((string)$rendererConfig['type']);

			$result[(string)$field['alias']] = [
				'field' => $field,
				'config' => $rendererConfig,
				'renderer' => $renderer
			];
		}

		return $result;
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

		if(!isset($result['type']) || !is_scalar($result['type']) || trim((string)$result['type']) === '') {
			$result['type'] = $defaultType;
		}

		$result['type'] = strtolower(trim((string)$result['type']));

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
}
