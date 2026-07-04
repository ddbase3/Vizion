<?php declare(strict_types=1);

namespace Vizion\Filter\Type;

final class SliderFilterType extends NumberFilterType {

	public static function getName(): string {
		return 'sliderfiltertype';
	}

	public function getType(): string {
		return 'slider';
	}
}
