'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

function assertSame( expected, actual, message ) {
	if ( expected !== actual ) {
		throw new Error( message + '\nExpected: ' + expected + '\nActual: ' + actual );
	}
}

function classList() {
	const values = [];
	return {
		addCalls: 0,
		add( value ) {
			this.addCalls += 1;
			if ( values.indexOf( value ) === -1 ) {
				values.push( value );
			}
		},
		contains( value ) {
			return values.indexOf( value ) !== -1;
		}
	};
}

function element( attributes = {} ) {
	return {
		attributes: Object.assign( {}, attributes ),
		classList: classList(),
		hidden: false,
		closestMap: {},
		clickCalls: 0,
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
		}
	};
}

function runFixture( editorActive ) {
	const hiddenTab = element( { role: 'tab', 'aria-selected': 'true' } );
	const fallback = element( { role: 'tab', 'aria-selected': 'false' } );
	const tablist = element();
	tablist.querySelectorAll = () => [ hiddenTab, fallback ];
	hiddenTab.closestMap[ '[role="tablist"]' ] = tablist;

	const panel = element( { 'aria-labelledby': 'hidden-tab' } );
	const autoWidget = element();
	autoWidget.closestMap[ '[role="tabpanel"][aria-labelledby]' ] = panel;
	const customWidgetA = element();
	const customWidgetB = element();
	const customTarget = element();

	const widgets = {
		'.elementor-element-auto': autoWidget,
		'.elementor-element-custom_a': customWidgetA,
		'.elementor-element-custom_b': customWidgetB
	};
	let selectorQueries = 0;
	const document = {
		readyState: 'complete',
		body: { classList: { contains: () => editorActive } },
		querySelector: selector => widgets[ selector ] || null,
		querySelectorAll: selector => {
			selectorQueries += 1;
			if ( '[' === selector ) {
				throw new Error( 'Invalid selector' );
			}
			return '.shared-target' === selector ? [ customTarget ] : [];
		},
		getElementById: id => 'hidden-tab' === id ? hiddenTab : null,
		addEventListener: () => {}
	};

	const context = {
		document,
		window: {
			ELGQR_EMPTY_RESULTS: {
				records: [
					{ widgetId: 'auto', selector: '' },
					{ widgetId: 'custom_a', selector: '.shared-target' },
					{ widgetId: 'custom_b', selector: '.shared-target' },
					{ widgetId: 'custom_a', selector: '[' }
				]
			}
		},
		Array,
		Object,
		RegExp
	};

	const source = fs.readFileSync( path.join( __dirname, '..', 'assets', 'frontend.js' ), 'utf8' );
	vm.runInNewContext( source, context );

	return { hiddenTab, fallback, customTarget, selectorQueries };
}

try {
	const result = runFixture( false );
	assertSame( true, result.hiddenTab.classList.contains( 'elgqr-hidden-on-empty' ), 'Automatic Nested Tabs target must be hidden.' );
	assertSame( 1, result.fallback.clickCalls, 'A selected hidden tab must activate one fallback tab.' );
	assertSame( true, result.customTarget.classList.contains( 'elgqr-hidden-on-empty' ), 'A custom selector target must be hidden.' );
	assertSame( 1, result.customTarget.classList.addCalls, 'Duplicate selectors must not process the same target twice.' );
	assertSame( 2, result.selectorQueries, 'A shared selector must be queried once; the distinct invalid selector is attempted once.' );

	const editor = runFixture( true );
	assertSame( false, editor.hiddenTab.classList.contains( 'elgqr-hidden-on-empty' ), 'Editor mode must not hide automatic targets.' );
	assertSame( false, editor.customTarget.classList.contains( 'elgqr-hidden-on-empty' ), 'Editor mode must not hide custom targets.' );
	console.log( 'Frontend empty-result contract tests passed.' );
} catch ( error ) {
	console.error( error.message );
	process.exit( 1 );
}
