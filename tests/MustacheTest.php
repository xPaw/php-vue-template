<?php
declare(strict_types=1);

use xPaw\Template\Compiler;
use PHPUnit\Framework\TestCase;

final class MustacheTest extends TestCase
{
	private static function output( string $Input ) : string
	{
		$Template = new Compiler();
		$Template->Parse( $Input );

		return $Template->OutputCode();
	}

	public function testBasicMustache(): void
	{
		$this->assertEquals( '<span><?php { echo \htmlspecialchars($hello, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');  }?></span>', self::output( '<span>{{ $hello }}</span>' ) );
		$this->assertEquals( '<span><?php { echo \htmlspecialchars($hello, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');  }?></span>', self::output( '<span>{{$hello}}</span>' ) );
	}

	public function testEmoji(): void
	{
		$this->assertEquals( '<span><?php { echo \htmlspecialchars("🤣🤣", \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, \'UTF-8\');  }?></span>', self::output( '<span>{{ "🤣🤣" }}</span>' ) );
	}

	public function testBasicMustacheNoEscape(): void
	{
		$this->assertEquals( '<span><?php { echo $hello;  }?></span>', self::output( '<span>{{{$hello}}}</span>' ) );
		$this->assertEquals( '<span><?php { echo $hello;  }?></span>', self::output( '<span>{{{ $hello }}}</span>' ) );
	}

	public function testBasicMustacheNoEcho(): void
	{
		$this->assertEquals( '<span><?php { $hello;  }?></span>', self::output( '<span>{{=$hello}}</span>' ) );
		$this->assertEquals( '<span><?php { $hello;  }?></span>', self::output( '<span>{{= $hello }}</span>' ) );
	}

	public function testMultipleMustachesInSameTextNode(): void
	{
		$this->assertEquals(
			'<span>hello <?php { echo $a;  }?> some text <?php { echo $b;  }?> ending</span>',
			self::output( '<span>hello {{{$a}}} some text {{{$b}}} ending</span>' )
		);
	}
}
