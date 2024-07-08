<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Qce\Component\Blocks\BlocksManager;

return static function ( ContainerConfigurator $container ) {
	$container->parameters()
		->set( 'wp.blocks_dir', '%kernel.project_dir%/build/blocks' );

	$container->services()
		->set( 'wp.blocks_manager', BlocksManager::class )
		->public()
			->arg( 0, abstract_arg( 'List of block paths to register.' ) );
};
