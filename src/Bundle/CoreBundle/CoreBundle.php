<?php

namespace Qce\Bundle\CoreBundle;

use Qce\Component\Blocks\DependencyInjection\BlocksPass;
use Qce\Component\Hooks\DependencyInjection\HooksPass;
use Qce\Component\Hooks\HooksManager;
use Qce\Component\Kernel\Bundle\AbstractBundle;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * @extends AbstractBundle<array{}>
 */
class CoreBundle extends AbstractBundle {
	public function configure( DefinitionConfigurator $definition ): void {
		$definition->import( 'Resources/config/definition.php' );
	}

	public function prependExtension( ContainerConfigurator $container, ContainerBuilder $builder ): void {
		$container->import( 'Resources/config/blocks.php' );
		$container->import( 'Resources/config/hooks.php' );
	}

	public function boot(): void {
		/** @var ?HooksManager $hooks_manager */
		$hooks_manager = $this->container?->get( 'wp.hooks_manager', ContainerInterface::NULL_ON_INVALID_REFERENCE );
		$hooks_manager?->register_hooks();
	}

	public function build( ContainerBuilder $container ): void {
		$container->addCompilerPass( new BlocksPass() );
		$container->addCompilerPass( new HooksPass() );
	}

	public function get_path(): string {
		return __DIR__;
	}
}
