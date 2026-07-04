<?php declare(strict_types=1);

namespace Vizion\Filter\Control;

use Vizion\Api\IReportFilterControl;

final class RangeFilterControl implements IReportFilterControl {

	public static function getName(): string {
		return 'rangefiltercontrol';
	}

	public function getControl(): string {
		return 'range';
	}

	public function getAliases(): array {
		return ['numberrange', 'number_range', 'number-range'];
	}

	public function configureGridField(array $gridField, array $field, array $definition): array {
		$gridField['renderControlKey'] = 'vizion.range';
		$gridField['valueType'] = 'range';

		return $gridField;
	}

	public function getAssetPaths(array $definition): array {
		return ['plugin/Vizion/assets/js/vizion-report-filter-controls.js'];
	}
}
