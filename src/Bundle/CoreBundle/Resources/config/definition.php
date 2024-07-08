<?php

namespace Symfony\Component\Config\Definition\Configurator;

return static function ( DefinitionConfigurator $definition ): void {
	$definition->rootNode()
		->children()
			->arrayNode( 'assets' )
				->children()
			->end() // assets
		->end();
};
