<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of Vizion for BASE3 Framework.
 *
 * Vizion extends the BASE3 framework with modular, visual display
 * components for reports and structured data.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/vizion
 * https://github.com/ddbase3/Vizion
 **********************************************************************/

namespace Vizion\Api;

interface IReportTreeFilterService {

	/**
	 * @param array<string,mixed> $config
	 * @return array<int,array<string,mixed>>
	 */
	public function buildGridTreeFilters(array $config): array;

	/**
	 * @param mixed $payload
	 * @param array<string,mixed> $config
	 * @return array<string,string>
	 */
	public function normalizeTreeFilters(mixed $payload, array $config): array;

	/**
	 * @param array<string,mixed> $config
	 * @return array<int,array<string,mixed>>
	 */
	public function loadTree(array $config, string $key): array;

	/**
	 * @param array<string,string> $treeFilters
	 * @param array<string,mixed> $config
	 * @return array<string,mixed>|null
	 */
	public function buildFilterWhere(array $treeFilters, array $config): ?array;
}
