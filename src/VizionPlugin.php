<?php declare(strict_types=1);

namespace Vizion;

use Base3\Api\ICheck;
use Base3\Api\IClassMap;
use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use Base3\Api\IRequest;
use Vizion\Api\IReportConfigProvider;
use Vizion\Api\IReportDisplay;
use Vizion\Service\StaticReportConfigProvider;
use Vizion\ReportDisplay\GeneralReportDisplay;

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
				fn() => new StaticReportConfigProvider(),
				IContainer::SHARED | IContainer::NOOVERWRITE)

			->set(
				IReportDisplay::class,
				fn($c) => new GeneralReportDisplay(
					$c->get(IRequest::class),
					$c->get(IClassMap::class),
					$c->get(IReportConfigProvider::class)),
				IContainer::SHARED | IContainer::NOOVERWRITE);
	}

	// Implementation of ICheck

	public function checkDependencies() {
		return [
			'datahawkplugin_installed' => $this->container->get('datahawkplugin') ? 'Ok' : 'datahawkplugin not installed',
			'clientstackplugin_installed' => $this->container->get('clientstackplugin') ? 'Ok' : 'clientstackplugin not installed',
		];
	}
}
