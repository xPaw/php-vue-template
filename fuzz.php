<?php
declare(strict_types=1);

/** @var PhpFuzzer\Fuzzer $fuzzer */

use xPaw\Template\Compiler;

require __DIR__ . '/vendor/autoload.php';

$parser = new Compiler();
$fuzzer->setTarget( function( string $input ) use( $parser )
{
	$parser->Parse( $input );
	$parser->OutputCode();
} );
$fuzzer->setMaxLen( 50 );
