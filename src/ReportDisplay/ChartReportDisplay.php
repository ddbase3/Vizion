<?php declare(strict_types=1);

namespace Vizion\ReportDisplay;

use Base3\Api\IAssetResolver;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use ResourceFoundation\Api\IQueryService;
use Vizion\Api\IReportChartService;
use Vizion\Api\IReportFilterService;

final class ChartReportDisplay implements IDisplay {

	/** @var array<string,mixed>|null */
	private ?array $config = null;

	public function __construct(
		private readonly IMvcView $view,
		private readonly IRequest $request,
		private readonly IQueryService $queryService,
		private readonly ILinkTargetService $linkTargetService,
		private readonly IAssetResolver $assetResolver,
		private readonly IReportFilterService $reportFilterService,
		private readonly IReportChartService $reportChartService
	) {}

	public static function getName(): string {
		return 'chartreportdisplay';
	}

	public function setData($data): void {
		$this->config = is_array($data) ? $data : [];
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		return strtolower($out) === 'json'
			? $this->getJsonOutput($final)
			: $this->getHtmlOutput();
	}

	private function getHtmlOutput(): string {
		$config = $this->getConfig();
		$fields = $this->getFields();
		$report = (string) ($config['report'] ?? '');
		$ajaxUrl = $this->linkTargetService->getLink([
			'name' => 'generalreportdisplay',
			'out' => 'json'
		], [
			'report' => $report
		]);

		$this->view->setPath(DIR_PLUGIN . 'Vizion');
		$this->view->setTemplate('ReportDisplay/ChartReportDisplay.php');
		$this->view->assign('ajaxUrl', $ajaxUrl);
		$this->view->assign('config', $config);
		$this->view->assign('filterFields', $this->reportFilterService->buildGridFilterFields($fields));
		$this->view->assign('filterInitialValues', $this->reportFilterService->buildInitialFilterValues($fields));
		$this->view->assign('chartConfig', $this->reportChartService->buildClientConfig($config, $fields));
		$this->view->assign('modulargridCssUrl', $this->assetResolver->resolve('plugin/ClientStack/assets/modulargrid/styles/modulargrid.css'));
		$this->view->assign('modulargridJsUrl', $this->assetResolver->resolve('plugin/ClientStack/assets/modulargrid/index.js'));
		$this->view->assign('chronoPickerCssUrl', $this->assetResolver->resolve('plugin/ClientStack/assets/chronopicker/styles/chronopicker.css'));
		$this->view->assign('chronoPickerJsUrl', $this->assetResolver->resolve('plugin/ClientStack/assets/chronopicker/index.js'));
		$this->view->assign('chartJsUrl', $this->assetResolver->resolve('plugin/ClientStack/assets/chart/chart.js'));
		$this->view->assign('filterControlsJsUrl', $this->assetResolver->resolve('plugin/Vizion/assets/js/vizion-report-filter-controls.js'));
		$this->view->assign('cellRenderersJsUrl', $this->assetResolver->resolve('plugin/Vizion/assets/js/vizion-report-cell-renderers.js'));
		$this->view->assign('chartToolsJsUrl', $this->assetResolver->resolve('plugin/Vizion/assets/js/vizion-report-chart.js'));
		$this->view->assign('chartCssUrl', $this->assetResolver->resolve('plugin/Vizion/assets/css/vizion-report-chart.css'));

		return $this->view->loadTemplate();
	}

	private function getJsonOutput(bool $final = false): string {
		try {
			$response = $this->buildJsonResponse();
		}
		catch(\Throwable $exception) {
			$response = [
				'ok' => false,
				'error' => $exception->getMessage(),
				'mode' => 'chart',
				'labels' => [],
				'datasets' => [],
				'rows' => [],
				'total' => 0
			];
		}

		if($final && !headers_sent()) {
			header('Content-Type: application/json; charset=utf-8');
		}

		return (string) json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	/** @return array<string,mixed> */
	private function buildJsonResponse(): array {
		$payload = $this->request->getJsonBody();
		$payload = is_array($payload) ? $payload : [];
		$request = $this->normalizeRequest($payload);
		$query = $this->reportChartService->buildQuery($this->getConfig(), $this->getFields(), $request['filters']);
		$result = $this->queryService->executeQuery($query);
		$response = $this->reportChartService->buildPayload($this->getConfig(), $result);

		$response['appliedFilters'] = $request['filters'];

		return $response;
	}

	/** @param array<string,mixed> $payload @return array<string,mixed> */
	private function normalizeRequest(array $payload): array {
		$filters = $this->reportFilterService->normalizeFilters($payload['filters'] ?? null, $this->getFields());

		return [
			'filters' => $filters
		];
	}

	/** @return array<string,mixed> */
	private function getConfig(): array {
		return $this->config ?? [];
	}

	/** @return array<int,array<string,mixed>> */
	private function getFields(): array {
		$fields = $this->getConfig()['fields'] ?? [];
		return is_array($fields) ? $fields : [];
	}

	public function getHelp(): string {
		return 'Displays a Vizion report as a Chart.js chart with shared Vizion filters.';
	}
}
