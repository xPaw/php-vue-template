<?php
declare(strict_types=1);

namespace xPaw\Template;

class Compiler
{
	private const LIBXML_OPTIONS =
		\LIBXML_COMPACT | // Activate small nodes allocation optimization.
		\LIBXML_NOERROR | // Suppress error reports.
		\LIBXML_HTML_NOIMPLIED; // Turn off the automatic adding of implied html/body... elements.

	private const ATTR_IF = 'v-if';
	private const ATTR_ELSE = 'v-else';
	private const ATTR_ELSE_IF = 'v-else-if';
	private const ATTR_FOR = 'v-for';
	private const ATTR_PRE = 'v-pre';
	private const ATTR_MUSTACHE_TAG = 'mustache-tag';

	// https://www.php.net/manual/en/tokens.php
	private const array ASSIGNMENT_OPERATORS =
	[
		'=',              // =
		T_AND_EQUAL,      // &=
		T_COALESCE_EQUAL, // ??=
		T_CONCAT_EQUAL,   // .=
		T_DIV_EQUAL,      // /=
		T_MINUS_EQUAL,    // -=
		T_MOD_EQUAL,      // %=
		T_MUL_EQUAL,      // *=
		T_OR_EQUAL,       // |=
		T_PLUS_EQUAL,     // +=
		T_POW_EQUAL,      // **=
		T_SL_EQUAL,       // <<=
		T_SR_EQUAL,       // >>=
		T_XOR_EQUAL,      // ^=
	];

	private const array ALLOWED_TOKENS =
	[
		'-',
		',',
		':',
		'!',
		'?',
		'.',
		'(',
		')',
		'[',
		']',
		'*',
		'/',
		'&',
		'%',
		'+',
		'<',
		'=',
		'>',
		'|',
		'~',
		'$',
		T_AND_EQUAL,                  // &=
		T_ARRAY_CAST,                 // (array)
		T_BOOL_CAST,                  // (bool) or (boolean)
		T_BOOLEAN_AND,                // &&
		T_BOOLEAN_OR,                 // ||
		T_COALESCE_EQUAL,             // ??=
		T_COALESCE,                   // ??
		T_CONCAT_EQUAL,               // .=
		T_CONSTANT_ENCAPSED_STRING,   // "foo" or 'bar'
		T_DEC,                        // --
		T_DIV_EQUAL,                  // /=
		T_DNUMBER,                    // 0.12, etc.
		T_DOUBLE_ARROW,               // =>
		T_DOUBLE_CAST,                // (real), (double) or (float)
		T_DOUBLE_COLON,               // ::
		T_EMPTY,                      // empty
		T_ENCAPSED_AND_WHITESPACE,    // " $a"
		T_FN,                         // fn (arrow functions)
		T_INC,                        // ++
		T_INT_CAST,                   // (int) or (integer)
		T_IS_EQUAL,                   // ==
		T_IS_GREATER_OR_EQUAL,        // >=
		T_IS_IDENTICAL,               // ===
		T_IS_NOT_EQUAL,               // != or <>
		T_IS_NOT_IDENTICAL,           // !==
		T_IS_SMALLER_OR_EQUAL,        // <=
		T_ISSET,                      // isset()
		T_LNUMBER,                    // 123, 012, 0x1ac, etc.
		T_MINUS_EQUAL,                // -=
		T_MOD_EQUAL,                  // %=
		T_MUL_EQUAL,                  // *=
		T_NAME_FULLY_QUALIFIED,       // \App\Namespace
		T_OBJECT_CAST,                // (object)
		T_OBJECT_OPERATOR,            // ->
		T_OR_EQUAL,                   // |=
		T_PLUS_EQUAL,                 // +=
		T_POW_EQUAL,                  // **=
		T_POW,                        // **
		T_SL_EQUAL,                   // <<=
		T_SL,                         // <<
		T_SPACESHIP,                  // <=>
		T_SR_EQUAL,                   // >>=
		T_SR,                         // >>
		T_STRING_CAST,                // (string)
		T_STRING,                     // parent, self, etc.
		T_UNSET,                      // unset()
		T_VARIABLE,                   // $foo
		T_WHITESPACE,                 // \t \r\n
		T_XOR_EQUAL,                  // ^=
	];

	private \Dom\HTMLDocument $DOM;

	/** @var array<int, string> */
	private array $expressions = [];
	private int $expressionCount = 0;
	private string $expressionTag = 'PHP-EXPRESSION-';

	public bool $Debug = false;

	public function __construct()
	{
		$this->expressionTag .= \strtoupper( \bin2hex( \random_bytes( 6 ) ) );
	}

	public function Parse( string $Data ) : void
	{
		$DOM = \Dom\HTMLDocument::createFromString( $Data, self::LIBXML_OPTIONS, 'UTF-8' );
		$this->HandleDOM( $DOM );
	}

	public function ParseFile( string $Filepath ) : void
	{
		// @codeCoverageIgnoreStart
		if( $this->Debug )
		{
			echo '===== [1]' . PHP_EOL . $Filepath . PHP_EOL;
		}
		// @codeCoverageIgnoreEnd

		$DOM = \Dom\HTMLDocument::createFromFile( $Filepath, self::LIBXML_OPTIONS, 'UTF-8' );
		$this->HandleDOM( $DOM );
	}

	private function HandleDOM( \Dom\HTMLDocument $DOM ) : void
	{
		$this->expressions = [];
		$this->expressionCount = 0;

		/*
		$errors = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors( $previousUseError );

		if( $this->Debug && !empty( $errors ) )
		{
			print_r( $errors );
		}
		*/

		// @codeCoverageIgnoreStart
		if( $this->Debug )
		{
			echo '===== [2]' . PHP_EOL . $DOM->saveHtml() . PHP_EOL;
		}
		// @codeCoverageIgnoreEnd

		$this->DOM = $DOM;
		$this->HandleNode( $this->DOM );
	}

	public function OutputCode() : string
	{
		$this->InsertExpressions( $this->DOM );
		$this->MergeAdjacentExpressions( $this->DOM );

		$code = $this->DOM->saveHtml();

		// @codeCoverageIgnoreStart
		if( $this->Debug )
		{
			echo '===== [3]' . PHP_EOL . $code . PHP_EOL;
		}
		// @codeCoverageIgnoreEnd

		return $code;
	}

	private function InsertExpressions( \Dom\Node $node ) : void
	{
		foreach( $node->childNodes as $childNode )
		{
			if( $childNode instanceof \Dom\HTMLElement )
			{
				$this->InsertExpressions( $childNode );
			}
		}

		if( $node instanceof \Dom\HTMLElement && $node->tagName === $this->expressionTag )
		{
			$expressionId = $node->getAttribute( 'c' );
			$expression = $this->expressions[ $expressionId ] . '?';
			$requiresClosingBracket = \str_ends_with( $expression, '{?' );

			/** @var \Dom\Node[] */
			$newNodes = [];
			$newNodes[] = $this->DOM->createProcessingInstruction( 'php', $expression );

			foreach( $node->childNodes as $childNode )
			{
				$newNodes[] = $childNode;
			}

			if( $requiresClosingBracket )
			{
				$newNodes[] = $this->DOM->createProcessingInstruction( 'php', '}?' );
			}

			$node->replaceWith( ...$newNodes );
		}
	}

	private function MergeAdjacentExpressions( \Dom\Node $node ) : void
	{
		foreach( $node->childNodes as $childNode )
		{
			$this->MergeAdjacentExpressions( $childNode );
		}

		if( $node instanceof \Dom\ProcessingInstruction && $node->target === 'php' )
		{
			while( $node->nextSibling instanceof \Dom\ProcessingInstruction && $node->nextSibling->target === 'php' )
			{
				$node->data = \rtrim( $node->data, '?' ) . \ltrim( $node->nextSibling->data, ' ' );
				$node->nextSibling->remove();
			}
		}
	}

	private function HandleNode( \Dom\Node $parentNode ) : void
	{
		// Use iterator_to_array to iterate over the current children state
		// as the functions will modify the children
		foreach( \iterator_to_array( $parentNode->childNodes ) as $node )
		{
			if( $node instanceof \Dom\ProcessingInstruction )
			{
				throw new SyntaxError( "Processing instruction is not allowed", $node->getLineNo() );
			}

			// TODO: Handle DOM\Comment, it's harder to do because it will split into separate <!-- --> tags, which would break conditional comments
			if( $node instanceof \Dom\Text )
			{
				$this->HandleMustacheVariables( $node );
				continue;
			}

			if( !( $node instanceof \Dom\HTMLElement ) )
			{
				continue;
			}

			if( $node->attributes->length === 0 )
			{
				$this->HandleNode( $node );
				continue;
			}

			// Skip compilation for this element and all its children.
			$attribute = $node->getAttributeNode( self::ATTR_PRE );

			if( $attribute !== null )
			{
				$node->removeAttributeNode( $attribute );

				if( !empty( $attribute->value ) )
				{
					throw new SyntaxError( "Attribute $attribute->name must be empty", $node->getLineNo() );
				}

				continue;
			}

			$this->HandleAttributes( $node );
			$this->HandleNode( $node );
		}
	}

	private function HandleAttributes( \Dom\HTMLElement $node ) : void
	{
		if( $node->parentNode === null )
		{
			throw new \AssertionError( 'This should never happen.' );
		}

		/** @var \Dom\Attr[] $attributes */
		$attributes = [];
		/** @var \Dom\HTMLElement|null $newNode */
		$newNode = null;

		$testPreviousSibling = function( string $name, \Dom\HTMLElement $node, ?\Dom\HTMLElement $newNode )
		{
			if( $newNode !== null )
			{
				throw new SyntaxError( "Do not put $name on the same element that already has {$newNode->getAttribute( 'type' )}", $node->getLineNo() );
			}

			if( !( $node->previousElementSibling instanceof \Dom\HTMLElement ) )
			{
				throw new SyntaxError( 'Previous sibling must be a DOM element', $node->getLineNo() );
			}

			$previousExpressionType = null;

			if( $node->previousElementSibling->tagName === $this->expressionTag )
			{
				$previousExpressionType = $node->previousElementSibling->getAttribute( 'type' );
			}

			if( $previousExpressionType !== self::ATTR_IF && $previousExpressionType !== self::ATTR_ELSE_IF )
			{
				throw new SyntaxError( "Previous sibling element must have " . self::ATTR_IF . " or " . self::ATTR_ELSE_IF, $node->getLineNo() );
			}
		};

		// Conditionally render an element based on the truthy-ness of the expression value.
		$attribute = $node->getAttributeNode( self::ATTR_IF );
		if( $attribute !== null )
		{
			$node->removeAttributeNode( $attribute );

			$raw = \trim( $attribute->value );

			if( empty( $raw ) )
			{
				throw new SyntaxError( "Attribute $attribute->name must not be empty", $node->getLineNo() );
			}

			$tokens = $this->ValidateExpression( "if($raw)", self::ATTR_IF, $node->getLineNo() );

			if( empty( $tokens ) )
			{
				throw new SyntaxError( "Attribute $attribute->name must not be empty", $node->getLineNo() );
			}

			/** @var \Dom\HTMLElement */
			$newNode = $this->DOM->createElement( $this->expressionTag );
			$newNode->setAttribute( 'c', (string)$this->expressionCount );
			$newNode->setAttribute( 'type', $attribute->name );
			$node->parentNode->replaceChild( $newNode, $node );
			$newNode->appendChild( $node );

			$this->expressions[ $this->expressionCount ] = "if($raw){";
			$this->expressionCount++;
		}

		// Denote the "else if block" for v-if. Can be chained.
		// Restriction: previous sibling element must have v-if or v-else-if.
		$attribute = $node->getAttributeNode( self::ATTR_ELSE_IF );
		if( $attribute !== null )
		{
			$node->removeAttributeNode( $attribute );

			$raw = \trim( $attribute->value );

			if( empty( $raw ) )
			{
				throw new SyntaxError( "Attribute $attribute->name must not be empty", $node->getLineNo() );
			}

			$tokens = $this->ValidateExpression( "if($raw)", self::ATTR_ELSE_IF, $node->getLineNo() );

			if( empty( $tokens ) )
			{
				throw new SyntaxError( "Attribute $attribute->name must not be empty", $node->getLineNo() );
			}

			$testPreviousSibling( $attribute->name, $node, $newNode );

			/** @var \Dom\HTMLElement */
			$newNode = $this->DOM->createElement( $this->expressionTag );
			$newNode->setAttribute( 'c', (string)$this->expressionCount );
			$newNode->setAttribute( 'type', $attribute->name );
			$node->parentNode->replaceChild( $newNode, $node );
			$newNode->appendChild( $node );

			$this->expressions[ $this->expressionCount ] = "elseif($raw){";
			$this->expressionCount++;
		}

		// Denote the "else block" for v-if or a v-if / v-else-if chain.
		// Restriction: previous sibling element must have v-if or v-else-if.
		$attribute = $node->getAttributeNode( self::ATTR_ELSE );
		if( $attribute !== null )
		{
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

			$this->expressions[ $this->expressionCount ] = "else{";
			$this->expressionCount++;
		}

		// Render the element multiple times based on the source data.
		$attribute = $node->getAttributeNode( self::ATTR_FOR );
		if( $attribute !== null )
		{
			$node->removeAttributeNode( $attribute );

			if( empty( $attribute->value ) )
			{
				throw new SyntaxError( "Attribute $attribute->name must not be empty", $node->getLineNo() );
			}

			$this->ValidateExpression( "foreach($attribute->value)", self::ATTR_FOR, $node->getLineNo() );

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

			$this->expressions[ $this->expressionCount ] = "foreach($attribute->value){";
			$this->expressionCount++;
		}

		unset( $attribute );

		/** @var \Dom\Attr $attribute */
		foreach( \iterator_to_array( $node->attributes ) as $attribute )
		{
			$node->removeAttributeNode( $attribute );

			// Dynamically bind attribute to an expression.
			if( $attribute->name[ 0 ] !== ':' )
			{
				$attributes[] = $attribute;

				continue;
			}

			$raw = \trim( $attribute->value );
			$tokens = $this->ValidateExpression( $raw, null, $node->getLineNo() );

			if( empty( $tokens ) )
			{
				throw new SyntaxError( 'Attribute is empty', $node->getLineNo() );
			}

			$newAttribute = $this->DOM->createAttribute( \substr( $attribute->name, 1 ) );
			$newAttribute->value = "<?php echo \htmlspecialchars($raw, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, 'UTF-8'); ?>";
			$attributes[] = $newAttribute;
		}

		unset( $attribute );

		// Set new attributes after processing
		foreach( $attributes as $attribute )
		{
			$node->setAttributeNode( $attribute );
		}
	}

	private function HandleMustacheVariables( \Dom\Text $node ) : void
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
			$remainder = $this->DOM->createTextNode( \substr( $node->data, $end ) );
			$node->parentNode->insertBefore( $remainder, $newNode->nextSibling );
		}

		// Truncate the beginning text until the mustache
		if( $start === 0 )
		{
			$node->remove();
		}
		else
		{
			$node->data = \substr( $node->data, 0, $start );
		}

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

		$tokens = $this->ValidateExpression( $raw, self::ATTR_MUSTACHE_TAG, $node->getLineNo() );

		if( empty( $tokens ) )
		{
			throw new SyntaxError( 'Mustache tag is empty', $node->getLineNo() );
		}

		if( !$this->ExpressionShouldEcho( $tokens ) )
		{
			if( $noEcho )
			{
				throw new SyntaxError( "Mustache tags with assigments should not use {{= modifier", $node->getLineNo() );
			}

			if( $noEscape )
			{
				throw new SyntaxError( "Mustache tags with assigments should not use {{{ modifier", $node->getLineNo() );
			}

			$noEcho = true;
		}

		if( $noEcho )
		{
			$this->expressions[ $this->expressionCount ] = "$raw;";
		}
		else if( $noEscape )
		{
			$this->expressions[ $this->expressionCount ] = "echo $raw;";
		}
		else
		{
			// todo: create our own safe function which will handle ints, and accept file/line context for exceptions
			$this->expressions[ $this->expressionCount ] = "echo \htmlspecialchars($raw, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, 'UTF-8');";
		}

		$this->expressionCount++;

		if( $remainder !== null )
		{
			$this->HandleMustacheVariables( $remainder );
		}
	}

	/**
	 * @param self::ATTR_* $tokenType
	 * @return \PhpToken[]
	 */
	private function ValidateExpression( string $expression, ?string $tokenType, int $line ) : array
	{
		try
		{
			$tokens = \PhpToken::tokenize( "<?php {$expression}?>", TOKEN_PARSE );
		}
		catch( \Throwable $e )
		{
			throw new SyntaxError( "Expression \"$expression\" failed to parse: {$e->getMessage()}", $line, $e );
		}

		$token = \array_shift( $tokens );
		if( $token === null || !$token->is( T_OPEN_TAG ) )
		{
			throw new SyntaxError( "Expression \"$expression\" was misparsed", $line );
		}

		$token = \array_pop( $tokens );
		if( $token === null || !$token->is( T_CLOSE_TAG ) )
		{
			throw new SyntaxError( "Expression \"$expression\" was misparsed", $line );
		}

		if( $tokenType === self::ATTR_IF || $tokenType === self::ATTR_ELSE_IF )
		{
			$token = \array_shift( $tokens );
			if( $token === null || !$token->is( T_IF ) )
			{
				throw new SyntaxError( "Expression \"$expression\" was misparsed", $line );
			}
		}

		if( $tokenType === self::ATTR_FOR )
		{
			$token = \array_shift( $tokens );
			if( $token === null || !$token->is( T_FOREACH ) )
			{
				throw new SyntaxError( "Expression \"$expression\" was misparsed", $line );
			}
		}

		unset( $token );

		foreach( $tokens as $token )
		{
			// @codeCoverageIgnoreStart
			if( $this->Debug )
			{
				echo $token->getTokenName() . ' ';
				print_r( $token );
			}
			// @codeCoverageIgnoreEnd

			if( $tokenType === self::ATTR_FOR && $token->is( T_AS ) )
			{
				continue;
			}

			if( $tokenType === self::ATTR_MUSTACHE_TAG && $token->is( '"' ) )
			{
				continue;
			}

			if( !$token->is( self::ALLOWED_TOKENS ) )
			{
				throw new SyntaxError( "Token {$token->getTokenName()} is disallowed in expression \"{$expression}\"", $line );
			}
		}

		return $tokens;
	}

	/** @param \PhpToken[] $tokens */
	private function ExpressionShouldEcho( array $tokens ) : bool
	{
		if( $tokens[ 0 ]->is( \T_UNSET ) ) // unset( $var );
		{
			return false;
		}

		foreach( $tokens as $token )
		{
			if( $token->is( self::ASSIGNMENT_OPERATORS ) )
			{
				return false;
			}
		}

		return true;
	}
}
