<?php declare(strict_types=1);

namespace Vizion\Renderer\Value;

use Vizion\Api\IReportValueRenderer;

final class LinkValueRenderer implements IReportValueRenderer {

	public static function getName(): string {
		return 'linkvaluerenderer';
	}

	public function renderValue(mixed $value, array $row, array $field, array $rendererConfig): string {
		$text = $this->stringValue($value, (string)($rendererConfig['placeholder'] ?? '—'));
		$hrefTemplate = isset($rendererConfig['href']) && is_scalar($rendererConfig['href']) ? trim((string)$rendererConfig['href']) : '';

		if($hrefTemplate === '' || $text === (string)($rendererConfig['placeholder'] ?? '—')) {
			return $this->escape($text);
		}

		$href = $this->replacePlaceholders($hrefTemplate, $row, $value);

		if($href === '' || preg_match('/\{[^}]+\}/', $href) === 1) {
			return $this->escape($text);
		}

		$target = isset($rendererConfig['target']) && is_scalar($rendererConfig['target']) ? trim((string)$rendererConfig['target']) : '';
		$attrs = ' href="' . $this->escapeAttribute($href) . '"';

		if($target !== '') {
			$attrs .= ' target="' . $this->escapeAttribute($target) . '"';

			if($target === '_blank') {
				$attrs .= ' rel="noopener noreferrer"';
			}
		}

		return '<a class="vizion-modulargrid-cell-link"' . $attrs . '>' . $this->escape($text) . '</a>';
	}

	public function rendersHtml(): bool {
		return true;
	}

	private function replacePlaceholders(string $template, array $row, mixed $value): string {
		return (string)preg_replace_callback('/\{([^}]+)\}/', function(array $matches) use ($row, $value): string {
			$key = trim((string)$matches[1]);

			if($key === 'value') {
				return rawurlencode(is_scalar($value) || $value === null ? (string)$value : '');
			}

			$rowValue = $this->getRowValue($row, $key);

			if($rowValue === null || (!is_scalar($rowValue) && !is_bool($rowValue))) {
				return '';
			}

			return rawurlencode((string)$rowValue);
		}, $template);
	}

	private function getRowValue(array $row, string $key): mixed {
		if(array_key_exists($key, $row)) {
			return $row[$key];
		}

		$parts = explode('.', $key);
		$current = $row;

		foreach($parts as $part) {
			if($part === '' || !is_array($current) || !array_key_exists($part, $current)) {
				return null;
			}

			$current = $current[$part];
		}

		return $current;
	}

	private function stringValue(mixed $value, string $placeholder): string {
		if($value === null || $value === '') {
			return $placeholder;
		}

		return is_scalar($value) ? (string)$value : $placeholder;
	}

	private function escape(string $value): string {
		return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	private function escapeAttribute(string $value): string {
		return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}
