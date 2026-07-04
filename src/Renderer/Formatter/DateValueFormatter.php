<?php declare(strict_types=1);

namespace Vizion\Renderer\Formatter;

use Vizion\Api\IReportValueFormatter;

final class DateValueFormatter implements IReportValueFormatter {

	public static function getName(): string {
		return 'datevalueformatter';
	}

	public function getFormatterType(): string {
		return 'date';
	}

	public function getAliases(): array {
		return ['datetime'];
	}

	public function buildClientConfig(array $field, array $formatterConfig): array {
		$fieldConfig = isset($field['config']) && is_array($field['config']) ? $field['config'] : [];
		$type = (string) ($formatterConfig['type'] ?? $fieldConfig['type'] ?? $this->getFormatterType());
		$isDateTime = strtolower($type) === 'datetime';

		return array_merge([
			'type' => $isDateTime ? 'datetime' : 'date',
			'format' => $isDateTime ? 'DD.MM.YYYY HH:mm' : 'DD.MM.YYYY',
			'valueFormat' => $isDateTime ? 'YYYY-MM-DD HH:mm' : 'YYYY-MM-DD'
		], $formatterConfig);
	}
}
