<?php

namespace Qce\Component\Blocks;

class BlocksManager {
	/**
	 * @param string[] $blocks
	 */
	public function __construct( private array $blocks ) {
	}

	public function register_blocks(): void {
		\array_map( \register_block_type( ... ), $this->blocks ); // @phpstan-ignore function.notFound
	}
}
