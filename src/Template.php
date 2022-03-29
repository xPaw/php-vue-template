<?php
declare(strict_types=1);

namespace xPaw\Template;

class Template
{
	private const LIBXML_OPTIONS =
		\LIBXML_COMPACT | // Activate small nodes allocation optimization.
		\LIBXML_HTML_NODEFDTD | // Prevent a default doctype being added when one is not found.
		\LIBXML_HTML_NOIMPLIED | // Turn off the automatic adding of implied html/body... elements.
		\LIBXML_NONET | // Disable network access when loading documents.
		\LIBXML_NOXMLDECL | // Drop the XML declaration when saving a document.
		\LIBXML_PARSEHUGE | // Relax any hardcoded limit from the parser.
		\LIBXML_NOERROR | // Suppress error reports.
		\LIBXML_PEDANTIC; // Enable pedantic error reporting.

	private \DOMDocument $DOM;

	/** @var array<int, string> */
	private array $expressions = [];
	private int $expressionCount = 0;
	private string $expressionTag = 'PHPEXPRESSION';

	public function __construct()
	{
		$this->expressionTag .= bin2hex( random_bytes( 6 ) );
	}

	public function Parse( string $Data ) : void
	{
		$Data = '<?xml encoding="UTF-8">' . $Data;

		$previousUseError = libxml_use_internal_errors( true );

		// TODO: Handle loadHTML warnings, e.g. when html is not fully valid
		$this->DOM = new \DOMDocument;

		$loadResult = $this->DOM->loadHTML( $Data, self::LIBXML_OPTIONS );

		$errors = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors( $previousUseError );

		if( $loadResult === false )
		{
			throw new \Exception( 'loadHTML call failed' ); // todo: better message
		}

		if( !empty( $errors ) )
		{
			print_r( $errors );
		}

		// Remove the <?xml encoding="UTF-8">
		foreach( $this->DOM->childNodes as $node )
		{
			if( $node instanceof \DOMProcessingInstruction )
			{
				$this->DOM->removeChild( $node );
				break;
			}
		}

		$this->DOM->encoding = 'UTF-8';

		$this->HandleNode( $this->DOM );
	}

	public function OutputCode() : string
	{
		// todo: this adds <p> wrapper for text without elements
		// todo: this turns <div /> into <div></div>
		$code = $this->DOM->saveHTML();

		if( $code === false )
		{
			throw new \Exception( 'saveHTML call failed' ); // todo: better message
		}

		echo '[2] ' . $code . PHP_EOL;

		// todo: a way to do this without replaces?
		/** @var string $code */
		$code = preg_replace_callback(
			'/<' . $this->expressionTag . ' c="(?<id>[0-9]+)">/s',
			fn( array $matches ) : string => $this->expressions[ (int)$matches[ 'id' ] ],
			$code
		);

		$code = preg_replace_callback(
			'/' . $this->expressionTag . '_ATTR_(?<id>[0-9]+)/s',
			fn( array $matches ) : string => $this->expressions[ (int)$matches[ 'id' ] ],
			$code
		);

		if( $code === null )
		{
			throw new \Exception( 'preg_replace_callback call failed' ); // todo: better message
		}

		$code = str_replace( '</' . $this->expressionTag . '>', '<?php }?>', $code );
		$code = str_replace( '?><?php', '', $code );

		return $code;
	}

	private function HandleNode( \DOMNode $parentNode ) : void
	{
		foreach( \iterator_to_array( $parentNode->childNodes ) as $node )
		{
			// TODO: Handle DOMComment, it has no splitText
			if( $node instanceof \DOMText )
			{
				$this->HandleMustacheVariables( $node );
				continue;
			}

			if( !( $node instanceof \DOMElement ) )
			{
				continue;
			}

			if( $node->attributes === null )
			{
				$this->HandleNode( $node );
				continue;
			}


			/** @var \DOMAttr[] $attributes */
			$attributes = [];
			$parsedAttribute = null;

			/** @var \DOMAttr $attribute */
			foreach( $node->attributes as $id => $attribute )
			{
				if( $attribute->name[ 0 ] === ':' )
				{
					$attributes[] = new \DOMAttr(
						substr( $attribute->name, 1 ),
						$this->expressionTag . '_ATTR_' . $this->expressionCount
					);

					$this->expressions[ $this->expressionCount ] = "<?php echo \htmlspecialchars($attribute->value, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, 'UTF-8'); ?>";
					$this->expressionCount++;

					continue;
				}

				// todo: check for duplicate attributes
				// todo: check whether an `if` is open when handling `else`
				// todo: check whether if/else is within the same parent node
				if( $attribute->name === 'v-if' )
				{
					$parsedAttribute = $attribute->name;
					$expression = $attribute->value;
					$node->removeAttribute( $attribute->name ); // todo: this modifies array in place, breaks iteration

					$newNode = $this->DOM->createElement( $this->expressionTag );
					$newNode->setAttribute( 'c', (string)$this->expressionCount );
					$parentNode->replaceChild( $newNode, $node );
					$newNode->appendChild( $node );

					$this->expressions[ $this->expressionCount ] = "<?php if({$expression}){ ?>";
					$this->expressionCount++;

					continue;
				}

				if( $attribute->name === 'v-else-if' )
				{
					if( $parsedAttribute !== null )
					{
						throw new \Exception( "Do not put v-else-if on the same element that has $parsedAttribute on line {$node->getLineNo()}." );
					}

					$parsedAttribute = $attribute->name;
					$expression = $attribute->value;
					$node->removeAttribute( $attribute->name ); // todo: this modifies array in place, breaks iteration

					$newNode = $this->DOM->createElement( $this->expressionTag );
					$newNode->setAttribute( 'c', (string)$this->expressionCount );
					$parentNode->replaceChild( $newNode, $node );
					$newNode->appendChild( $node );

					$this->expressions[ $this->expressionCount ] = "<?php elseif({$expression}){ ?>";
					$this->expressionCount++;

					continue;
				}

				if( $attribute->name === 'v-else' )
				{
					if( $parsedAttribute !== null )
					{
						throw new \Exception( "Do not put v-else on the same element that has $parsedAttribute on line {$node->getLineNo()}." );
					}

					$parsedAttribute = $attribute->name;
					$node->removeAttribute( $attribute->name ); // todo: this modifies array in place, breaks iteration

					$newNode = $this->DOM->createElement( $this->expressionTag );
					$newNode->setAttribute( 'c', (string)$this->expressionCount );
					$parentNode->replaceChild( $newNode, $node );
					$newNode->appendChild( $node );

					$this->expressions[ $this->expressionCount ] = "<?php else{ ?>";
					$this->expressionCount++;

					continue;
				}

				$attributes[] = $attribute;
			}

			/** @var \DOMAttr $attribute */
			foreach( iterator_to_array( $node->attributes ) as $attribute )
			{
				$node->removeAttributeNode( $attribute );
			}

			foreach( $attributes as $attribute )
			{
				$node->setAttributeNode( $attribute );
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
					throw new \Exception( "Opening mustache tag at position $i, but a tag was already open at position $start on line {$node->getLineNo()}." );
				}

				$isOpen = true;
				$start = $i;
			}

			if( $node->data[ $i ] === '}' && $node->data[ $i + 1 ] === '}' )
			{
				if( !$isOpen )
				{
					throw new \Exception( "Closing mustache tag at position $i, but it was never opened on line {$node->getLineNo()}." );
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
			throw new \Exception( "Opening mustache tag at position $start, but it was never closed on line {$node->getLineNo()}." );
		}

		$mustache = $node->splitText( $start );
		$remainder = $mustache->splitText( $end - $start );
		$raw = \substr( $mustache->data, 2, -2 ); // todo: mb_?
		$raw = \trim( $raw );

		$newNode = $this->DOM->createElement( $this->expressionTag );
		$newNode->setAttribute( 'c', (string)$this->expressionCount );

		if( $mustache->parentNode === null )
		{
			throw new \Exception( 'mustache->parentNode is null' ); // todo: better message
		}

		$mustache->parentNode->replaceChild( $newNode, $mustache );

		// todo: unsafe output without htmlspecialchars
		$this->expressions[ $this->expressionCount ] = "<?php { echo \htmlspecialchars($raw, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, 'UTF-8'); ?>";
		$this->expressionCount++;

		$this->HandleMustacheVariables( $remainder );
	}
}
