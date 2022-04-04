<?php
declare(strict_types=1);

use xPaw\Template\Compiler;
use PHPUnit\Framework\TestCase;

final class BasicTest extends TestCase
{
	private static function output( string $Input ) : string
	{
		$Template = new Compiler();
		$Template->Parse( $Input );

		return $Template->OutputCode();
	}

	public function testBasicUnicode(): void
	{
		$this->assertEquals( '<span>hello</span>', self::output( '<span>hello</span>' ) );
		//$this->assertEquals( '<span>привет</span>', self::output( '<span>привет</span>' ) );
		$this->assertEquals( '<span>&#128106;</span>', self::output( '<span>👪</span>' ) );
	}

	public function testNestedElements(): void
	{
		$this->assertEquals( '<html><div><span></span></div></html>', self::output( '<html><div><span></span></div></html>' ) );
	}

	public function testRetainDoctype(): void
	{
		$this->assertEquals( "<!DOCTYPE html>\n<html><h1>hello</h1></html>", self::output( '<!DOCTYPE html><html><h1>hello</h1></html>' ) );
	}

	public function testClosesTag(): void
	{
		$this->assertEquals( '<div><p>Hello</p></div>', self::output( '<div><p>Hello' ) );
	}

	public function testBasicMustache(): void
	{
		$this->assertEquals( '<span><?php { echo \htmlspecialchars($hello, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');  }?></span>', self::output( '<span>{{ $hello }}</span>' ) );
	}

	public function testIf(): void
	{
		$this->assertEquals(
			'<?php if($test === 123){ ?><span>hello</span><?php }?>',
			self::output( '<span v-if="$test === 123">hello</span>' )
		);
	}

	public function testElseIf(): void
	{
		$this->assertEquals(
			'<?php if($test === 123){ ?><span>hello</span><?php } elseif($test === 456){ ?><span>world</span><?php }?>',
			self::output( '<span v-if="$test === 123">hello</span><span v-else-if="$test === 456">world</span>' )
		);
	}

	public function testElse(): void
	{
		$this->assertEquals(
			'<?php if($test === 123){ ?><span>hello</span><?php } else{ ?><span>world</span><?php }?>',
			self::output( '<span v-if="$test === 123">hello</span><span v-else>world</span>' )
		);
	}

	public function testElseIfElse(): void
	{
		$this->assertEquals(
			'<?php if($test === 123){ ?><span>hello</span><?php } if($test === 456){ ?><span>world</span><?php } else{ ?><span>sailor</span><?php }?>',
			self::output( '<span v-if="$test === 123">hello</span><span v-if="$test === 456">world</span><span v-else>sailor</span>' )
		);
	}

	public function testPre(): void
	{
		$this->assertEquals(
			'<?php if($test === 123){ ?><div>hello<span v-if="$test === 456">world</span><span v-else>{{ $mustache }}</span></div><?php }?>',
			self::output( '<div v-if="$test === 123" v-pre>hello<span v-if="$test === 456">world</span><span v-else>{{ $mustache }}</span></div>' )
		);
	}

	public function testFor(): void
	{
		$this->assertEquals(
			'<?php foreach($array as $value){ ?><li>text</li><?php }?>',
			self::output( '<li v-for="$array as $value">text</li>' )
		);
	}

	public function testIfFor(): void
	{
		$this->assertEquals(
			'<?php if($test === 123){  foreach($array as $key => $value){ ?><span>hello</span><?php } }?>',
			self::output( '<span v-if="$test === 123" v-for="$array as $key => $value">hello</span>' )
		);
	}

	public function testBindAttribute(): void
	{
		$this->assertEquals(
			'<span data-attr="test" id="<?php echo \htmlspecialchars($test, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\'); ?>">hello</span>',
			self::output( '<span data-attr="test" :id="$test">hello</span>' )
		);
	}
}
