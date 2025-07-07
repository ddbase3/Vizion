<?php declare(strict_types=1);

namespace Vizion;

use Base3\Api\ICheck;
use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use Base3\Core\Check;

class VizionPlugin implements IPlugin, ICheck {

	public function __construct(private readonly IContainer $container) {}

	// Implementation of IBase

	public static function getName(): string {
		return 'vizionplugin';
	}

	// Implementation of IPlugin

	public function init() {
		$this->container
			->set(self::getName(), $this, IContainer::SHARED);
	}

	// Implementation of ICheck

	public function checkDependencies() {
		return [
			'moduledpageplugin_installed' => $this->container->get('moduledpageplugin') ? 'Ok' : 'moduledpageplugin not installed',
			'clientstackplugin_installed' => $this->container->get('clientstackplugin') ? 'Ok' : 'clientstackplugin not installed',
		];
	}
}
