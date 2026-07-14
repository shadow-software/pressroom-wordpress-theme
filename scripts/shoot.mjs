/**
 * Screenshot every page of the theme, at every breakpoint, in one command.
 *
 * WHY THIS EXISTS: the rule at the top of CLAUDE.md is "a green test suite is
 * evidence, not proof — look at the page". That rule is only followable if
 * looking at the page is CHEAP. When it takes ten minutes of ad-hoc scripting to
 * see what you just built, you stop doing it, and you ship a masthead that says
 * "the week in marksmanship" on a cannabis newspaper. (That happened. See §7a of
 * docs/INCIDENT-2026-07-13-vps-outage.md.)
 *
 * So: one command, every page, both breakpoints, into a folder you can open.
 *
 *   ./scripts/shoot.sh                  the local sandbox    (localhost:8080)
 *   ./scripts/shoot.sh --live           both live sites
 *   ./scripts/shoot.sh --site https://… any one origin
 *   ./scripts/shoot.sh --only cart      just the routes matching "cart"
 *   ./scripts/shoot.sh --full           full-page, not just the fold
 *
 * Output: .shots/<origin>/<route>-<viewport>.png, and an index.html contact
 * sheet that puts desktop and mobile side by side so a regression is visible at
 * a glance rather than by opening thirty files.
 */

import { chromium } from 'playwright';
import { mkdir, writeFile, rm } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';

/* -------------------------------------------------------------------------- *
 * What to shoot.
 *
 * Every route the theme owns a template for, plus every page WooCommerce adds.
 * The Woo routes are marked `woo: true` and are SKIPPED with a note rather than
 * failed when the target has no shop — cannabisdigest.net has no WooCommerce and
 * never will, and a red X against a page that is not supposed to exist trains you
 * to ignore red X's.
 * -------------------------------------------------------------------------- */
const ROUTES = [
	{ slug: 'front', path: '/', desc: 'Front page — the three-column lead grid' },
	{ slug: 'article', path: '/the-thousand-yard-question/', desc: 'Article — featured-image backdrop, furniture blocks' },
	{ slug: 'archive', path: '/category/features/', desc: 'Category archive' },
	{ slug: 'search', path: '/?s=mirage', desc: 'Search results' },
	{ slug: 'author', path: '/author/e-vance/', desc: 'Author archive' },
	{ slug: 'page', path: '/sample-page/', desc: 'A plain page' },
	{ slug: '404', path: '/definitely-not-a-real-page/', desc: '404' },

	{ slug: 'shop', path: '/shop/', desc: 'Shop — the product grid', woo: true },
	{ slug: 'product', path: '/product/ranging-scope/', desc: 'Single product', woo: true },
	{ slug: 'product-variable', path: '/product/shooting-jacket/', desc: 'Variable product — size select, price range', woo: true },
	{ slug: 'cart', path: '/cart/', desc: 'Cart — with items in it', woo: true, needsCart: true },
	{ slug: 'checkout', path: '/checkout/', desc: 'Checkout — the order form', woo: true, needsCart: true },
	{ slug: 'account', path: '/my-account/', desc: 'My Account — logged out (the login form)', woo: true },
];

const VIEWPORTS = [
	{ name: 'desktop', width: 1440, height: 900 },
	{ name: 'mobile', width: 390, height: 844 },
];

/* -------------------------------------------------------------------------- */

const argv = process.argv.slice( 2 );
const has = ( f ) => argv.includes( f );
const val = ( f ) => { const i = argv.indexOf( f ); return i >= 0 ? argv[ i + 1 ] : null; };

const LIVE = [ 'https://cannabisdigest.net', 'https://marksmansdigest.com' ];

let origins;
if ( val( '--site' ) ) origins = [ val( '--site' ).replace( /\/$/, '' ) ];
else if ( has( '--live' ) ) origins = LIVE;
else origins = [ 'http://localhost:8080' ];

const only = val( '--only' );
const fullPage = has( '--full' );
const OUT = path.resolve( process.cwd(), '.shots' );

const routes = only
	? ROUTES.filter( ( r ) => r.slug.includes( only ) || r.path.includes( only ) )
	: ROUTES;

if ( ! routes.length ) {
	console.error( `no route matches "${ only }". Known: ${ ROUTES.map( ( r ) => r.slug ).join( ', ' ) }` );
	process.exit( 1 );
}

const label = ( o ) => o.replace( /^https?:\/\//, '' ).replace( /[:.]/g, '-' );

/**
 * Put something in the cart.
 *
 * The cart and the checkout are the two pages that render COMPLETELY differently
 * depending on whether the cart is empty — an empty cart is a sentence and a
 * button, and /checkout/ simply 302s away. Screenshotting those and calling the
 * cart "styled" is exactly the kind of green-but-meaningless check that this
 * whole sandbox exists to prevent. So before shooting them, fill the cart.
 *
 * Done through the real UI (the add-to-cart link) rather than by writing session
 * rows, because the point is to exercise what a reader's browser actually does.
 */
async function fillCart( page, origin ) {
	// ?add-to-cart=<id> is Woo's own GET endpoint; it needs the post ID, which we
	// do not know from here. The shop page's add-to-cart button carries it, so
	// press the real button — and press two of them, because a one-line cart and
	// a multi-line cart lay out differently.
	await page.goto( `${ origin }/shop/`, { waitUntil: 'domcontentloaded', timeout: 30000 } );

	const buttons = page.locator( 'a.add_to_cart_button, a.ajax_add_to_cart, .wp-block-button__link.add_to_cart_button' );
	const n = await buttons.count();

	if ( n === 0 ) return false;

	for ( let i = 0; i < Math.min( 2, n ); i++ ) {
		try {
			await buttons.nth( i ).click( { timeout: 8000 } );
			await page.waitForTimeout( 1200 );
		} catch { /* a variable product has no direct add button; skip it */ }
	}

	return true;
}

/**
 * Wait for the page to actually be finished, not merely quiet.
 *
 * `networkidle` is not enough for the cart or the checkout. Those are React
 * blocks that hydrate from the Store API AFTER the network has gone quiet, and
 * they render grey skeleton placeholders in the meantime. Screenshot on
 * networkidle and you photograph the skeleton — which is how the first pass at
 * this produced a "styled" cart whose line items were four grey blobs, and looked
 * convincing enough that I nearly kept it.
 *
 * So: wait for every skeleton to go, and for every image to have real pixels.
 */
async function settle( page ) {
	// Woo's skeletons all carry a class with "skeleton" in it.
	await page
		.waitForFunction(
			() => ! document.querySelector( '[class*="skeleton" i]' ),
			null,
			{ timeout: 8000 }
		)
		.catch( () => {} ); // a page with no skeletons never had any — fine.

	// An <img> that has not decoded has naturalWidth 0 and would photograph blank.
	await page
		.waitForFunction(
			() => [ ...document.images ].every( ( i ) => ! i.complete || i.naturalWidth > 0 ),
			null,
			{ timeout: 8000 }
		)
		.catch( () => {} );

	// Fonts, so the first paint is not in a fallback face.
	await page.evaluate( () => document.fonts?.ready ).catch( () => {} );
	await page.waitForTimeout( 400 );
}

async function main() {
	// Only a FULL run clears the folder. A filtered run (--only cart) must not
	// delete the other twenty-four shots — you reach for --only precisely when you
	// are iterating on one page, and having it silently destroy the rest of the
	// contact sheet makes the comparison you were about to do impossible.
	if ( ! only && existsSync( OUT ) ) await rm( OUT, { recursive: true, force: true } );
	await mkdir( OUT, { recursive: true } );

	const browser = await chromium.launch();
	const shot = [];

	for ( const origin of origins ) {
		const dir = path.join( OUT, label( origin ) );
		await mkdir( dir, { recursive: true } );

		console.log( `\n\x1b[1m${ origin }\x1b[0m` );

		// Does this origin have a shop at all? Ask once, rather than failing six
		// routes one at a time.
		let hasWoo = false;
		{
			const ctx = await browser.newContext();
			const p = await ctx.newPage();
			try {
				const r = await p.goto( `${ origin }/shop/`, { waitUntil: 'domcontentloaded', timeout: 20000 } );
				hasWoo = !! r && r.status() < 400;
			} catch { hasWoo = false; }
			await ctx.close();
		}

		for ( const vp of VIEWPORTS ) {
			// One context per viewport, so the cart cookie survives across the
			// routes within it but a mobile run never inherits a desktop cart.
			const ctx = await browser.newContext( {
				viewport: { width: vp.width, height: vp.height },
				deviceScaleFactor: 2,
				isMobile: vp.name === 'mobile',
				hasTouch: vp.name === 'mobile',
			} );
			const page = await ctx.newPage();

			let cartFilled = false;

			for ( const route of routes ) {
				const file = path.join( dir, `${ route.slug }-${ vp.name }.png` );
				const rel = path.relative( OUT, file );

				if ( route.woo && ! hasWoo ) {
					console.log( `  \x1b[90m·\x1b[0m ${ route.slug.padEnd( 17 ) } ${ vp.name.padEnd( 7 ) } \x1b[90mno shop on this site — skipped\x1b[0m` );
					shot.push( { origin, route, vp, rel: null, note: 'no shop on this site' } );
					continue;
				}

				// Fill the cart lazily, and only once per viewport — but BEFORE the
				// first route that needs it.
				if ( route.needsCart && ! cartFilled ) {
					cartFilled = await fillCart( page, origin );
					if ( ! cartFilled ) {
						console.log( `  \x1b[33m!\x1b[0m ${ route.slug.padEnd( 17 ) } ${ vp.name.padEnd( 7 ) } \x1b[33mcould not add to cart\x1b[0m` );
					}
				}

				try {
					const res = await page.goto( origin + route.path, { waitUntil: 'networkidle', timeout: 45000 } );
					const code = res ? res.status() : 0;

					await settle( page );
					await page.screenshot( { path: file, fullPage } );

					const bad = code >= 400 && route.slug !== '404';
					const mark = bad ? '\x1b[31m✗\x1b[0m' : '\x1b[32m✓\x1b[0m';
					console.log( `  ${ mark } ${ route.slug.padEnd( 17 ) } ${ vp.name.padEnd( 7 ) } ${ code }` );

					shot.push( { origin, route, vp, rel, code } );
				} catch ( e ) {
					console.log( `  \x1b[31m✗\x1b[0m ${ route.slug.padEnd( 17 ) } ${ vp.name.padEnd( 7 ) } \x1b[31m${ e.message.split( '\n' )[ 0 ] }\x1b[0m` );
					shot.push( { origin, route, vp, rel: null, note: e.message.split( '\n' )[ 0 ] } );
				}
			}

			await ctx.close();
		}
	}

	await browser.close();
	await writeFile( path.join( OUT, 'index.html' ), contactSheet( shot ), 'utf8' );

	const ok = shot.filter( ( s ) => s.rel ).length;
	console.log( `\n\x1b[1m${ ok }\x1b[0m shots → \x1b[36m${ path.join( OUT, 'index.html' ) }\x1b[0m` );
}

/**
 * A contact sheet.
 *
 * Desktop and mobile side by side, because almost every layout bug this theme has
 * had was a bug at ONE breakpoint that looked perfect at the other. Thirty
 * separate PNGs cannot show you that; two columns can.
 */
function contactSheet( shot ) {
	const byOrigin = {};
	for ( const s of shot ) {
		( byOrigin[ s.origin ] ||= {} );
		( byOrigin[ s.origin ][ s.route.slug ] ||= { route: s.route, shots: {} } );
		byOrigin[ s.origin ][ s.route.slug ].shots[ s.vp.name ] = s;
	}

	const cell = ( s ) => {
		if ( ! s ) return '<div class="miss">—</div>';
		if ( ! s.rel ) return `<div class="miss">${ esc( s.note || 'failed' ) }</div>`;
		return `<a href="${ s.rel }" target="_blank"><img src="${ s.rel }" loading="lazy"></a>`;
	};

	const body = Object.entries( byOrigin ).map( ( [ origin, rs ] ) => `
	<h2>${ esc( origin ) }</h2>
	${ Object.values( rs ).map( ( { route, shots } ) => `
	<section>
		<h3>${ esc( route.slug ) } <code>${ esc( route.path ) }</code></h3>
		<p class="desc">${ esc( route.desc ) }</p>
		<div class="pair">
			<figure class="d">${ cell( shots.desktop ) }<figcaption>desktop · 1440</figcaption></figure>
			<figure class="m">${ cell( shots.mobile ) }<figcaption>mobile · 390</figcaption></figure>
		</div>
	</section>` ).join( '' ) }` ).join( '' );

	return `<!doctype html><meta charset="utf-8"><title>Digest — contact sheet</title>
<style>
	:root { color-scheme: light dark; }
	body { font: 14px/1.5 ui-monospace, SFMono-Regular, Menlo, monospace; margin: 0; padding: 28px 32px 80px;
	       background: #14120f; color: #e7dfce; }
	h1 { font-size: 20px; margin: 0 0 4px; }
	h2 { font-size: 15px; margin: 40px 0 0; padding-bottom: 8px; border-bottom: 2px solid #6b1f1f;
	     letter-spacing: .08em; text-transform: uppercase; }
	section { margin: 30px 0 0; }
	h3 { font-size: 13px; margin: 0; letter-spacing: .1em; text-transform: uppercase; color: #c99a5b; }
	h3 code { color: #6b645a; text-transform: none; letter-spacing: 0; margin-left: 8px; }
	.desc { margin: 3px 0 12px; color: #6b645a; }
	.pair { display: grid; grid-template-columns: 1fr 390px; gap: 18px; align-items: start; }
	figure { margin: 0; }
	figure img { width: 100%; display: block; border: 1px solid #3a3630; background: #f4efe4; }
	figcaption { font-size: 11px; color: #6b645a; padding-top: 6px; letter-spacing: .08em; }
	.miss { display: grid; place-items: center; min-height: 120px; border: 1px dashed #3a3630;
	        color: #6b645a; font-size: 12px; padding: 20px; text-align: center; }
	@media (max-width: 900px) { .pair { grid-template-columns: 1fr; } }
</style>
<h1>Digest — contact sheet</h1>
<p class="desc">Regenerate: <code>./scripts/shoot.sh</code> · live: <code>./scripts/shoot.sh --live</code></p>
${ body }`;
}

const esc = ( s ) => String( s ).replace( /[&<>"]/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ c ] ) );

main().catch( ( e ) => { console.error( e ); process.exit( 1 ); } );
