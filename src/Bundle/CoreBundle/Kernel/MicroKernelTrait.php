<?php

namespace Qce\Bundle\CoreBundle\Kernel;

use Qce\Bundle\CoreBundle\CoreBundle;
use Qce\Component\Kernel\KernelInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\AbstractConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

trait MicroKernelTrait {
	public function register_bundles(): iterable {
		return \apply_filters(
			'qce_register_bundles',
			[
				new CoreBundle(),
			]
		);
	}

	private function get_config_dir(): string {
		return $this->get_project_dir() . '/config';
	}

	private function configure_container( ContainerConfigurator $container ) {
		$config_dir = $this->get_config_dir();

		$container->import( "$config_dir/{packages}/*.{php,yaml}" );
		$container->import( "$config_dir/{packages}/$this->environment/*.{php,yaml}" );
		$container->import( "$config_dir/services.{php,yaml}" );
	}

	public function register_container_configuration( LoaderInterface $loader ): void {
		$loader->load(
			function ( ContainerBuilder $container ) use ( $loader ) {
				if ( ! $container->hasDefinition( 'kernel' ) ) {
					$container
						->register( 'kernel' )
						->setAutoconfigured( true )
						->setSynthetic( true )
						->setPublic( true );
				}

				$kernel_class = str_contains( static::class, "@anonymous\0" ) ? parent::class : static::class;
				$container->setAlias( $kernel_class, 'kernel' )->setPublic( true );
				$container->setAlias( KernelInterface::class, 'kernel' )->setPublic( true );

				$file = ( new \ReflectionObject( $this ) )->getFileName() ?: '';

				/** @var PhpFileLoader $kernel_loader */
				$kernel_loader = $loader->getResolver()->resolve( $file );
				$kernel_loader->setCurrentDir( \dirname( $file ) );
				$instanceof   = &( \Closure::bind( fn &() => $this->instanceof, $kernel_loader, $kernel_loader ) )(); // Extract private $instanceof property of $kernel_loader by reference
				$configurator = new ContainerConfigurator( $container, $kernel_loader, $instanceof, $file, $file, $this->get_environement() );

				// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$preprocessor                            = AbstractConfigurator::$valuePreProcessor;
				AbstractConfigurator::$valuePreProcessor = fn( $value ) => $value === $this ? new Reference( 'kernel' ) : $value;

				$this->configure_container( $configurator );

				$instanceof                              = [];
				AbstractConfigurator::$valuePreProcessor = $preprocessor;
				// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			}
		);
	}
}
