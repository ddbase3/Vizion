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

namespace Base3\Vizion\Test\Filter\Type;

use PHPUnit\Framework\TestCase;
use Base3\Vizion\Filter\Type\DateFilterType;
use Base3\Vizion\Filter\Type\DateRangeFilterType;
use Base3\Vizion\Filter\Type\DateTimeFilterType;
use Base3\Vizion\Filter\Type\DateTimeRangeFilterType;

final class DateFilterNormalizationTest extends TestCase {

	public function testDateParsesDayMonthYearAndStorageFormat(): void {
		$type = new DateFilterType();
		$this->assertSame('datefiltertype', $type::getName());
		$this->assertSame('2026-11-03', $type->normalizeValue('03.11.2026', []));
		$this->assertSame('03.11.2026', $type->normalizeValue('2026-11-03', ['valueFormat' => 'DD.MM.YYYY']));
		$this->assertSame('', $type->normalizeValue(null, []));
		$this->assertSame('', $type->normalizeValue(['not a date'], []));
	}

	public function testDateKeepsInvalidInputInsteadOfSilentlyConvertingIt(): void {
		$type = new DateFilterType();
		$this->assertSame('31.02.2026', $type->normalizeValue('31.02.2026', []));
		$this->assertSame('2026-13-01', $type->normalizeValue('2026-13-01', []));
		$this->assertSame('unknown', $type->normalizeValue(' unknown ', []));
	}

	public function testDateTimeSupportsIsoTAndDisplayInputFormats(): void {
		$type = new DateTimeFilterType();
		$this->assertSame('datetimefiltertype', $type::getName());
		$this->assertSame(['datetime-local'], $type->getAliases());
		$this->assertSame('2026-11-03 12:34', $type->normalizeValue('2026-11-03T12:34', []));
		$this->assertSame('2026-11-03 12:34', $type->normalizeValue('03.11.2026 12:34', []));
		$this->assertSame('2026-11-03 12:34', $type->normalizeValue('2026-11-03 12:34', []));
	}

	public function testDateTimeRejectsOutOfRangeTimeWithoutChangingOriginalValue(): void {
		$type = new DateTimeFilterType();
		$this->assertSame('03.11.2026 25:05', $type->normalizeValue('03.11.2026 25:05', []));
		$this->assertSame('2026-11-03T12:61', $type->normalizeValue('2026-11-03T12:61', []));
	}

	public function testDateRangeConvertsBothBoundariesAndBuildsWhere(): void {
		$type = new DateRangeFilterType();
		$this->assertSame(['date_range', 'date-range'], $type->getAliases());
		$this->assertSame(['from' => '', 'to' => ''], $type->normalizeValue(null, []));
		$range = $type->normalizeValue(['from' => '01.11.2026', 'to' => '03.11.2026'], []);
		$this->assertSame(['from' => '2026-11-01', 'to' => '2026-11-03'], $range);
		$where = $type->buildCondition('created', $range, []);
		$this->assertSame('AND', $where['operator']);
		$this->assertSame('2026-11-01', $where['params'][0]['params'][1]);
		$this->assertSame('2026-11-03', $where['params'][1]['params'][1]);
	}

	public function testDateTimeRangePreservesTimeAndOpenBoundaries(): void {
		$type = new DateTimeRangeFilterType();
		$this->assertSame('datetimerangefiltertype', $type::getName());
		$this->assertSame(['datetime_range', 'datetime-range'], $type->getAliases());
		$range = $type->normalizeValue(['from' => '03.11.2026 08:05', 'to' => ''], []);
		$this->assertSame(['from' => '2026-11-03 08:05', 'to' => ''], $range);
		$this->assertSame('>=', $type->buildCondition('created', $range, [])['operator']);
		$this->assertNull($type->buildCondition('created', ['from' => '', 'to' => ''], []));
	}
}
