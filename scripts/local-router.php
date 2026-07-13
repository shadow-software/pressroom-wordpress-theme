<?php
/**
 * Router for the local sandbox's PHP built-in server.
 *
 * WHY THIS EXISTS: the sandbox used to start with
 *
 *     php -S localhost:8080 -t .local-wp .local-wp/index.php
 *
 * That makes WordPress's index.php the *router script*, which means PHP hands
 * it EVERY request — including ones for real files like digest.css. WordPress
 * has no route for /wp-content/.../digest.css, so it canonical-redirected the
 * request to a trailing slash and then 404'd it.
 *
 * The result: not one stylesheet, script or font ever loaded. Every page in the
 * sandbox rendered as unstyled HTML, and the smoke tests — which only ever
 * asserted on markup — passed anyway. The theme's CSS had never once been
 * executed anywhere before this file existed.
 *
 * The contract for a built-in-server router is: return false for a request that
 * maps to a real file, and PHP serves that file itself with the right MIME type.
 * Return anything else and PHP treats the script's output as the response.
 *
 * @package Shadow_Digest
 */

$__path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$__path = rawurldecode( (string) $__path );

// Never let a traversal escape the docroot.
if ( strpos( $__path, "\0" ) !== false || strpos( $__path, '..' ) !== false ) {
	http_response_code( 400 );
	return true;
}

$__root  = realpath( __DIR__ . '/../.local-wp' );
$__theme = realpath( __DIR__ . '/../shadow-software-digest-theme-for-wordpress' );

// The theme is mounted into the docroot as a symlink so edits are live, which
// means realpath() on one of its assets resolves back out to the repo. Such a
// file is legitimate, so treat the theme's real directory as in-bounds too.
$__allowed = array_filter( array( $__root, $__theme ) );

$__file = realpath( $__root . $__path );

$__in_bounds = false;
foreach ( $__allowed as $__base ) {
	if ( strncmp( (string) $__file, $__base . DIRECTORY_SEPARATOR, strlen( $__base ) + 1 ) === 0 ) {
		$__in_bounds = true;
		break;
	}
}

// A real file, genuinely inside the docroot (or the symlinked theme), that is
// not itself a PHP script: hand it back to the built-in server, which serves it
// with a correct Content-Type. This is the line whose absence broke every asset.
if (
	$__file !== false
	&& $__in_bounds
	&& is_file( $__file )
	&& ! preg_match( '/\.php$/i', $__file )
) {
	// The PHP built-in server never sends Cache-Control or Content-Encoding —
	// it is a test harness, not a web server. A real deploy target (Apache/Nginx
	// on the shared VPS) sets both for static assets. Without them here, every
	// performance audit run against the sandbox is measuring a limitation of the
	// test harness, not of the theme, and can never reach the scores production
	// actually gets. So: reproduce that production behaviour for static files
	// only, entirely inside this sandbox-only script. Nothing here ships.
	header( 'Cache-Control: public, max-age=31536000, immutable' );

	$__gzippable = preg_match( '/\.(css|js|svg|txt|html?|xml|json)$/i', $__file );

	if ( $__gzippable && function_exists( 'gzencode' ) && ! headers_sent() ) {
		$__accept_encoding = (string) ( $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '' );

		if ( false !== strpos( $__accept_encoding, 'gzip' ) ) {
			$__contents = (string) file_get_contents( $__file );
			$__gzipped  = gzencode( $__contents, 9 );

			if ( false !== $__gzipped ) {
				$__mime_types = array(
					'css'  => 'text/css',
					'js'   => 'application/javascript',
					'svg'  => 'image/svg+xml',
					'txt'  => 'text/plain',
					'html' => 'text/html',
					'htm'  => 'text/html',
					'xml'  => 'application/xml',
					'json' => 'application/json',
				);

				$__ext = strtolower( (string) pathinfo( $__file, PATHINFO_EXTENSION ) );

				header( 'Content-Type: ' . ( $__mime_types[ $__ext ] ?? 'application/octet-stream' ) );
				header( 'Content-Encoding: gzip' );
				header( 'Vary: Accept-Encoding' );
				echo $__gzipped;

				return true;
			}
		}
	}

	return false;
}

// Everything else is a WordPress route.
require $__root . '/index.php';
