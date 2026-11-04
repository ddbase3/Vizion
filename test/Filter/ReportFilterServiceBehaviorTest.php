<?php declare(strict_types=1);

namespace Vizion\Test\Filter;

use Base3\Test\Core\ClassMapStub;
use PHPUnit\Framework\TestCase;
use Vizion\Filter\Control\DateRangeFilterControl;
use Vizion\Filter\Control\NativeFilterControl;
use Vizion\Filter\Control\RangeFilterControl;
use Vizion\Filter\ReportFilterService;
use Vizion\Filter\Type\CheckboxFilterType;
use Vizion\Filter\Type\DateRangeFilterType;
use Vizion\Filter\Type\MultiSelectFilterType;
use Vizion\Filter\Type\NumberFilterType;
use Vizion\Filter\Type\RangeFilterType;
use Vizion\Filter\Type\SelectFilterType;
use Vizion\Filter\Type\TextFilterType;

final class ReportFilterServiceBehaviorTest extends TestCase {

	public function testBuildGridFieldsSkipsDisabledAndUnconfiguredFields(): void {
		$service = $this->service();
		$fields = [
			['alias' => 'notConfigured', 'config' => ['label' => 'Name']],
			['alias' => 'disabled', 'config' => ['filter' => ['enabled' => false]]],
			['alias' => 'enabled', 'config' => ['label' => 'Visible', 'filter' => true]],
			['alias' => 'hidden', 'config' => ['visible' => false, 'filter' => true]]
		];
		$grid = $service->buildGridFilterFields($fields);
		$this->assertSame(['enabled', 'hidden'], array_column($grid, 'key'));
		$this->assertSame('always', $grid[0]['visibility']);
		$this->assertSame('optional', $grid[1]['visibility']);
		$this->assertSame('contains', $grid[0]['match']);
		$this->assertSame('Visible', $grid[0]['label']);
	}

	public function testOptionsNormalizeAssociativeAndListForms(): void {
		$grid = $this->service()->buildGridFilterFields([[
			'alias' => 'status', 'config' => ['filter' => [
				'enabled' => true, 'type' => 'select', 'visibility' => 'INVALID',
				'options' => ['a' => 'Active', ['value' => 'b', 'label' => 'Blocked'], 'Other']
			]]
		]])[0];
		$this->assertSame('always', $grid['visibility']);
		$this->assertSame('equals', $grid['match']);
		$this->assertSame([
			['value' => 'a', 'label' => 'Active'],
			['value' => 'b', 'label' => 'Blocked'],
			['value' => 'Other', 'label' => 'Other']
		], $grid['options']);
	}

	public function testGridFiltersApplyNumericAndDateRangeControls(): void {
		$grid = $this->service()->buildGridFilterFields([
			['alias' => 'price', 'config' => ['filter' => [
				'enabled' => true, 'type' => 'range', 'initialValue' => ['min' => '1', 'max' => '5']
			]]],
			['alias' => 'created', 'config' => ['filter' => [
				'enabled' => true, 'type' => 'daterange', 'initialValue' => ['from' => '01.11.2026', 'to' => '']
			]]]
		]);
		$this->assertSame('vizion.range', $grid[0]['renderControlKey']);
		$this->assertSame(['min' => 1, 'max' => 5], $grid[0]['initialValue']);
		$this->assertSame('vizion.dateRange', $grid[1]['renderControlKey']);
		$this->assertSame(['from' => '2026-11-01', 'to' => ''], $grid[1]['initialValue']);
	}

	public function testInitialValuesOnlyIncludeExplicitlyConfiguredKeys(): void {
		$values = $this->service()->buildInitialFilterValues([
			['alias' => 'status', 'config' => ['filter' => ['enabled' => true, 'type' => 'select', 'initialValue' => 'active']]],
			['alias' => 'limit', 'config' => ['filter' => ['enabled' => true, 'type' => 'number', 'initialValue' => '8.5']]],
			['alias' => 'other', 'config' => ['filter' => true]],
		]);
		$this->assertSame(['status' => 'active', 'limit' => 8.5, 'other' => ''], $values);
	}

	public function testNormalizeFiltersIgnoresUnknownDisabledAndEmptyFields(): void {
		$fields = [
			['alias' => 'term', 'config' => ['filter' => true]],
			['alias' => 'active', 'config' => ['filter' => ['enabled' => true, 'type' => 'checkbox']]],
			['alias' => 'multi', 'config' => ['filter' => ['enabled' => true, 'type' => 'multiselect']]],
			['alias' => 'disabled', 'config' => ['filter' => ['enabled' => false]]],
		];
		$service = $this->service();
		$this->assertSame([], $service->normalizeFilters('invalid', $fields));
		$this->assertSame(['term' => 'search', 'active' => true, 'multi' => ['a', 'b']], $service->normalizeFilters([
			'term' => ' search ', 'active' => 'YES', 'multi' => ['a', 'a', 'b', ''],
			'disabled' => 'not visible', 'missing' => 'ignored'
		], $fields));
	}

	public function testBuildFilterWhereReturnsNullOrSingleCondition(): void {
		$fields = [['alias' => 'name', 'config' => ['filter' => true]]];
		$service = $this->service();
		$this->assertNull($service->buildFilterWhere([], $fields, ['name' => ['type' => 'fld', 'field' => 'name']]));
		$this->assertNull($service->buildFilterWhere(['missing' => 'other'], $fields, []));
		$where = $service->buildFilterWhere(['name' => 'Ali'], $fields, ['name' => ['type' => 'fld', 'field' => 'name']]);
		$this->assertSame('LIKE', $where['operator']);
		$this->assertSame('%Ali%', $where['params'][1]);
	}

	public function testBuildFilterWhereCombinesIndependentTypedConditions(): void {
		$fields = [
			['alias' => 'name', 'config' => ['filter' => true]],
			['alias' => 'status', 'config' => ['filter' => ['enabled' => true, 'type' => 'select']]],
			['alias' => 'roles', 'config' => ['filter' => ['enabled' => true, 'type' => 'multiselect']]]
		];
		$defs = ['name' => 'name_col', 'status' => 'status_col', 'roles' => 'role_col'];
		$where = $this->service()->buildFilterWhere(['name' => 'Alice', 'status' => 'on', 'roles' => ['admin', 'editor']], $fields, $defs);
		$this->assertSame('AND', $where['operator']);
		$this->assertSame(['LIKE', '=', 'IN'], array_column($where['params'], 'operator'));
		$this->assertSame(['role_col', 'admin', 'editor'], $where['params'][2]['params']);
	}

	private function service(): ReportFilterService {
		$map = new ClassMapStub();
		foreach([
			TextFilterType::class, SelectFilterType::class, NumberFilterType::class,
			CheckboxFilterType::class, MultiSelectFilterType::class, RangeFilterType::class,
			DateRangeFilterType::class, NativeFilterControl::class, RangeFilterControl::class,
			DateRangeFilterControl::class
		] as $class) {
			$map->register($class);
		}
		return new ReportFilterService($map);
	}
}
