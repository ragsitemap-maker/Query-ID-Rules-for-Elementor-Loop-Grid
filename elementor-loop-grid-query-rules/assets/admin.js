( function () {
	'use strict';

	function slugify( value ) {
		return value
			.toLowerCase()
			.trim()
			.replace( /[^a-z0-9_-]+/g, '_' )
			.replace( /^_+|_+$/g, '' ) || 'loop_grid_rule';
	}

	function refreshSource( row ) {
		var source = row.querySelector( '[data-elgqr-source]' );
		if ( ! source ) {
			return;
		}
		var isStatic = source.value === 'static';
		row.querySelectorAll( '[data-elgqr-static]' ).forEach( function ( item ) {
			item.hidden = ! isStatic;
		} );
		row.querySelectorAll( '[data-elgqr-dynamic]' ).forEach( function ( item ) {
			item.hidden = isStatic;
		} );
	}

	function initRow( row ) {
		refreshSource( row );
		var source = row.querySelector( '[data-elgqr-source]' );
		if ( source ) {
			source.addEventListener( 'change', function () { refreshSource( row ); } );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.elgqr-row' ).forEach( initRow );

		document.addEventListener( 'click', function ( event ) {
			var addButton = event.target.closest( '[data-elgqr-add]' );
			if ( addButton ) {
				var type = addButton.getAttribute( 'data-elgqr-add' );
				var template = document.querySelector( '[data-elgqr-template="' + type + '"]' );
				var container = document.querySelector( '[data-elgqr-rows="' + type + '"]' );
				if ( template && container ) {
					var index = Date.now().toString();
					var wrapper = document.createElement( 'div' );
					wrapper.innerHTML = template.innerHTML.replace( /__INDEX__/g, index );
					var row = wrapper.firstElementChild;
					container.appendChild( row );
					initRow( row );
				}
				return;
			}

			var removeButton = event.target.closest( '[data-elgqr-remove]' );
			if ( removeButton ) {
				removeButton.closest( '.elgqr-row' ).remove();
				return;
			}

			if ( event.target.closest( '[data-elgqr-generate]' ) ) {
				var title = document.getElementById( 'title' );
				var input = document.getElementById( 'elgqr-query-id' );
				if ( input ) {
					input.value = slugify( title ? title.value : '' );
				}
				return;
			}

			if ( event.target.closest( '[data-elgqr-copy]' ) ) {
				var queryInput = document.getElementById( 'elgqr-query-id' );
				if ( queryInput && navigator.clipboard ) {
					navigator.clipboard.writeText( queryInput.value );
				}
			}
		} );
	} );
}() );
