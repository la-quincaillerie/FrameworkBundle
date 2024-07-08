<?php

namespace Qce\Component\Blocks\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class BlocksPass implements CompilerPassInterface {
	public function process( ContainerBuilder $container ) {
		if ( ! $container->has( 'wp.blocks_manager' ) ) {
			return;
		}

		$blocks_manager = $container->getDefinition( 'wp.blocks_manager' );

		$blocks = $blocks_manager->getArgument( 0 );
		$blocks = \is_array( $blocks ) ? $blocks : [];

		if (
			\is_string( $blocks_dir = $container->getParameterBag()->resolveValue( '%wp.blocks_dir%' ) )
			&& $container->fileExists( $blocks_dir )
		) {
			$files  = array_filter(
				iterator_to_array( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $blocks_dir, \FilesystemIterator::SKIP_DOTS ) ) ),
				static fn ( $file ) =>  $file instanceof \SplFileInfo && $file->getBasename() === 'block.json'
			);
			$files  = array_map( static fn( $file ) => (string) $file, $files );
			$blocks = array_merge( $blocks, $files );
		}

		if ( $blocks ) {
			$blocks_manager
				->setArgument( 0, $blocks )
				->addTag(
					'wp.hook',
					[
						'name'   => 'init',
						'method' => 'register_blocks',
					]
				);
		} else {
			$container->removeDefinition( 'wp.blocks_manager' );
		}
	}
}
