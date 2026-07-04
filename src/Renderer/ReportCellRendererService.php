<?php declare(strict_types=1);

namespace Vizion\Renderer;

use Base3\Api\IClassMap;
use Vizion\Api\IReportCellRendererService;

final class ReportCellRendererService implements IReportCellRendererService {

	private ReportValueFormatterRegistry $formatterRegistry;
	private ReportCellRendererRegistry $rendererRegistry;

	public function __construct(IClassMap $classMap) {
		$this->formatterRegistry = new ReportValueFormatterRegistry($classMap);
		$this->rendererRegistry = new ReportCellRendererRegistry($classMap);
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

	/** @param array<string,mixed> $field @return array<string,mixed> */
	private function buildGridColumn(array $field): array {
		$config = isset($field['config']) && is_array($field['config']) ? $field['config'] : [];
		$alias = (string) $field['alias'];
		$type = (string) ($config['type'] ?? 'string');
		$rendererConfig = $this->normalizeConfig($config['renderer'] ?? null, $this->getDefaultRendererType($type));
		$formatterConfig = $this->normalizeConfig($config['formatter'] ?? null, $this->getDefaultFormatterType($type, $rendererConfig));
		$formatter = $this->formatterRegistry->getFormatter((string) ($formatterConfig['type'] ?? 'text'));
		$renderer = $this->rendererRegistry->getRenderer((string) ($rendererConfig['type'] ?? 'text'));

		$column = [
			'key' => $alias,
			'label' => (string) ($config['label'] ?? $alias),
			'visible' => $config['visible'] ?? true,
			'type' => $type,
			'rendererKey' => $renderer->getClientRendererKey($rendererConfig),
			'formatter' => $formatter->buildClientConfig($field, $formatterConfig),
		];

		if(isset($config['width']) && is_numeric($config['width'])) {
			$column['width'] = (int) $config['width'];
		}

		if(isset($config['lines']) && is_numeric($config['lines'])) {
			$column['lines'] = max(1, (int) $config['lines']);
		}

		return $renderer->configureColumn($column, $field, $rendererConfig);
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

		return $result;
	}

	private function getDefaultFormatterType(string $fieldType, array $rendererConfig): string {
		if(isset($rendererConfig['formatter']) && is_string($rendererConfig['formatter'])) {
			return $rendererConfig['formatter'];
		}

		return match(strtolower($fieldType)) {
			'int', 'integer', 'float', 'decimal', 'number' => 'number',
			'date' => 'date',
			'datetime' => 'datetime',
			default => 'text'
		};
	}

	private function getDefaultRendererType(string $fieldType): string {
		return match(strtolower($fieldType)) {
			'int', 'integer', 'float', 'decimal', 'number' => 'number',
			'date', 'datetime' => 'date',
			'json' => 'json',
			'code' => 'json',
			default => 'text'
		};
	}
}
