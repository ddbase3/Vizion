<?php declare(strict_types=1);

namespace Vizion\Renderer\Value;

use Vizion\Api\IReportValueRenderer;

final class EmailLinkValueRenderer implements IReportValueRenderer {

	public static function getName(): string {
		return 'emaillinkvaluerenderer';
	}

	public function renderValue(mixed $value, array $row, array $field, array $rendererConfig): string {
		$text = $this->stringValue($value, (string)($rendererConfig['placeholder'] ?? '—'));
		$email = $this->extractEmail($text);

		if($email === '') {
			return $this->escape($text);
		}

		return '<a class="vizion-modulargrid-cell-email-link" href="mailto:' . $this->escapeAttribute($email) . '">' . $this->escape($text) . '</a>';
	}

	public function rendersHtml(): bool {
		return true;
	}

	private function extractEmail(string $value): string {
		if(preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $value, $matches) !== 1) {
			return '';
		}

		return $matches[0];
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
