<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$Template = new xPaw\Template\Engine();
$Template->CacheDirectiory = __DIR__ . '/cache/';
$Template->TemplateDirectiory = __DIR__ . '/templates/';

$Template->Assign( 'title', 'Hello world' );

$Template->Render( 'test' );
