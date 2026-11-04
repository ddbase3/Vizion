<?php declare(strict_types=1);

namespace Vizion\Test\Renderer\Formatter;

use PHPUnit\Framework\TestCase;
use Vizion\Renderer\Formatter\DateValueFormatter;
use Vizion\Renderer\Formatter\EmailValueFormatter;
use Vizion\Renderer\Formatter\EnumValueFormatter;
use Vizion\Renderer\Formatter\NumberValueFormatter;
use Vizion\Renderer\Formatter\TextValueFormatter;

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
