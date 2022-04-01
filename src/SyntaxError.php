<?php
declare(strict_types=1);

namespace xPaw\Template;

final class SyntaxError extends \Exception
{
	public function __construct( string $message, int $line = -1, ?\Throwable $previous = null )
	{
		$this->line = $line;
		$this->file = "TODO: template name";

		parent::__construct( $message, 0, $previous );
	}
}
