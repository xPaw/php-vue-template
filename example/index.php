<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$Template = new xPaw\Template\Engine();
$Template->CacheDirectory = __DIR__ . '/cache/';
$Template->TemplateDirectory = __DIR__ . '/templates/';

$Template->Assign( 'title', 'Hello world' );

$Template->Render( 'test' );
