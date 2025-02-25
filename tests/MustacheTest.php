<?php
declare(strict_types=1);

use xPaw\Template\Compiler;
use PHPUnit\Framework\TestCase;

final class MustacheTest extends TestCase
{
	private static function code( string $Input ) : string
	{
		$Template = new Compiler();
		$Template->Parse( $Input );

		return $Template->OutputCode();
	}

	public function testBasicMustache(): void
	{
		static::assertEquals( '<span><?php echo \htmlspecialchars($hello, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></span>', self::code( '<span>{{ $hello }}</span>' ) );
		static::assertEquals( '<span><?php echo \htmlspecialchars($hello, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></span>', self::code( '<span>{{$hello}}</span>' ) );
	}

	public function testEmoji(): void
	{
		static::assertEquals( '<span><?php echo \htmlspecialchars("🤣🤣", \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></span>', self::code( '<span>{{ "🤣🤣" }}</span>' ) );
	}

	public function testBasicMustacheNoEscape(): void
	{
		static::assertEquals( '<span><?php echo $hello;?></span>', self::code( '<span>{{{$hello}}}</span>' ) );
		static::assertEquals( '<span><?php echo $hello;?></span>', self::code( '<span>{{{ $hello }}}</span>' ) );
	}

	public function testBasicMustacheNoEcho(): void
	{
		static::assertEquals( '<span><?php $hello;?></span>', self::code( '<span>{{=$hello}}</span>' ) );
		static::assertEquals( '<span><?php $hello;?></span>', self::code( '<span>{{= $hello }}</span>' ) );
	}

	public function testBasicEchoConcat(): void
	{
		static::assertEquals( '<span><?php echo \htmlspecialchars("Hey " . $hello, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></span>', self::code( '<span>{{ "Hey " . $hello}}</span>' ) );
		static::assertEquals( "<span><?php echo \htmlspecialchars('Hey ' . \$hello, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, 'UTF-8');?></span>", self::code( "<span>{{ 'Hey ' . \$hello}}</span>" ) );
		static::assertEquals( '<span><?php echo \htmlspecialchars($hello . "!", \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></span>', self::code( '<span>{{  $hello . "!"  }}</span>' ) );
		static::assertEquals( '<span><?php echo \htmlspecialchars("Hey $hello", \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></span>', self::code( '<span>{{ "Hey $hello" }}</span>' ) );
	}

	public function testMultipleMustachesInSameTextNode(): void
	{
		static::assertEquals(
			'<span>hello <?php echo $a;?> some text <?php echo $b;?> ending</span>',
			self::code( '<span>hello {{{$a}}} some text {{{$b}}} ending</span>' )
		);
	}

	public function testCdata(): void
	{
		static::assertEquals(
			'<script type="text/javascript">var t = <?php echo \htmlspecialchars($type, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?>;</script>',
			self::code( '<script type="text/javascript">var t = {{ $type }};</script>' )
		);
		static::assertEquals(
			'<script type="text/javascript"><![CDATA[var t = <?php echo \htmlspecialchars($type, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?>;]]></script>',
			self::code( '<script type="text/javascript"><![CDATA[var t = {{ $type }};]]></script>' )
		);
	}

	public function testRemovesAdjecentTags(): void
	{
		static::assertEquals(
			'<test><?php if($title){?><span></span><?php }echo \htmlspecialchars($title, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');if($title){?><small>(<?php echo \htmlspecialchars($title, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?>)</small><?php }?></test>',
			self::code( '<test><span v-if="$title"></span>{{ $title }}<small v-if="$title">({{ $title }})</small></test>' )
		);
	}

	public function testBasicAssignment(): void
	{
		$input = '<div>{{ $var = 123 }}</div>';
		$expected = '<div><?php $var = 123;?></div>';

		static::assertEquals($expected, self::code($input));
	}

	public function testAssignmentWithoutNoEchoSyntax(): void
	{
		// This will echo the assignment result, which is the assigned value (123)
		$input = '<div>{{ $var = 123 }}</div>';
		$expected = '<div><?php $var = 123;?></div>';

		static::assertEquals($expected, self::code($input));
	}

	public function testCompoundAssignments(): void
	{
		$input = '<div>{{ $var = 10 }}{{ $var += 5 }}{{ $var *= 2 }}{{ $var }}</div>';
		$expected = '<div><?php $var = 10;$var += 5;$var *= 2;echo \htmlspecialchars($var, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></div>';

		static::assertEquals($expected, self::code($input));
	}

	public function testAssignmentWithExpression(): void
	{
		$input = '<div>{{ $result = $a + $b * 3 }}{{ $result }}</div>';
		$expected = '<div><?php $result = $a + $b * 3;echo \htmlspecialchars($result, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></div>';

		static::assertEquals($expected, self::code($input));
	}

	public function testStringAssignment(): void
	{
		$input = '<div>{{ $message = "Hello, " . $name }}{{ $message }}</div>';
		$expected = '<div><?php $message = "Hello, " . $name;echo \htmlspecialchars($message, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></div>';

		static::assertEquals($expected, self::code($input));
	}

	public function testMultipleAssignments(): void
	{
		$input = '<div>
			{{ $firstName = "John" }}
			{{ $lastName = "Doe" }}
			{{ $fullName = $firstName . " " . $lastName }}
			Hello, {{ $fullName }}!
		</div>';
		$expected = '<div>
			<?php $firstName = "John";?>
			<?php $lastName = "Doe";?>
			<?php $fullName = $firstName . " " . $lastName;?>
			Hello, <?php echo \htmlspecialchars($fullName, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?>!
		</div>';

		static::assertEquals($expected, self::code($input));
	}

	public function testArrayAssignment(): void
	{
		$input = '<div>{{ $items = ["apple", "banana", "cherry"] }}</div>';
		$expected = '<div><?php $items = ["apple", "banana", "cherry"];?></div>';

		static::assertEquals($expected, self::code($input));
	}

	public function testArrayItemsAssignment(): void
	{
		$input = '<div>{{ $items[0] = "new value" }}</div>';
		$expected = '<div><?php $items[0] = "new value";?></div>';

		static::assertEquals($expected, self::code($input));
	}

	public function testObjectPropertyAssignment(): void
	{
		$input = '<div>{{ $user->name = "John" }}</div>';
		$expected = '<div><?php $user->name = "John";?></div>';

		static::assertEquals($expected, self::code($input));
	}

	public function testAssignmentWithTernary(): void
	{
		$input = '<div>{{ $displayText = $isActive ? "Active" : "Inactive" }}</div>';
		$expected = '<div><?php $displayText = $isActive ? "Active" : "Inactive";?></div>';

		static::assertEquals($expected, self::code($input));
	}

	public function testAssignmentInConditional(): void
	{
		$input = '<test><div v-if="$count > 0">{{ $total = $count * $price }}</div></test>';
		$expected = '<test><?php if($count > 0){?><div><?php $total = $count * $price;?></div><?php }?></test>';

		static::assertEquals($expected, self::code($input));
	}

	public function testAssignmentInLoop(): void
	{
		$input = '<ul><li v-for="$items as $key => $item">{{ $formattedItem = strtoupper($item) }}{{ $formattedItem }}</li></ul>';
		$expected = '<ul><?php foreach($items as $key => $item){?><li><?php $formattedItem = strtoupper($item);echo \htmlspecialchars($formattedItem, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');?></li><?php }?></ul>';

		static::assertEquals($expected, self::code($input));
	}

	public function testNestedAssignments(): void
	{
		$input = '<div>{{ $x = ($y = 5) * 2 }}</div>';
		$expected = '<div><?php $x = ($y = 5) * 2;?></div>';

		static::assertEquals($expected, self::code($input));
	}
}
