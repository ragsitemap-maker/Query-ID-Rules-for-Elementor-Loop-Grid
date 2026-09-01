'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

function assertSame( expected, actual, message ) {
	if ( expected !== actual ) {
		throw new Error( message + '\nExpected: ' + expected + '\nActual: ' + actual );
	}
}

function classList( initial = [] ) {
	const values = initial.slice();

	return {
		addCalls: 0,
		add( value ) {
			this.addCalls += 1;
			if ( values.indexOf( value ) === -1 ) {
				values.push( value );
			}
		},
		remove( value ) {
			const index = values.indexOf( value );
			if ( index !== -1 ) {
				values.splice( index, 1 );
			}
		},
		contains( value ) {
			return values.indexOf( value ) !== -1;
		}
	};
}

function element( attributes = {}, classes = [] ) {
	return {
		attributes: Object.assign( {}, attributes ),
		classList: classList( classes ),
		hidden: false,
		closestMap: {},
		clickCalls: 0,
		onClick: null,
		getAttribute( key ) {
			return Object.prototype.hasOwnProperty.call( this.attributes, key ) ? this.attributes[ key ] : null;
		},
		setAttribute( key, value ) {
			this.attributes[ key ] = value;
		},
		closest( selector ) {
			return this.closestMap[ selector ] || null;
		},
		click() {
			this.clickCalls += 1;
			if ( this.onClick ) {
				this.onClick();
			}
		}
	};
}

function runFixture( options = {} ) {
	const groupOptions = options.groups || [ { count: 3, activeIndex: 0, emptyIndexes: [ 0 ] } ];
	const handlerReady = { value: !! options.handlerReady };
	const widgets = {};
	const tabsById = {};
	const records = [];
	const groups = groupOptions.map( ( settings, groupIndex ) => {
		const state = { activePanelIndex: settings.activeIndex };
		const tabs = [];
		const panels = [];
		const tablist = element();
		const tabsWidget = element();

		if ( handlerReady.value ) {
			tabsWidget.classList.add( 'e-activated' );
		}

		for ( let tabIndex = 0; tabIndex < settings.count; tabIndex += 1 ) {
			const id = 'tab-' + groupIndex + '-' + tabIndex;
			const tab = element( {
				id,
				role: 'tab',
				'aria-selected': tabIndex === settings.activeIndex ? 'true' : 'false',
				'aria-disabled': ( settings.disabledIndexes || [] ).indexOf( tabIndex ) !== -1 ? 'true' : 'false'
			} );
			const panel = element(
				{ role: 'tabpanel', 'aria-labelledby': id },
				tabIndex === settings.activeIndex ? [ 'e-active' ] : []
			);

			tab.hidden = ( settings.hiddenIndexes || [] ).indexOf( tabIndex ) !== -1;
			tab.closestMap[ '[role="tablist"]' ] = tablist;
			tab.closestMap[ '.e-n-tabs' ] = tabsWidget;
			tab.onClick = () => {
				if ( ! handlerReady.value ) {
					return;
				}

				tabs.forEach( candidate => candidate.setAttribute( 'aria-selected', candidate === tab ? 'true' : 'false' ) );
				panels.forEach( ( candidate, panelIndex ) => {
					if ( panelIndex === tabIndex ) {
						candidate.classList.add( 'e-active' );
					} else {
						candidate.classList.remove( 'e-active' );
					}
				} );
				state.activePanelIndex = tabIndex;
			};
			tabs.push( tab );
			panels.push( panel );
			tabsById[ id ] = tab;
		}

		tablist.querySelectorAll = selector => '[role="tab"]' === selector ? tabs : [];

		settings.emptyIndexes.forEach( tabIndex => {
			const widgetId = 'auto_' + groupIndex + '_' + tabIndex;
			const widget = element();
			widget.closestMap[ '[role="tabpanel"][aria-labelledby]' ] = panels[ tabIndex ];
			widgets[ '.elementor-element-' + widgetId ] = widget;
			records.push( { widgetId, selector: '' } );
		} );

		return { tabs, panels, tablist, tabsWidget, state };
	} );

	const customTarget = element();
	widgets[ '.elementor-element-custom_a' ] = element();
	widgets[ '.elementor-element-custom_b' ] = element();

	if ( false !== options.includeCustom ) {
		records.push(
			{ widgetId: 'custom_a', selector: '.shared-target' },
			{ widgetId: 'custom_b', selector: '.shared-target' },
			{ widgetId: 'custom_a', selector: '[' }
		);
	}

	let selectorQueries = 0;
	let now = 0;
	let frameRuns = 0;
	const scheduled = [];
	const domReadyCallbacks = [];
	const document = {
		readyState: options.readyState || 'complete',
		body: { classList: { contains: () => !! options.editorActive } },
		querySelector: selector => widgets[ selector ] || null,
		querySelectorAll: selector => {
			selectorQueries += 1;
			if ( '[' === selector ) {
				throw new Error( 'Invalid selector' );
			}
			return '.shared-target' === selector ? [ customTarget ] : [];
		},
		getElementById: id => tabsById[ id ] || null,
		addEventListener( name, callback ) {
			if ( 'DOMContentLoaded' === name ) {
				domReadyCallbacks.push( callback );
			}
		}
	};

	class FakeDate {
		static now() {
			return now;
		}
	}

	const window = { ELGQR_EMPTY_RESULTS: { records } };

	if ( false !== options.withAnimationFrame ) {
		window.requestAnimationFrame = callback => scheduled.push( callback );
	}

	const context = { document, window, Array, Object, RegExp, Date: FakeDate };
	const source = fs.readFileSync( path.join( __dirname, '..', 'assets', 'frontend.js' ), 'utf8' );
	vm.runInNewContext( source, context );

	return {
		groups,
		customTarget,
		source,
		get selectorQueries() {
			return selectorQueries;
		},
		get pendingFrames() {
			return scheduled.length;
		},
		get frameRuns() {
			return frameRuns;
		},
		advanceTime( milliseconds ) {
			now += milliseconds;
		},
		flushOne( milliseconds = 16 ) {
			if ( ! scheduled.length ) {
				return false;
			}
			now += milliseconds;
			frameRuns += 1;
			scheduled.shift()();
			return true;
		},
		flushAll( milliseconds = 16, guard = 500 ) {
			while ( this.flushOne( milliseconds ) ) {
				if ( frameRuns > guard ) {
					throw new Error( 'Bounded reconciliation did not settle.' );
				}
			}
		},
		fireDOMContentLoaded() {
			domReadyCallbacks.forEach( callback => callback() );
		},
		activateWithoutEvent() {
			handlerReady.value = true;
			groups.forEach( group => group.tabsWidget.classList.add( 'e-activated' ) );
		}
	};
}

try {
	const firstEmpty = runFixture();
	assertSame( true, firstEmpty.groups[0].tabs[0].classList.contains( 'elgqr-hidden-on-empty' ), 'The empty first TAB must be hidden before readiness.' );
	assertSame( true, firstEmpty.groups[0].panels[0].classList.contains( 'e-active' ), 'The fixture must begin with the empty panel active.' );
	assertSame( 0, firstEmpty.groups[0].tabs[1].clickCalls, 'An early attempt must not click before Elementor readiness.' );
	assertSame( 1, firstEmpty.pendingFrames, 'A hidden selected Nested TAB must schedule one bounded retry.' );
	firstEmpty.activateWithoutEvent();
	firstEmpty.flushOne();
	assertSame( 1, firstEmpty.groups[0].state.activePanelIndex, 'The next visible TAB must become active without an activation event.' );
	assertSame( 'false', firstEmpty.groups[0].tabs[0].getAttribute( 'aria-selected' ), 'The hidden first TAB must no longer remain selected.' );
	assertSame( 'true', firstEmpty.groups[0].tabs[1].getAttribute( 'aria-selected' ), 'The second TAB must become selected.' );
	assertSame( false, firstEmpty.groups[0].panels[0].classList.contains( 'e-active' ), 'The empty panel must no longer remain active.' );
	assertSame( true, firstEmpty.groups[0].panels[1].classList.contains( 'e-active' ), 'The fallback panel must be active.' );
	assertSame( 0, firstEmpty.pendingFrames, 'A successful transition must terminate the retry loop.' );
	assertSame( true, firstEmpty.customTarget.classList.contains( 'elgqr-hidden-on-empty' ), 'A custom selector target must still be hidden.' );
	assertSame( 1, firstEmpty.customTarget.classList.addCalls, 'Duplicate selectors must not process the same target twice.' );
	assertSame( 2, firstEmpty.selectorQueries, 'A shared selector must be queried once; the invalid selector is attempted once.' );

	const consecutive = runFixture( { groups: [ { count: 3, activeIndex: 0, emptyIndexes: [ 0, 1 ] } ], includeCustom: false } );
	consecutive.activateWithoutEvent();
	consecutive.flushOne();
	assertSame( 2, consecutive.groups[0].state.activePanelIndex, 'Consecutive empty TABs must skip to the first visible candidate.' );
	assertSame( 0, consecutive.groups[0].tabs[1].clickCalls, 'A TAB hidden by the same batch must never be used as fallback.' );

	const nextInOrder = runFixture( { groups: [ { count: 4, activeIndex: 1, emptyIndexes: [ 1 ] } ], includeCustom: false } );
	nextInOrder.activateWithoutEvent();
	nextInOrder.flushOne();
	assertSame( 2, nextInOrder.groups[0].state.activePanelIndex, 'Fallback must prefer the next visible TAB instead of the first TAB.' );

	const wrapped = runFixture( { groups: [ { count: 3, activeIndex: 2, emptyIndexes: [ 2 ] } ], includeCustom: false } );
	wrapped.activateWithoutEvent();
	wrapped.flushOne();
	assertSame( 0, wrapped.groups[0].state.activePanelIndex, 'Fallback must wrap within the same tablist.' );

	const unavailableCandidates = runFixture( {
		groups: [ { count: 4, activeIndex: 0, emptyIndexes: [ 0 ], disabledIndexes: [ 1 ], hiddenIndexes: [ 2 ] } ],
		includeCustom: false
	} );
	unavailableCandidates.activateWithoutEvent();
	unavailableCandidates.flushOne();
	assertSame( 3, unavailableCandidates.groups[0].state.activePanelIndex, 'Fallback must skip disabled and natively hidden TABs.' );

	const allEmpty = runFixture( { groups: [ { count: 3, activeIndex: 0, emptyIndexes: [ 0, 1, 2 ] } ], includeCustom: false } );
	allEmpty.activateWithoutEvent();
	allEmpty.flushOne();
	assertSame( 0, allEmpty.groups[0].state.activePanelIndex, 'An all-empty tablist must remain a no-op.' );
	assertSame( 0, allEmpty.groups[0].tabs.reduce( ( total, tab ) => total + tab.clickCalls, 0 ), 'An all-empty tablist must not click a hidden TAB.' );
	assertSame( 0, allEmpty.pendingFrames, 'An all-empty ready tablist must terminate immediately.' );

	const multiple = runFixture( {
		groups: [ { count: 2, activeIndex: 0, emptyIndexes: [ 0 ] }, { count: 3, activeIndex: 2, emptyIndexes: [ 2 ] } ],
		includeCustom: false
	} );
	multiple.activateWithoutEvent();
	multiple.flushOne();
	assertSame( 1, multiple.groups[0].state.activePanelIndex, 'The first tablist must select only its own fallback.' );
	assertSame( 0, multiple.groups[1].state.activePanelIndex, 'The second tablist must wrap within its own candidates.' );

	const neverReady = runFixture( { includeCustom: false } );
	neverReady.flushAll( 1 );
	assertSame( 239, neverReady.frameRuns, 'The attempt cap must allow at most 240 attempts including the initial attempt.' );
	assertSame( 0, neverReady.pendingFrames, 'A never-ready handler must leave no scheduled callback.' );
	assertSame( 0, neverReady.groups[0].tabs[1].clickCalls, 'A never-ready handler must not click after timeout.' );

	const deadline = runFixture( { includeCustom: false } );
	deadline.flushAll( 100 );
	assertSame( 20, deadline.frameRuns, 'The 2000 ms deadline must terminate retries before the attempt cap.' );
	assertSame( 0, deadline.pendingFrames, 'The deadline must leave no scheduled callback.' );

	const resumedReady = runFixture( { includeCustom: false } );
	resumedReady.activateWithoutEvent();
	resumedReady.advanceTime( 3000 );
	resumedReady.flushOne( 0 );
	assertSame( 1, resumedReady.groups[0].state.activePanelIndex, 'A resumed page must commit ready state before applying the elapsed-time terminal check.' );
	assertSame( 0, resumedReady.pendingFrames, 'A resumed successful transition must terminate.' );

	const reentry = runFixture( { includeCustom: false, readyState: 'loading' } );
	reentry.fireDOMContentLoaded();
	assertSame( 1, reentry.pendingFrames, 'The first initialization must create one retry.' );
	reentry.fireDOMContentLoaded();
	assertSame( 1, reentry.pendingFrames, 'Repeated initialization must not create a second in-flight retry.' );
	assertSame( 1, reentry.groups[0].tabs[0].classList.addCalls, 'Repeated initialization must not process a target twice.' );
	reentry.activateWithoutEvent();
	reentry.flushOne();
	assertSame( 1, reentry.groups[0].tabs[1].clickCalls, 'Reentry must still produce only one successful fallback click.' );

	const noAnimationFrame = runFixture( { includeCustom: false, withAnimationFrame: false } );
	assertSame( 0, noAnimationFrame.pendingFrames, 'Missing requestAnimationFrame must fail closed without persistent work.' );
	assertSame( 0, noAnimationFrame.groups[0].tabs[1].clickCalls, 'Missing requestAnimationFrame must not click before readiness.' );

	const editor = runFixture( { editorActive: true } );
	assertSame( false, editor.groups[0].tabs[0].classList.contains( 'elgqr-hidden-on-empty' ), 'Editor mode must not hide automatic targets.' );
	assertSame( false, editor.customTarget.classList.contains( 'elgqr-hidden-on-empty' ), 'Editor mode must not hide custom targets.' );
	assertSame( 0, editor.pendingFrames, 'Editor mode must not start a retry loop.' );

	assertSame( false, /MutationObserver|setInterval|elementor\/nested-tabs\/activate|frontend\/element_ready/.test( firstEmpty.source ), 'The runtime must not restore observer, interval, or failed event paths.' );
	console.log( 'Frontend empty-result bounded-retry contract tests passed.' );
} catch ( error ) {
	console.error( error.message );
	process.exit( 1 );
}
