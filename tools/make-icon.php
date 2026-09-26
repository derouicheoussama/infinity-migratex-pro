<?php
/**
 * ∞ INFINITY CODER — création originale de Derouiche Oussama
 *
 * Génère l'icône « pro » du menu admin : uniquement le symbole central
 * (serveurs isométriques) extrait automatiquement du logo officiel, sans
 * le texte — lisible à 20 px dans le menu WordPress.
 * Usage : php make-icon.php
 */

$src_path   = __DIR__ . '/../assets/images/logo-migratex-256.png';
$out_128    = __DIR__ . '/../assets/images/imp-icon-128.png';
$out_64     = __DIR__ . '/../assets/images/imp-icon-64.png';

$src = imagecreatefrompng( $src_path );
if ( ! $src ) {
	fwrite( STDERR, "ERR: logo source illisible\n" );
	exit( 1 );
}

$w = imagesx( $src );
$h = imagesy( $src );

/* --- 1. Boîte englobante du symbole : les cubes forment la PREMIÈRE
       bande lumineuse de l'image ; le texte arrive après un espace
       sombre horizontal — on coupe à cet espace (sinon le texte,
       illisible en 20 px, pollue l'icône). --- */
$bg = imagecolorat( $src, 3, 3 );
$br = ( $bg >> 16 ) & 0xFF;
$bgg = ( $bg >> 8 ) & 0xFF;
$bb = $bg & 0xFF;

$scan_limit = (int) ( $h * 0.75 );
$row_hits   = array();
$margin_x   = (int) ( $w * 0.05 ); // ignore le contour arrondi du cadre.
for ( $y = 0; $y < $scan_limit; $y++ ) {
	$n = 0;
	for ( $x = $margin_x; $x < $w - $margin_x; $x++ ) {
		$c = imagecolorat( $src, $x, $y );
		$r = ( $c >> 16 ) & 0xFF;
		$g = ( $c >> 8 ) & 0xFF;
		$b = $c & 0xFF;
		if ( abs( $r - $br ) + abs( $g - $bgg ) + abs( $b - $bb ) > 60 ) {
			$n++;
		}
	}
	$row_hits[ $y ] = $n;
}

/* Bandes : suites de lignes lumineuses séparées par < 6 lignes sombres. */
$bands     = array();
$band_y0   = null;
$dark_run  = 0;
for ( $y = 0; $y < $scan_limit; $y++ ) {
	if ( $row_hits[ $y ] > 8 ) {
		if ( null === $band_y0 ) {
			$band_y0 = $y;
		}
		$band_y1  = $y;
		$dark_run = 0;
	} elseif ( null !== $band_y0 ) {
		$dark_run++;
		if ( $dark_run >= 6 ) {
			$bands[]  = array( $band_y0, $band_y1 );
			$band_y0  = null;
			$dark_run = 0;
		}
	}
}
if ( null !== $band_y0 ) {
	$bands[] = array( $band_y0, $band_y1 );
}
if ( empty( $bands ) ) {
	fwrite( STDERR, "ERR: symbole introuvable\n" );
	exit( 1 );
}
$min_y = $bands[0][0];
$max_y = $bands[0][1];

/* Balayage horizontal limité aux lignes du symbole. */
$min_x = $w; $max_x = 0;
for ( $y = $min_y; $y <= $max_y; $y++ ) {
	for ( $x = $margin_x; $x < $w - $margin_x; $x++ ) {
		$c = imagecolorat( $src, $x, $y );
		$r = ( $c >> 16 ) & 0xFF;
		$g = ( $c >> 8 ) & 0xFF;
		$b = $c & 0xFF;
		if ( abs( $r - $br ) + abs( $g - $bgg ) + abs( $b - $bb ) > 60 ) {
			if ( $x < $min_x ) { $min_x = $x; }
			if ( $x > $max_x ) { $max_x = $x; }
		}
	}
}
$sym_w = $max_x - $min_x + 1;
$sym_h = $max_y - $min_y + 1;
fwrite( STDERR, "symbole: {$sym_w}x{$sym_h} @ ({$min_x},{$min_y})\n" );

/* --- 2. Région symbole + marge de 6 % (on ne colle QUE cette région
       sur un fond recalculé : le texte ne peut jamais fuir dans
       l'icône, même si le carré englobant déborde de la bande). --- */
$pad    = (int) ( max( $sym_w, $sym_h ) * 0.06 );
$crop_x = max( 0, $min_x - $pad );
$crop_y = max( 0, $min_y - $pad );
$crop_w = min( $w - $crop_x, $sym_w + 2 * $pad );
$crop_h = min( $h - $crop_y, $sym_h + 2 * $pad );

/* --- 3. Composition : fond de marque + symbole ajusté et centré,
       coins arrondis (rayon 18 %). --- */
foreach ( array( 128 => $out_128, 64 => $out_64 ) as $size => $out ) {
	$icon = imagecreatetruecolor( $size, $size );
	$bgc  = imagecolorallocate( $icon, $br, $bgg, $bb );
	imagefill( $icon, 0, 0, $bgc );
	imagealphablending( $icon, true );

	// Symbole ajusté dans ~86 % de la toile, centré.
	$inner   = (int) ( $size * 0.86 );
	$scale   = $inner / max( $crop_w, $crop_h );
	$draw_w  = (int) round( $crop_w * $scale );
	$draw_h  = (int) round( $crop_h * $scale );
	$draw_x  = (int) ( ( $size - $draw_w ) / 2 );
	$draw_y  = (int) ( ( $size - $draw_h ) / 2 );
	$sym     = imagecreatetruecolor( $draw_w, $draw_h );
	imagecopyresampled( $sym, $src, 0, 0, $crop_x, $crop_y, $draw_w, $draw_h, $crop_w, $crop_h );
	imagecopy( $icon, $sym, $draw_x, $draw_y, 0, 0, $draw_w, $draw_h );
	imagedestroy( $sym );

	// Coins arrondis : masque alpha (rayon 18 %).
	$radius = (int) ( $size * 0.18 );
	$mask   = imagecreatetruecolor( $size, $size );
	imagealphablending( $mask, false );
	imagefill( $mask, 0, 0, imagecolorallocatealpha( $mask, 0, 0, 0, 127 ) );
	$white  = imagecolorallocate( $mask, 255, 255, 255 );
	imagefilledrectangle( $mask, $radius, 0, $size - $radius - 1, $size - 1, $white );
	imagefilledrectangle( $mask, 0, $radius, $size - 1, $size - $radius - 1, $white );
	imagefilledellipse( $mask, $radius, $radius, $radius * 2, $radius * 2, $white );
	imagefilledellipse( $mask, $size - $radius - 1, $radius, $radius * 2, $radius * 2, $white );
	imagefilledellipse( $mask, $radius, $size - $radius - 1, $radius * 2, $radius * 2, $white );
	imagefilledellipse( $mask, $size - $radius - 1, $size - $radius - 1, $radius * 2, $radius * 2, $white );

	imagealphablending( $icon, false );
	for ( $yy = 0; $yy < $size; $yy++ ) {
		for ( $xx = 0; $xx < $size; $xx++ ) {
			$a = ( imagecolorat( $mask, $xx, $yy ) >> 24 ) & 0xFF;
			if ( $a > 0 ) {
				$px = imagecolorat( $icon, $xx, $yy );
				imagesetpixel( $icon, $xx, $yy, imagecolorallocatealpha(
					$icon,
					( $px >> 16 ) & 0xFF,
					( $px >> 8 ) & 0xFF,
					$px & 0xFF,
					$a
				) );
			}
		}
	}
	imagesavealpha( $icon, true );
	imagepng( $icon, $out );
	imagedestroy( $icon );
	imagedestroy( $mask );
	fwrite( STDERR, "écrit: {$out} ({$size}px)\n" );
}

imagedestroy( $src );
echo "OK icône pro générée\n";

// ∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com
