<?php
/** @var \Pronamic\WordPress\Documentor\Documentor $documentor */
if ( ! isset( $documentor ) ) {
	return;
}

$actions = $documentor->get_actions();
$filters = $documentor->get_filters();

/**
 * Sort the hooks by source file and line.
 *
 * Documentor lists files in the order the filesystem returns them, which
 * differs between machines, so without this every regeneration reorders the
 * whole file.
 *
 * @param \Pronamic\WordPress\Documentor\Hook $a The first hook.
 * @param \Pronamic\WordPress\Documentor\Hook $b The second hook.
 * @return int
 */
$by_source = static function ( $a, $b ) {
	$file = strcmp( $a->get_file()->getRelativePathname(), $b->get_file()->getRelativePathname() );

	return 0 !== $file ? $file : $a->get_start_line() <=> $b->get_start_line();
};

usort( $actions, $by_source );
usort( $filters, $by_source );

$eol = "\n";

echo '# Hooks', $eol;

echo $eol;

echo '- [Actions](#actions)', $eol;
echo '- [Filters](#filters)', $eol;

echo $eol;

echo '## Actions', $eol;

echo $eol;

foreach ( $actions as $hook ) {
	include __DIR__  . '/hook.php';
	echo $eol, '---', $eol;
}

echo '## Filters', $eol;

echo $eol;

foreach ( $filters as $hook ) {
	include __DIR__  . '/hook.php';
	echo $eol, '---', $eol;
}

echo $eol;
