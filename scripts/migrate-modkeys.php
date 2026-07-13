<?php
/**
 * Rename the theme-mod KEYS from digest_* to shadow_digest_*.
 *
 * Run on the SERVER, against ONE site:
 *   wp eval-file migrate-modkeys.php --allow-root
 *
 * ---------------------------------------------------------------------------
 * WHY THIS IS NEEDED, AND WHY IT WAS ALMOST MISSED
 *
 * The slug rename rewrote every `digest_` identifier to `shadow_digest_`. That
 * was correct for functions and constants — but the setting IDs declared in
 * shadow_digest_settings() are also `digest_*` strings, and they got rewritten
 * too. So the code now asks for:
 *
 *     get_theme_mod( 'shadow_digest_newsletter_blurb' )
 *
 * while the database, migrated faithfully by migrate-slug.php, still holds:
 *
 *     digest_newsletter_blurb
 *
 * The lookup misses, and shadow_digest_get() returns the DEFAULT. The defaults
 * are the theme's own — which are the Marksman's ones. The result: Cannabis
 * Digest served "The Weekly Dispatch" and "the week in marksmanship" to its
 * readers, with a green accent, and every single check passed. The theme was
 * live, fast, valid, and quietly wearing the wrong publication's clothes.
 *
 * Nothing errored. No test failed. The only thing that caught it was reading the
 * rendered page — which is exactly the lesson of the incident that preceded this
 * rename, restated a third time.
 * ---------------------------------------------------------------------------
 *
 * Idempotent and non-destructive: it copies old keys to new ones and leaves the
 * old keys in place, so re-running is safe and rolling back means simply
 * reverting the code.
 */

$slug = get_option( 'stylesheet' );
$mods = get_option( 'theme_mods_' . $slug );

if ( ! is_array( $mods ) ) {
	echo "No theme mods found for '{$slug}'.\n";
	return;
}

echo "Active theme: {$slug}\n";
echo 'Existing mods: ' . count( $mods ) . "\n\n";

$renamed = 0;
$new     = $mods;

foreach ( $mods as $key => $value ) {
	// Only the theme's own settings. Leave WordPress's own mods
	// (nav_menu_locations, custom_logo, sidebars_widgets…) exactly as they are —
	// core reads those by their real names and renaming them would break menus.
	if ( ! is_string( $key ) || 0 !== strpos( $key, 'digest_' ) ) {
		continue;
	}

	$target = 'shadow_' . $key;   // digest_accent -> shadow_digest_accent

	// Never clobber a value that already exists under the new key.
	if ( array_key_exists( $target, $new ) ) {
		continue;
	}

	$new[ $target ] = $value;
	++$renamed;

	printf(
		"  %-32s -> %-40s %s\n",
		$key,
		$target,
		is_scalar( $value ) ? mb_substr( (string) $value, 0, 30 ) : '(array)'
	);
}

if ( 0 === $renamed ) {
	echo "Nothing to rename — the keys are already migrated.\n";
	return;
}

update_option( 'theme_mods_' . $slug, $new );

echo "\n✓ {$renamed} setting keys migrated.\n";
echo "  The old keys are left in place, so this is reversible by reverting the code.\n";
