<?php declare(strict_types=1);

namespace Vizion\Renderer\Formatter;

use Vizion\Api\IReportValueFormatter;

final class EnumValueFormatter implements IReportValueFormatter {

	public static function getName(): string {
		return 'enumvalueformatter';
	}

	public function getFormatterType(): string {
		return 'enum';
	}

	public function getAliases(): array {
		return ['options', 'status'];
	}

	public function buildClientConfig(array $field, array $formatterConfig): array {
		return array_merge([
			'type' => $this->getFormatterType(),
			'options' => []
		], $formatterConfig);
	}
}
