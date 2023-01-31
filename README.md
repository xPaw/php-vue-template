This is an unfinished PHP templating system which takes inspiration from Vue.

Attribute values, directives, and text interpolation accept PHP expressions.

For example:
```html
<div v-if="$type === 'B'">
	some white space {{ $test }}
</div>
<p :id="$type" v-else>
	something else
</p>

<ul>
	<li v-for="$array as $value">
		{{ $value }}
	</li>
</ul>
```

compiles to:
```php
<?php if ($type === 'B') { ?>
	<div>
		some white space <?php { echo \htmlspecialchars($test, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, 'UTF-8'); } ?>
	</div>
<?php } else { ?>
	<p id="<?php echo \htmlspecialchars($type, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, 'UTF-8'); ?>">
		something else
	</p>
<?php } ?>

<ul>
	<?php foreach ($array as $value) { ?>
		<li><?php { echo \htmlspecialchars($value, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_DISALLOWED|\ENT_HTML5, 'UTF-8'); } ?></li>
	<?php } ?>
</ul>
```
