<?php declare(strict_types=1);

namespace Vizion\Test\Filter\Control;

use PHPUnit\Framework\TestCase;
use Vizion\Filter\Control\ChronoPickerFilterControl;
use Vizion\Filter\Control\DateRangeFilterControl;
use Vizion\Filter\Control\NativeFilterControl;
use Vizion\Filter\Control\RangeFilterControl;

final class FilterControlBehaviorTest extends TestCase {

	public function testNativeControlDoesNotModifyFieldsOrLoadAssets(): void {
		$control = new NativeFilterControl();
		$this->assertSame('native', $control->getControl());
		$this->assertSame(['default', 'modulargrid'], $control->getAliases());
		$this->assertSame(['key' => 'name'], $control->configureGridField(['key' => 'name'], [], []));
		$this->assertSame([], $control->getAssetPaths([]));
	}

	public function testNumericRangeAddsKeyAndUsesPluginAsset(): void {
		$control = new RangeFilterControl();
		$this->assertSame('range', $control->getControl());
		$this->assertSame(['numberrange', 'number_range', 'number-range'], $control->getAliases());
		$this->assertSame(['key' => 'age', 'renderControlKey' => 'vizion.range', 'valueType' => 'range'], $control->configureGridField(['key' => 'age'], [], []));
		$this->assertSame(['plugin/Vizion/assets/js/vizion-report-filter-controls.js'], $control->getAssetPaths([]));
	}

	public function testDateRangeCopiesValueTypeAndAssets(): void {
		$control = new DateRangeFilterControl();
		$this->assertSame('daterange', $control->getControl());
		$this->assertSame('datetimerange', $control->configureGridField(['type' => 'datetimerange'], [], [])['valueType']);
		$this->assertSame('vizion.dateRange', $control->configureGridField([], [], [])['renderControlKey']);
		$this->assertSame(['plugin/Vizion/assets/js/vizion-report-filter-controls.js'], $control->getAssetPaths([]));
	}

	public function testChronoPickerChoosesDateOrDateRangeComponent(): void {
		$control = new ChronoPickerFilterControl();
		$this->assertSame(['chrono'], $control->getAliases());
		$this->assertSame('vizion.chronopickerDate', $control->configureGridField(['type' => 'datetime'], [], [])['renderControlKey']);
		$this->assertSame('vizion.chronopickerDateRange', $control->configureGridField(['type' => 'datetimerange'], [], [])['renderControlKey']);
		$this->assertCount(3, $control->getAssetPaths([]));
		$this->assertContains('plugin/ClientStack/assets/chronopicker/index.js', $control->getAssetPaths([]));
	}
}
