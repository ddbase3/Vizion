<?php declare(strict_types=1);

namespace Vizion\Test\Filter;

use Base3\Test\Core\ClassMapStub;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vizion\Filter\Control\NativeFilterControl;
use Vizion\Filter\Control\RangeFilterControl;
use Vizion\Filter\ReportFilterControlRegistry;
use Vizion\Filter\ReportFilterTypeRegistry;
use Vizion\Filter\Type\RangeFilterType;
use Vizion\Filter\Type\TextFilterType;

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
