import { chromium } from 'playwright';

/** @type {import('playwright').Browser | null} */
let sharedBrowser = null;

/** @type {Promise<import('playwright').Browser> | null} */
let browserLaunchPromise = null;

/** @type {Set<{ close: () => Promise<void>, closed: boolean }>} */
const openSessions = new Set();

/**
 * True when HEADED=1 opts into headed (non-headless) mode.
 * Default is headless Playwright-managed Chromium — never system Google Chrome.
 */
export function isHeadedMode() {
	return process.env.HEADED === '1';
}

/**
 * Launch options for Playwright-managed Chromium (never channel: 'chrome').
 */
export function defaultLaunchOptions( overrides = {} ) {
	return {
		headless: !isHeadedMode(),
		args: [ '--no-sandbox' ],
		...overrides,
	};
}

async function getSharedBrowser( launchOptions = {} ) {
	if ( sharedBrowser?.isConnected() ) {
		return sharedBrowser;
	}

	if ( ! browserLaunchPromise ) {
		browserLaunchPromise = chromium
			.launch( defaultLaunchOptions( launchOptions ) )
			.then( ( browser ) => {
				sharedBrowser = browser;
				browserLaunchPromise = null;
				return browser;
			} )
			.catch( ( error ) => {
				browserLaunchPromise = null;
				throw error;
			} );
	}

	return browserLaunchPromise;
}

/**
 * One shared Browser process; each call gets a fresh isolated context + page.
 * `close()` is idempotent and tears down the shared browser when the last session closes.
 *
 * @param {import('playwright').BrowserContextOptions & { launchOptions?: import('playwright').LaunchOptions }} [options]
 */
export async function createIsolatedBrowserSession( options = {} ) {
	const { launchOptions, ...contextOptions } = options;
	const browser = await getSharedBrowser( launchOptions );
	const context = await browser.newContext( contextOptions );
	const page = await context.newPage();

	let closed = false;

	const close = async () => {
		if ( closed ) {
			return;
		}
		closed = true;
		openSessions.delete( sessionHandle );

		await page.close( { runBeforeUnload: false } ).catch( () => {} );
		await context.close().catch( () => {} );

		if ( openSessions.size === 0 && sharedBrowser?.isConnected() ) {
			await sharedBrowser.close().catch( () => {} );
			sharedBrowser = null;
		}
	};

	const sessionHandle = { close, closed: false };
	openSessions.add( sessionHandle );

	return { browser, context, page, close };
}

/** Close every tracked session and the shared browser. Idempotent. */
export async function closeAllBrowserSessions() {
	const sessions = [ ...openSessions ];
	await Promise.all( sessions.map( ( session ) => session.close() ) );
}

/** @returns {number} Active session count (for tests/diagnostics). */
export function getOpenSessionCount() {
	return openSessions.size;
}
