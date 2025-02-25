<?php
declare(strict_types=1);

use xPaw\Template\Compiler;
use xPaw\Template\SyntaxError;
use PHPUnit\Framework\TestCase;

final class SyntaxErrorTest extends TestCase
{
	private static function code( string $Input ) : string
	{
		$Template = new Compiler();
		$Template->Parse( $Input );

		return $Template->OutputCode();
	}

	public function testIfElse(): void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( 'Do not put v-else on the same element that already has v-if' );

		self::code( '<div v-if="$test" v-else></div>' );
	}

	public function testIfElseIf(): void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( 'Do not put v-else-if on the same element that already has v-if' );

		self::code( '<div v-if="$test" v-else-if="$test"></div>' );
	}

	public function testElseNoIf(): void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( 'Previous sibling element must have v-if or v-else-if' );

		self::code( '<div></div><div v-else></div>' );
	}

	public function testElseIfNoIf(): void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( 'Previous sibling element must have v-if or v-else-if' );

		self::code( '<div></div><div v-else-if="$test"></div>' );
	}

	public function testElseIfNoIfElement(): void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( 'Previous sibling must be a DOM element' );

		self::code( '<div>test<div v-else-if="$test"></div></div>' );
	}

	public function testIfNoCondition(): void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( 'Attribute v-if must not be empty' );

		self::code( '<div v-if></div>' );
	}

	public function testElseIfNoCondition(): void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( 'Attribute v-else-if must not be empty' );

		self::code( '<div v-else-if></div>' );
	}

	public function testForNoCondition(): void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( 'Attribute v-for must not be empty' );

		self::code( '<div v-for></div>' );
	}

	public function testElseCondition(): void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( 'Attribute v-else must be empty' );

		self::code( '<div v-else="$test"></div>' );
	}

	public function testPreCondition(): void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( 'Attribute v-pre must be empty' );

		self::code( '<div v-pre="$test"></div>' );
	}

	public function testNestedMustache(): void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( 'Opening mustache tag at position 3, but a tag was already open at position 0' );

		self::code( '<div>{{ {{ $tag }} }}</div>' );
	}

	public function testUnclosedMustache(): void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( 'Opening mustache tag at position 0, but it was never closed' );

		self::code( '<div>{{ $tag</div>' );
	}

	public function testUnopenedMustache(): void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( 'Closing mustache tag at position 5, but it was never opened' );

		self::code( '<div>$tag }}</div>' );
	}

	public function testEmptyMustache(): void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( 'Mustache tag is empty' );

		self::code( '<div>{{}}</div>' );
	}

	public function testSpaceMustache(): void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( 'Mustache tag is empty' );

		self::code( '<div>{{ }}</div>' );
	}

	public function testEmptyNoEscapeMustache(): void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( 'Mustache tag is empty' );

		self::code( '<div>{{{}}}</div>' );
	}

	public function testSpaceNoEscapeMustache(): void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( 'Mustache tag is empty' );

		self::code( '<div>{{{ }}}</div>' );
	}

	/*
	public function testPhpTag() : void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( 'Processing instruction is not allowed' );

		self::code( '<?php echo "hello"; ?>' );
	}
	*/

	public function testBadPhp() : void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( 'Expression ", "test"" failed to parse: syntax error, unexpected token ",", expecting end of file' );

		self::code( '<div>{{ , "test" }}</div>' );
	}

	public function testPhpNoCloseTag() : void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( 'Expression "1?>" was misparsed' );

		self::code( '<div>{{ 1?> }}</div>' );
	}

	public function testPhpNoMultilineComment() : void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( 'Token T_COMMENT is disallowed in expression "1/* test */"' );

		self::code( '<div>{{ 1/* test */ }}</div>' );
	}

	public function testPhpNoComment() : void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( "Token T_COMMENT is disallowed in expression \"1//\n;\"" );

		self::code( "<div>{{ 1//\n; }}</div>" );
	}

	public function testPhpNoClass() : void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( 'Token T_CLASS is disallowed in expression "class HelloWorld {}"' );

		self::code( '<div>{{= class HelloWorld {} }}</div>' );
	}

	public function testPhpNoDeclare() : void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( 'Token T_DECLARE is disallowed in expression "declare(strict_types=1)"' );

		self::code( '<div>{{= declare(strict_types=1) }}</div>' );
	}

	public function testPhpNoAttribute() : void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( 'Token T_ATTRIBUTE is disallowed in expression "#[HelloWorld] class HelloWorld {}"' );

		self::code( '<div>{{= #[HelloWorld] class HelloWorld {} }}</div>' );
	}

	public function testBasicAssignmentModifier(): void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( 'Mustache tags with assigments should not use {{= modifier' );

		self::code( '<div>{{= $var = 123 }}</div>' );
	}

	public function testBasicAssignmentNoEscape(): void
	{
		$this->expectException( SyntaxError::class );
		$this->expectExceptionMessage( 'Mustache tags with assigments should not use {{{ modifier' );

		self::code( '<div>{{{ $var = 123 }}}</div>' );
	}
}
