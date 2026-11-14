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

namespace Base3\Vizion;

use Base3\CoreFoundation\Api\ICheck;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Api\IContainer;
use Base3\CoreFoundation\Api\IPlugin;
use Base3\CoreFoundation\Api\IRequest;
use Base3\CoreFoundation\Translation\Api\ITranslation;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\ResourceFoundation\Api\IQueryService;
use Base3\ResourceFoundation\Api\IReportingScopeRegistry;
use Base3\Vizion\Api\IReportConfigProvider;
use Base3\Vizion\Api\IReportDisplay;
use Base3\Vizion\Service\CompositeReportConfigProvider;
use Base3\Vizion\ReportDisplay\GeneralReportDisplay;
use Base3\Vizion\Api\IReportFilterService;
use Base3\Vizion\Api\IReportDataService;
use Base3\Vizion\Api\IReportTreeFilterService;
use Base3\Vizion\Api\IReportCellRendererService;
use Base3\Vizion\Api\IReportChartService;
use Base3\Vizion\Filter\ReportFilterService;
use Base3\Vizion\TreeFilter\ReportTreeFilterService;
use Base3\Vizion\Renderer\ReportCellRendererService;
use Base3\Vizion\Chart\ReportChartService;
use Base3\Vizion\Service\ModularGridReportQueryBuilder;
use Base3\Vizion\Service\ReportDataService;

class VizionPlugin implements IPlugin, ICheck {

	public function __construct(private readonly IContainer $container) {}

	// Implementation of IBase

	public static function getName(): string {
		return 'vizionplugin';
	}

	// Implementation of IPlugin

	public function init() {
		$this->container
			->set(self::getName(), $this, IContainer::SHARED)

			->set(
				IReportConfigProvider::class,
				fn($c) => new CompositeReportConfigProvider(
					$c->get(IClassMap::class),
					$c->get(IReportingScopeRegistry::class)
				),
				IContainer::SHARED | IContainer::NOOVERWRITE)

			->set(
				IReportFilterService::class,
				fn($c) => new ReportFilterService($c->get(IClassMap::class)),
				IContainer::SHARED | IContainer::NOOVERWRITE)

			->set(
				IReportTreeFilterService::class,
				fn($c) => new ReportTreeFilterService($c->get(IQueryService::class)),
				IContainer::SHARED | IContainer::NOOVERWRITE)

			->set(
				IReportCellRendererService::class,
				fn($c) => new ReportCellRendererService($c->get(IClassMap::class)),
				IContainer::SHARED | IContainer::NOOVERWRITE)

			->set(
				ModularGridReportQueryBuilder::class,
				fn($c) => new ModularGridReportQueryBuilder(
					$c->get(IReportFilterService::class),
					$c->get(IReportTreeFilterService::class)
				),
				IContainer::SHARED | IContainer::NOOVERWRITE)

			->set(
				IReportDataService::class,
				fn($c) => new ReportDataService(
					$c->get(IClassMap::class),
					$c->get(IReportingScopeRegistry::class),
					$c->get(IReportConfigProvider::class),
					$c->get(IQueryService::class),
					$c->get(IReportFilterService::class),
					$c->get(IReportTreeFilterService::class),
					$c->get(ModularGridReportQueryBuilder::class),
					$c->get(ILogger::class)
				),
				IContainer::SHARED | IContainer::NOOVERWRITE)

			->set(
				IReportChartService::class,
				fn($c) => new ReportChartService(
					$c->get(IClassMap::class),
					$c->get(IReportFilterService::class),
					$c->get(IReportCellRendererService::class)),
				IContainer::SHARED | IContainer::NOOVERWRITE)

			->set(
				IReportDisplay::class,
				fn($c) => new GeneralReportDisplay(
					$c->get(IRequest::class),
					$c->get(IClassMap::class),
					$c->get(IReportConfigProvider::class),
					$c->get(ITranslation::class)),
				IContainer::SHARED | IContainer::NOOVERWRITE);
	}

	// Implementation of ICheck

	public function checkDependencies() {
		return [
			'clientstackplugin_installed' => $this->container->get('clientstackplugin') ? 'Ok' : 'clientstackplugin not installed',
			'resourcefoundationplugin_installed' => $this->container->get('resourcefoundationplugin') ? 'Ok' : 'resourcefoundationplugin not installed'
		];
	}
}
