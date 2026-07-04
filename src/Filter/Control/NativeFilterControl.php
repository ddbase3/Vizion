<?php declare(strict_types=1);

namespace Vizion\Filter\Control;

use Vizion\Api\IReportFilterControl;

final class NativeFilterControl implements IReportFilterControl {

	public static function getName(): string {
		return 'nativefiltercontrol';
	}

	public function getControl(): string {
		return 'native';
	}

	public function getAliases(): array {
		return ['default', 'modulargrid'];
	}

	public function configureGridField(array $gridField, array $field, array $definition): array {
		return $gridField;
	}

	public function getAssetPaths(array $definition): array {
		return [];
	}
}
