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
}
