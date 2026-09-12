<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of Vizion for BASE3 Framework.
 *
 * Vizion extends the BASE3 framework with modular, visual display
 * components for reports and structured data. It provides flexible
 * renderers such as interactive tables and charts, driven by
 * declarative configuration and seamlessly integrated into BASE3 pages.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/vizion
 * https://github.com/ddbase3/Vizion
 **********************************************************************/

namespace Vizion\ReportDisplay;

use Base3\Api\IAssetResolver;
use Base3\Api\IClassMap;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Logger\Api\ILogger;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Api\IReportExporter;
use ResourceFoundation\Dto\QueryResult;
use Vizion\Api\IReportCellRendererService;
use Vizion\Api\IReportFilterService;
use Vizion\Service\ModularGridReportQueryBuilder;

class ModularGridReportDisplay implements IDisplay {

	private ?array $config = null;
	private ?QueryResult $result = null;

	private bool $logSql = true;

	private array $translations = [];

	public function __construct(
		private readonly IMvcView $view,
		private readonly IRequest $request,
		private readonly IQueryService $reportqueryservice,
		private readonly ILogger $logger,
		private readonly ILinkTargetService $linkTargetService,
		private readonly IAssetResolver $assetResolver,
		private readonly IClassMap $classmap,
		private readonly IReportFilterService $reportFilterService,
		private readonly IReportCellRendererService $reportCellRendererService,
		private readonly ModularGridReportQueryBuilder $queryBuilder
	) {}

	public static function getName(): string {
		return 'modulargridreportdisplay';
	}

	public function setData($data): void {
		$this->config = is_array($data) ? $data : [];

		if(isset($data['result']) && $data['result'] instanceof QueryResult) {
			$this->result = $data['result'];
		}
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$this->loadTranslations();
		$out = strtolower($out);

		if($out === 'json') {
			return $this->getJsonOutput($final);
		}

		if($out === 'export') {
			return $this->getExportOutput($final);
		}

		return $this->getHtmlOutput();
	}

	private function getJsonOutput(bool $final = false): string {
		try {
			$response = $this->buildJsonResponse();
		}
		catch(\Throwable $exception) {
			$response = [
				'ok' => false,
				'error' => $exception->getMessage(),
				'data' => [],
				'groups' => [],
				'page' => 1,
				'pageSize' => 0,
				'total' => 0,
				'totalPages' => 0,
				'hasMore' => false,
				'nextCursor' => null
			];
		}

		if($final && !headers_sent()) {
			header('Content-Type: application/json; charset=utf-8');
		}

		return (string)json_encode(
			$response,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildJsonResponse(): array {
		$payload = $this->request->getJsonBody();
		if(!is_array($payload)) {
			$payload = [];
		}

		$config = $this->config ?? [];
		$request = $this->queryBuilder->normalizeRequest($payload, $config);
		$fields = $this->getFields();
		$baseQuery = $this->queryBuilder->buildBaseQuery($config, $request);
		$total = $this->loadTotal($baseQuery);
		$pageSize = $request['pageSize'];
		$page = $request['page'];
		$totalPages = $pageSize > 0 ? (int)ceil($total / $pageSize) : 0;

		$dataQuery = $this->queryBuilder->buildDataQuery($config, $baseQuery, $request);
		$result = $this->reportqueryservice->executeQuery($dataQuery);

		if($this->logSql) {
			$this->logger->log('Vizion', 'MODULARGRID RES | ' . $result->debugSql);
		}

		$rows = [];
		$offset = (($page - 1) * $pageSize);

		foreach(($result->rows ?? []) as $index => $row) {
			if(!is_array($row)) {
				continue;
			}

			$row['__row_key'] = $this->buildRowKey($row, $offset + $index + 1);
			$rows[] = $row;
		}

		$rows = $this->reportCellRendererService->renderGridRows($rows, $fields);

		return [
			'mode' => 'page',
			'data' => $rows,
			'groups' => [],
			'page' => $page,
			'pageSize' => $pageSize,
			'total' => $total,
			'totalPages' => $totalPages,
			'hasMore' => ($offset + $pageSize) < $total,
			'nextCursor' => null,
			'appliedSearch' => $request['search'],
			'appliedSort' => [$request['sort']],
			'appliedFilters' => $request['filters'],
			'appliedGroup' => [],
		];
	}

	private function getExportOutput(bool $final): string {
		try {
			$payload = $this->request->getJsonBody();
			if(!is_array($payload)) {
				$payload = [];
			}

			$config = $this->config ?? [];
			$exportConfig = $this->getExportConfig();
			$exporterName = is_scalar($payload['exporter'] ?? null) ? trim((string)$payload['exporter']) : '';
			$allowedExporters = $this->getConfiguredExporterNames($exportConfig);

			if($exporterName === '' || !in_array($exporterName, $allowedExporters, true)) {
				throw new \RuntimeException($this->t('export_invalid_exporter', 'The requested exporter is not configured for this report.'));
			}

			$exporter = $this->classmap->getInstanceByInterfaceName(IReportExporter::class, $exporterName);
			if(!$exporter instanceof IReportExporter) {
				throw new \RuntimeException($this->t('export_unavailable_exporter', 'The requested exporter is not available.'));
			}

			$scope = is_scalar($payload['scope'] ?? null) ? strtolower(trim((string)$payload['scope'])) : 'filtered';
			if(!in_array($scope, ['selected', 'filtered', 'all'], true)) {
				throw new \RuntimeException($this->t('export_invalid_scope', 'The requested export scope is invalid.'));
			}

			$fieldAliases = $this->resolveExportFieldAliases($payload['fields'] ?? null, $exportConfig);
			if($fieldAliases === []) {
				throw new \RuntimeException($this->t('export_no_fields', 'Select at least one field for export.'));
			}

			$requestPayload = isset($payload['request']) && is_array($payload['request']) ? $payload['request'] : [];
			$request = $scope === 'all'
				? $this->queryBuilder->normalizeRequest([], $config)
				: $this->queryBuilder->normalizeRequest($requestPayload, $config);
			$baseQuery = $this->queryBuilder->buildBaseQuery($config, $request);

			if($scope === 'selected') {
				$selectedRowIds = $this->normalizeSelectedRowIds($payload['selectedRowIds'] ?? null);
				if($selectedRowIds === []) {
					throw new \RuntimeException($this->t('export_no_selection', 'Select at least one row for this export.'));
				}

				$allRequest = $this->queryBuilder->normalizeRequest([], $config);
				$selectionBaseQuery = $this->queryBuilder->buildBaseQuery($config, $allRequest);
				$query = $this->queryBuilder->buildDataQuery($config, $selectionBaseQuery, $allRequest, null, false);
				$result = $this->reportqueryservice->executeQuery($query);
				$result = $this->filterSelectedResult($result, $selectedRowIds);
				$result = $this->projectExportResult($result, $fieldAliases);
			}
			else {
				$query = $this->queryBuilder->buildDataQuery($config, $baseQuery, $request, $fieldAliases, false);
				$result = $this->reportqueryservice->executeQuery($query);
				$result = $this->projectExportResult($result, $fieldAliases);
			}

			if($this->logSql) {
				$this->logger->log('Vizion', 'MODULARGRID EXPORT | ' . $result->debugSql);
			}

			$exporter->setResult($result);
			$content = $exporter->toString();
			$fileName = $this->buildExportFileName($exporter);

			if($final && !headers_sent()) {
				header('Content-Type: ' . $exporter->getMimeType());
				header('Content-Disposition: attachment; filename="' . $fileName . '"');
				header('X-Content-Type-Options: nosniff');
			}

			return $content;
		}
		catch(\Throwable $exception) {
			if($final && !headers_sent()) {
				header('Content-Type: text/plain; charset=utf-8');
				http_response_code(400);
			}

			return $exception->getMessage();
		}
	}

	private function getHtmlOutput(): string {
		$this->view->setPath(DIR_PLUGIN . 'Vizion');
		$this->view->setTemplate('ReportDisplay/ModularGridReportDisplay.php');

		$fields = $this->getFields();
		$columns = $this->reportCellRendererService->buildGridColumns($fields);
		$columns = $this->reportCellRendererService->stripInternalGridColumnMetadata($columns);
		$filterFields = $this->reportFilterService->buildGridFilterFields($fields);
		$filterInitialValues = $this->reportFilterService->buildInitialFilterValues($fields);
		$report = $this->config['report'] ?? '';
		$ajaxUrl = $this->linkTargetService->getLink(
			[
				'name' => 'generalreportdisplay',
				'out' => 'json'
			],
			[
				'report' => $report
			]
		);
		$exportUrl = $this->linkTargetService->getLink(
			[
				'name' => 'generalreportdisplay',
				'out' => 'export'
			],
			[
				'report' => $report
			]
		);

		$this->view->assign('ajaxUrl', $ajaxUrl);
		$this->view->assign('exportUrl', $exportUrl);
		$this->view->assign('exportOptions', $this->buildExportOptions());
		$this->view->assign('columns', $columns);
		$this->view->assign('filterFields', $filterFields);
		$this->view->assign('filterInitialValues', $filterInitialValues);
		$this->view->assign('config', $this->config ?? []);
		$this->view->assign('modulargridCssUrl', $this->assetResolver->resolve('plugin/ClientStack/assets/modulargrid/styles/modulargrid.css'));
		$this->view->assign('modulargridJsUrl', $this->assetResolver->resolve('plugin/ClientStack/assets/modulargrid/index.js'));
		$this->view->assign('modulargridExportPluginJsUrl', $this->assetResolver->resolve('plugin/ClientStack/assets/modulargrid/plugins/ExportPlugin.js'));
		$this->view->assign('chronoPickerCssUrl', $this->assetResolver->resolve('plugin/ClientStack/assets/chronopicker/styles/chronopicker.css'));
		$this->view->assign('chronoPickerJsUrl', $this->assetResolver->resolve('plugin/ClientStack/assets/chronopicker/index.js'));
		$this->view->assign('filterControlsJsUrl', $this->assetResolver->resolve('plugin/Vizion/assets/js/vizion-report-filter-controls.js'));
		$this->view->assign('cellRenderersJsUrl', $this->assetResolver->resolve('plugin/Vizion/assets/js/vizion-report-cell-renderers.js'));
		$this->view->assign('translations', $this->translations);

		return $this->view->loadTemplate();
	}

	/**
	 * @param array<string, mixed> $baseQuery
	 */
	private function loadTotal(array $baseQuery): int {
		$countQuery = $this->queryBuilder->buildCountQuery($this->config ?? [], $baseQuery);
		$result = $this->reportqueryservice->executeQuery($countQuery);
		$total = (int)($result->rows[0]['__total__'] ?? 0);

		if($this->logSql) {
			$this->logger->log('Vizion', 'MODULARGRID CNT | ' . $result->debugSql);
		}

		return $total;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function getFields(): array {
		return $this->queryBuilder->getFields($this->config ?? []);
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function buildRowKey(array $row, int $fallback): string {
		$normalized = [];

		foreach($row as $key => $value) {
			$key = (string)$key;
			if(str_starts_with($key, '__')) {
				continue;
			}

			if(is_scalar($value) || $value === null) {
				$normalized[$key] = $value;
				continue;
			}

			$normalized[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		ksort($normalized);
		$encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if(!is_string($encoded) || $encoded === '') {
			$encoded = 'fallback-' . (string)$fallback;
		}

		return 'report-row-' . substr(sha1($encoded), 0, 20);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function getExportConfig(): array {
		$export = $this->config['export'] ?? [];
		return is_array($export) ? $export : [];
	}

	/**
	 * @param array<string, mixed> $exportConfig
	 * @return array<int, string>
	 */
	private function getConfiguredExporterNames(array $exportConfig): array {
		$names = [];

		foreach(($exportConfig['exporters'] ?? []) as $name) {
			$name = trim((string)$name);
			if($name === '' || in_array($name, $names, true)) {
				continue;
			}
			$names[] = $name;
		}

		return $names;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildExportOptions(): array {
		$exportConfig = $this->getExportConfig();
		$exporters = [];

		foreach($this->getConfiguredExporterNames($exportConfig) as $name) {
			$exporter = $this->classmap->getInstanceByInterfaceName(IReportExporter::class, $name);
			if(!$exporter instanceof IReportExporter) {
				continue;
			}

			$exporters[] = [
				'name' => $name,
				'label' => $this->getExporterLabel($name)
			];
		}

		if($exporters === []) {
			return [];
		}

		$fields = [];
		foreach($this->getConfiguredExportFields($exportConfig) as $field) {
			$alias = (string)$field['alias'];
			$fieldConfig = isset($field['config']) && is_array($field['config']) ? $field['config'] : [];

			$fields[] = [
				'key' => $alias,
				'label' => (string)($fieldConfig['label'] ?? $alias)
			];
		}

		if($fields === []) {
			return [];
		}

		$defaultFields = $this->resolveExportFieldAliases($exportConfig['defaultFields'] ?? null, $exportConfig);

		return [
			'buttonLabel' => $this->t('export', 'Export'),
			'exporters' => $exporters,
			'defaultExporter' => (string)($exporters[0]['name'] ?? ''),
			'scopes' => [
				['key' => 'selected', 'label' => $this->t('export_scope_selected', 'Current selection')],
				['key' => 'filtered', 'label' => $this->t('export_scope_filtered', 'Current filtering')],
				['key' => 'all', 'label' => $this->t('export_scope_all', 'All data')],
			],
			'defaultScope' => 'filtered',
			'fields' => $fields,
			'defaultFields' => $defaultFields,
			'labels' => [
				'formatTab' => $this->t('export_tab_format', 'Format'),
				'dataTab' => $this->t('export_tab_data', 'Data'),
				'fieldsTab' => $this->t('export_tab_fields', 'Fields'),
				'run' => $this->t('export_run', 'Export'),
				'working' => $this->t('export_working', 'Exporting ...'),
				'failed' => $this->t('export_failed', 'Export failed.'),
				'noFields' => $this->t('export_no_fields', 'Select at least one field for export.'),
				'noSelection' => $this->t('export_no_selection', 'Select at least one row for this export.'),
			]
		];
	}

	/**
	 * @param mixed $requested
	 * @param array<string, mixed> $exportConfig
	 * @return array<int, string>
	 */
	private function resolveExportFieldAliases(mixed $requested, array $exportConfig): array {
		$exportableFields = $this->getConfiguredExportFields($exportConfig);
		$fieldDefs = $this->queryBuilder->buildFieldDefs($exportableFields);
		$requestedFields = is_array($requested) ? $requested : [];

		if($requestedFields === []) {
			$requestedFields = is_array($exportConfig['defaultFields'] ?? null)
				? $exportConfig['defaultFields']
				: [];
		}

		return $this->queryBuilder->normalizeFieldAliases($requestedFields, $fieldDefs);
	}

	/**
	 * Export fields are an explicit report-level whitelist.
	 *
	 * The configured names reference aliases from the normal report fields.
	 * This keeps query definitions in one place while allowing an export to use
	 * a hidden/raw field instead of the field rendered in the grid. Value
	 * renderers are UI-only and are never applied to exported QueryResult data.
	 *
	 * @param array<string, mixed> $exportConfig
	 * @return array<int, array<string, mixed>>
	 */
	private function getConfiguredExportFields(array $exportConfig): array {
		$fieldsByAlias = [];
		foreach($this->getFields() as $field) {
			$alias = trim((string)($field['alias'] ?? ''));
			if($alias === '' || str_starts_with($alias, '__') || !isset($field['element'])) {
				continue;
			}
			$fieldsByAlias[$alias] = $field;
		}

		$result = [];
		$seen = [];
		$configuredFields = is_array($exportConfig['fields'] ?? null) ? $exportConfig['fields'] : [];

		foreach($configuredFields as $configuredField) {
			if(!is_scalar($configuredField)) {
				continue;
			}

			$alias = trim((string)$configuredField);
			if($alias === '' || isset($seen[$alias]) || !isset($fieldsByAlias[$alias])) {
				continue;
			}

			$seen[$alias] = true;
			$result[] = $fieldsByAlias[$alias];
		}

		return $result;
	}

	/**
	 * @param mixed $value
	 * @return array<int, string>
	 */
	private function normalizeSelectedRowIds(mixed $value): array {
		if(!is_array($value)) {
			return [];
		}

		$result = [];
		foreach($value as $rowId) {
			if(!is_scalar($rowId)) {
				continue;
			}

			$rowId = trim((string)$rowId);
			if($rowId === '' || in_array($rowId, $result, true)) {
				continue;
			}
			$result[] = $rowId;
		}

		return $result;
	}

	/**
	 * @param array<int, string> $selectedRowIds
	 */
	private function filterSelectedResult(QueryResult $result, array $selectedRowIds): QueryResult {
		$selected = array_fill_keys($selectedRowIds, true);
		$rows = [];

		foreach($result->rows as $index => $row) {
			if(!is_array($row)) {
				continue;
			}

			$rowKey = $this->buildRowKey($row, $index + 1);
			if(isset($selected[$rowKey])) {
				$rows[] = $row;
			}
		}

		return new QueryResult(
			$result->columns,
			$rows,
			$result->debugSql,
			$result->sensitive,
			$result->affectedRows,
			$result->insertId
		);
	}

	/**
	 * @param array<int, string> $fieldAliases
	 */
	private function projectExportResult(QueryResult $result, array $fieldAliases): QueryResult {
		$fieldsByAlias = [];
		foreach($this->getFields() as $field) {
			$alias = (string)($field['alias'] ?? '');
			if($alias !== '') {
				$fieldsByAlias[$alias] = $field;
			}
		}

		$columnsByName = [];
		foreach($result->columns as $column) {
			if(!is_array($column)) {
				continue;
			}
			$name = (string)($column['name'] ?? '');
			if($name !== '') {
				$columnsByName[$name] = $column;
			}
		}

		$columns = [];
		foreach($fieldAliases as $alias) {
			$column = $columnsByName[$alias] ?? ['name' => $alias];
			$fieldConfig = isset($fieldsByAlias[$alias]['config']) && is_array($fieldsByAlias[$alias]['config'])
				? $fieldsByAlias[$alias]['config']
				: [];
			$column['name'] = $alias;
			$column['label'] = (string)($fieldConfig['label'] ?? $alias);
			$columns[] = $column;
		}

		$rows = [];
		foreach($result->rows as $row) {
			if(!is_array($row)) {
				continue;
			}

			$projected = [];
			foreach($fieldAliases as $alias) {
				$projected[$alias] = $row[$alias] ?? null;
			}
			$rows[] = $projected;
		}

		return new QueryResult(
			$columns,
			$rows,
			$result->debugSql,
			$result->sensitive,
			$result->affectedRows,
			$result->insertId
		);
	}

	private function getExporterLabel(string $name): string {
		return match($name) {
			'csvreportexporter' => 'CSV',
			'excelhtmlreportexporter' => 'Excel HTML',
			'xlsxreportexporter' => 'Excel',
			'jsonreportexporter' => 'JSON',
			'htmlpagereportexporter' => 'HTML',
			'htmltablereportexporter' => 'HTML Table',
			'datatablereportexporter' => 'Data Table',
			'barchartreportexporter' => 'Bar Chart',
			'piechartreportexporter' => 'Pie Chart',
			default => $name,
		};
	}

	private function buildExportFileName(IReportExporter $exporter): string {
		$report = (string)($this->config['report'] ?? 'report');
		$base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $report) ?: 'report';
		return $base . '.' . ltrim($exporter->getFileExtension(), '.');
	}

	private function loadTranslations(): void {
		$this->view->setPath(DIR_PLUGIN . 'Vizion');
		$this->view->loadBricks('Display');

		$translations = $this->view->getBricks('vizion_report_display');
		$this->translations = is_array($translations) ? $translations : [];
	}

	private function t(string $key, string $fallback, mixed ...$values): string {
		$text = trim((string)($this->translations[$key] ?? ''));
		if($text === '') {
			$text = $fallback;
		}

		return $values === [] ? $text : vsprintf($text, $values);
	}

	public function getHelp(): string {
		$this->loadTranslations();
		return $this->t('help_modular_grid', 'Displays query results as a ModularGrid table using the Vizion ReportDisplay system.');
	}
}
