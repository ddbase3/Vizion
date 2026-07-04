<?php declare(strict_types=1);

namespace Vizion\Filter\Type;

final class RadioFilterType extends AbstractReportFilterType {

	public static function getName(): string {
		return 'radiofiltertype';
	}

	public function getType(): string {
		return 'radio';
	}

	protected function getDefaultMatch(): string {
		return 'equals';
	}
}
