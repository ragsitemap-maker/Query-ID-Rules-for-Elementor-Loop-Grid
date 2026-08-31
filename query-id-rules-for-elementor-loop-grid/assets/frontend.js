( function () {
	'use strict';

	var hiddenClass = 'elgqr-hidden-on-empty';
	var selectorTargets = Object.create( null );
	var processedTargets = [];

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
		var fallback = tabs.find( function ( candidate ) {
			return candidate !== hiddenTab
				&& ! candidate.classList.contains( hiddenClass )
				&& ! candidate.hidden
				&& candidate.getAttribute( 'aria-disabled' ) !== 'true';
		} );

		if ( fallback ) {
			fallback.click();
		}
	}

	function hideTarget( target ) {
		if ( processedTargets.indexOf( target ) !== -1 ) {
			return;
		}

		processedTargets.push( target );

		var wasSelectedTab = target.getAttribute( 'role' ) === 'tab'
			&& target.getAttribute( 'aria-selected' ) === 'true';

		target.classList.add( hiddenClass );
		target.setAttribute( 'data-elgqr-hidden-empty', 'true' );

		if ( wasSelectedTab ) {
			activateFallbackTab( target );
		}
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
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initialize );
	} else {
		initialize();
	}
}() );
