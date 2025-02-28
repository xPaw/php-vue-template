<?php
declare(strict_types=1);

use xPaw\Template\Compiler;
use PHPUnit\Framework\TestCase;

final class BasicTest extends TestCase
{
	private static function code( string $Input ) : string
	{
		$Template = new Compiler();
		$Template->Parse( $Input );

		return $Template->OutputCode();
	}

	public function testBasicUnicode(): void
	{
		static::assertEquals( '<span>hello</span>', self::code( '<span>hello</span>' ) );
		static::assertEquals( '<span>привет</span>', self::code( '<span>привет</span>' ) );
		static::assertEquals( '<span>👪</span>', self::code( '<span>👪</span>' ) );
	}

	public function testNestedElements(): void
	{
		static::assertEquals( '<html><div><span></span></div></html>', self::code( '<html><div><span></span></div></html>' ) );
	}

	public function testRetainDoctype(): void
	{
		static::assertEquals( "<!DOCTYPE html><html><h1>hello</h1></html>", self::code( '<!DOCTYPE html><html><h1>hello</h1></html>' ) );
	}

	public function testClosesTag(): void
	{
		static::assertEquals( '<div><p>Hello</p></div>', self::code( '<div><p>Hello' ) );
	}

	public function testIf(): void
	{
		static::assertEquals(
			'<test><?php if($test === 123){?><span>hello</span><?php }?></test>',
			self::code( '<test><span v-if="$test === 123">hello</span></test>' )
		);
	}

	public function testElseIf(): void
	{
		static::assertEquals(
			'<test><?php if($test === 123){?><span>hello</span><?php }elseif($test === 456){?><span>world</span><?php }?></test>',
			self::code( '<test><span v-if="$test === 123">hello</span><span v-else-if="$test === 456">world</span></test>' )
		);
	}

	public function testElse(): void
	{
		static::assertEquals(
			'<test><?php if($test === 123){?><span>hello</span><?php }else{?><span>world</span><?php }?></test>',
			self::code( '<test><span v-if="$test === 123">hello</span><span v-else>world</span></test>' )
		);
	}

	public function testElseIfElse(): void
	{
		static::assertEquals(
			'<test><?php if($test === 123){?><span>hello</span><?php }if($test === 456){?><span>world</span><?php }else{?><span>sailor</span><?php }?></test>',
			self::code( '<test><span v-if="$test === 123">hello</span><span v-if="$test === 456">world</span><span v-else>sailor</span></test>' )
		);
	}

	public function testPre(): void
	{
		static::assertEquals(
			'<test><div v-if="$test === 123">hello<span v-if="$test === 456">world</span><span v-else="">{{ $mustache }}</span></div></test>',
			self::code( '<test><div v-if="$test === 123" v-pre>hello<span v-if="$test === 456">world</span><span v-else>{{ $mustache }}</span></div></test>' )
		);
	}

	public function testFor(): void
	{
		static::assertEquals(
			'<test><?php foreach($array as $value){?><li>text</li><?php }?></test>',
			self::code( '<test><li v-for="$array as $value">text</li></test>' )
		);
	}

	public function testIfFor(): void
	{
		static::assertEquals(
			'<test><?php if($test === 123){foreach($array as $key => $value){?><span>hello</span><?php }}?></test>',
			self::code( '<test><span v-if="$test === 123" v-for="$array as $key => $value">hello</span></test>' )
		);
	}

	public function testBindAttribute(): void
	{
		static::assertEquals(
			'<span data-attr="test" id="<?php echo \htmlspecialchars($test, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\'); ?>">hello</span>',
			self::code( '<span data-attr="test" :id="$test">hello</span>' )
		);
	}
}
