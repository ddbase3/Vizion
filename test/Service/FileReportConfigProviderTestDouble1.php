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

use Base3\CoreFoundation\Api\IModuleRegistry;
use PHPUnit\Framework\TestCase;
use Base3\Vizion\Service\FileReportConfigProvider;

final class FileReportConfigProviderTestDouble1 implements IModuleRegistry {
			/** @param array<string,string> $paths */
			public function __construct(private readonly array $paths) {}
			public function getModuleNames(): array { return array_keys($this->paths); }
			public function getModulePath(string $name): ?string { return $this->paths[$name] ?? null; }
			public function requireModulePath(string $name): string {
				$path = $this->getModulePath($name);
				if ($path === null) throw new \RuntimeException('Module not found: ' . $name);
				return $path;
			}
		}
