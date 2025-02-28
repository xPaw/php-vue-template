<?php
declare(strict_types=1);

namespace xPaw\Template;

class Engine
{
	public bool $Precompiled = false;
	public string $TemplateExtension = 'html';
	public string $TemplateDirectory = 'templates/';
	public string $CacheDirectory = 'cache/';

	/** @var array<string, mixed> */
	public array $Variables = [];

	/**
	 * Render the template.
	 */
	public function Render( string $__templateFilePath ) : void
	{
		\extract( $this->Variables, \EXTR_REFS | \EXTR_OVERWRITE );

		require $this->CheckTemplate( $__templateFilePath );
	}

	/**
	 * Assign a variable.
	 *
	 * @param string|array<string, mixed> $variable Name of template variable or associative array name/value
	 * @param mixed $value value assigned to this variable. Not set if variable_name is an associative array
	 */
	public function Assign( array|string $variable, mixed $value = null ) : void
	{
		if( \is_array( $variable ) )
		{
			if( $value !== null )
			{
				throw new \Exception( 'Do not provide value if assigning an array.' );
			}

			foreach( $variable as $key => $innerValue )
			{
				$this->Assign( $key, $innerValue );
			}
		}
		else
		{
			$this->Variables[ $variable ] = $value;
		}
	}

	/**
	 * Check if the template exist and compile it if necessary.
	 */
	public function CheckTemplate( string $template ) : string
	{
		$TemplateFilepath = $this->TemplateDirectory . $template . '.' . $this->TemplateExtension;
		$ParsedFilepath = $this->CacheDirectory . 'rtpl_' . \preg_replace( '/[^a-zA-Z0-9_]/', '_', $template ) . '.php';

		if( $this->Precompiled )
		{
			return $ParsedFilepath;
		}

		// Compile the template if the original has been updated
		if( !\file_exists( $ParsedFilepath ) || \filemtime( $ParsedFilepath ) < \filemtime( $TemplateFilepath ) )
		{
			$this->CompileFile( $TemplateFilepath, $ParsedFilepath );
		}

		return $ParsedFilepath;
	}

	/**
	 * Compile the file and save it in the cache.
	 */
	private function CompileFile( string $templateFilepath, string $parsedTemplateFilepath ) : void
	{
		$compiler = new Compiler();
		$compiler->ParseFile( $templateFilepath );
		$parsedCode = $compiler->OutputCode();

		// write compiled file
		if( \file_put_contents( $parsedTemplateFilepath, $parsedCode, LOCK_EX ) === false )
		{
			throw new \Exception( 'Failed to write ' . $parsedTemplateFilepath );
		}
	}
}
