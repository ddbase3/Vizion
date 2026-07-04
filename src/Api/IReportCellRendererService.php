<?php declare(strict_types=1);

namespace Vizion\Api;

/**
 * Builds presentation-ready grid column definitions from Vizion vdef fields.
 *
 * This service is the PHP-side orchestrator for value formatters and cell
 * renderers. It keeps display classes from containing renderer selection logic
 * and prepares columns that can later also be consumed by chart or export
 * presentations.
 */
interface IReportCellRendererService {

	/**
	 * Converts vdef fields into ModularGrid column definitions.
	 *
	 * @param array<int,array<string,mixed>> $fields vdef fields
	 * @return array<int,array<string,mixed>> JSON-serializable grid columns
	 */
	public function buildGridColumns(array $fields): array;
}
