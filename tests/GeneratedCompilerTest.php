<?php
declare(strict_types=1);

use xPaw\Template\Compiler;
use xPaw\Template\SyntaxError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class GeneratedCompilerTest extends TestCase
{
	private static function code(string $Input): string
	{
		$Template = new Compiler();
		$Template->Parse($Input);

		return $Template->OutputCode();
	}

	#[DataProvider('provideNestedControlStructures')]
	public function testNestedControlStructures(string $input, string $expected): void
	{
		static::assertEquals($expected, self::code($input));
	}

	/** @return array<string, string[]> */
	public static function provideNestedControlStructures(): array
	{
		return [
			'nested if conditions' => [
				'<test><div v-if="$outer"><span v-if="$inner">content</span></div></test>',
				'<test><?php if($outer){?><div><?php if($inner){?><span>content</span><?php }?></div><?php }?></test>',
			],
			'if-else inside for' => [
				'<test><ul v-for="$items as $item"><li v-if="$item->visible">{{ $item->name }}</li><li v-else>Hidden item</li></ul></test>',
				'<test><?php foreach($items as $item){?><ul><?php if($item->visible){?><li><?php echo \htmlspecialchars($item->name, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></li><?php }else{?><li>Hidden item</li><?php }?></ul><?php }?></test>',
			],
			'for inside if with else' => [
				'<test><div v-if="$hasItems"><span v-for="$items as $item">{{ $item }}</span></div><div v-else>No items</div></test>',
				'<test><?php if($hasItems){?><div><?php foreach($items as $item){?><span><?php echo \htmlspecialchars($item, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></span><?php }?></div><?php }else{?><div>No items</div><?php }?></test>',
			]
		];
	}

	#[DataProvider('provideMustacheExpressions')]
	public function testComplexMustacheExpressions(string $input, string $expected): void
	{
		static::assertEquals($expected, self::code($input));
	}

	/** @return array<string, string[]> */
	public static function provideMustacheExpressions(): array
	{
		return [
			'ternary operator' => [
				'<span>{{ $condition ? "yes" : "no" }}</span>',
				'<span><?php echo \htmlspecialchars($condition ? "yes" : "no", \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></span>'
			],
			'method call' => [
				'<span>{{ $object->method($param) }}</span>',
				'<span><?php echo \htmlspecialchars($object->method($param), \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></span>'
			],
			'array access' => [
				'<span>{{ $array[$index] }}</span>',
				'<span><?php echo \htmlspecialchars($array[$index], \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></span>'
			],
			'string concatenation' => [
				'<span>{{ "Prefix: " . $value }}</span>',
				'<span><?php echo \htmlspecialchars("Prefix: " . $value, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></span>'
			]
		];
	}

	#[DataProvider('provideAttributeBindings')]
	public function testComplexAttributeBindings(string $input, string $expected): void
	{
		static::assertEquals($expected, self::code($input));
	}

	/** @return array<string, string[]> */
	public static function provideAttributeBindings(): array
	{
		return [
			'multiple bindings' => [
				'<div :class="$isActive ? \'active\' : \'\'" :style="$style" :data-id="$id"></div>',
				'<div class="<?php echo \htmlspecialchars($isActive ? \'active\' : \'\', \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\'); ?>" style="<?php echo \htmlspecialchars($style, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\'); ?>" data-id="<?php echo \htmlspecialchars($id, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\'); ?>"></div>'
			],
			'mixed static and dynamic' => [
				'<div class="static" :class="$dynamicClass" id="static-id" :data-dynamic="$value"></div>',
				'<div class="<?php echo \htmlspecialchars($dynamicClass, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\'); ?>" id="static-id" data-dynamic="<?php echo \htmlspecialchars($value, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\'); ?>"></div>'
			]
		];
	}

	public function testComplexForLoop(): void
	{
		$input = '<test><ul v-for="$users as $id => $user"><li :data-id="$id"><span v-if="$user->isAdmin">Admin: </span>{{ $user->name }}<small v-if="$user->isOnline">online</small></li></ul></test>';
		$expected = '<test><?php foreach($users as $id => $user){?><ul><li data-id="<?php echo \htmlspecialchars($id, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\'); ?>"><?php if($user->isAdmin){?><span>Admin: </span><?php }echo \htmlspecialchars($user->name, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');if($user->isOnline){?><small>online</small><?php }?></li></ul><?php }?></test>';

		static::assertEquals($expected, self::code($input));
	}

	public function testPreWithComplexContent(): void
	{
		$input = '<div v-pre><span v-if="$condition">{{ $value }}</span><span v-for="$items as $item">{{ $item }}</span><span :class="$cls">{{ $text }}</span></div>';
		$expected = '<div><span v-if="$condition">{{ $value }}</span><span v-for="$items as $item">{{ $item }}</span><span :class="$cls">{{ $text }}</span></div>';

		static::assertEquals($expected, self::code($input));
	}

	#[DataProvider('provideInvalidSyntax')]
	public function testInvalidSyntax(string $input, string $expectedMessage): void
	{
		$this->expectException(SyntaxError::class);
		$this->expectExceptionMessage($expectedMessage);

		self::code($input);
	}

	/** @return array<string, string[]> */
	public static function provideInvalidSyntax(): array
	{
		return [
			'invalid PHP in mustache' => [
				'<div>{{ $obj-> }}</div>',
				'Expression "$obj->" failed to parse: syntax error, unexpected token ";", expecting identifier or variable or "{" or "$"'
			],
			'invalid for loop syntax' => [
				'<div v-for="$items as"></div>',
				'Expression "foreach($items as)" failed to parse: syntax error, unexpected token ")"'
			],
			'v-else without previous v-if' => [
				'<div>text</div><span v-else></span>',
				'Previous sibling element must have v-if or v-else-if'
			]
		];
	}

	public function testMultipleMustachesWithHtml(): void
	{
		$input = '<div>Start {{ $first }} <b>bold {{ $second }}</b> middle<i>italic {{ $third }}</i> {{ $fourth }} end</div>';
		$expected = '<div>Start <?php echo \htmlspecialchars($first, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?> <b>bold <?php echo \htmlspecialchars($second, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></b> middle<i>italic <?php echo \htmlspecialchars($third, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></i> <?php echo \htmlspecialchars($fourth, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?> end</div>';

		static::assertEquals($expected, self::code($input));
	}

	public function testDeepNestedStructure(): void
	{
		$input = '<test><div v-if="$level1"><div v-for="$items1 as $item1">{{ $item1->title }}<div v-if="$item1->hasChildren"><ul v-for="$item1->children as $item2"><li v-if="$item2->isVisible">{{ $item2->name }}</li></ul></div></div></div></test>';
		$expected = '<test><?php if($level1){?><div><?php foreach($items1 as $item1){?><div><?php echo \htmlspecialchars($item1->title, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');if($item1->hasChildren){?><div><?php foreach($item1->children as $item2){?><ul><?php if($item2->isVisible){?><li><?php echo \htmlspecialchars($item2->name, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></li><?php }?></ul><?php }?></div><?php }?></div><?php }?></div><?php }?></test>';

		static::assertEquals($expected, self::code($input));
	}

	#[DataProvider('provideWhitespaceHandling')]
	public function testWhitespaceHandling(string $input, string $expected): void
	{
		static::assertEquals($expected, self::code($input));
	}

	/** @return array<string, string[]> */
	public static function provideWhitespaceHandling(): array
	{
		return [
			'newlines in mustache' => [
				'<test><div>{{
					$variable
				}}</div></test>',
				'<test><div><?php echo \htmlspecialchars($variable, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></div></test>'
			],
			'spaces in directives' => [
				'<test><div v-if = "$condition" v-for = "$items as $item"></div></test>',
				'<test><?php if($condition){foreach($items as $item){?><div></div><?php }}?></test>'
			],
			'indented nested structure' => [
				"<test><div v-if=\"\$show\">\n\t<span>\n\t\t{{\$text}}\n\t</span>\n</div></test>",
				"<test><?php if(\$show){?><div>\n\t<span>\n\t\t<?php echo \htmlspecialchars(\$text, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, 'UTF-8');?>\n\t</span>\n</div><?php }?></test>"
			]
		];
	}

	#[DataProvider('provideComplexMustacheOperations')]
	public function testComplexMustacheOperations(string $input, string $expected): void
	{
		static::assertEquals($expected, self::code($input));
	}

	/** @return array<string, string[]> */
	public static function provideComplexMustacheOperations(): array
	{
		return [
			'array operations' => [
				'<div>{{ array_map(fn($x) => $x * 2, $items) }}</div>',
				'<div><?php echo \htmlspecialchars(array_map(fn($x) => $x * 2, $items), \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></div>'
			],
			'nested method calls' => [
				'<div>{{ $object->getChild()->getValue()->format() }}</div>',
				'<div><?php echo \htmlspecialchars($object->getChild()->getValue()->format(), \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></div>'
			],
			'complex math' => [
				'<div>{{ ($a + $b) * $c / ($d ?: 1) }}</div>',
				'<div><?php echo \htmlspecialchars(($a + $b) * $c / ($d ?: 1), \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></div>'
			],
			'string operations' => [
				'<div>{{ trim(strtoupper($text)) . " - " . strtolower($title) }}</div>',
				'<div><?php echo \htmlspecialchars(trim(strtoupper($text)) . " - " . strtolower($title), \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></div>'
			]
		];
	}

	#[DataProvider('provideNestedLoopEdgeCases')]
	public function testNestedLoopEdgeCases(string $input, string $expected): void
	{
		static::assertEquals($expected, self::code($input));
	}

	/** @return array<string, string[]> */
	public static function provideNestedLoopEdgeCases(): array
	{
		return [
			'triple nested loops' => [
				'<test><div v-for="$a as $x"><div v-for="$x->items as $y"><div v-for="$y->sub as $z">{{$z}}</div></div></div></test>',
				'<test><?php foreach($a as $x){?><div><?php foreach($x->items as $y){?><div><?php foreach($y->sub as $z){?><div><?php echo \htmlspecialchars($z, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></div><?php }?></div><?php }?></div><?php }?></test>'
			],
			'loop with key and conditions' => [
				'<test><div v-for="$items as $key => $item"><span v-if="$key % 2 === 0">Even</span><span v-else>Odd</span>{{$item}}</div></test>',
				'<test><?php foreach($items as $key => $item){?><div><?php if($key % 2 === 0){?><span>Even</span><?php }else{?><span>Odd</span><?php }echo \htmlspecialchars($item, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></div><?php }?></test>'
			]
		];
	}

	#[DataProvider('provideComplexAttributeCombinations')]
	public function testComplexAttributeCombinations(string $input, string $expected): void
	{
		static::assertEquals($expected, self::code($input));
	}

	/** @return array<string, string[]> */
	public static function provideComplexAttributeCombinations(): array
	{
		return [
			'multiple dynamic attributes' => [
				'<div :id="\'prefix-\' . $id" :class="$isActive ? \'active\' : \'\'" :style="$style" :data-custom="json_encode($data)"></div>',
				'<div id="<?php echo \htmlspecialchars(\'prefix-\' . $id, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\'); ?>" class="<?php echo \htmlspecialchars($isActive ? \'active\' : \'\', \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\'); ?>" style="<?php echo \htmlspecialchars($style, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\'); ?>" data-custom="<?php echo \htmlspecialchars(json_encode($data), \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\'); ?>"></div>'
			]
		];
	}

	public function testComplexNestedStructureWithMultipleConditions(): void
	{
		$input = '<test><div v-if="$level1"><div v-for="$items1 as $item1"><div v-if="$item1->type === \'group\'"><h2>{{ $item1->title }}</h2><ul v-if="!empty($item1->children)"><li v-for="$item1->children as $child" :class="$child->status"><span v-if="$child->isHighlighted" class="highlight">★</span>{{ $child->name }}<small v-if="$child->hasDetails">({{ $child->details }})</small></li></ul><p v-else>No children available</p></div><div v-else-if="$item1->type === \'single\'">{{ $item1->content }}</div><div v-else>Unknown type</div></div></div></test>';
		$expected = '<test><?php if($level1){?><div><?php foreach($items1 as $item1){?><div><?php if($item1->type === \'group\'){?><div><h2><?php echo \htmlspecialchars($item1->title, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></h2><?php if(!empty($item1->children)){?><ul><?php foreach($item1->children as $child){?><li class="<?php echo \htmlspecialchars($child->status, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\'); ?>"><?php if($child->isHighlighted){?><span class="highlight">★</span><?php }echo \htmlspecialchars($child->name, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');if($child->hasDetails){?><small>(<?php echo \htmlspecialchars($child->details, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?>)</small><?php }?></li><?php }?></ul><?php }else{?><p>No children available</p><?php }?></div><?php }elseif($item1->type === \'single\'){?><div><?php echo \htmlspecialchars($item1->content, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></div><?php }else{?><div>Unknown type</div><?php }?></div><?php }?></div><?php }?></test>';

		static::assertEquals($expected, self::code($input));
	}

	#[DataProvider('provideSpecialCharacters')]
	public function testSpecialCharacters(string $input, string $expected): void
	{
		static::assertEquals($expected, self::code($input));
	}

	/** @return array<string, string[]> */
	public static function provideSpecialCharacters(): array
	{
		return [
			'unicode in static text' => [
				'<div>Hello 世界 👋</div>',
				'<div>Hello 世界 👋</div>'
			],
			'unicode in mustache' => [
				'<div>{{ "Hello 世界 👋" }}</div>',
				'<div><?php echo \htmlspecialchars("Hello 世界 👋", \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></div>'
			],
			'html entities' => [
				'<div>&lt;script&gt; {{ $content }} &lt;/script&gt;</div>',
				'<div>&lt;script&gt; <?php echo \htmlspecialchars($content, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?> &lt;/script&gt;</div>'
			]
		];
	}

	#[DataProvider('provideEdgeCaseErrors')]
	public function testEdgeCaseErrors(string $input, string $expectedMessage): void
	{
		$this->expectException(SyntaxError::class);
		$this->expectExceptionMessage($expectedMessage);

		self::code($input);
	}

	/** @return array<string, string[]> */
	public static function provideEdgeCaseErrors(): array
	{
		return [
			'incomplete ternary' => [
				'<div>{{ $condition ? }}</div>',
				'Expression "$condition ?" failed to parse: syntax error, unexpected token ">"'
			],
			'invalid array access' => [
				'<div>{{ $array[ }}</div>',
				'Expression "$array[" failed to parse: syntax error, unexpected token ";", expecting "]"'
			],
			'unclosed method call' => [
				'<div>{{ $object->method( }}</div>',
				'Expression "$object->method(" failed to parse: syntax error, unexpected token ";"'
			],
			'invalid v-for syntax' => [
				'<div v-for="$items as as $item"></div>',
				'Expression "foreach($items as as $item)" failed to parse: syntax error, unexpected token "as"'
			]
		];
	}

	#[DataProvider('provideMalformedInput')]
	public function testMalformedInput(string $input, string $expected): void
	{
		static::assertEquals($expected, self::code($input));
	}

	/** @return array<string, string[]> */
	public static function provideMalformedInput(): array
	{
		return [
			'unclosed div' => [
				'<div><span>test</span>',
				'<div><span>test</span></div>'
			],
			'unclosed multiple levels' => [
				'<div><span><b>test',
				'<div><span><b>test</b></span></div>'
			],
			'mixed unclosed tags' => [
				'<div><p>test<span>nested</p></div>',
				'<div><p>test<span>nested</span></p></div>'
			]
		];
	}

	#[DataProvider('provideScriptContent')]
	public function testScriptContent(string $input, string $expected): void
	{
		static::assertEquals($expected, self::code($input));
	}

	/** @return array<string, string[]> */
	public static function provideScriptContent(): array
	{
		return [
			'script with mustache' => [
				'<test><script>var x = {{ $value }};</script></test>',
				'<test><script>var x = <?php echo \htmlspecialchars($value, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?>;</script></test>',
			],
			'script with condition' => [
				'<test><script v-if="$debug">console.log({{ $value }});</script></test>',
				'<test><?php if($debug){?><script>console.log(<?php echo \htmlspecialchars($value, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?>);</script><?php }?></test>',
			],
			'script with CDATA' => [
				'<test><script><![CDATA[var x = {{ $value }};]]></script></test>',
				'<test><script><![CDATA[var x = <?php echo \htmlspecialchars($value, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?>;]]></script></test>',
			]
		];
	}

	#[DataProvider('provideStyleContent')]
	public function testStyleContent(string $input, string $expected): void
	{
		static::assertEquals($expected, self::code($input));
	}

	/** @return array<string, string[]> */
	public static function provideStyleContent(): array
	{
		return [
			'style with mustache' => [
				'<test><style>.test { color: {{ $color }}; }</style></test>',
				'<test><style>.test { color: <?php echo \htmlspecialchars($color, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?>; }</style></test>',
			],
			'style with condition' => [
				'<test><style v-if="$customStyle">.custom { background: {{ $bg }}; }</style></test>',
				'<test><?php if($customStyle){?><style>.custom { background: <?php echo \htmlspecialchars($bg, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?>; }</style><?php }?></test>',
			]
		];
	}

	public function testNestedPreWithComplexDirectives(): void
	{
		$input = '<div v-pre><span v-if="$condition">{{ $value }}<div v-for="$items as $item"><p v-if="$item->show">{{ $item->text }}</p><p v-else>{{ $item->default }}</p></div></span><span v-else>{{ $alternate }}</span></div>';
		$expected = '<div><span v-if="$condition">{{ $value }}<div v-for="$items as $item"><p v-if="$item->show">{{ $item-&gt;text }}</p><p v-else="">{{ $item-&gt;default }}</p></div></span><span v-else="">{{ $alternate }}</span></div>';

		static::assertEquals($expected, self::code($input));
	}

	public function testMultipleConditionsChain(): void
	{
		$input = '<div><p v-if="$type === \'a\'">A</p><p v-else-if="$type === \'b\'">B</p><p v-else-if="$type === \'c\'">C</p><p v-else-if="$type === \'d\'">D</p><p v-else>Other</p></div>';
		$expected = '<div><?php if($type === \'a\'){?><p>A</p><?php }elseif($type === \'b\'){?><p>B</p><?php }elseif($type === \'c\'){?><p>C</p><?php }elseif($type === \'d\'){?><p>D</p><?php }else{?><p>Other</p><?php }?></div>';

		static::assertEquals($expected, self::code($input));
	}

	#[DataProvider('provideComplexExpressions')]
	public function testComplexExpressions(string $input, string $expected): void
	{
		static::assertEquals($expected, self::code($input));
	}

	/** @return array<string, string[]> */
	public static function provideComplexExpressions(): array
	{
		return [
			'nested ternary' => [
				'<div>{{ $a ? ($b ? "B" : "notB") : ($c ? "C" : "notC") }}</div>',
				'<div><?php echo \htmlspecialchars($a ? ($b ? "B" : "notB") : ($c ? "C" : "notC"), \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></div>'
			],
			'complex math' => [
				'<div>{{ ($a + $b) * ($c - $d) / (($e * $f) ?: 1) }}</div>',
				'<div><?php echo \htmlspecialchars(($a + $b) * ($c - $d) / (($e * $f) ?: 1), \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></div>'
			],
			'array operations' => [
				'<div>{{ array_filter($items, fn($x) => $x->type === "test" && $x->value > 10) }}</div>',
				'<div><?php echo \htmlspecialchars(array_filter($items, fn($x) => $x->type === "test" && $x->value > 10), \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></div>'
			]
		];
	}

	#[DataProvider('provideCommentsHandling')]
	public function testCommentsHandling(string $input, string $expected): void
	{
		static::assertEquals($expected, self::code($input));
	}

	/** @return array<string, string[]> */
	public static function provideCommentsHandling(): array
	{
		return [
			'html comment' => [
				'<div><!-- test -->{{ $value }}</div>',
				'<div><!-- test --><?php echo \htmlspecialchars($value, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></div>'
			],
			'conditional comment' => [
				'<!--[if IE]><div>{{ $value }}</div><![endif]-->',
				'<!--[if IE]><div>{{ $value }}</div><![endif]-->'
			],
			'comment with mustache' => [
				'<!-- {{ $value }} -->',
				'<!-- {{ $value }} -->'
			]
		];
	}

		/**
	 * Tests empty elements that should throw exceptions
	 */
	#[DataProvider('provideEmptyElementsWithExceptions')]
	public function testEmptyElementsWithExceptions(string $input, string $expectedMessage): void
	{
		$this->expectException(SyntaxError::class);
		$this->expectExceptionMessage($expectedMessage);

		self::code($input);
	}

	/**
	 * Tests empty elements that should be properly rendered
	 */
	#[DataProvider('provideEmptyElementsRendering')]
	public function testEmptyElementsRendering(string $input, string $expected): void
	{
		$this->assertEquals($expected, self::code($input));
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function provideEmptyElementsWithExceptions(): array
	{
		return [
			'empty mustache' => [
				'<div>{{ }}</div>',
				'Mustache tag is empty'
			],
			'empty v-if' => [
				'<div v-if=""></div>',
				'Attribute v-if must not be empty'
			],
			'empty v-else-if' => [
				'<test><div v-if="$condition"></div><div v-else-if=""></div></test>',
				'Attribute v-else-if must not be empty'
			],
			'empty v-for' => [
				'<div v-for=""></div>',
				'Attribute v-for must not be empty'
			],
			'empty dynamic attribute' => [
				'<div :attr=""></div>',
				'Attribute is empty'
			],
			'whitespace-only mustache' => [
				'<div>{{

				}}</div>',
				'Mustache tag is empty'
			],
			'whitespace-only dynamic attr' => [
				'<div :attr="  "></div>',
				'Attribute is empty'
			]
		];
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function provideEmptyElementsRendering(): array
	{
		return [
			'void element with directives' => [
				'<test><img v-if="$show" src="test.jpg" /></test>',
				'<test><?php if($show){?><img src="test.jpg"><?php }?></test>'
			],
			'empty div' => [
				'<div></div>',
				'<div></div>'
			],
			'multiple empty static attributes' => [
				'<div class="" id="" role=""></div>',
				'<div class="" id="" role=""></div>'
			],
			'mixed empty and dynamic attributes' => [
				'<div class="" id="" :data-test="$value"></div>',
				'<div class="" id="" data-test="<?php echo \htmlspecialchars($value, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\'); ?>"></div>'
			],
			'element with only whitespace' => [
				'<div>    </div>',
				'<div>    </div>'
			],
			'element with newlines only' => [
				"<div>\n\n</div>",
				"<div>\n\n</div>"
			],
			'nested empty elements' => [
				'<div><span></span><p></p></div>',
				'<div><span></span><p></p></div>'
			],
			'all self-closing void elements' => [
				'<div><img src="test.jpg"/><br/><hr/><input type="text"/><meta/></div>',
				'<div><img src="test.jpg"><br><hr><input type="text"><meta></div>'
			],
			'self-closing with dynamic attributes' => [
				'<test><input v-if="$show" :type="$inputType" :value="$value" /></test>',
				'<test><?php if($show){?><input type="<?php echo \htmlspecialchars($inputType, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\'); ?>" value="<?php echo \htmlspecialchars($value, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\'); ?>"><?php }?></test>'
			],
			'void element with dynamic binding and condition' => [
				'<test><hr v-if="$showLine" :class="$lineClass" /></test>',
				'<test><?php if($showLine){?><hr class="<?php echo \htmlspecialchars($lineClass, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\'); ?>"><?php }?></test>'
			],
			'void element with condition and loop' => [
				'<test><input v-for="$inputs as $input" :type="$input->type" /></test>',
				'<test><?php foreach($inputs as $input){?><input type="<?php echo \htmlspecialchars($input->type, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\'); ?>"><?php }?></test>'
			],
			'empty element with pre directive' => [
				'<div v-pre></div>',
				'<div></div>'
			],
			'element with conditional empty child' => [
				'<div><span v-if="$show"></span></div>',
				'<div><?php if($show){?><span></span><?php }?></div>'
			],
			'element with v-pre and dynamic attributes' => [
				'<div v-pre :class="$cls" :data-value="$val"></div>',
				'<div :class="$cls" :data-value="$val"></div>'
			]
		];
	}

	/**
	 * Test specifically for HTML5 void elements which are self-closing
	 */
	#[DataProvider('provideHtml5VoidElements')]
	public function testHtml5VoidElements(string $element): void
	{
		$input = "<$element/>";
		$expected = "<$element>";
		$this->assertEquals($expected, self::code($input));

		// With attributes
		$input = "<$element class=\"test\" id=\"test-id\"/>";
		$expected = "<$element class=\"test\" id=\"test-id\">";
		$this->assertEquals($expected, self::code($input));

		// With dynamic attributes
		$input = "<$element :class=\"\$dynamicClass\" :id=\"\$dynamicId\"/>";
		$expected = "<$element class=\"<?php echo \htmlspecialchars(\$dynamicClass, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, 'UTF-8'); ?>\" id=\"<?php echo \htmlspecialchars(\$dynamicId, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, 'UTF-8'); ?>\">";
		$this->assertEquals($expected, self::code($input));
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function provideHtml5VoidElements(): array
	{
		return [
			'area' => ['area'],
			'base' => ['base'],
			'br' => ['br'],
			//'col' => ['col'],
			'embed' => ['embed'],
			'hr' => ['hr'],
			'img' => ['img'],
			'input' => ['input'],
			'link' => ['link'],
			'meta' => ['meta'],
			'source' => ['source'],
			'track' => ['track'],
			'wbr' => ['wbr']
		];
	}

	/**
	 * Test for non-void elements incorrectly self-closed
	 */
	public function testNonVoidSelfClosedElements(): void
	{
		// Non-void elements should be corrected if self-closed
		$input = '<div/>';
		$expected = '<div></div>';
		$this->assertEquals($expected, self::code($input));

		// This should work with attributes too
		$input = '<span class="test" id="id"/>';
		$expected = '<span class="test" id="id"></span>';
		$this->assertEquals($expected, self::code($input));

		// And with dynamic attributes
		$input = '<p :class="$cls"/>';
		$expected = '<p class="<?php echo \htmlspecialchars($cls, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\'); ?>"></p>';
		$this->assertEquals($expected, self::code($input));
	}

	/**
	 * Test nested conditions and loops with empty elements
	 */
	public function testNestedConditionsWithEmptyElements(): void
	{
		// Testing correct order of nested v-if and v-for
		// v-if has higher precedence than v-for according to the compiler
		$input = '<test><div v-if="$outer"><span v-if="$inner"></span></div></test>';
		$expected = '<test><?php if($outer){?><div><?php if($inner){?><span></span><?php }?></div><?php }?></test>';
		$this->assertEquals($expected, self::code($input));

		// Test a more complex scenario with nested conditions and loops
		$input = '<test><ul v-if="$hasItems"><li v-for="$items as $item" v-if="$item->show"></li><li v-else>Empty</li></ul><div v-else></div></test>';
		$expected = '<test><?php if($hasItems){?><ul><?php if($item->show){foreach($items as $item){?><li></li><?php }}else{?><li>Empty</li><?php }?></ul><?php }else{?><div></div><?php }?></test>';
		$this->assertEquals($expected, self::code($input));
	}
}
