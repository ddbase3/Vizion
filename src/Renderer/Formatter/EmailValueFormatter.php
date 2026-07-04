<?php declare(strict_types=1);

namespace Vizion\Renderer\Formatter;

use Vizion\Api\IReportValueFormatter;

final class EmailValueFormatter implements IReportValueFormatter {

	public static function getName(): string {
		return 'emailvalueformatter';
	}

	public function getFormatterType(): string {
		return 'email';
	}

	public function getAliases(): array {
		return ['mail', 'mailto', 'email-link'];
	}

	public function buildClientConfig(array $field, array $formatterConfig): array {
		return array_merge([
			'type' => $this->getFormatterType(),
			'placeholder' => '—'
		], $formatterConfig);
	}
}
