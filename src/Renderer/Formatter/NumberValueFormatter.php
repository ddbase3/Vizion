<?php declare(strict_types=1);

namespace Vizion\Renderer\Formatter;

use Vizion\Api\IReportValueFormatter;

final class NumberValueFormatter implements IReportValueFormatter {

	public static function getName(): string {
		return 'numbervalueformatter';
	}

	public function getFormatterType(): string {
		return 'number';
	}

	public function getAliases(): array {
		return ['int', 'integer', 'float', 'decimal'];
	}

	public function buildClientConfig(array $field, array $formatterConfig): array {
		return array_merge([
			'type' => $this->getFormatterType(),
			'maximumFractionDigits' => 2
		], $formatterConfig);
	}
}
