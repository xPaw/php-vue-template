<?php
declare(strict_types=1);

use xPaw\Template\Compiler;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class VariableReplaceTest extends TestCase
{
	/**
	 * Helper method to compile template and get the output code
	 */
	private static function code(string $input): string
	{
		$compiler = new Compiler();
		$compiler->Parse($input);
		return $compiler->OutputCode();
	}

	/**
	 * Tests basic dot notation for object properties in Mustache expressions
	 */
	#[DataProvider('provideMustacheExpressions')]
	public function testMustacheExpressions(string $expression, string $expected): void
	{
		$input = "<span>{{ $expression }}</span>";
		$output = self::code($input);
		static::assertEquals("<span><?php echo \htmlspecialchars($expected, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, 'UTF-8');?></span>", $output);
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function provideMustacheExpressions(): array
	{
		return [
			'simple property access' => [
				'$user.name',
				'$user[\'name\']'
			],
			'multiple property access' => [
				'$user.profile.email',
				'$user[\'profile\'][\'email\']'
			],
			'mixed notation' => [
				'$user[\'profile\'].email',
				'$user[\'profile\'][\'email\']'
			],
			'dot notation with numeric index' => [
				'$items.0.name',
				'$items[0][\'name\']'
			],
			'consecutive numeric indices' => [
				'$matrix.0.1.2',
				'$matrix[0][1][2]'
			],
			'variable as property' => [
				'$user.$property',
				'$user[$property]'
			],
			'property with variables' => [
				'$user.name.$suffix',
				'$user[\'name\'][$suffix]'
			],
			'string with dots (unchanged)' => [
				'"user.profile.name"',
				'"user.profile.name"'
			],
			'single quoted string with dots' => [
				'\'property.path\'',
				'\'property.path\''
			],
			/* TODO: This currently fails
			'dot in method call (unchanged)' => [
				'$object->method("path.to.file")',
				'$object->method("path.to.file")'
			],
			*/
			'conditional expression' => [
				'$user.active ? $user.name : "Anonymous"',
				'$user[\'active\'] ? $user[\'name\'] : "Anonymous"'
			],
			'property in function arg' => [
				'strtoupper($user.name)',
				'strtoupper($user[\'name\'])'
			],
			'property name with underscores' => [
				'$user.first_name',
				'$user[\'first_name\']'
			],
			'property name with numbers' => [
				'$item.option1',
				'$item[\'option1\']'
			],
			'decimal point (unchanged)' => [
				'$value * 1.5',
				'$value * 1.5'
			],
		];
	}

	/**
	 * Tests for special mustache syntax (unescaped and no-echo)
	 */
	#[DataProvider('provideSpecialMustache')]
	public function testSpecialMustache(string $input, string $expected): void
	{
		$output = self::code($input);
		static::assertEquals($expected, $output);
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function provideSpecialMustache(): array
	{
		return [
			'unescaped output' => [
				'<span>{{{ $user.name }}}</span>',
				'<span><?php echo $user[\'name\'];?></span>'
			],
			'assignment with no echo' => [
				'<span>{{ $selected = $user.preferences.theme }}</span>',
				'<span><?php $selected = $user[\'preferences\'][\'theme\'];?></span>'
			]
		];
	}

	/**
	 * Tests dot notation in v-if conditions
	 */
	#[DataProvider('provideConditions')]
	public function testConditions(string $condition, string $expected): void
	{
		$input = "<test><span v-if=\"$condition\">Text</span></test>";
		$output = self::code($input);
		static::assertEquals("<test><?php if($expected){?><span>Text</span><?php }?></test>", $output);
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function provideConditions(): array
	{
		return [
			'simple condition' => [
				'$user.isAdmin',
				'$user[\'isAdmin\']'
			],
			'complex condition' => [
				"\$user.role === 'admin' && \$user.permissions.canEdit",
				"\$user['role'] === 'admin' && \$user['permissions']['canEdit']"
			],
			'ternary operator' => [
				'$item.status ? $item.active.state : $item.inactive.state',
				'$item[\'status\'] ? $item[\'active\'][\'state\'] : $item[\'inactive\'][\'state\']'
			]
		];
	}

	/**
	 * Tests dot notation in v-else-if conditions
	 */
	public function testElseIfCondition(): void
	{
		$input = "<div><span v-if=\"\$user.isAdmin\">Admin</span><span v-else-if=\"\$user.role.name === 'editor'\">Editor</span></div>";
		$expected = "<div><?php if(\$user['isAdmin']){?><span>Admin</span><?php }elseif(\$user['role']['name'] === 'editor'){?><span>Editor</span><?php }?></div>";

		$output = self::code($input);
		static::assertEquals($expected, $output);
	}

	/**
	 * Tests dot notation in v-for loops
	 */
	#[DataProvider('provideLoops')]
	public function testLoops(string $loop, string $expected): void
	{
		$input = "<test><span v-for=\"$loop\">Item</span></test>";
		$output = self::code($input);
		static::assertEquals("<test><?php foreach($expected){?><span>Item</span><?php }?></test>", $output);
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function provideLoops(): array
	{
		return [
			'loop through property' => [
				'$user.items as $item',
				'$user[\'items\'] as $item'
			],
			'loop with key' => [
				'$user.items as $key => $item',
				'$user[\'items\'] as $key => $item'
			],
			'nested property' => [
				'$user.profile.permissions as $permission',
				'$user[\'profile\'][\'permissions\'] as $permission'
			]
		];
	}

	/**
	 * Tests dot notation in dynamic attributes
	 */
	#[DataProvider('provideDynamicAttributes')]
	public function testDynamicAttributes(string $attribute, string $expected): void
	{
		$input = "<span :class=\"$attribute\">Text</span>";
		$output = self::code($input);
		static::assertEquals("<span class=\"<?php echo \htmlspecialchars($expected, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, 'UTF-8'); ?>\">Text</span>", $output);
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function provideDynamicAttributes(): array
	{
		return [
			'simple property' => [
				'$user.role',
				'$user[\'role\']'
			],
			'conditional class' => [
				"\$user.isAdmin ? 'admin' : 'user'",
				"\$user['isAdmin'] ? 'admin' : 'user'",
			],
			'nested property' => [
				'$user.profile.theme',
				'$user[\'profile\'][\'theme\']'
			]
		];
	}

	/**
	 * Tests v-pre directive (dot notation should be untouched)
	 */
	public function testPreDirective(): void
	{
		$input = '<span v-pre>{{ $user.name }}</span>';
		$expected = '<span>{{ $user.name }}</span>';

		$output = self::code($input);
		static::assertEquals($expected, $output);
	}
}
