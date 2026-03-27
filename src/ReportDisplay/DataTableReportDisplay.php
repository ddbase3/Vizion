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
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Logger\Api\ILogger;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Dto\QueryResult;

class DataTableReportDisplay implements IDisplay {

        private ?array $config = null;
        private ?QueryResult $result = null;

        private $logSql = true;

        public function __construct(
                private readonly IMvcView $view,
                private readonly IQueryService $reportqueryservice,
                private readonly IAssetResolver $assetResolver,
                private readonly ILogger $logger,
                private readonly ILinkTargetService $linkTargetService
        ) {}

        public static function getName(): string {
                return "datatablereportdisplay";
        }

        public function setData($data): void {
                $this->config = is_array($data) ? $data : [];
                if (isset($data['result']) && $data['result'] instanceof QueryResult) {
                        $this->result = $data['result'];
                }
        }

        public function getOutput(string $out = 'html', bool $final = false): string {
                return $out === "json"
                        ? $this->getJsonOutput()
                        : $this->getHtmlOutput();
        }

        private function getJsonOutput(): string {
                $sort = $_GET['sort'] ?? null;
                $direction = strtolower($_GET['direction'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
                $pageSize = max(1, intval($_GET['pageSize'] ?? ($this->config['config']['pageSize'] ?? 10)));
                $page = max(1, intval($_GET['page'] ?? 1));
                $filters = $_GET['filter'] ?? [];

                $fields = $this->config['fields'] ?? [];
                $fieldDefs = [];
                foreach ($fields as $f) {
                        $fieldDefs[$f['alias']] = $f['element'];
                }

                $baseQuery = $this->config;
                $baseQuery['type'] = 'select';

                $where = $this->config['where'] ?? null;
                $whereParams = $where ? [$where] : [];

                foreach ($filters as $alias => $value) {
                        if (!isset($fieldDefs[$alias]) || $value === '') continue;
                        $whereParams[] = [
                                'type' => 'op',
                                'operator' => 'LIKE',
                                'params' => [$fieldDefs[$alias], '%' . $value . '%']
                        ];
                }

                if (count($whereParams) === 1) {
                        $baseQuery['where'] = $whereParams[0];
                } elseif (count($whereParams) > 1) {
                        $baseQuery['where'] = [
                                'type' => 'op',
                                'operator' => 'AND',
                                'params' => $whereParams
                        ];
                }

                $countQuery = $baseQuery;

                $countQuery['fields'] = [];
                foreach ($fields as $f) {
                        $countQuery['fields'][] = [
                                'element' => $f['element'],
                                'alias' => $f['alias']
                        ];
                }

                $countQuery['fields'][] = [
                        'element' => [
                                'type' => 'windowfn',
                                'function' => 'COUNT',
                                'params' => ['*'],
                                'over' => []
                        ],
                        'alias' => '__total__'
                ];

                if (isset($this->config['group'])) {
                        $countQuery['group'] = $this->config['group'];
                }
                if (isset($this->config['having'])) {
                        $countQuery['having'] = $this->config['having'];
                }

                unset($countQuery['limit'], $countQuery['offset'], $countQuery['order_by']);

                $totalResult = $this->reportqueryservice->executeQuery($countQuery);
                $total = $totalResult->rows[0]['__total__'] ?? 0;

                if ($this->logSql) $this->logger->log('Vizion', 'CNT | ' . $totalResult->debugSql);

                $dataQuery = $baseQuery;

                $dataQuery['fields'] = [];
                foreach ($fields as $f) {
                        $dataQuery['fields'][] = [
                                'element' => $f['element'],
                                'alias' => $f['alias']
                        ];
                }

                if ($sort && isset($fieldDefs[$sort])) {
                        $dataQuery['order_by'] = [[
                                'element' => $fieldDefs[$sort],
                                'direction' => $direction
                        ]];
                }

                $totalPages = max(1, ceil($total / $pageSize));
                $offset = ($page - 1) * $pageSize;
                $dataQuery['offset'] = $offset;
                $dataQuery['limit'] = $pageSize;

                if (isset($this->config['group'])) {
                        $dataQuery['group'] = $this->config['group'];
                }
                if (isset($this->config['having'])) {
                        $dataQuery['having'] = $this->config['having'];
                }

                $result = $this->reportqueryservice->executeQuery($dataQuery);
                $rows = $result->rows ?? [];

                if ($this->logSql) $this->logger->log('Vizion', 'RES | ' . $result->debugSql);

                return json_encode([
                        'total' => $total,
                        'page' => $page,
                        'pageSize' => $pageSize,
                        'totalPages' => $totalPages,
                        'data' => $rows
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        private function getHtmlOutput(): string {
                $this->view->setPath(DIR_PLUGIN . 'Vizion');
                $this->view->setTemplate('ReportDisplay/DataTableReportDisplay.php');

                $fields = $this->config['fields'] ?? [];
                $columns = array_map(fn($f) => [
                        'key' => $f['alias'],
                        'label' => $f['config']['label'] ?? $f['alias'],
                        'visible' => $f['config']['visible'] ?? true
                ], $fields);

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

                $this->view->assign('ajaxUrl', $ajaxUrl);
                $this->view->assign('columns', $columns);
                $this->view->assign('config', $this->config);

                $this->view->assign('resolve', fn($src) => $this->assetResolver->resolve($src));

                return $this->view->loadTemplate();
        }

        public function getHelp(): string {
                return "Displays data as a jQuery DataTable using the Vizion ReportDisplay system.";
        }
}
