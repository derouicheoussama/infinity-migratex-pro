<?php
/**
 * Injecte le footer-watermark exécutable dans chaque fichier PHP de code
 * (juste avant le dernier `}` de classe ou à la fin du fichier) :
 *
 *   // ∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com
 *
 * Idempotent : les fichiers déjà marqués sont ignorés. Exclut les
 * index.php muets et le dossier .git.
 * Usage : php inject-watermarks.php
 */
$root = 'C:/Users/Derouiche Oussama/.zcode/workspace/default/infinity-migratex-pro';
$mark = "\n\n// ∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com\n";

$it = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::LEAVES_ONLY
);
$injected = 0;
$skipped  = 0;
foreach ( $it as $f ) {
	if ( 'php' !== $f->getExtension() ) { continue; }
	$p = str_replace( chr( 92 ), '/', $f->getPathname() );
	if ( false !== strpos( $p, '/.git/' ) ) { continue; }
	$rel = str_replace( $root . '/', '', $p );
	if ( preg_match( '#(^|/)index\.php$#', $rel ) ) { $skipped++; continue; }

	$c = file_get_contents( $p );
	if ( false !== strpos( $c, '∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com' ) ) { continue; }

	// Injection : à la fin du fichier, après le dernier caractère utile.
	$new = rtrim( $c ) . $mark;
	file_put_contents( $p, $new );
	$injected++;
	echo 'marqué: ' . $rel . PHP_EOL;
}
echo "--- injecté: $injected, ignorés (index): $skipped ---" . PHP_EOL;

/* Régénère la liste SIGNATURED de IMP_Watermark. */
$wm = $root . '/includes/class-watermark.php';
if ( is_file( $wm ) ) {
	$list = array();
	$it2  = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::LEAVES_ONLY
	);
	foreach ( $it2 as $f ) {
		if ( 'php' !== $f->getExtension() ) { continue; }
		$p = str_replace( chr( 92 ), '/', $f->getPathname() );
		if ( false !== strpos( $p, '/.git/' ) ) { continue; }
		$rel = str_replace( $root . '/', '', $p );
		if ( preg_match( '#(^|/)index\.php$#', $rel ) ) { continue; }
		$list[] = $rel;
	}
	sort( $list );
	$items = '';
	foreach ( $list as $rel ) { $items .= "\t\t\t'" . $rel . "',\n"; }
	$c = file_get_contents( $wm );
	$c = preg_replace(
		'/(public static function files\(\) \{\s*return array\(\n)(.*?)(\t\t\t\);)/s',
		'$1' . $items . '$3',
		$c,
		1,
		$done
	);
	if ( $done ) { file_put_contents( $wm, $c ); echo 'liste SIGNATURED régénérée: ' . count( $list ) . " fichiers\n"; }
}
