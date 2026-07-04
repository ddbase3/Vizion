<?php declare(strict_types=1);

namespace Vizion\Renderer\Formatter;

use Vizion\Api\IReportValueFormatter;

final class TextValueFormatter implements IReportValueFormatter {

	public static function getName(): string {
		return 'textvalueformatter';
	}

	public function getFormatterType(): string {
		return 'text';
	}

	public function getAliases(): array {
		return ['string'];
	}

	public function buildClientConfig(array $field, array $formatterConfig): array {
		return array_merge($formatterConfig, ['type' => $this->getFormatterType()]);
	}
}
