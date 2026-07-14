/**
 * Newsletter form — progressive enhancement.
 *
 * The form works with plain HTML submit-and-reload as its baseline (see
 * shadow_digest_newsletter() in inc/template-tags.php). This intercepts submit and
 * posts via fetch instead, so a visitor sees an inline confirmation without leaving
 * the article. Any failure — JS disabled, fetch unsupported, network error, non-2xx
 * response — falls through to the plain form submit, which still works because the
 * form's action/method are real HTML attributes, not JS-only behaviour.
 */
( function () {
	'use strict';

	if ( typeof window.fetch !== 'function' ) {
		return;
	}

	document.querySelectorAll( '[data-digest-newsletter-form]' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			var status = form.parentElement
				? form.parentElement.querySelector( '[data-digest-newsletter-status]' )
				: null;
			var button = form.querySelector( 'button[type="submit"]' );

			setStatus( status, '', null );

			if ( button ) {
				button.disabled = true;
			}

			window
				.fetch( form.action, {
					method: 'POST',
					body: new FormData( form ),
					headers: { Accept: 'application/json' },
				} )
				.then( function ( response ) {
					if ( ! response.ok ) {
						throw new Error( 'newsletter signup failed' );
					}

					form.reset();
					setStatus( status, form.dataset.successText || '', 'success' );
				} )
				.catch( function () {
					setStatus( status, form.dataset.errorText || '', 'error' );
				} )
				.finally( function () {
					if ( button ) {
						button.disabled = false;
					}
				} );
		} );
	} );

	/**
	 * @param {Element|null} status
	 * @param {string} message
	 * @param {string|null} state
	 */
	function setStatus( status, message, state ) {
		if ( ! status ) {
			return;
		}

		status.textContent = message;

		if ( state ) {
			status.setAttribute( 'data-state', state );
		} else {
			status.removeAttribute( 'data-state' );
		}

		status.hidden = '' === message;
	}
} )();
