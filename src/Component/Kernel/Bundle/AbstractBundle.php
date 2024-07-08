<?php

namespace Qce\Component\Kernel\Bundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ConfigurableExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * @template T of array
 */
class AbstractBundle implements BundleInterface, ConfigurableExtensionInterface {
	protected string $extension_alias;
	protected string $name;
	protected string $namespace;
	protected string $path;
	protected ?ExtensionInterface $extension;
	protected ?ContainerInterface $container;

	public function boot(): void {
	}

	public function build( ContainerBuilder $container ): void {
	}

	public function configure( DefinitionConfigurator $definition ): void {
	}

	public function prependExtension( ContainerConfigurator $container, ContainerBuilder $builder ): void {
	}

	/**
	 * @param T $config
	 */
	public function loadExtension( array $config, ContainerConfigurator $container, ContainerBuilder $builder ): void {
	}

	public function set_container( ?ContainerInterface $container ): void {
		$this->container = $container;
	}

	public function get_container_extension(): ?ExtensionInterface {
		$this->extension_alias ??= Container::underscore( preg_replace( '/Bundle$/', '', $this->get_name() ) ?? '' );

		return $this->extension ??= new BundleExtension( $this, $this->extension_alias );
	}

	public function get_name(): string {
		if ( ! isset( $this->name ) ) {
			$this->parse_info();
		}

		return $this->name;
	}

	public function get_namespace(): string {
		if ( ! isset( $this->namespace ) ) {
			$this->parse_info();
		}

		return $this->namespace;
	}

	public function get_path(): string {
		if ( ! isset( $this->path ) ) {
			$this->parse_info();
		}

		return $this->path;
	}

	private function parse_info(): void {
		$r = new \ReflectionClass( $this );

		$this->namespace ??= $r->getNamespaceName();
		$this->name      ??= $r->getShortName();
		$this->path      ??= \dirname( $r->getFileName() ?: '' );
	}
}
