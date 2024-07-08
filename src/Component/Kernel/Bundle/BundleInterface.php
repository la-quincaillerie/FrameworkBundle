<?php

namespace Qce\Component\Kernel\Bundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

interface BundleInterface {

	public function boot(): void;

	public function build( ContainerBuilder $container ): void;

	public function get_container_extension(): ?ExtensionInterface;

	public function get_name(): string;

	public function get_namespace(): string;

	public function get_path(): string;

	public function set_container( ?ContainerInterface $container ): void;
}
