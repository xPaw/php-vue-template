<?php
declare(strict_types=1);

namespace xPaw\Template;

class SyntaxError extends \Exception
{
	public function __construct( string $message, int $line = -1 )
	{
		$this->message = $message;
		$this->line = $line;
		$this->file = "TODO: template name";
	}
}
