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

namespace Base3\Vizion\Test\Filter;

use Base3\Test\Core\ClassMapStub;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Base3\Vizion\Filter\Control\NativeFilterControl;
use Base3\Vizion\Filter\Control\RangeFilterControl;
use Base3\Vizion\Filter\ReportFilterControlRegistry;
use Base3\Vizion\Filter\ReportFilterTypeRegistry;
use Base3\Vizion\Filter\Type\RangeFilterType;
use Base3\Vizion\Filter\Type\TextFilterType;

final class FilterRegistriesTest extends TestCase {

	public function testFilterTypeRegistryMatchesTechnicalAndSemanticNames(): void {
		$map = new ClassMapStub();
		$map->register(TextFilterType::class)->register(RangeFilterType::class);
		$registry = new ReportFilterTypeRegistry($map);
		$this->assertInstanceOf(RangeFilterType::class, $registry->getType(' Number_Range '));
		$this->assertInstanceOf(TextFilterType::class, $registry->getType(' textfiltertype '));
		$this->assertInstanceOf(TextFilterType::class, $registry->getType('unregistered'));
		$this->assertCount(2, $registry->getTypes());
	}

	public function testFilterTypeRegistryFailsWithoutTextType(): void {
		$this->expectException(RuntimeException::class);
		(new ReportFilterTypeRegistry(new ClassMapStub()))->getType('missing');
	}

	public function testFilterControlRegistryFallsBackToNativeControl(): void {
		$map = new ClassMapStub();
		$map->register(NativeFilterControl::class)->register(RangeFilterControl::class);
		$registry = new ReportFilterControlRegistry($map);
		$this->assertInstanceOf(RangeFilterControl::class, $registry->getControl(' Number-Range '));
		$this->assertInstanceOf(NativeFilterControl::class, $registry->getControl('nativefiltercontrol'));
		$this->assertInstanceOf(NativeFilterControl::class, $registry->getControl('not-registered'));
		$this->assertCount(2, $registry->getControls());
	}

	public function testFilterControlRegistryFailsWithoutNativeControl(): void {
		$this->expectException(RuntimeException::class);
		(new ReportFilterControlRegistry(new ClassMapStub()))->getControl('missing');
	}
}
