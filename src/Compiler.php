<?php
declare(strict_types=1);

namespace xPaw\Template;

class Compiler
{
	private const LIBXML_OPTIONS =
		\LIBXML_COMPACT | // Activate small nodes allocation optimization.
		\LIBXML_HTML_NODEFDTD | // Prevent a default doctype being added when one is not found.
		\LIBXML_HTML_NOIMPLIED | // Turn off the automatic adding of implied html/body... elements.
		\LIBXML_NONET | // Disable network access when loading documents.
		\LIBXML_NOXMLDECL | // Drop the XML declaration when saving a document.
		\LIBXML_PARSEHUGE | // Relax any hardcoded limit from the parser.
		\LIBXML_NOERROR | // Suppress error reports.
		\LIBXML_NOBLANKS | // Remove blank nodes.
		\LIBXML_PEDANTIC; // Enable pedantic error reporting.

	private const ATTR_IF = 'v-if';
	private const ATTR_ELSE = 'v-else';
	private const ATTR_ELSE_IF = 'v-else-if';
	private const ATTR_FOR = 'v-for';
	private const ATTR_PRE = 'v-pre';

	private \DOMDocument $DOM;

	/** @var array<int, string> */
	private array $expressions = [];
	private int $expressionCount = 0;
	private string $expressionTag = 'PHPEXPRESSION';

	public bool $Debug = false;

	public function __construct()
	{
		$this->expressionTag .= bin2hex( random_bytes( 6 ) );
	}

	public function Parse( string $Data ) : void
	{
		$this->expressions = [];
		$this->expressionCount = 0;

		// @codeCoverageIgnoreStart
		if( $this->Debug )
		{
			echo '===== [1]' . PHP_EOL . $Data . PHP_EOL;
		}
		// @codeCoverageIgnoreEnd

		$Data = '<?xml encoding="UTF-8">' . $Data;

		$previousUseError = libxml_use_internal_errors( true );

		// TODO: Handle loadHTML warnings, e.g. when html is not fully valid
		$this->DOM = new \DOMDocument( encoding: 'UTF-8' );
		//$this->DOM->preserveWhiteSpace = false;
		//$this->DOM->formatOutput = false;

		$loadResult = $this->DOM->loadHTML( $Data, self::LIBXML_OPTIONS );

		$errors = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors( $previousUseError );

		if( $loadResult === false )
		{
			throw new \AssertionError( 'loadHTML call failed' ); // todo: better message
		}

		if( $this->Debug && !empty( $errors ) )
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

		// @codeCoverageIgnoreStart
		if( $this->Debug )
		{
			echo '===== [2]' . PHP_EOL . $this->DOM->saveHTML() . PHP_EOL;
		}
		// @codeCoverageIgnoreEnd

		$this->HandleNode( $this->DOM );
	}

	public function OutputCode() : string
	{
		// todo: this adds <p> wrapper for text without elements
		// todo: this turns <div /> into <div></div>
		$code = $this->DOM->saveHTML();

		if( $code === false )
		{
			throw new \AssertionError( 'saveHTML call failed' ); // todo: better message
		}

		$code = \rtrim( $code, "\n" ); // todo: why is it outputting a new line?

		// @codeCoverageIgnoreStart
		if( $this->Debug )
		{
			echo '===== [3]' . PHP_EOL . $code . PHP_EOL;
		}
		// @codeCoverageIgnoreEnd

		// todo: a way to do this without replaces?
		/** @var string $code */
		$code = preg_replace_callback(
			'/<' . $this->expressionTag . ' c="(?<id>[0-9]+)" type="[a-z\-]+">/s',
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
			throw new \AssertionError( 'preg_replace_callback call failed' ); // todo: better message
		}

		$code = str_replace( '</' . $this->expressionTag . '>', '<?php }?>', $code );
		$code = str_replace( '?><?php', '', $code );

		// @codeCoverageIgnoreStart
		if( $this->Debug )
		{
			echo '===== [4]' . PHP_EOL . $code . PHP_EOL;
		}
		// @codeCoverageIgnoreEnd

		return $code;
	}

	private function HandleNode( \DOMNode $parentNode ) : void
	{
		// Use iterator_to_array to iterate over the current children state
		// as the functions will modify the children
		foreach( \iterator_to_array( $parentNode->childNodes ) as $node )
		{
			if( $node instanceof \DOMProcessingInstruction )
			{
				throw new SyntaxError( "DOMProcessingInstruction is not allowed", $node->getLineNo() );
			}

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

			if( $node->attributes === null || $node->attributes->length === 0 )
			{
				$this->HandleNode( $node );
				continue;
			}

			$this->HandleAttributes( $node );

			// Skip compilation for this element and all its children.
			if( $node->hasAttribute( self::ATTR_PRE ) )
			{
				$attribute = $node->getAttributeNode( self::ATTR_PRE );
				$node->removeAttributeNode( $attribute );

				if( !empty( $attribute->value ) )
				{
					throw new SyntaxError( "Attribute $attribute->name must be empty", $node->getLineNo() );
				}

				continue;
			}

			$this->HandleNode( $node );
		}
	}

	private function HandleAttributes( \DOMElement $node ) : void
	{
		if( $node->parentNode === null || $node->attributes === null )
		{
			throw new \AssertionError( 'This should never happen.' );
		}

		/** @var \DOMAttr[] $attributes */
		$attributes = [];
		/** @var \DOMElement|null $newNode */
		$newNode = null;

		$testPreviousSibling = function( string $name, \DOMElement $node, ?\DOMElement $newNode )
		{
			if( $newNode !== null )
			{
				throw new SyntaxError( "Do not put $name on the same element that already has {$newNode->getAttribute( 'type' )}", $node->getLineNo() );
			}

			if( !( $node->previousSibling instanceof \DOMElement ) )
			{
				throw new SyntaxError( 'Previous sibling must be a DOM element', $node->getLineNo() );
			}

			$previousExpressionType = null;

			if( $node->previousSibling->tagName === $this->expressionTag )
			{
				$previousExpressionType = $node->previousSibling->getAttribute( 'type' );
			}

			if( $previousExpressionType !== self::ATTR_IF && $previousExpressionType !== self::ATTR_ELSE_IF )
			{
				throw new SyntaxError( "Previous sibling element must have " . self::ATTR_IF . " or " . self::ATTR_ELSE_IF, $node->getLineNo() );
			}
		};

		// Conditionally render an element based on the truthy-ness of the expression value.
		if( $node->hasAttribute( self::ATTR_IF ) )
		{
			$attribute = $node->getAttributeNode( self::ATTR_IF );
			$node->removeAttributeNode( $attribute );

			if( empty( $attribute->value ) )
			{
				throw new SyntaxError( "Attribute $attribute->name must not be empty", $node->getLineNo() );
			}

			$this->ValidateExpression( "if($attribute->value);", $node->getLineNo() );

			$newNode = $this->DOM->createElement( $this->expressionTag );
			$newNode->setAttribute( 'c', (string)$this->expressionCount );
			$newNode->setAttribute( 'type', $attribute->name );
			$node->parentNode->replaceChild( $newNode, $node );
			$newNode->appendChild( $node );

			$this->expressions[ $this->expressionCount ] = "<?php if($attribute->value){ ?>";
			$this->expressionCount++;
		}

		// Denote the "else if block" for v-if. Can be chained.
		// Restriction: previous sibling element must have v-if or v-else-if.
		if( $node->hasAttribute( self::ATTR_ELSE_IF ) )
		{
			$attribute = $node->getAttributeNode( self::ATTR_ELSE_IF );
			$node->removeAttributeNode( $attribute );

			if( empty( $attribute->value ) )
			{
				throw new SyntaxError( "Attribute $attribute->name must not be empty", $node->getLineNo() );
			}

			$testPreviousSibling( $attribute->name, $node, $newNode );

			$this->ValidateExpression( "if($attribute->value);", $node->getLineNo() );

			$newNode = $this->DOM->createElement( $this->expressionTag );
			$newNode->setAttribute( 'c', (string)$this->expressionCount );
			$newNode->setAttribute( 'type', $attribute->name );
			$node->parentNode->replaceChild( $newNode, $node );
			$newNode->appendChild( $node );

			$this->expressions[ $this->expressionCount ] = "<?php elseif($attribute->value){ ?>";
			$this->expressionCount++;
		}

		// Denote the "else block" for v-if or a v-if / v-else-if chain.
		// Restriction: previous sibling element must have v-if or v-else-if.
		if( $node->hasAttribute( self::ATTR_ELSE ) )
		{
			$attribute = $node->getAttributeNode( self::ATTR_ELSE );
			$node->removeAttributeNode( $attribute );

			if( !empty( $attribute->value ) )
			{
				throw new SyntaxError( "Attribute $attribute->name must be empty", $node->getLineNo() );
			}

			$testPreviousSibling( $attribute->name, $node, $newNode );

			$newNode = $this->DOM->createElement( $this->expressionTag );
			$newNode->setAttribute( 'c', (string)$this->expressionCount );
			$newNode->setAttribute( 'type', $attribute->name );
			$node->parentNode->replaceChild( $newNode, $node );
			$newNode->appendChild( $node );

			$this->expressions[ $this->expressionCount ] = "<?php else{ ?>";
			$this->expressionCount++;
		}

		// Render the element multiple times based on the source data.
		if( $node->hasAttribute( self::ATTR_FOR ) )
		{
			$attribute = $node->getAttributeNode( self::ATTR_FOR );
			$node->removeAttributeNode( $attribute );

			if( empty( $attribute->value ) )
			{
				throw new SyntaxError( "Attribute $attribute->name must not be empty", $node->getLineNo() );
			}

			$this->ValidateExpression( "foreach($attribute->value);", $node->getLineNo() );

			$newNodeFor = $this->DOM->createElement( $this->expressionTag );
			$newNodeFor->setAttribute( 'c', (string)$this->expressionCount );
			$newNodeFor->setAttribute( 'type', $attribute->name );

			// When used together, v-if has a higher priority than v-for.
			if( $newNode !== null )
			{
				$newNode->replaceChild( $newNodeFor, $node );
			}
			else
			{
				$node->parentNode->replaceChild( $newNodeFor, $node );
			}

			$newNodeFor->appendChild( $node );

			$this->expressions[ $this->expressionCount ] = "<?php foreach($attribute->value){ ?>";
			$this->expressionCount++;
		}

		/** @var \DOMAttr $attribute */
		foreach( \iterator_to_array( $node->attributes ) as $attribute )
		{
			$node->removeAttributeNode( $attribute );

			// Dynamically bind attribute to an expression.
			if( $attribute->name[ 0 ] !== ':' )
			{
				$attributes[] = $attribute;

				continue;
			}

			$this->ValidateExpression( "$attribute->value;", $node->getLineNo() );

			$attributes[] = new \DOMAttr(
				\substr( $attribute->name, 1 ),
				$this->expressionTag . '_ATTR_' . $this->expressionCount
			);

			$this->expressions[ $this->expressionCount ] = "<?php echo \htmlspecialchars($attribute->value, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, 'UTF-8'); ?>";
			$this->expressionCount++;
		}

		// Set new attributes after processing
		foreach( $attributes as $attribute )
		{
			$node->setAttributeNode( $attribute );
		}
	}

	private function HandleMustacheVariables( \DOMText $node ) : void
	{
		if( $node->parentNode === null )
		{
			throw new \AssertionError( 'node->parentNode is null' ); // todo: better message
		}

		$length = \strlen( $node->data );
		$bracketsOpen = 0;
		$isOpen = false;
		$start = -1;
		$end = -1;

		for( $i = 0; $i < $length - 1; $i++ )
		{
			// todo: support escaping
			if( $node->data[ $i ] === '{' && $node->data[ $i + 1 ] === '{' )
			{
				$bracketsOpen++;

				if( $isOpen )
				{
					if( $i === $start + 1 ) // continuation
					{
						continue;
					}

					throw new SyntaxError( "Opening mustache tag at position $i, but a tag was already open at position $start", $node->getLineNo() );
				}

				$isOpen = true;
				$start = $i;
			}

			if( $node->data[ $i ] === '}' && $node->data[ $i + 1 ] === '}' )
			{
				if( !$isOpen )
				{
					throw new SyntaxError( "Closing mustache tag at position $i, but it was never opened", $node->getLineNo() );
				}

				$end = $i + 2;
				$bracketsOpen--;

				if( $bracketsOpen === 0 )
				{
					break;
				}
			}
		}

		if( !$isOpen )
		{
			return;
		}

		if( $end === -1 || $bracketsOpen > 0 )
		{
			throw new SyntaxError( "Opening mustache tag at position $start, but it was never closed", $node->getLineNo() );
		}

		// Split the text into beginning, mustache itself, and remainder
		$mustache = \substr( $node->data, $start, $end - $start );

		// Create new tag for the mustache
		$newNode = $this->DOM->createElement( $this->expressionTag );
		$newNode->setAttribute( 'c', (string)$this->expressionCount );
		$newNode->setAttribute( 'type', 'mustache' );
		$node->parentNode->insertBefore( $newNode, $node->nextSibling );

		// Remaining text after the mustache if any
		$remainder = null;

		if( $length > $end )
		{
			$remainder = new \DOMText( \substr( $node->data, $end ) );
			$node->parentNode->insertBefore( $remainder, $newNode->nextSibling );
		}

		// Truncate the beginning text until the mustache
		$node->data = \substr( $node->data, 0, $start );

		$modifier = $mustache[ 2 ];
		$noEcho = false;
		$noEscape = false;

		if( $modifier === '=' )
		{
			$noEcho = true;
			$raw = \substr( $mustache, 3, -2 );
		}
		else if( $modifier === '{' && $mustache[ \strlen( $mustache ) - 2 ] === '}' )
		{
			$noEscape = true;
			$raw = \substr( $mustache, 3, -3 );
		}
		else
		{
			$raw = \substr( $mustache, 2, -2 );
		}

		$raw = \trim( $raw );

		$this->ValidateExpression( $noEcho ? "$raw;" : "echo $raw;", $node->getLineNo() );

		if( $noEcho )
		{
			$this->expressions[ $this->expressionCount ] = "<?php { $raw; ?>";
		}
		else if( $noEscape )
		{
			$this->expressions[ $this->expressionCount ] = "<?php { echo $raw; ?>";
		}
		else
		{
			// todo: create our own safe function which will handle ints, and accept file/line context for exceptions
			$this->expressions[ $this->expressionCount ] = "<?php { echo \htmlspecialchars($raw, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, 'UTF-8'); ?>";
		}

		$this->expressionCount++;

		if( $remainder !== null )
		{
			$this->HandleMustacheVariables( $remainder );
		}
	}

	private function ValidateExpression( string $expression, int $line ) : void
	{
		try
		{
			$tokens = \PhpToken::tokenize( "<?php $expression", TOKEN_PARSE );
		}
		catch( \Throwable $e )
		{
			throw new SyntaxError( "Expression \"$expression\" failed to parse: {$e->getMessage()}", $line, $e );
		}

		$disallowedTokens =
		[
			T_ATTRIBUTE, // #[
			T_CLASS, // class
			T_CLOSE_TAG, // ?\> or %>
			T_COMMENT, // // or #, and /* */
			T_DECLARE, // declare
			T_DOC_COMMENT, // /** */
			T_END_HEREDOC, // heredoc end
			T_INLINE_HTML, // text outside PHP
			T_OPEN_TAG_WITH_ECHO, // <?= or <%=
			T_OPEN_TAG, // <?php, <? or <%
			T_START_HEREDOC, // <<<
		];

		foreach( $tokens as $i => $token )
		{
			if( $i === 0 )
			{
				if( !$token->is( T_OPEN_TAG ) )
				{
					throw new SyntaxError( "Expression \"$expression\" was misparsed", $line );
				}

				continue;
			}

			if( $token->is( $disallowedTokens ) )
			{
				throw new SyntaxError( "Token {$token->getTokenName()} is disallowed in expression \"$expression\"", $line );
			}

			// @codeCoverageIgnoreStart
			if( $this->Debug )
			{
				echo $token->getTokenName() . ' ';
				print_r( $token );
			}
			// @codeCoverageIgnoreEnd
		}
	}
}
