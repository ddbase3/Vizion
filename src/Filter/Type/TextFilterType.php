<?php declare(strict_types=1);

namespace Vizion\Filter\Type;

final class TextFilterType extends AbstractReportFilterType {

	public static function getName(): string {
		return 'textfiltertype';
	}

	public function getType(): string {
		return 'text';
	}

	public function getAliases(): array {
		return ['search', 'string'];
	}
}
