( function () {
	'use strict';

	var hiddenClass = 'elgqr-hidden-on-empty';
	var maxReconcileAttempts = 240;
	var maxReconcileDuration = 2000;
	var selectorTargets = Object.create( null );
	var processedTargets = [];
	var retryInFlight = false;
	var retryAttempts = 0;
	var retryStartedAt = 0;

	function automaticTabTarget( widget ) {
		var panel = widget.closest( '[role="tabpanel"][aria-labelledby]' );
		var tabId = panel ? panel.getAttribute( 'aria-labelledby' ) : '';

		return tabId ? document.getElementById( tabId ) : null;
	}

	function targetsFor( record, widget ) {
		if ( ! record.selector ) {
			var automaticTarget = automaticTabTarget( widget );
			return automaticTarget ? [ automaticTarget ] : [];
		}

		try {
			if ( ! Object.prototype.hasOwnProperty.call( selectorTargets, record.selector ) ) {
				selectorTargets[ record.selector ] = Array.prototype.slice.call( document.querySelectorAll( record.selector ) );
			}

			return selectorTargets[ record.selector ];
		} catch ( error ) {
			return [];
		}
	}

	function activateFallbackTab( hiddenTab ) {
		if ( hiddenTab.getAttribute( 'role' ) !== 'tab' || hiddenTab.getAttribute( 'aria-selected' ) !== 'true' ) {
			return;
		}

		var tablist = hiddenTab.closest( '[role="tablist"]' );

		if ( ! tablist ) {
			return;
		}

		var tabs = Array.prototype.slice.call( tablist.querySelectorAll( '[role="tab"]' ) );
		var hiddenIndex = tabs.indexOf( hiddenTab );

		if ( hiddenIndex === -1 ) {
			return;
		}

		for ( var offset = 1; offset < tabs.length; offset += 1 ) {
			var candidate = tabs[ ( hiddenIndex + offset ) % tabs.length ];

			if ( candidate.classList.contains( hiddenClass )
				|| candidate.hidden
				|| candidate.getAttribute( 'aria-disabled' ) === 'true' ) {
				continue;
			}

			candidate.click();
			return;
		}
	}

	function reconcileHiddenSelectedTabs() {
		var pendingReadiness = false;

		processedTargets.forEach( function ( target ) {
			if ( target.getAttribute( 'role' ) !== 'tab'
				|| ! target.classList.contains( hiddenClass )
				|| target.getAttribute( 'aria-selected' ) !== 'true' ) {
				return;
			}

			var nestedTabs = target.closest( '.e-n-tabs' );

			if ( nestedTabs && ! nestedTabs.classList.contains( 'e-activated' ) ) {
				pendingReadiness = true;
				return;
			}

			activateFallbackTab( target );
		} );

		return pendingReadiness;
	}

	function resetRetry() {
		retryInFlight = false;
		retryAttempts = 0;
		retryStartedAt = 0;
	}

	function runReconciliationAttempt() {
		retryAttempts += 1;

		var pendingReadiness = reconcileHiddenSelectedTabs();

		if ( ! pendingReadiness ) {
			resetRetry();
			return;
		}

		if ( retryAttempts >= maxReconcileAttempts
			|| Date.now() - retryStartedAt >= maxReconcileDuration
			|| typeof window.requestAnimationFrame !== 'function' ) {
			resetRetry();
			return;
		}

		window.requestAnimationFrame( runReconciliationAttempt );
	}

	function startReconciliation() {
		if ( retryInFlight ) {
			return;
		}

		retryInFlight = true;
		retryAttempts = 0;
		retryStartedAt = Date.now();
		runReconciliationAttempt();
	}

	function hideTarget( target ) {
		if ( processedTargets.indexOf( target ) !== -1 ) {
			return;
		}

		processedTargets.push( target );

		target.classList.add( hiddenClass );
		target.setAttribute( 'data-elgqr-hidden-empty', 'true' );
	}

	function applyRecord( record ) {
		if ( ! record || ! /^[a-z0-9_-]+$/.test( record.widgetId || '' ) ) {
			return;
		}

		var widget = document.querySelector( '.elementor-element-' + record.widgetId );

		if ( ! widget ) {
			return;
		}

		targetsFor( record, widget ).forEach( hideTarget );
	}

	function initialize() {
		var config = window.ELGQR_EMPTY_RESULTS;

		if ( document.body && document.body.classList.contains( 'elementor-editor-active' ) ) {
			return;
		}

		if ( ! config || ! Array.isArray( config.records ) ) {
			return;
		}

		config.records.forEach( applyRecord );
		startReconciliation();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initialize );
	} else {
		initialize();
	}
}() );
