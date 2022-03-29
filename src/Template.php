<?php
declare(strict_types=1);

namespace xPaw\Template;

class Template
{
	private const LIBXML_OPTIONS =
		\LIBXML_COMPACT |
		\LIBXML_HTML_NODEFDTD |
		\LIBXML_HTML_NOIMPLIED |
		\LIBXML_NONET |
		\LIBXML_NOXMLDECL |
		\LIBXML_PARSEHUGE |
		\LIBXML_PEDANTIC;

	private \DOMDocument $DOM;

	/** @var array<int, string> */
	private array $expressions = [];
	private int $expressionCount = 0;

	public function __construct()
	{
		\libxml_use_internal_errors( true ); // todo
	}

	public function Parse( string $Data ) : void
	{
		$this->DOM = new \DOMDocument;
		$this->DOM->loadHTML( '<?xml encoding="UTF-8">' . $Data, self::LIBXML_OPTIONS );

		//libxml_clear_errors();

		// Remove the <?xml encoding="UTF-8">
		foreach( $this->DOM->childNodes as $node )
		{
			if( $node->nodeType == XML_PI_NODE )
			{
				$this->DOM->removeChild( $node );
			}
		}

		$this->DOM->encoding = 'UTF-8';

		$this->HandleNode( $this->DOM );
	}

	public function OutputCode() : string
	{
		$code = $this->DOM->saveHTML(); // todo: this adds <p> wrapper for text without elements

		echo '[2] ' . $code . PHP_EOL;

		// todo: a way to do this without replaces?
		$code = preg_replace_callback(
			'/<PHPEXPRESSION c="([0-9]+)">/s',
			fn( array $matches ) : string => $this->expressions[ $matches[1] ],
			$code
		);
		$code = str_replace( '</PHPEXPRESSION>', '<?php }?>', $code );
		$code = str_replace( '?><?php', '', $code );

		return $code;
	}

	private function HandleNode( \DOMNode $parentNode ) : void
	{
		foreach( \iterator_to_array( $parentNode->childNodes ) as $node )
		{
			if( $node instanceof \DOMText )
			{
				$this->HandleMustacheVariables( $node );
				continue;
			}

			// todo: check for duplicate attributes
			// todo: check whether an `if` is open when handling `else`
			// todo: check whether if/else is within the same parent node
			if( $node->hasAttribute( 'v-if' ) )
			{
				$this->expression = $node->getAttribute( 'v-if' );
				$node->removeAttribute( 'v-if' );

				$newNode = $this->DOM->createElement( 'PHPEXPRESSION' );
				$newNode->setAttribute( 'c', (string)$this->expressionCount );
				$parentNode->replaceChild( $newNode, $node );
				$newNode->appendChild( $node );

				$this->expressions[ $this->expressionCount ] = "<?php if({$this->expression}){ ?>";
				$this->expressionCount++;
			}
			else if( $node->hasAttribute( 'v-else-if' ) )
			{
				$this->expression = $node->getAttribute( 'v-else-if' );
				$node->removeAttribute( 'v-else-if' );

				$newNode = $this->DOM->createElement( 'PHPEXPRESSION' );
				$newNode->setAttribute( 'c', (string)$this->expressionCount );
				$parentNode->replaceChild( $newNode, $node );
				$newNode->appendChild( $node );

				$this->expressions[ $this->expressionCount ] = "<?php elseif({$this->expression}){ ?>";
				$this->expressionCount++;
			}
			else if( $node->hasAttribute( 'v-else' ) )
			{
				$node->removeAttribute( 'v-else' );

				$newNode = $this->DOM->createElement( 'PHPEXPRESSION' );
				$newNode->setAttribute( 'c', (string)$this->expressionCount );
				$parentNode->replaceChild( $newNode, $node );
				$newNode->appendChild( $node );

				$this->expressions[ $this->expressionCount ] = "<?php else{ ?>";
				$this->expressionCount++;
			}

			$this->HandleNode( $node );
		}
	}

	private function HandleMustacheVariables( \DOMText $node ) : void
	{
		$length = \strlen( $node->data ); // todo: does this need to use mb_ functions?
		$isOpen = false;
		$start = -1;
		$end = -1;

		for( $i = 0; $i < $length - 1; $i++ )
		{
			// todo: support escaping
			if( $node->data[ $i ] === '{' && $node->data[ $i + 1 ] === '{' )
			{
				if( $isOpen )
				{
					throw new \Exception( "Opening mustache tag at position $i, but a tag was already open at position $start." );
				}

				$isOpen = true;
				$start = $i;
			}

			if( $node->data[ $i ] === '}' && $node->data[ $i + 1 ] === '}' )
			{
				if( !$isOpen )
				{
					throw new \Exception( "Closing mustache tag at position $i, but it was never opened." );
				}

				$end = $i + 2;
				break;
			}
		}

		if( !$isOpen )
		{
			return;
		}

		if( $end === -1 )
		{
			throw new \Exception( "Opening mustache tag at position $start, but it was never closed." );
		}

		$mustache = $node->splitText( $start );
		$remainder = $mustache->splitText( $end - $start );
		$raw = \substr( $mustache->data, 2, -2 ); // todo: mb_?
		$raw = \trim( $raw );

		$newNode = $this->DOM->createElement( 'PHPEXPRESSION' );
		$newNode->setAttribute( 'c', (string)$this->expressionCount );

		$mustache->parentNode->replaceChild( $newNode, $mustache );

		// todo: unsafe output without htmlspecialchars
		$this->expressions[ $this->expressionCount ] = "<?php { echo \htmlspecialchars($raw, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, 'UTF-8'); ?>";
		$this->expressionCount++;

		$this->HandleMustacheVariables( $remainder );
	}
}
