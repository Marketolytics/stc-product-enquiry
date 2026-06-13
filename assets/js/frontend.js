/**
 * STC Product Enquiry - Frontend behaviour.
 *
 * Handles opening the enquiry modal, populating product data (from the
 * triggering button or the closest product container), validating input and
 * submitting via WordPress AJAX without a page reload.
 *
 * @package STC_Product_Enquiry
 */
( function () {
	'use strict';

	var settings = window.STC_PE || {};
	var i18n = settings.i18n || {};

	var modal = null;
	var form = null;
	var messageBox = null;
	var lastFocused = null;

	/**
	 * Run a callback once the DOM is ready.
	 *
	 * @param {Function} fn Callback.
	 */
	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	/**
	 * Find the closest ancestor matching a selector.
	 *
	 * @param {Element} el       Starting element.
	 * @param {string}  selector CSS selector.
	 * @return {Element|null} Matched element or null.
	 */
	function closest( el, selector ) {
		if ( el.closest ) {
			return el.closest( selector );
		}
		while ( el ) {
			if ( el.matches && el.matches( selector ) ) {
				return el;
			}
			el = el.parentElement;
		}
		return null;
	}

	/**
	 * Attempt to resolve product data from a triggering button and, when
	 * missing, from the closest product container in the DOM.
	 *
	 * @param {Element} btn The Enquire Now button.
	 * @return {Object} Product data.
	 */
	function resolveProductData( btn ) {
		var data = {
			id: btn.getAttribute( 'data-product-id' ) || '',
			name: btn.getAttribute( 'data-product-name' ) || '',
			sku: btn.getAttribute( 'data-product-sku' ) || '',
			url: btn.getAttribute( 'data-product-url' ) || ''
		};

		// If essential data is missing, inspect the surrounding product container.
		if ( ! data.name || ! data.id ) {
			var container = closest(
				btn,
				'li.product, .product, .elementor-widget-woocommerce-product, .eael-product-grid, .eael-product, .woocommerce-product-gallery, [data-product-id], [data-product_id]'
			);

			if ( container ) {
				if ( ! data.id ) {
					data.id =
						container.getAttribute( 'data-product-id' ) ||
						container.getAttribute( 'data-product_id' ) ||
						extractIdFromClass( container ) ||
						'';
				}

				if ( ! data.name ) {
					var titleEl = container.querySelector(
						'.woocommerce-loop-product__title, .product_title, h2.woocommerce-loop-product__title, .eael-product-title, h1, h2, h3'
					);
					if ( titleEl ) {
						data.name = titleEl.textContent.trim();
					}
				}

				if ( ! data.url ) {
					var linkEl = container.querySelector( 'a[href]' );
					if ( linkEl ) {
						data.url = linkEl.getAttribute( 'href' );
					}
				}
			}
		}

		// Final fallback for URL on single product pages.
		if ( ! data.url ) {
			data.url = window.location.href;
		}

		return data;
	}

	/**
	 * Extract a product ID from "post-123" style class names.
	 *
	 * @param {Element} el Element.
	 * @return {string} ID or empty string.
	 */
	function extractIdFromClass( el ) {
		var match = ( el.className || '' ).match( /\bpost-(\d+)\b/ );
		return match ? match[ 1 ] : '';
	}

	/**
	 * Populate the modal hidden fields and product label.
	 *
	 * @param {Object} data Product data.
	 */
	function populateModal( data ) {
		if ( ! form ) {
			return;
		}

		form.querySelector( 'input[name="product_id"]' ).value = data.id || '';
		form.querySelector( 'input[name="product_name"]' ).value = data.name || '';
		form.querySelector( 'input[name="product_sku"]' ).value = data.sku || '';
		form.querySelector( 'input[name="product_url"]' ).value = data.url || '';

		var label = modal.querySelector( '[data-stc-pe-product-label]' );
		if ( label ) {
			if ( data.name ) {
				label.textContent = data.name;
				label.hidden = false;
			} else {
				label.textContent = '';
				label.hidden = true;
			}
		}
	}

	/**
	 * Open the modal.
	 *
	 * @param {Element} btn Triggering button.
	 */
	function openModal( btn ) {
		if ( ! modal ) {
			return;
		}

		lastFocused = btn;
		clearMessage();
		clearErrors();

		populateModal( resolveProductData( btn ) );

		modal.hidden = false;
		// Force reflow so the transition runs.
		void modal.offsetWidth;
		modal.classList.add( 'is-open' );
		document.body.classList.add( 'stc-pe-modal-open' );

		var firstInput = form ? form.querySelector( 'input[name="customer_name"]' ) : null;
		if ( firstInput ) {
			firstInput.focus();
		}
	}

	/**
	 * Close the modal.
	 */
	function closeModal() {
		if ( ! modal ) {
			return;
		}

		modal.classList.remove( 'is-open' );
		document.body.classList.remove( 'stc-pe-modal-open' );

		window.setTimeout( function () {
			modal.hidden = true;
		}, 250 );

		if ( lastFocused && typeof lastFocused.focus === 'function' ) {
			lastFocused.focus();
		}
	}

	/**
	 * Show a message in the modal.
	 *
	 * @param {string}  text Message text.
	 * @param {string}  type "error" or "success".
	 */
	function showMessage( text, type ) {
		if ( ! messageBox ) {
			return;
		}
		messageBox.textContent = text;
		messageBox.className = 'stc-pe-message is-' + ( type || 'error' );
		messageBox.hidden = false;
	}

	/**
	 * Clear any displayed message.
	 */
	function clearMessage() {
		if ( ! messageBox ) {
			return;
		}
		messageBox.textContent = '';
		messageBox.hidden = true;
		messageBox.className = 'stc-pe-message';
	}

	/**
	 * Clear field error states.
	 */
	function clearErrors() {
		if ( ! form ) {
			return;
		}
		var invalids = form.querySelectorAll( '.stc-pe-invalid' );
		Array.prototype.forEach.call( invalids, function ( el ) {
			el.classList.remove( 'stc-pe-invalid' );
		} );
	}

	/**
	 * Validate the form client-side.
	 *
	 * @return {boolean} Whether the form is valid.
	 */
	function validateForm() {
		clearErrors();

		var nameField = form.querySelector( 'input[name="customer_name"]' );
		var mobileField = form.querySelector( 'input[name="mobile"]' );
		var errors = [];

		if ( ! nameField.value.trim() ) {
			nameField.classList.add( 'stc-pe-invalid' );
			errors.push( i18n.requiredName || 'Please enter your name.' );
		}

		var digits = ( mobileField.value || '' ).replace( /\D/g, '' );
		if ( digits.length < 7 || digits.length > 15 ) {
			mobileField.classList.add( 'stc-pe-invalid' );
			errors.push( i18n.requiredMobile || 'Please enter a valid mobile number.' );
		}

		if ( errors.length ) {
			showMessage( errors.join( ' ' ), 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Submit the form via AJAX.
	 *
	 * @param {Event} event Submit event.
	 */
	function handleSubmit( event ) {
		event.preventDefault();

		if ( ! validateForm() ) {
			return;
		}

		clearMessage();

		var submitBtn = form.querySelector( '.stc-pe-submit' );
		var originalLabel = submitBtn ? submitBtn.textContent : '';
		if ( submitBtn ) {
			submitBtn.disabled = true;
			submitBtn.textContent = i18n.sending || 'Sending…';
		}

		var payload = new FormData( form );
		payload.append( 'action', settings.action || 'stc_pe_submit_enquiry' );
		payload.append( 'nonce', settings.nonce || '' );

		var request = new XMLHttpRequest();
		request.open( 'POST', settings.ajaxUrl, true );

		request.onload = function () {
			restoreButton( submitBtn, originalLabel );

			var response = null;
			try {
				response = JSON.parse( request.responseText );
			} catch ( e ) {
				response = null;
			}

			if ( response && response.success ) {
				showMessage(
					( response.data && response.data.message ) || i18n.success || 'Thank you!',
					'success'
				);
				form.reset();
				window.setTimeout( closeModal, 2200 );
			} else {
				var msg =
					( response && response.data && response.data.message ) ||
					i18n.error ||
					'Something went wrong. Please try again.';
				showMessage( msg, 'error' );
			}
		};

		request.onerror = function () {
			restoreButton( submitBtn, originalLabel );
			showMessage( i18n.error || 'Something went wrong. Please try again.', 'error' );
		};

		request.send( payload );
	}

	/**
	 * Restore the submit button to its idle state.
	 *
	 * @param {Element} btn   Submit button.
	 * @param {string}  label Original label.
	 */
	function restoreButton( btn, label ) {
		if ( btn ) {
			btn.disabled = false;
			btn.textContent = label;
		}
	}

	/**
	 * Bind global event listeners.
	 */
	function bindEvents() {
		// Open via delegation (works for dynamically loaded / AJAX content).
		document.addEventListener( 'click', function ( event ) {
			var trigger = closest( event.target, '[data-stc-pe-open]' );
			if ( trigger ) {
				event.preventDefault();
				event.stopPropagation();
				openModal( trigger );
				return;
			}

			// Close when clicking the overlay background or a close control.
			if ( event.target === modal || closest( event.target, '[data-stc-pe-close]' ) ) {
				event.preventDefault();
				closeModal();
			}
		} );

		// Close on Escape.
		document.addEventListener( 'keydown', function ( event ) {
			if ( ( event.key === 'Escape' || event.keyCode === 27 ) && modal && ! modal.hidden ) {
				closeModal();
			}
		} );

		if ( form ) {
			form.addEventListener( 'submit', handleSubmit );
		}
	}

	ready( function () {
		modal = document.getElementById( 'stc-pe-modal' );
		form = document.getElementById( 'stc-pe-form' );
		messageBox = document.getElementById( 'stc-pe-message' );

		if ( ! modal || ! form ) {
			// Modal markup not present; still allow buttons to be bound later.
			return;
		}

		bindEvents();
	} );
} )();
