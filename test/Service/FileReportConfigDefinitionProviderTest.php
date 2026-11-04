<?php declare(strict_types=1);

namespace Vizion\Test\Service;

use PHPUnit\Framework\TestCase;
use Vizion\Service\FileReportConfigDefinitionProvider;

final class FileReportConfigDefinitionProviderTest extends TestCase {

	public function testBuiltInProviderPublishesVizionScopeAndStableName(): void {
		$provider = new FileReportConfigDefinitionProvider();
		$this->assertSame('vizionfilereportconfigdefinitionprovider', $provider::getName());
		$this->assertSame('vizion', $provider->getScope());
	}

	public function testBundledExampleIsValidAndMarkedEnabled(): void {
		$provider = new FileReportConfigDefinitionProvider();
		$definitions = $provider->getDefinitions();
		$this->assertArrayHasKey('example', $definitions);
		$this->assertTrue($definitions['example']['enabled']);
		$this->assertSame('datatablereportdisplay', $definitions['example']['definition']['display']);
		$this->assertSame('repository_name', $definitions['example']['definition']['fields'][0]['alias']);
		$this->assertSame('git_repository', $definitions['example']['definition']['table']);
	}
}
