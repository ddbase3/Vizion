<?php declare(strict_types=1);

namespace Vizion\Renderer\Value;

use Vizion\Api\IReportValueRenderer;

final class TextValueRenderer implements IReportValueRenderer {

	public static function getName(): string {
		return 'textvaluerenderer';
	}

	public function renderValue(mixed $value, array $row, array $field, array $rendererConfig): string {
		return $this->stringValue($value, (string)($rendererConfig['placeholder'] ?? '—'));
	}

	public function rendersHtml(): bool {
		return false;
	}

	private function stringValue(mixed $value, string $placeholder): string {
		if($value === null || $value === '') {
			return $placeholder;
		}

		if(is_bool($value)) {
			return $value ? 'true' : 'false';
		}

		if(is_scalar($value)) {
			return (string)$value;
		}

		$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return is_string($json) && $json !== '' ? $json : $placeholder;
	}
}
