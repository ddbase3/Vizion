<?php declare(strict_types=1);

namespace Vizion\Test\Renderer\Value;

use PHPUnit\Framework\TestCase;
use Vizion\Renderer\Value\EmailLinkValueRenderer;
use Vizion\Renderer\Value\LinkValueRenderer;
use Vizion\Renderer\Value\MapValueRenderer;
use Vizion\Renderer\Value\TextValueRenderer;

final class ReportValueRenderersBehaviorTest extends TestCase {

	public function testTextRendererStringifiesScalarAndBooleanValues(): void {
		$renderer = new TextValueRenderer();
		$this->assertSame('textvaluerenderer', $renderer::getName());
		$this->assertFalse($renderer->rendersHtml());
		$this->assertSame('true', $renderer->renderValue(true, [], [], []));
		$this->assertSame('false', $renderer->renderValue(false, [], [], []));
		$this->assertSame('0', $renderer->renderValue(0, [], [], []));
		$this->assertSame('<b>raw</b>', $renderer->renderValue('<b>raw</b>', [], [], []));
	}

	public function testTextRendererUsesPlaceholderForEmptyAndJsonForStructuredValues(): void {
		$renderer = new TextValueRenderer();
		$this->assertSame('missing', $renderer->renderValue(null, [], [], ['placeholder' => 'missing']));
		$this->assertSame('missing', $renderer->renderValue('', [], [], ['placeholder' => 'missing']));
		$this->assertSame('{"key":"value"}', $renderer->renderValue(['key' => 'value'], [], [], []));
		$this->assertSame('["one","two"]', $renderer->renderValue(['one', 'two'], [], [], []));
	}

	public function testMapRendererSelectsScalarValuesOrReturnsOriginalKey(): void {
		$renderer = new MapValueRenderer();
		$this->assertSame('mapvaluerenderer', $renderer::getName());
		$this->assertFalse($renderer->rendersHtml());
		$config = ['values' => ['1' => 'Enabled', '2' => 0, '3' => ['invalid']]];
		$this->assertSame('Enabled', $renderer->renderValue(1, [], [], $config));
		$this->assertSame('0', $renderer->renderValue('2', [], [], $config));
		$this->assertSame('3', $renderer->renderValue(3, [], [], $config));
		$this->assertSame('other', $renderer->renderValue('other', [], [], $config));
	}

	public function testMapRendererHandlesMissingValuesAndNonArrayMappings(): void {
		$renderer = new MapValueRenderer();
		$this->assertSame('none', $renderer->renderValue('', [], [], ['placeholder' => 'none']));
		$this->assertSame('none', $renderer->renderValue(null, [], [], ['placeholder' => 'none']));
		$this->assertSame('id', $renderer->renderValue('id', [], [], ['values' => 'invalid']));
	}

	public function testLinkRendererEncodesNestedRowValuesAndEscapesAttributes(): void {
		$renderer = new LinkValueRenderer();
		$this->assertTrue($renderer->rendersHtml());
		$html = $renderer->renderValue('<Record>', ['id' => 23, 'owner' => ['name' => 'A & B']], [], [
			'href' => '/record/{id}?owner={owner.name}&value={value}', 'target' => '_blank'
		]);
		$this->assertStringContainsString('href="/record/23?owner=A%20%26%20B&amp;value=%3CRecord%3E"', $html);
		$this->assertStringContainsString('rel="noopener noreferrer"', $html);
		$this->assertStringContainsString('&lt;Record&gt;', $html);
		$this->assertSame('linkvaluerenderer', $renderer::getName());
	}

	public function testLinkRendererAvoidsLinksForMissingValueOrHref(): void {
		$renderer = new LinkValueRenderer();
		$this->assertSame('missing', $renderer->renderValue(null, ['id' => 1], [], ['href' => '/item/{id}', 'placeholder' => 'missing']));
		$this->assertSame('No link', $renderer->renderValue('No link', [], [], []));
		$this->assertSame('label', $renderer->renderValue('label', [], [], ['href' => '{missing}', 'target' => '_blank']));
		$this->assertSame('label', $renderer->renderValue('label', [], [], ['href' => '', 'target' => '_blank']));
	}

	public function testLinkRendererEscapesUntrustedTextAndTarget(): void {
		$html = (new LinkValueRenderer())->renderValue('<script>', ['id' => 'x'], [], ['href' => '/safe/{id}', 'target' => 'custom" onclick="alert(1)']);
		$this->assertStringContainsString('&lt;script&gt;', $html);
		$this->assertStringContainsString('target="custom&quot; onclick=&quot;alert(1)"', $html);
		$this->assertStringNotContainsString('rel="noopener noreferrer"', $html);
	}

	public function testEmailRendererOnlyLinksRecognizedAddresses(): void {
		$renderer = new EmailLinkValueRenderer();
		$this->assertTrue($renderer->rendersHtml());
		$this->assertSame('emaillinkvaluerenderer', $renderer::getName());
		$this->assertSame('none', $renderer->renderValue(null, [], [], ['placeholder' => 'none']));
		$this->assertSame('not an address', $renderer->renderValue('not an address', [], [], []));
		$this->assertSame('Invalid &lt;b&gt;', $renderer->renderValue('Invalid <b>', [], [], []));
		$this->assertStringContainsString('href="mailto:alice@example.org"', $renderer->renderValue('Contact alice@example.org', [], [], []));
	}

	public function testEmailRendererEscapesLinkText(): void {
		$html = (new EmailLinkValueRenderer())->renderValue('Alice <alice@example.org>', [], [], []);
		$this->assertStringContainsString('&lt;alice@example.org&gt;', $html);
		$this->assertStringContainsString('href="mailto:alice@example.org"', $html);
	}
}
