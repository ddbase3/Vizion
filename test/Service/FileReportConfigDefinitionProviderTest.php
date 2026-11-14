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

namespace Base3\Vizion\Test\Service;

use PHPUnit\Framework\TestCase;
use Base3\Vizion\Service\FileReportConfigDefinitionProvider;

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
