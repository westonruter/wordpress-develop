/* globals define, CodeMirror */

/* jshint esversion: 11 */

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

	async function validator( text, options ) {
		const espree = await import( 'espree' );

		const errors = [];
		try {
			espree.parse( text, {
				...getEspreeOptions( options ),
				loc: true,
			} );
		} catch ( error ) {
			// Note: A lineNumber of 0 causes CodeMirror to log out a warning in the console. This is as desired for a generic Espree error.

			const line = error.lineNumber - 1;
			const start = error.column - 1;
			const end = error.column;

			errors.push( {
				message: error.message,
				severity: 'error',
				from: CodeMirror.Pos( line, start ),
				to: CodeMirror.Pos( line, end ),
			} );
		}

		return errors;
	}

	CodeMirror.registerHelper( 'lint', 'javascript', validator );

	/**
	 * JSHint options supported by Espree.
	 *
	 * @see https://jshint.com/docs/options/
	 * @see https://www.npmjs.com/package/espree#options
	 *
	 * @typedef {Object} SupportedJSHintOptions
	 * @property {number} [esversion] - "This option is used to specify the ECMAScript version to which the code must adhere."
	 * @property {boolean} [es5] - "This option enables syntax first defined in the ECMAScript 5.1 specification. This includes allowing reserved keywords as object properties."
	 * @property {boolean} [es3] - "This option tells JSHint that your code needs to adhere to ECMAScript 3 specification. Use this option if you need your program to be executable in older browsers—such as Internet Explorer 6/7/8/9—and other legacy JavaScript environments."
	 * @property {boolean} [module] - "This option informs JSHint that the input code describes an ECMAScript 6 module. All module code is interpreted as strict mode code."
	 * @property {'implied'} [strict] - "This option requires the code to run in ECMAScript 5's strict mode."
	 */

	/**
	 * Gets the options for Espree from the supported JSHint options.
	 *
	 * @param {SupportedJSHintOptions} options - Linting options for JSHint.
	 * @return {{
	 *     ecmaVersion?: number|'latest',
	 *     ecmaFeatures?: {
	 *         impliedStrict?: true
	 *     }
	 * }}
	 */
	function getEspreeOptions( options ) {
		const ecmaFeatures = {};
		if ( options.strict === 'implied' ) {
			ecmaFeatures.impliedStrict = true;
		}

		return {
			ecmaVersion: getEcmaVersion( options ),
			sourceType: options.module ? 'module' : 'script',
			ecmaFeatures,
		};
	}

	/**
	 * Gets the ECMAScript version.
	 *
	 * @param {SupportedJSHintOptions} options - Options.
	 * @return {number|'latest'} ECMAScript version.
	 */
	function getEcmaVersion( options ) {
		if ( typeof options.esversion === 'number' ) {
			return options.esversion;
		}
		if ( options.es5 ) {
			return 5;
		}
		if ( options.es3 ) {
			return 3;
		}
		return 'latest';
	}
} );
