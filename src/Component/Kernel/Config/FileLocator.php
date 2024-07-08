<?php

namespace Qce\Component\Kernel\Config;

use Qce\Component\Kernel\KernelInterface;
use Symfony\Component\Config\FileLocator as BaseFileLocator;

class FileLocator extends BaseFileLocator {

	public function __construct( private KernelInterface $kernel ) {
		parent::__construct();
	}

	/**
	 * @return string|string[]
	 */
	public function locate( string $file, ?string $current_path = null, bool $first = true ): string|array {
		if ( isset( $file[0] ) && '@' === $file[0] ) {
			$resource = $this->kernel->locate_resource( $file );

			return $first ? $resource : [ $resource ];
		}

		return parent::locate( $file, $current_path, $first );
	}
}
