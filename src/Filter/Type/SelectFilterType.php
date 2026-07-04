<?php declare(strict_types=1);

namespace Vizion\Filter\Type;

final class SelectFilterType extends AbstractReportFilterType {

	public static function getName(): string {
		return 'selectfiltertype';
	}

	public function getType(): string {
		return 'select';
	}

	protected function getDefaultMatch(): string {
		return 'equals';
	}
}
