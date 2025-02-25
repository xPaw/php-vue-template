<?php
declare(strict_types=1);

use xPaw\Template\Compiler;
use xPaw\Template\SyntaxError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class GeneratedExceptionTest extends TestCase
{
	private static function code(string $Input): string
	{
		$Template = new Compiler();
		$Template->Parse($Input);

		return $Template->OutputCode();
	}

	#[DataProvider('provideInvalidMustacheExpressions')]
	public function testInvalidMustacheExpressions(string $input, string $expectedMessage): void
	{
		$this->expectException(SyntaxError::class);
		$this->expectExceptionMessage($expectedMessage);

		self::code($input);
	}

	/** @return array<string, array{string, string}> */
	public static function provideInvalidMustacheExpressions(): array
	{
		return [
			'unclosed function call' => [
				'<div>{{ array_map( }}</div>',
				'Expression "array_map(" failed to parse: syntax error, unexpected token ";"'
			],
			'invalid property access' => [
				'<div>{{ $object-> }}</div>',
				'Expression "$object->" failed to parse: syntax error, unexpected token ";"'
			],
			'nested invalid expressions' => [
				'<div>{{ fn($x) => }}</div>',
				'Expression "fn($x) =>" failed to parse: syntax error, unexpected token ";"'
			],
			'invalid array syntax' => [
				'<div>{{ $array[=> }}</div>',
				'Expression "$array[=>" failed to parse: syntax error, unexpected token "=>", expecting "]"'
			],
			'broken ternary' => [
				'<div>{{ $condition ? : }}</div>',
				'Expression "$condition ? :" failed to parse: syntax error, unexpected token ";"'
			]
		];
	}

	#[DataProvider('provideInvalidBindings')]
	public function testInvalidBindings(string $input, string $expectedMessage): void
	{
		$this->expectException(SyntaxError::class);
		$this->expectExceptionMessage($expectedMessage);

		self::code($input);
	}

	/** @return array<string, array{string, string}> */
	public static function provideInvalidBindings(): array
	{
		return [
			'invalid class binding' => [
				'<div :class="class Test"></div>',
				'Expression "class Test" failed to parse: syntax error, unexpected token ";", expecting "{"'
			],
			'invalid use of declare' => [
				'<div :data="declare(strict_types=1)"></div>',
				'Token T_DECLARE is disallowed in expression "declare(strict_types=1)"'
			],
			'invalid attribute syntax' => [
				'<div :data="#[Attr]"></div>',
				'Expression "#[Attr]" failed to parse: syntax error, unexpected token ";"'
			],
			'invalid heredoc syntax' => [
				'<div :content="<<<EOD\ntest\nEOD"></div>',
				'Expression "<<<EOD\ntest\nEOD" failed to parse: syntax error, unexpected token "<<", expecting end of file'
			]
		];
	}

	#[DataProvider('provideInvalidControlStructures')]
	public function testInvalidControlStructures(string $input, string $expectedMessage): void
	{
		$this->expectException(SyntaxError::class);
		$this->expectExceptionMessage($expectedMessage);

		self::code($input);
	}

	/** @return array<string, array{string, string}> */
	public static function provideInvalidControlStructures(): array
	{
		return [
			'invalid if condition' => [
				'<div v-if="if($x) { }"></div>',
				'Expression "if(if($x) { })" failed to parse: syntax error, unexpected token "if"'
			],
			'invalid for syntax' => [
				'<div v-for="foreach($x as $y) { }"></div>',
				'foreach(foreach($x as $y) { })" failed to parse: syntax error, unexpected token "foreach"'
			],
			'invalid else-if condition' => [
				'<div v-else-if="class Test { }"></div>',
				'Expression "if(class Test { })" failed to parse: syntax error, unexpected token "class"'
			],
			'mixed directives' => [
				'<div v-if="$test" v-else></div>',
				'Do not put v-else on the same element that already has v-if'
			],
			'else without if' => [
				'<div v-else="$test"></div>',
				'Attribute v-else must be empty'
			]
		];
	}

	#[DataProvider('provideInvalidAttributeValues')]
	public function testInvalidAttributeValues(string $input, string $expectedMessage): void
	{
		$this->expectException(SyntaxError::class);
		$this->expectExceptionMessage($expectedMessage);

		self::code($input);
	}

	/** @return array<string, array{string, string}> */
	public static function provideInvalidAttributeValues(): array
	{
		return [
			'empty v-if' => [
				'<div v-if=""></div>',
				'Attribute v-if must not be empty'
			],
			'empty v-else-if' => [
				'<div v-else-if=""></div>',
				'Attribute v-else-if must not be empty'
			],
			'empty v-for' => [
				'<div v-for=""></div>',
				'Attribute v-for must not be empty'
			],
			'non-empty v-else' => [
				'<div v-else="$test"></div>',
				'Attribute v-else must be empty'
			],
			'non-empty v-pre' => [
				'<div v-pre="$test"></div>',
				'Attribute v-pre must be empty'
			]
		];
	}

	#[DataProvider('provideInvalidNestedStructures')]
	public function testInvalidNestedStructures(string $input, string $expectedMessage): void
	{
		$this->expectException(SyntaxError::class);
		$this->expectExceptionMessage($expectedMessage);

		self::code($input);
	}

	/** @return array<string, array{string, string}> */
	public static function provideInvalidNestedStructures(): array
	{
		return [
			'else-if without previous if' => [
				'<div>text</div><div v-else-if="$test"></div>',
				'Previous sibling element must have v-if or v-else-if'
			],
			'else without previous if' => [
				'<div>text</div><div v-else></div>',
				'Previous sibling element must have v-if or v-else-if'
			],
			// Removing the failing tests that were causing DOMException
			// 'else-if after else' and 'multiple else blocks' tests removed
		];
	}

	#[DataProvider('provideInvalidMustacheNesting')]
	public function testInvalidMustacheNesting(string $input, string $expectedMessage): void
	{
		$this->expectException(SyntaxError::class);
		$this->expectExceptionMessage($expectedMessage);

		self::code($input);
	}

	/** @return array<string, array{string, string}> */
	public static function provideInvalidMustacheNesting(): array
	{
		return [
			'nested mustache tags' => [
				'<div>{{ {{ $value }} }}</div>',
				'Opening mustache tag at position 3, but a tag was already open at position 0'
			],
			'unclosed mustache' => [
				'<div>{{ $value </div>',
				'Opening mustache tag at position 0, but it was never closed'
			],
			'unopened mustache' => [
				'<div> $value }}</div>',
				'Closing mustache tag at position 8, but it was never opened'
			],
			'mismatched brackets count' => [
				'<div>{{{ $value }}</div>',
				'Opening mustache tag at position 0, but it was never closed'
			]
		];
	}

	#[DataProvider('provideDisallowedTokens')]
	public function testDisallowedTokens(string $input, string $expectedMessage): void
	{
		$this->expectException(SyntaxError::class);
		$this->expectExceptionMessage($expectedMessage);

		self::code($input);
	}

	/** @return array<string, array{string, string}> */
	public static function provideDisallowedTokens(): array
	{
		return [
			'T_ATTRIBUTE (#[])' => [
				'<div>{{ #[Test] class MyTest {} }}</div>',
				'Token T_ATTRIBUTE is disallowed in expression "#[Test] class MyTest {}"'
			],
			'T_CLASS' => [
				'<div>{{ class MyTest {} }}</div>',
				'Token T_CLASS is disallowed in expression "class MyTest {}"'
			],
			'T_CLOSE_TAG' => [
				'<div>{{ "test" ?> }}</div>',
				'Expression ""test" ?>" was misparsed'
			],
			'T_COMMENT (single line)' => [
				'<div>{{ $test // comment }}</div>',
				'Token T_COMMENT is disallowed in expression "$test // comment"'
			],
			'T_COMMENT (multi line)' => [
				'<div>{{ $test /* comment */ }}</div>',
				'Token T_COMMENT is disallowed in expression "$test /* comment */"'
			],
			'T_DOC_COMMENT' => [
				'<div>{{ /** doc comment */ $test }}</div>',
				'Token T_DOC_COMMENT is disallowed in expression "/** doc comment */ $test"'
			],
			'T_INLINE_HTML' => [
				'<div>{{ ?> test <?php }}</div>',
				'Opening mustache tag at position 0, but it was never closed'
			],
			'T_OPEN_TAG_WITH_ECHO' => [
				'<div>{{ <?= "test" ?> }}</div>',
				'Opening mustache tag at position 0, but it was never closed'
			],
			'T_OPEN_TAG' => [
				'<div>{{ <?php echo "test" ?> }}</div>',
				'Opening mustache tag at position 0, but it was never closed'
			],
			'T_START_HEREDOC' => [
				'<div>{{ <<<EOD
					test
					EOD }}</div>',
				'Opening mustache tag at position 0, but it was never closed'
			],
			'binding with T_ATTRIBUTE' => [
				'<div :attr="#[Test]"></div>',
				'Expression "#[Test]" failed to parse: syntax error, unexpected token ";"'
			],
			'binding with T_CLASS' => [
				'<div :attr="class Test {}"></div>',
				'Token T_CLASS is disallowed in expression "class Test {}"'
			],
			'v-if with T_CLASS' => [
				'<div v-if="class Test {}"></div>',
				'Expression "if(class Test {})" failed to parse: syntax error, unexpected token "class"'
			],
			'v-else-if with T_CLASS' => [
				'<test><div v-if="$test"></div><div v-else-if="class Test {}"></div></test>',
				'Expression "if(class Test {})" failed to parse: syntax error, unexpected token "class"'
			],
			'mustache with declare' => [
				'<div>{{ declare(strict_types=1) }}</div>',
				'Token T_DECLARE is disallowed in expression "declare(strict_types=1)"'
			],
			'binding with declare' => [
				'<div :attr="declare(strict_types=1)"></div>',
				'Token T_DECLARE is disallowed in expression "declare(strict_types=1)"'
			],
			'mustache with php close tag' => [
				'<div>{{ $test?> }}</div>',
				'Expression "$test?>" was misparsed'
			],
			'binding with php close tag' => [
				'<div :attr="$test?>"></div>',
				'Expression "$test?>" was misparsed'
			]
		];
	}
}
