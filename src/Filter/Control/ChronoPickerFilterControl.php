<?php declare(strict_types=1);

namespace Vizion\Filter\Control;

use Vizion\Api\IReportFilterControl;

final class ChronoPickerFilterControl implements IReportFilterControl {

	public static function getName(): string {
		return 'chronopickerfiltercontrol';
	}

	public function getControl(): string {
		return 'chronopicker';
	}

	public function getAliases(): array {
		return ['chrono'];
	}

	public function configureGridField(array $gridField, array $field, array $definition): array {
		$type = (string) ($gridField['type'] ?? $definition['type'] ?? 'date');
		$gridField['valueType'] = $type;
		$gridField['renderControlKey'] = in_array($type, ['daterange', 'datetimerange'], true)
			? 'vizion.chronopickerDateRange'
			: 'vizion.chronopickerDate';

		return $gridField;
	}

	public function getAssetPaths(array $definition): array {
		return [
			'plugin/ClientStack/assets/chronopicker/styles/chronopicker.css',
			'plugin/ClientStack/assets/chronopicker/index.js',
			'plugin/Vizion/assets/js/vizion-report-filter-controls.js'
		];
	}
}
