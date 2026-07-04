<?php declare(strict_types=1);

namespace Vizion\Api;

use Base3\Api\IBase;

/**
 * Defines a Chart.js presentation renderer for Vizion chart reports.
 *
 * Chart renderers translate semantic Vizion chart configuration into a
 * browser-side Chart.js configuration. They do not fetch report rows, do not
 * apply filters, and do not build ResourceFoundation query JSON. Their only
 * responsibility is chart-presentation metadata such as chart type, default
 * options, dataset settings, and client-side renderer keys.
 *
 * Implementations are discoverable through IClassMap by this interface and
 * IBase::getName(). getName() must be the globally unique technical class name,
 * usually the full lowercase class name such as barchartrenderer. The short
 * vdef chart type is returned by getChartType().
 *
 * Project plugins may provide additional chart renderers, for example
 * project-specific Chart.js defaults or future non-Chart.js renderers, without
 * changing Vizion core code.
 */
interface IReportChartRenderer extends IBase {

	/**
	 * Returns the canonical short chart type used in vdefs.
	 *
	 * Examples are bar, line, pie, and doughnut. This value is intentionally
	 * separate from IBase::getName() so class map names remain globally unique.
	 *
	 * @return string Lowercase vdef chart type
	 */
	public function getChartType(): string;

	/**
	 * Returns alternate vdef chart names accepted for this renderer.
	 *
	 * @return array<int,string> Lowercase or case-insensitive alias names
	 */
	public function getAliases(): array;

	/**
	 * Builds client-side Chart.js presentation configuration.
	 *
	 * The returned array must be JSON-serializable and must not contain callables.
	 * It is merged with data labels and datasets in the browser-side Vizion chart
	 * module. Implementations may pass through safe Chart.js options from the
	 * vdef, but should keep business data, query logic, and filters outside of
	 * the renderer.
	 *
	 * @param array<string,mixed> $chartConfig Chart section from the report vdef
	 * @param array<int,array<string,mixed>> $measures Normalized measure configs
	 * @param array<string,mixed> $dimension Normalized dimension config
	 * @return array<string,mixed> Client-side chart config
	 */
	public function buildClientConfig(array $chartConfig, array $measures, array $dimension): array;
}
