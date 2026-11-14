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

namespace Base3\Vizion\Test\Renderer\Formatter;

use PHPUnit\Framework\TestCase;
use Base3\Vizion\Renderer\Formatter\DateValueFormatter;
use Base3\Vizion\Renderer\Formatter\EmailValueFormatter;
use Base3\Vizion\Renderer\Formatter\EnumValueFormatter;
use Base3\Vizion\Renderer\Formatter\NumberValueFormatter;
use Base3\Vizion\Renderer\Formatter\TextValueFormatter;

final class ReportValueFormattersBehaviorTest extends TestCase {

	public function testTextFormatterAlwaysOverridesProvidedType(): void {
		$formatter = new TextValueFormatter();
		$this->assertSame('textvalueformatter', $formatter::getName());
		$this->assertSame('text', $formatter->getFormatterType());
		$this->assertSame(['string'], $formatter->getAliases());
		$this->assertSame(['type' => 'text', 'precision' => 3], $formatter->buildClientConfig([], ['type' => 'number', 'precision' => 3]));
	}

	public function testNumberFormatterPreservesExplicitPrecision(): void {
		$formatter = new NumberValueFormatter();
		$this->assertSame('numbervalueformatter', $formatter::getName());
		$this->assertSame(['int', 'integer', 'float', 'decimal'], $formatter->getAliases());
		$this->assertSame(['type' => 'number', 'maximumFractionDigits' => 2], $formatter->buildClientConfig([], []));
		$this->assertSame(5, $formatter->buildClientConfig([], ['maximumFractionDigits' => 5])['maximumFractionDigits']);
	}

	public function testDateFormatterChoosesDateTimeDefaultsFromField(): void {
		$formatter = new DateValueFormatter();
		$this->assertSame('datevalueformatter', $formatter::getName());
		$this->assertSame(['datetime'], $formatter->getAliases());
		$this->assertSame('DD.MM.YYYY', $formatter->buildClientConfig([], [])['format']);
		$config = $formatter->buildClientConfig(['config' => ['type' => 'datetime']], []);
		$this->assertSame('datetime', $config['type']);
		$this->assertSame('YYYY-MM-DD HH:mm', $config['valueFormat']);
	}

	public function testDateFormatterAllowsExplicitFormatOverrides(): void {
		$formatter = new DateValueFormatter();
		$config = $formatter->buildClientConfig(['config' => ['type' => 'date']], ['type' => 'datetime', 'format' => 'YYYY-MM-DD']);
		$this->assertSame('datetime', $config['type']);
		$this->assertSame('YYYY-MM-DD', $config['format']);
		$this->assertSame('YYYY-MM-DD HH:mm', $config['valueFormat']);
	}

	public function testEnumFormatterDefaultsToEmptyOptions(): void {
		$formatter = new EnumValueFormatter();
		$this->assertSame('enumvalueformatter', $formatter::getName());
		$this->assertSame(['options', 'status'], $formatter->getAliases());
		$this->assertSame(['type' => 'enum', 'options' => []], $formatter->buildClientConfig([], []));
		$this->assertSame(['a' => 'Active'], $formatter->buildClientConfig([], ['options' => ['a' => 'Active']])['options']);
	}

	public function testEmailFormatterProvidesPlaceholderAndAliases(): void {
		$formatter = new EmailValueFormatter();
		$this->assertSame('emailvalueformatter', $formatter::getName());
		$this->assertSame(['mail', 'mailto', 'email-link'], $formatter->getAliases());
		$this->assertSame('—', $formatter->buildClientConfig([], [])['placeholder']);
		$this->assertSame('not set', $formatter->buildClientConfig([], ['placeholder' => 'not set'])['placeholder']);
	}
}
