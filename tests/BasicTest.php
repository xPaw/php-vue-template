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
		$this->assertEquals( '<span>hello</span>', self::code( '<span>hello</span>' ) );
		$this->assertEquals( '<span>привет</span>', self::code( '<span>привет</span>' ) );
		$this->assertEquals( '<span>👪</span>', self::code( '<span>👪</span>' ) );
	}

	public function testNestedElements(): void
	{
		$this->assertEquals( '<html><div><span></span></div></html>', self::code( '<html><div><span></span></div></html>' ) );
	}

	public function testRetainDoctype(): void
	{
		$this->assertEquals( "<!DOCTYPE html><html><h1>hello</h1></html>", self::code( '<!DOCTYPE html><html><h1>hello</h1></html>' ) );
	}

	public function testClosesTag(): void
	{
		$this->assertEquals( '<div><p>Hello</p></div>', self::code( '<div><p>Hello' ) );
	}

	public function testIf(): void
	{
		$this->assertEquals(
			'<test><?php if($test === 123){?><span>hello</span><?php }?></test>',
			self::code( '<test><span v-if="$test === 123">hello</span></test>' )
		);
	}

	public function testElseIf(): void
	{
		$this->assertEquals(
			'<test><?php if($test === 123){?><span>hello</span><?php }elseif($test === 456){?><span>world</span><?php }?></test>',
			self::code( '<test><span v-if="$test === 123">hello</span><span v-else-if="$test === 456">world</span></test>' )
		);
	}

	public function testElse(): void
	{
		$this->assertEquals(
			'<test><?php if($test === 123){?><span>hello</span><?php }else{?><span>world</span><?php }?></test>',
			self::code( '<test><span v-if="$test === 123">hello</span><span v-else>world</span></test>' )
		);
	}

	public function testElseIfElse(): void
	{
		$this->assertEquals(
			'<test><?php if($test === 123){?><span>hello</span><?php }if($test === 456){?><span>world</span><?php }else{?><span>sailor</span><?php }?></test>',
			self::code( '<test><span v-if="$test === 123">hello</span><span v-if="$test === 456">world</span><span v-else>sailor</span></test>' )
		);
	}

	public function testPre(): void
	{
		// TODO: Adds value v-else=""
		$this->assertEquals(
			'<test><?php if($test === 123){?><div>hello<span v-if="$test === 456">world</span><span v-else="">{{ $mustache }}</span></div><?php }?></test>',
			self::code( '<test><div v-if="$test === 123" v-pre>hello<span v-if="$test === 456">world</span><span v-else>{{ $mustache }}</span></div></test>' )
		);
	}

	public function testFor(): void
	{
		$this->assertEquals(
			'<test><?php foreach($array as $value){?><li>text</li><?php }?></test>',
			self::code( '<test><li v-for="$array as $value">text</li></test>' )
		);
	}

	public function testIfFor(): void
	{
		$this->assertEquals(
			'<test><?php if($test === 123){foreach($array as $key => $value){?><span>hello</span><?php } }?></test>',
			self::code( '<test><span v-if="$test === 123" v-for="$array as $key => $value">hello</span></test>' )
		);
	}

	public function testBindAttribute(): void
	{
		$this->assertEquals(
			'<span data-attr="test" id="<?php echo \htmlspecialchars($test, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\'); ?>">hello</span>',
			self::code( '<span data-attr="test" :id="$test">hello</span>' )
		);
	}
}
