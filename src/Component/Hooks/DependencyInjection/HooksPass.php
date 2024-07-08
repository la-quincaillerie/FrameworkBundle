<?php

namespace Qce\Component\Hooks\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class HooksPass implements CompilerPassInterface {

	public function process( ContainerBuilder $container ): void {
		if ( ! $container->hasDefinition( 'wp.hooks_manager' ) ) {
			return;
		}

		$tagged_services = $container->findTaggedServiceIds( 'wp.hook' );

		$hooks = [];
		foreach ( $tagged_services as $id => $tags ) {
			foreach ( $tags as $tag ) {
				$hooks[] = [
					$tag['name'],
					[ $id, $tag['method'] ?? '__invoke' ],
					$tag['priority'] ?? 10,
					$tag['accepted_args'] ?? 1,
				];
			}
		}

		if ( $hooks ) {
			$container->getDefinition( 'wp.hooks_manager' )->setArgument( 0, $hooks );
		} else {
			$container->removeDefinition( 'wp.hooks_manager' );
		}
	}
}
