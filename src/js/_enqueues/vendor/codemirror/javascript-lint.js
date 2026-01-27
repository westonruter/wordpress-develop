/* globals define, CodeMirror, JSHINT */

// CodeMirror, copyright (c) by Marijn Haverbeke and others
// Distributed under an MIT license: https://codemirror.net/5/LICENSE

// Depends on jshint.js from https://github.com/jshint/jshint

( function ( mod ) {
	if ( typeof exports === 'object' && typeof module === 'object' ) {
		// CommonJS
		mod( require( 'codemirror' ) );
	} else if ( typeof define === 'function' && define.amd ) {
		// AMD
		define( [ 'codemirror' ], mod );
		// Plain browser env
	} else {
		mod( CodeMirror );
	}
} )( function ( CodeMirror ) {
	'use strict';
	// declare global: JSHINT

	async function validator( text, options ) {
		// TODO: Await import espree.

		if ( ! window.JSHINT ) {
			if ( window.console ) {
				window.console.error(
					'Error: window.JSHINT not defined, CodeMirror JavaScript linting cannot run.'
				);
			}
			return [];
		}
		if ( ! options.indent ) {
			// JSHint error.character actually is a column index, this fixes underlining on lines using tabs for indentation
			options.indent = 1; // JSHint default value is 4
		}
		JSHINT( text, options, options.globals );

		var errors = JSHINT.data().errors,
			result = [];
		if ( errors ) {
			parseErrors( errors, result );
		}
		return result;
	}

	CodeMirror.registerHelper( 'lint', 'javascript', validator );

	function parseErrors( errors, output ) {
		for ( var i = 0; i < errors.length; i++ ) {
			var error = errors[ i ];
			if ( error ) {
				if ( error.line <= 0 ) {
					if ( window.console ) {
						window.console.warn(
							'Cannot display JSHint error (invalid line ' +
								error.line +
								')',
							error
						);
					}
					continue;
				}

				let start = error.character - 1,
					end = start + 1;
				if ( error.evidence ) {
					const index = error.evidence
						.substring( start )
						.search( /.\b/ );
					if ( index > -1 ) {
						end += index;
					}
				}

				// Convert to format expected by validation service
				const hint = {
					message: error.reason,
					severity: error.code ?
						( error.code.startsWith( 'W' ) ? 'warning' : 'error' )
						: 'error',
					from: CodeMirror.Pos( error.line - 1, start ),
					to: CodeMirror.Pos( error.line - 1, end ),
				};

				output.push( hint );
			}
		}
	}
} );
