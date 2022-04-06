<?php
declare(strict_types=1);

/** @var PhpFuzzer\Fuzzer $fuzzer */

use xPaw\Template\Compiler;

require __DIR__ . '/vendor/autoload.php';

$words = [];

/** @var \FileInfo $file */
foreach( new DirectoryIterator( __DIR__ . '/tests/' ) as $file )
{
	if( !$file->isFile() )
	{
		continue;
	}

	$contents = file_get_contents( $file->getPathname() );
	$tokens = PhpToken::tokenize( $contents, TOKEN_PARSE );
	$strings = array_filter( $tokens, fn( PhpToken $a ) : bool => $a->is( T_CONSTANT_ENCAPSED_STRING ) );

	foreach( $strings as $string )
	{
		$word = $string->text;
		$word = substr( $word, 1, -1 );
		$word = str_replace( [
			'"',
			'\\',
		], [
			'\x34',
			'\x92',
		], $word );

		$words[] = '"' . $word . '"';
	}
}

file_put_contents( 'dictionary.txt', implode( "\n", $words ) );

$parser = new Compiler();
$fuzzer->setTarget( function( string $input ) use( $parser )
{
	$parser->Parse( $input );
	$parser->OutputCode();
} );
$fuzzer->setMaxLen( 256 );
$fuzzer->addDictionary( 'dictionary.txt' );
