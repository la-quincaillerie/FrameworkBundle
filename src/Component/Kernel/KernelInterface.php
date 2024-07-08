<?php

namespace Qce\Component\Kernel;

use Qce\Component\Kernel\Bundle\BundleInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

interface KernelInterface {

	/**
	 * @return iterable<BundleInterface>
	 */
	public function register_bundles(): iterable;

	public function register_container_configuration( LoaderInterface $loader ): void;

	public function boot(): void;

	/**
	 * @return array<string, BundleInterface>
	 */
	public function get_bundles(): array;

	/**
	 * Returns the file path for a given bundle resource.
	 *
	 * A Resource can be a file or a directory.
	 *
	 * The resource name must follow the following pattern:
	 *
	 *     "@BundleName/path/to/a/file.something"
	 *
	 * where BundleName is the name of the bundle
	 * and the remaining part is the relative path in the bundle.
	 */
	public function locate_resource( string $name ): string;

	public function get_environement(): string;

	public function is_debug(): bool;

	public function get_container(): ContainerInterface;

	public function get_project_dir(): string;

	public function get_build_dir(): string;
}
