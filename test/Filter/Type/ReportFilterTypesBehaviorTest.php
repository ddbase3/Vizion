<?php declare(strict_types=1);

namespace Vizion\Test\Filter\Type;

use PHPUnit\Framework\TestCase;
use Vizion\Filter\Type\CheckboxFilterType;
use Vizion\Filter\Type\MultiSelectFilterType;
use Vizion\Filter\Type\NumberFilterType;
use Vizion\Filter\Type\RadioFilterType;
use Vizion\Filter\Type\RangeFilterType;
use Vizion\Filter\Type\SelectFilterType;
use Vizion\Filter\Type\SliderFilterType;
use Vizion\Filter\Type\TextFilterType;

final class ReportFilterTypesBehaviorTest extends TestCase {

	public function testTextTypeNormalizesScalarInputAndEmptyValues(): void {
		$type = new TextFilterType();
		$this->assertSame('textfiltertype', $type::getName());
		$this->assertSame('text', $type->getType());
		$this->assertSame(['search', 'string'], $type->getAliases());
		$this->assertSame('abc', $type->normalizeValue(' abc ', []));
		$this->assertSame('', $type->normalizeValue(['bad'], []));
		$this->assertTrue($type->isEmptyValue($type->normalizeValue(' ', []), []));
		$this->assertSame('placeholder', $type->getDefaultValue(['defaultValue' => 'placeholder']));
		$this->assertSame('custom', $type->getEmptyValue(['emptyValue' => 'custom']));
	}

	public function testTextConditionOperatorsAreNormalizedAndUseExpectedWildcards(): void {
		$type = new TextFilterType();
		foreach([' = ' => 'equals', 'NOT-EQUALS' => 'notEquals', 'gte' => 'gte', '<' => 'lt', 'starts-with' => 'startsWith', 'ends_with' => 'endsWith', 'unknown' => 'contains'] as $match => $expected) {
			$this->assertSame($expected, $type->normalizeMatch($match));
		}
		$element = ['type' => 'fld', 'field' => 'title'];
		$this->assertSame(['type' => 'op', 'operator' => 'LIKE', 'params' => [$element, '%Alice%']], $type->buildCondition($element, 'Alice', []));
		$this->assertSame(['type' => 'op', 'operator' => 'LIKE', 'params' => [$element, 'A%']], $type->buildCondition($element, 'A', ['match' => 'startsWith']));
		$this->assertSame(['type' => 'op', 'operator' => 'LIKE', 'params' => [$element, '%Z']], $type->buildCondition($element, 'Z', ['match' => 'endsWith']));
		$this->assertSame(['type' => 'op', 'operator' => '<>', 'params' => [$element, 'skip']], $type->buildCondition($element, 'skip', ['match' => '!=']));
	}

	public function testSelectAndRadioDefaultToEquality(): void {
		foreach([new SelectFilterType(), new RadioFilterType()] as $type) {
			$this->assertSame('equals', $type->normalizeMatch(''));
			$this->assertSame('=', $type->buildCondition('column', 'active', [])['operator']);
			$this->assertSame('active', $type->normalizeValue(' active ', []));
		}
		$this->assertSame('selectfiltertype', SelectFilterType::getName());
		$this->assertSame('radiofiltertype', RadioFilterType::getName());
	}

	public function testNumericTypesDistinguishValidDecimalsAndInvalidValues(): void {
		foreach([new NumberFilterType(), new SliderFilterType()] as $type) {
			$this->assertSame(12, $type->normalizeValue('12', []));
			$this->assertSame(12.5, $type->normalizeValue('12.5', []));
			$this->assertSame('', $type->normalizeValue('no number', []));
			$this->assertSame('', $type->normalizeValue(null, []));
			$this->assertSame('', $type->normalizeValue(['12'], []));
			$this->assertSame('=', $type->buildCondition('column', 8, [])['operator']);
		}
		$this->assertSame('slider', (new SliderFilterType())->getType());
		$this->assertSame(['int', 'integer', 'float', 'decimal'], (new NumberFilterType())->getAliases());
	}

	public function testCheckboxRecognizesExplicitTrueAndFalseValues(): void {
		$type = new CheckboxFilterType();
		$this->assertSame('checkbox', $type->getType());
		$this->assertSame(['bool', 'boolean'], $type->getAliases());
		foreach([true, 1, '1', 'yes', 'JA', 'on', 'TRUE'] as $value) {
			$this->assertTrue($type->normalizeValue($value, []));
		}
		foreach([false, 0, 2, '0', 'no', null, [], new \stdClass()] as $value) {
			$this->assertFalse($type->normalizeValue($value, []));
		}
		$this->assertTrue($type->isEmptyValue(false, []));
		$this->assertFalse($type->isEmptyValue(true, []));
		$this->assertSame('=', $type->buildCondition('column', true, [])['operator']);
	}

	public function testMultiSelectDeduplicatesAndSkipsNonScalarOptions(): void {
		$type = new MultiSelectFilterType();
		$this->assertSame('multiselect', $type->getType());
		$this->assertSame(['multi_select', 'multi-select'], $type->getAliases());
		$this->assertSame(['blue', 'red', '0'], $type->normalizeValue([' blue ', 'red', 'blue', null, ['invalid'], '', 0], []));
		$this->assertSame(['value'], $type->normalizeValue(' value ', []));
		$this->assertSame([], $type->normalizeValue(null, []));
		$this->assertSame([], $type->getEmptyValue([]));
	}

	public function testMultiSelectBuildsInOperatorAndIgnoresEmptySelections(): void {
		$type = new MultiSelectFilterType();
		$this->assertNull($type->buildCondition('field', [], []));
		$this->assertNull($type->buildCondition('field', ['', null], []));
		$this->assertSame(['type' => 'op', 'operator' => 'IN', 'params' => ['field', 'a', 'b']], $type->buildCondition('field', ['a', '', 'b', null], []));
		$this->assertSame(['type' => 'op', 'operator' => 'IN', 'params' => ['field', 'a']], $type->buildCondition('field', 'a', []));
	}

	public function testNumericRangeBuildsSingleOrCombinedBounds(): void {
		$type = new RangeFilterType();
		$this->assertSame('between', $type->normalizeMatch(''));
		$this->assertSame(['min' => '', 'max' => ''], $type->normalizeValue('invalid', []));
		$this->assertSame(['min' => 0, 'max' => 17.5], $type->normalizeValue(['min' => '0', 'max' => '17.5'], []));
		$this->assertNull($type->buildCondition('value', ['min' => '', 'max' => ''], []));
		$this->assertNull($type->buildCondition('value', null, []));
		$this->assertSame(['type' => 'op', 'operator' => '>=', 'params' => ['value', 0]], $type->buildCondition('value', ['min' => 0], []));
		$this->assertSame(['type' => 'op', 'operator' => '<=', 'params' => ['value', 8]], $type->buildCondition('value', ['max' => 8], []));
		$both = $type->buildCondition('value', ['min' => 1, 'max' => 10], []);
		$this->assertSame('AND', $both['operator']);
		$this->assertSame(['>=', '<='], array_column($both['params'], 'operator'));
	}

	public function testRangeIsEmptyOnlyWhenBothBoundsAreAbsent(): void {
		$type = new RangeFilterType();
		$this->assertTrue($type->isEmptyValue(['min' => '', 'max' => ''], []));
		$this->assertFalse($type->isEmptyValue(['min' => 0, 'max' => ''], []));
		$this->assertFalse($type->isEmptyValue(['min' => '', 'max' => 0], []));
	}
}
