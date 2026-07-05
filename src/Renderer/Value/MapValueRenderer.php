<?php declare(strict_types=1);

namespace Vizion\Renderer\Value;

use Vizion\Api\IReportValueRenderer;

final class MapValueRenderer implements IReportValueRenderer {

	public static function getName(): string {
		return 'mapvaluerenderer';
	}

	public function renderValue(mixed $value, array $row, array $field, array $rendererConfig): string {
		$placeholder = (string)($rendererConfig['placeholder'] ?? '—');

		if($value === null || $value === '') {
			return $placeholder;
		}

		$key = (string)$value;
		$values = isset($rendererConfig['values']) && is_array($rendererConfig['values']) ? $rendererConfig['values'] : [];

		if(array_key_exists($key, $values) && is_scalar($values[$key])) {
			return (string)$values[$key];
		}

		return $key;
	}

	public function rendersHtml(): bool {
		return false;
	}
}
