<?php declare(strict_types=1);

namespace Vizion\Filter\Control;

use Vizion\Api\IReportFilterControl;

final class DateRangeFilterControl implements IReportFilterControl {

	public static function getName(): string {
		return 'daterangefiltercontrol';
	}

	public function getControl(): string {
		return 'daterange';
	}

	public function getAliases(): array {
		return ['date-range', 'date_range', 'native-daterange', 'native_date_range'];
	}

	public function configureGridField(array $gridField, array $field, array $definition): array {
		$type = (string) ($gridField['type'] ?? $definition['type'] ?? 'daterange');
		$gridField['renderControlKey'] = 'vizion.dateRange';
		$gridField['valueType'] = $type;

		return $gridField;
	}

	public function getAssetPaths(array $definition): array {
		return ['plugin/Vizion/assets/js/vizion-report-filter-controls.js'];
	}
}
