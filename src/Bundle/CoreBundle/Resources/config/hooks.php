<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Qce\Component\Hooks\Attribute\WPHook;
use Qce\Component\Hooks\HooksManager;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

return static function ( ContainerConfigurator $container, ContainerBuilder $builder ): void {
	$container->services()
		->set( 'wp.hooks_manager', HooksManager::class )
		->args(
			[
				abstract_arg( 'List of hooks to register.' ),
				tagged_locator( 'wp.hook' ),
			]
		)
		->public();

	$builder->registerAttributeForAutoconfiguration(
		WPHook::class,
		// @phpstan-ignore-next-line argument.type
		static function ( ChildDefinition $definition, WPHook $hook, \ReflectionMethod|\ReflectionClass $reflector ): void {
			if ( $reflector instanceof \ReflectionClass ) {
				$reflector = $reflector->hasMethod( '__invoke' )
					? $reflector->getMethod( '__invoke' )
					: throw new InvalidConfigurationException( sprintf( '%s can only be used on methods or invokable services.', WPHook::class ) );
			}

			$args = [
				'name'          => $hook->name,
				'priority'      => $hook->priority ?? 10,
				'accepted_args' => $hook->accepted_args ?? $reflector->getNumberOfParameters(),
				'method'        => $reflector->getName(),
			];

			$definition->addTag( 'wp.hook', $args );
		}
	);
};
