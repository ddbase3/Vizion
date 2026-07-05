<?php declare(strict_types=1);

namespace Vizion\Api;

/**
 * Builds grid column definitions and applies server-side value formatting.
 */
interface IReportCellRendererService {

	/**
	 * Converts vdef fields into ModularGrid column definitions.
	 *
	 * @param array<int,array<string,mixed>> $fields vdef fields
	 * @return array<int,array<string,mixed>> JSON-ready grid columns
	 */
	public function buildGridColumns(array $fields): array;

	/**
	 * Applies configured server-side value renderers to data rows.
	 *
	 * Renderers replace the displayed row value before it is sent to the grid.
	 * Query filtering and sorting already happened before this step.
	 *
	 * @param array<int,array<string,mixed>> $rows Data rows
	 * @param array<int,array<string,mixed>> $fields vdef fields
	 * @return array<int,array<string,mixed>> Rows with formatted display values
	 */
	public function renderGridRows(array $rows, array $fields): array;

	/**
	 * Extracts AssetResolver paths required by renderer browser modules.
	 *
	 * @param array<int,array<string,mixed>> $columns Columns from buildGridColumns()
	 * @return array<int,string> Unique AssetResolver paths
	 */
	public function collectGridRendererAssetPaths(array $columns): array;

	/**
	 * Removes internal server-only metadata before columns are JSON-encoded.
	 *
	 * @param array<int,array<string,mixed>> $columns Columns from buildGridColumns()
	 * @return array<int,array<string,mixed>> Public grid columns
	 */
	public function stripInternalGridColumnMetadata(array $columns): array;
}
