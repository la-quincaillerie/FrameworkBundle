<?php

namespace Qce\Component\Hooks\Attribute;

#[\Attribute( \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE )]
class WPHook {
	public function __construct(
		public string $name,
		public ?int $priority = null,
		public ?int $accepted_args = null,
	) {
	}
}
