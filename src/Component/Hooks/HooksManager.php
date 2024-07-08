<?php

namespace Qce\Component\Hooks;

use Psr\Container\ContainerInterface;

class HooksManager {
	/**
	 * @param array{string, array{string, string}, int, int}[] $hooks
	 */
	public function __construct( private array $hooks, ContainerInterface $services ) {

		foreach ( $this->hooks as &$hook ) {
			[$service, $method] = $hook[1];
			$hook[1]            = static fn( ...$args ) => $services->get( $service )->$method( ...$args );
		}
	}

	public function register_hooks(): void {
		foreach ( $this->hooks as $hook ) {
			\add_filter( ...$hook ); // @phpstan-ignore function.notFound
		}
	}
}
