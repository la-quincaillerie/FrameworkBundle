<?php

namespace Qce\Component\Kernel;

use Qce\Component\Kernel\Bundle\BundleInterface;
use Qce\Component\Kernel\Config\FileLocator;
use Qce\Component\Kernel\DependencyInjection\MergeExtensionConfigurationPass;
use Symfony\Component\Config\Builder\ConfigBuilderGenerator;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\ConfigCacheFactory;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\Compiler\RemoveBuildParametersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Filesystem\Filesystem;

abstract class Kernel implements KernelInterface {

	/**
	 * @var array<string, BundleInterface>
	 */
	protected array $bundles;

	protected ?ContainerInterface $container = null;

	protected bool $booted = false;

	private string $project_dir;

	public function __construct(
		protected string $environment = 'production',
		protected bool $debug = false,
	) {
	}

	public function __clone() {
		$this->booted    = false;
		$this->container = null;
	}

	public function get_container(): ContainerInterface {
		if ( ! $this->container ) {
			throw new \LogicException( 'Cannot retrieve the container from a non-booted kernel.' );
		}

		return $this->container;
	}

	public function get_bundles(): array {
		return $this->bundles;
	}

	public function get_bundle( string $name ): BundleInterface {
		if ( ! isset( $this->bundles[ $name ] ) ) {
			throw new \InvalidArgumentException( \sprintf( 'Bundle "%s" does not exist or it is not enabled.', $name ) );
		}

		return $this->bundles[ $name ];
	}

	public function locate_resource( string $name ): string {
		if ( '@' !== $name[0] ) {
			throw new \InvalidArgumentException( \sprintf( 'A resource name must start with @ ("%s" given).', $name ) );
		}

		if ( str_contains( $name, '..' ) ) {
			throw new \RuntimeException( \sprintf( 'File name "%s" contains invalid characters (..).', $name ) );
		}

		$bundle_name = substr( $name, 1 );
		$path        = '';
		if ( str_contains( $bundle_name, '/' ) ) {
			[$bundle_name, $path] = explode( '/', $bundle_name, 2 );
		}

		$bundle = $this->get_bundle( $bundle_name );
		if ( file_exists( $file = $bundle->get_path() . '/' . $path ) ) {
			return $file;
		}

		throw new \InvalidArgumentException( \sprintf( 'Unable to find file "%s".', $name ) );
	}

	public function get_environement(): string {
		return $this->environment;
	}

	public function is_debug(): bool {
		return $this->debug;
	}

	public function get_project_dir(): string {
		if ( isset( $this->project_dir ) ) {
			return $this->project_dir;
		}

		$reflector = ( new \ReflectionObject( $this ) );
		$dir       = $reflector->getFileName() ?: '';

		if ( ! is_file( $dir ) ) {
			throw new \LogicException( \sprintf( 'Cannot auto-detect project dir for kernel of class "%s".', $reflector->name ) );
		}

		$dir = $root_dir = \dirname( $dir );
		while ( ! is_file( $dir . '/composer.json' ) ) {
			if ( \dirname( $dir ) === $dir ) {
				return $this->project_dir = $root_dir;
			}
			$dir = \dirname( $dir );
		}

		return $this->project_dir = $dir;
	}

	public function get_build_dir(): string {
		return $this->get_project_dir() . '/var';
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->initialize_bundles();
		$this->initialize_container();

		foreach ( $this->get_bundles() as $bundle ) {
			$bundle->set_container( $this->container );
			$bundle->boot();
		}

		$this->booted = true;
	}

	protected function initialize_bundles(): void {
		$this->bundles = [];
		foreach ( $this->register_bundles() as $bundle ) {
			$name = $bundle->get_name();
			if ( isset( $this->bundles[ $name ] ) ) {
				throw new \LogicException( \sprintf( 'Trying to register two bundles with the same name "%s".', $name ) );
			}

			$this->bundles[ $name ] = $bundle;
		}
	}

	protected function get_container_class(): string {
		$class = static::class;
		$class = str_contains( $class, "@anonymous\0" )
			? get_parent_class( $class ) . str_replace( '.', '_', ContainerBuilder::hash( $class ) )
			: $class;
		$class =
			str_replace( '\\', '_', $class )
			. ucfirst( $this->environment )
			. ( $this->debug ? 'Debug' : '' )
			. $this->get_container_base_class();

		if ( ! preg_match( '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $class ) ) {
			throw new \InvalidArgumentException( \sprintf( 'The environment "%s" contains invalid characters, it can only contain characters allowed in PHP class names.', $this->environment ) );
		}

		return $class;
	}

	protected function get_container_base_class(): string {
		return 'Container';
	}

	protected function initialize_container(): void {
		$class     = $this->get_container_class();
		$build_dir = $this->get_build_dir();

		$cache_path    = "$build_dir/$class.php";
		$cache_factory = new ConfigCacheFactory( $this->debug );

		$cache = new ConfigCache( $cache_path, $this->debug );
		if (
			\is_file( $cache_path )
			&& ( $this->container = include $cache_path ) instanceof ContainerInterface
			&& ( $cache->isFresh() )
		) {
			$this->container->set( 'kernel', $this );
			return;
		}

		$old_container = \is_object( $this->container ) ? new \ReflectionObject( $this->container ) : null;

		$container = $this->build_container();
		$container->compile();
		$this->dump_container( $cache, $container, $class, $this->get_container_base_class() );

		$this->container = require $cache_path;
		$this->container->set( 'kernel', $this );

		if (
			$old_container
			&& $this->container::class !== $old_container->name
			&& ( $old_container_file = $old_container->getFileName() )
		) {
			( new Filesystem() )->remove( \dirname( $old_container_file ) );
		}
	}

	protected function build_container(): ContainerBuilder {
		$container = $this->get_container_builder();
		$container->addObjectResource( $this );

		$bundles = $this->get_bundles();
		foreach ( $bundles as $bundle ) {
			$extension = $bundle->get_container_extension();
			$extension && $container->registerExtension( $extension );
			$this->debug && $container->addObjectResource( $bundle );
		}
		foreach ( $bundles as $bundle ) {
			$bundle->build( $container );
		}

		$this->build( $container );

		$this->register_container_configuration( $this->create_container_loader( $container ) );

		$extensions = array_map( static fn( $extension ) => $extension->getAlias(), $container->getExtensions() );
		$container->getCompilerPassConfig()->setMergePass( new MergeExtensionConfigurationPass( $extensions ) );

		return $container;
	}

	protected function build( ContainerBuilder $container ): void {
	}

	protected function get_container_builder(): ContainerBuilder {
		$container = new ContainerBuilder();
		$container->getParameterBag()->add( $this->get_kernel_parameters() );

		if ( $this instanceof ExtensionInterface ) {
			$container->registerExtension( $this );
		}
		if ( $this instanceof CompilerPassInterface ) {
			$container->addCompilerPass( $this, PassConfig::TYPE_BEFORE_OPTIMIZATION, -10000 );
		}

		return $container;
	}

	protected function create_container_loader( ContainerBuilder $container ): DelegatingLoader {
			$env = $this->get_environement();

			$locator  = new FileLocator( $this );
			$resolver = new LoaderResolver(
				[
					new XmlFileLoader( $container, $locator, $env ),
					new YamlFileLoader( $container, $locator, $env ),
					new IniFileLoader( $container, $locator, $env ),
					new PhpFileLoader( $container, $locator, $env, \class_exists( ConfigBuilderGenerator::class ) ? new ConfigBuilderGenerator( $this->get_build_dir() ) : null ),
					new GlobFileLoader( $container, $locator, $env ),
					new DirectoryLoader( $container, $locator, $env ),
					new ClosureLoader( $container, $env ),
				]
			);

			return new DelegatingLoader( $resolver );
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function get_kernel_parameters(): array {
		$bundles          = [];
		$bundles_metadata = [];

		foreach ( $this->get_bundles() as $name => $bundle ) {
			$bundles[ $name ]          = $bundle::class;
			$bundles_metadata[ $name ] = [
				'path'      => $bundle->get_path(),
				'namespace' => $bundle->get_namespace(),
			];
		}

		return [
			'kernel.project_dir'      => $this->get_project_dir(),
			'kernel.environment'      => $this->environment,
			'kernel.debug'            => $this->debug,
			'kernel.build_dir'        => $this->get_build_dir(),
			'kernel.bundles'          => $bundles,
			'kernel.bundles_metadata' => $bundles_metadata,
			'kernel.container_class'  => $this->get_container_class(),
		];
	}

	protected function dump_container( ConfigCache $cache, ContainerBuilder $container, string $class, string $base_class ): void {
		$dumper = new PhpDumper( $container );

		$build_parameters = [];
		foreach ( $container->getCompilerPassConfig()->getPasses() as $pass ) {
			if ( $pass instanceof RemoveBuildParametersPass ) {
				$build_parameters[] = $pass->getRemovedParameters();
			}
		}
		$build_parameters = array_merge( ...$build_parameters );

		/** @var non-empty-array<string, string> $content -- Content includes at least the root container */
		$content = $dumper->dump(
			[
				'class'               => $class,
				'base_class'          => $base_class,
				'file'                => $cache->getPath(),
				'as_files'            => true,
				'debug'               => $this->debug,
				'inline_factories'    => $build_parameters['.container.dumper.inline_factories'] ?? false,
				'inline_class_loader' => $build_parameters['.container.dumper.inline_class_loader'] ?? $this->debug,
				'build_time'          => $container->hasParameter( 'kernel.container_build_time' ) ? $container->getParameter( 'kernel.container_build_time' ) : time(),
				'preload_classes'     => array_map( 'get_class', $this->bundles ),
			]
		);

		$root_code = array_pop( $content );
		$dir       = \dirname( $cache->getPath() ) . '/';
		$fs        = new Filesystem();

		foreach ( $content as $file => $code ) {
			$fs->dumpFile( $dir . $file, $code );
			$fs->chmod( $dir . $file, 0666, umask() );
		}

		$cache->write( $root_code, $container->getResources() );
	}

	public function __sleep(): array {
		return [ 'environment', 'debug' ];
	}
}
