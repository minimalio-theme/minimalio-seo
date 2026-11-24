/* global jQuery, wp, minimalioSeo */
( function ( $ ) {
	'use strict';

	var wp = window.wp || {};
	var settings = window.minimalioSeo || {};
	var i18n = settings.i18n || {};

	/* ---------------------------------------------------------------
	 * Settings tabs
	 * ------------------------------------------------------------- */
	function initTabs() {
		var $tabs = $( '.minimalio-seo-tabs .nav-tab' );
		if ( ! $tabs.length ) {
			return;
		}

		function show( id ) {
			var $tab = $tabs.filter( '[data-tab="' + id + '"]' );
			if ( ! $tab.length ) {
				return;
			}
			$tabs.removeClass( 'nav-tab-active' );
			$tab.addClass( 'nav-tab-active' );
			$( '.minimalio-seo-panel' ).attr( 'hidden', true );
			$( '.minimalio-seo-panel[data-panel="' + id + '"]' ).removeAttr( 'hidden' );

			// Return to the same tab after saving.
			var $referer = $( 'input[name="_wp_http_referer"]' );
			if ( $referer.length ) {
				$referer.val( $referer.val().split( '#' )[ 0 ] + '#' + id );
			}
		}

		$tabs.on( 'click', function ( e ) {
			e.preventDefault();
			var id = $( this ).data( 'tab' );
			show( id );
			if ( window.history && window.history.replaceState ) {
				window.history.replaceState( null, '', '#' + id );
			}
		} );

		if ( window.location.hash ) {
			show( window.location.hash.substring( 1 ) );
		}
	}

	/* ---------------------------------------------------------------
	 * Media picker
	 * ------------------------------------------------------------- */
	function initImages() {
		$( document ).on( 'click', '.minimalio-seo-image__select', function ( e ) {
			e.preventDefault();
			var $wrap = $( this ).closest( '.minimalio-seo-image' );

			var frame = wp.media( {
				title: i18n.chooseImage || 'Choose image',
				button: { text: i18n.useImage || 'Use this image' },
				library: { type: 'image' },
				multiple: false,
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				var sizes = attachment.sizes || {};
				var src = ( sizes.medium || sizes.full || attachment ).url;
				$wrap.find( 'input[type="hidden"]' ).val( attachment.id ).trigger( 'change' );
				$wrap.find( '.minimalio-seo-image__preview' ).html( $( '<img alt="">' ).attr( 'src', src ) );
				$wrap.find( '.minimalio-seo-image__remove' ).removeAttr( 'hidden' );
			} );

			frame.open();
		} );

		$( document ).on( 'click', '.minimalio-seo-image__remove', function ( e ) {
			e.preventDefault();
			var $wrap = $( this ).closest( '.minimalio-seo-image' );
			$wrap.find( 'input[type="hidden"]' ).val( '' ).trigger( 'change' );
			$wrap.find( '.minimalio-seo-image__preview' ).empty();
			$( this ).attr( 'hidden', true );
		} );
	}

	/* ---------------------------------------------------------------
	 * Character counters
	 * ------------------------------------------------------------- */
	function initCounters() {
		$( '[data-count]' ).each( function () {
			var $field = $( this );
			var max = parseInt( $field.data( 'count' ), 10 );
			var $counter = $( '<span class="minimalio-seo-counter" />' );
			$field.after( $counter );

			function update() {
				var len = $field.val().length;
				var state = 'ok';
				if ( len === 0 ) {
					state = 'empty';
				} else if ( len > max ) {
					state = 'long';
				} else if ( len < max * 0.5 ) {
					state = 'short';
				}
				$counter.text( len + ' / ' + max ).attr( 'data-state', state );
			}

			$field.on( 'input', update );
			update();
		} );
	}

	/* ---------------------------------------------------------------
	 * Google snippet preview
	 * ------------------------------------------------------------- */
	function initSnippet() {
		var $box = $( '.minimalio-seo-snippet' );
		if ( ! $box.length || ! settings.snippet ) {
			return;
		}

		var s = settings.snippet;
		var $title = $( '#minimalio-seo-title' );
		var $desc = $( '#minimalio-seo-description' );

		function escapeRegExp( str ) {
			return str.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
		}

		function postTitle() {
			if ( wp && wp.data && wp.data.select && wp.data.select( 'core/editor' ) ) {
				var t = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'title' );
				if ( typeof t === 'string' ) {
					return t;
				}
			}
			var $classic = $( '#title' );
			return $classic.length ? $classic.val() : '';
		}

		function permalink() {
			if ( wp && wp.data && wp.data.select && wp.data.select( 'core/editor' ) ) {
				var link = wp.data.select( 'core/editor' ).getPermalink();
				if ( link ) {
					return link;
				}
			}
			return s.url;
		}

		function replaceVars( template ) {
			var out = template
				.split( '%title%' ).join( postTitle() )
				.split( '%sitename%' ).join( s.siteName )
				.split( '%tagline%' ).join( s.tagline )
				.split( '%sep%' ).join( s.sep )
				.split( '%page%' ).join( '' );

			var q = escapeRegExp( s.sep );
			out = out.replace( /\s+/g, ' ' ).trim();
			out = out.replace( new RegExp( '\\s' + q + '(?:\\s' + q + ')+\\s', 'g' ), ' ' + s.sep + ' ' );
			out = out.replace( new RegExp( '^(?:' + q + '\\s)+|(?:\\s' + q + ')+$', 'g' ), '' );
			return out.trim();
		}

		function truncate( text, max ) {
			return text.length > max ? text.substring( 0, max - 1 ).trim() + '…' : text;
		}

		function prettyUrl( url ) {
			try {
				var u = new URL( url );
				var parts = u.pathname.split( '/' ).filter( Boolean );
				return u.hostname + ( parts.length ? ' › ' + parts.join( ' › ' ) : '' );
			} catch ( err ) {
				return url;
			}
		}

		function render() {
			// Jetpack SEO values apply while our fields are empty (same as the front end).
			var title = $title.val() ? replaceVars( $title.val() ) : s.jetpackTitle || replaceVars( s.template );
			var desc = $desc.val() || s.jetpackDesc || s.autoDesc || s.fallbackDesc;

			$box.find( '.minimalio-seo-snippet__url' ).text( prettyUrl( permalink() ) );
			$box.find( '.minimalio-seo-snippet__title' ).text( truncate( title, 60 ) );
			$box.find( '.minimalio-seo-snippet__desc' )
				.text( desc ? truncate( desc, 160 ) : s.noDesc )
				.toggleClass( 'is-empty', ! desc );
		}

		$title.add( $desc ).on( 'input', render );
		$( '#title' ).on( 'input', render );

		// Block editor: re-render when the title or permalink changes.
		if ( wp && wp.data && wp.data.subscribe ) {
			var last = '';
			wp.data.subscribe( function () {
				var editor = wp.data.select( 'core/editor' );
				if ( ! editor ) {
					return;
				}
				var key = postTitle() + '|' + ( editor.getPermalink() || '' );
				if ( key !== last ) {
					last = key;
					render();
				}
			} );
		}

		render();
	}

	$( function () {
		initTabs();
		initImages();
		initCounters();
		initSnippet();
	} );
} )( jQuery );
