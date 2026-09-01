( function () {
	'use strict';

	var hiddenClass = 'elgqr-hidden-on-empty';
	var reconcileDelay = 100;
	var maxReconcileAttempts = 100;
	var maxReconcileDuration = 10000;
	var selectorTargets = Object.create( null );
	var processedTargets = [];
	var resultCountRecords = [];
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

	function isAvailableTab( candidate ) {
		return ! candidate.classList.contains( hiddenClass )
			&& ! candidate.hidden
			&& candidate.getAttribute( 'aria-disabled' ) !== 'true';
	}

	function nextAvailableTab( hiddenTab, tabs ) {
		var hiddenIndex = tabs.indexOf( hiddenTab );

		if ( hiddenIndex === -1 ) {
			return null;
		}

		for ( var offset = 1; offset < tabs.length; offset += 1 ) {
			var candidate = tabs[ ( hiddenIndex + offset ) % tabs.length ];

			if ( isAvailableTab( candidate ) ) {
				return candidate;
			}
		}

		return null;
	}

	function resultCountsByTab() {
		var states = new Map();

		resultCountRecords.forEach( function ( record ) {
			if ( ! record || ! /^[a-z0-9_-]+$/.test( record.widgetId || '' ) ) {
				return;
			}

			var widget = document.querySelector( '.elementor-element-' + record.widgetId );
			var tab = widget ? automaticTabTarget( widget ) : null;

			if ( ! tab ) {
				return;
			}

			var state = states.get( tab ) || { seen: 0, exact: false, total: null };
			state.seen += 1;

			if ( state.seen === 1 && Number.isInteger( record.total ) && record.total >= 0 ) {
				state.exact = true;
				state.total = record.total;
			} else {
				state.exact = false;
				state.total = null;
			}

			states.set( tab, state );
		} );

		return states;
	}

	function maximumAvailableTab( candidates, countStates ) {
		var maximum = null;
		var maximumTotal = -1;

		for ( var index = 0; index < candidates.length; index += 1 ) {
			var candidate = candidates[ index ];
			var state = countStates.get( candidate );

			if ( ! state || state.seen !== 1 || ! state.exact ) {
				return null;
			}

			if ( state.total > maximumTotal ) {
				maximum = candidate;
				maximumTotal = state.total;
			}
		}

		return maximumTotal > 0 ? maximum : null;
	}

	function reconcileTablist( tablist, countStates ) {
		var tabs = Array.prototype.slice.call( tablist.querySelectorAll( '[role="tab"]' ) );
		var candidates = tabs.filter( isAvailableTab );
		var selected = null;

		for ( var index = 0; index < tabs.length; index += 1 ) {
			if ( tabs[ index ].getAttribute( 'aria-selected' ) === 'true' ) {
				selected = tabs[ index ];
				break;
			}
		}

		var fallback = maximumAvailableTab( candidates, countStates );

		if ( ! fallback && selected && selected.classList.contains( hiddenClass ) ) {
			fallback = nextAvailableTab( selected, tabs );
		}

		if ( fallback && fallback.getAttribute( 'aria-selected' ) !== 'true' ) {
			fallback.click();
		}
	}

	function reconcileAffectedTablists() {
		var pendingReadiness = false;
		var countStates = null;
		var affectedTablists = [];

		processedTargets.forEach( function ( target ) {
			if ( target.getAttribute( 'role' ) !== 'tab'
				|| ! target.classList.contains( hiddenClass ) ) {
				return;
			}

			var tablist = target.closest( '[role="tablist"]' );

			if ( ! tablist || affectedTablists.some( function ( state ) { return state.tablist === tablist; } ) ) {
				return;
			}

			affectedTablists.push( {
				tablist: tablist,
				nestedTabs: target.closest( '.e-n-tabs' )
			} );
		} );

		affectedTablists.forEach( function ( state ) {
			if ( state.nestedTabs && ! state.nestedTabs.classList.contains( 'e-activated' ) ) {
				pendingReadiness = true;
				return;
			}

			if ( null === countStates ) {
				countStates = resultCountsByTab();
			}

			reconcileTablist( state.tablist, countStates );
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

		var pendingReadiness = reconcileAffectedTablists();

		if ( ! pendingReadiness ) {
			resetRetry();
			return;
		}

		if ( retryAttempts >= maxReconcileAttempts
			|| Date.now() - retryStartedAt >= maxReconcileDuration
			|| typeof window.setTimeout !== 'function' ) {
			resetRetry();
			return;
		}

		window.setTimeout( runReconciliationAttempt, reconcileDelay );
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

		resultCountRecords = Array.isArray( config.counts ) ? config.counts : [];
		config.records.forEach( applyRecord );
		startReconciliation();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initialize );
	} else {
		initialize();
	}
}() );
