<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of Vizion for BASE3 Framework.
 *
 * Vizion provides reporting and data visualization for BASE3 projects.
 * It supports grids, interactive tables, charts and data exporters.
 * Visual components consume structured reporting and query results.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/vizion
 * https://github.com/ddbase3/Vizion
 **********************************************************************/

namespace Base3\Vizion\Test\Filter\Control;

use PHPUnit\Framework\TestCase;
use Base3\Vizion\Filter\Control\ChronoPickerFilterControl;
use Base3\Vizion\Filter\Control\DateRangeFilterControl;
use Base3\Vizion\Filter\Control\NativeFilterControl;
use Base3\Vizion\Filter\Control\RangeFilterControl;

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
