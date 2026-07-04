<?php declare(strict_types=1);

namespace Vizion\Api;

/**
 * Builds presentation-ready grid column definitions from Vizion vdef fields.
 *
 * This service orchestrates value formatters, value renderers, and column
 * renderers. Display classes receive plain JSON-ready column definitions and do
 * not need to know which renderer implementation created them.
 */
interface IReportCellRendererService {

	/**
	 * Converts vdef fields into ModularGrid column definitions.
	 *
	 * @param array<int,array<string,mixed>> $fields vdef fields
	 * @return array<int,array<string,mixed>> Grid columns, including internal asset metadata
	 */
	public function buildGridColumns(array $fields): array;

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
