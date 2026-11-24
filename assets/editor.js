/**
 * Minimalio SEO — block editor sidebar panel.
 * Plain ES5 + wp globals, no build step required.
 */
/* global wp, minimalioSeoEditor */
( function ( wp, data ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! data ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var C = wp.components;
	var MediaUpload = wp.blockEditor && wp.blockEditor.MediaUpload;
	var MediaUploadCheck = wp.blockEditor && wp.blockEditor.MediaUploadCheck;
	var PluginDocumentSettingPanel =
		( wp.editor && wp.editor.PluginDocumentSettingPanel ) ||
		( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

	if ( ! PluginDocumentSettingPanel ) {
		return;
	}

	var K = data.keys;

	/* ---------- helpers ---------- */

	function escapeRegExp( str ) {
		return str.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
	}

	function replaceVars( template, title ) {
		var sep = data.sep;
		var out = String( template )
			.split( '%title%' ).join( title || '' )
			.split( '%sitename%' ).join( data.siteName )
			.split( '%tagline%' ).join( data.tagline )
			.split( '%sep%' ).join( sep )
			.split( '%page%' ).join( '' );
		var q = escapeRegExp( sep );
		out = out.replace( /\s+/g, ' ' ).trim();
		out = out.replace( new RegExp( '\\s' + q + '(?:\\s' + q + ')+\\s', 'g' ), ' ' + sep + ' ' );
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
		} catch ( e ) {
			return url || '';
		}
	}

	function counter( value, max ) {
		var len = ( value || '' ).length;
		var state = len === 0 ? 'empty' : len > max ? 'long' : len < max * 0.5 ? 'short' : 'ok';
		return el(
			'span',
			{ className: 'minimalio-seo-counter', 'data-state': state },
			sprintf( /* translators: 1: characters used, 2: recommended maximum */ __( '%1$d / %2$d characters', 'minimalio-seo' ), len, max )
		);
	}

	/* ---------- panel ---------- */

	function SeoPanel() {
		var state = useSelect( function ( select ) {
			var editor = select( 'core/editor' );
			var meta = editor.getEditedPostAttribute( 'meta' ) || {};
			var imageId = parseInt( meta[ K.ogImage ], 10 ) || 0;
			return {
				postType: editor.getCurrentPostType(),
				postId: editor.getCurrentPostId(),
				title: editor.getEditedPostAttribute( 'title' ) || '',
				excerpt: editor.getEditedPostAttribute( 'excerpt' ) || '',
				link: editor.getPermalink(),
				meta: meta,
				imageId: imageId,
				image: imageId ? select( 'core' ).getMedia( imageId ) : null,
			};
		}, [] );

		var editPost = useDispatch( 'core/editor' ).editPost;

		if ( data.postTypes.indexOf( state.postType ) === -1 ) {
			return null;
		}

		var meta = state.meta;
		function get( key ) {
			return meta[ key ] || '';
		}
		function set( key, value ) {
			var update = {};
			update[ key ] = value;
			editPost( { meta: update } );
		}

		// wp_localize_script passes top-level values as strings.
		var frontPageId = parseInt( data.frontPageId, 10 ) || 0;
		var isFront = frontPageId > 0 && frontPageId === parseInt( state.postId, 10 );
		var template = isFront ? data.frontTitle : data.templates[ state.postType ] || '%title% %sep% %sitename%';
		var defaultNoindex = ! isFront && data.noindexDefaults[ state.postType ] === true;

		// Values saved earlier in Jetpack SEO apply while our fields are empty (same as the front end).
		var jetpack = data.jetpack || {};
		var jetpackTitle = jetpack.title || '';
		var jetpackDesc = jetpack.description || '';
		var jetpackNoindex = jetpack.noindex === true || jetpack.noindex === '1';

		var seoTitle = get( K.title );
		var seoDesc = get( K.description );
		var autoDesc = jetpackDesc || state.excerpt || data.autoDesc || ( isFront ? data.tagline : '' );
		var defaultTitle = jetpackTitle || replaceVars( template, state.title );
		var previewTitle = seoTitle ? replaceVars( seoTitle, state.title ) : defaultTitle;
		var previewDesc = seoDesc || autoDesc;

		var robots = get( K.robots );
		var isNoindex = robots === '' ? defaultNoindex || jetpackNoindex : robots === 'noindex';

		/* Social image */
		var imageUrl = '';
		if ( state.image ) {
			var sizes = ( state.image.media_details && state.image.media_details.sizes ) || {};
			imageUrl = ( sizes.medium || sizes.full || {} ).source_url || state.image.source_url;
		}

		var imageControl = MediaUpload
			? el(
					MediaUploadCheck,
					{ fallback: null },
					el( MediaUpload, {
						onSelect: function ( media ) {
							set( K.ogImage, media && media.id ? media.id : 0 );
						},
						allowedTypes: [ 'image' ],
						value: state.imageId || undefined,
						render: function ( props ) {
							return el(
								'div',
								{ className: 'minimalio-seo-panel-image' },
								imageUrl
									? el(
											'button',
											{ type: 'button', className: 'minimalio-seo-panel-image__preview', onClick: props.open },
											el( 'img', { src: imageUrl, alt: '' } )
									  )
									: null,
								el(
									'div',
									{ className: 'minimalio-seo-panel-image__actions' },
									el(
										C.Button,
										{ variant: 'secondary', onClick: props.open },
										state.imageId ? __( 'Replace image', 'minimalio-seo' ) : __( 'Select image', 'minimalio-seo' )
									),
									state.imageId
										? el(
												C.Button,
												{ variant: 'link', isDestructive: true, onClick: function () { set( K.ogImage, 0 ); } },
												__( 'Remove', 'minimalio-seo' )
										  )
										: null
								)
							);
						},
					} )
			  )
			: null;

		return el(
			PluginDocumentSettingPanel,
			{ name: 'minimalio-seo', title: __( 'SEO', 'minimalio-seo' ), className: 'minimalio-seo-panel', initialOpen: true },

			isFront
				? el(
						'p',
						{ className: 'minimalio-seo-front-badge' },
						el( 'strong', null, __( 'This is your homepage.', 'minimalio-seo' ) ),
						' ',
						__( 'The SEO title, description and share image set here are what Google and social networks show for your site.', 'minimalio-seo' )
				  )
				: null,

			/* Google preview */
			el(
				'div',
				{ className: 'minimalio-seo-snippet minimalio-seo-snippet--compact' },
				el( 'div', { className: 'minimalio-seo-snippet__label' }, __( 'Google preview', 'minimalio-seo' ) ),
				el( 'div', { className: 'minimalio-seo-snippet__url' }, prettyUrl( state.link ) ),
				el( 'div', { className: 'minimalio-seo-snippet__title' }, truncate( previewTitle, 60 ) ),
				el(
					'div',
					{ className: 'minimalio-seo-snippet__desc' + ( previewDesc ? '' : ' is-empty' ) },
					previewDesc ? truncate( previewDesc, 160 ) : __( 'No meta description. Google will pick text from the page.', 'minimalio-seo' )
				)
			),

			/* Title + description */
			el( C.TextControl, {
				label: __( 'SEO title', 'minimalio-seo' ),
				value: seoTitle,
				placeholder: defaultTitle,
				onChange: function ( v ) { set( K.title, v ); },
				help: el(
					Fragment,
					null,
					counter( previewTitle, 60 ),
					' ',
					jetpackTitle
						? __( 'Leave empty to keep the title from Jetpack SEO.', 'minimalio-seo' )
						: __( 'Leave empty for the default.', 'minimalio-seo' )
				),
				__nextHasNoMarginBottom: true,
			} ),
			el( 'div', { className: 'minimalio-seo-panel-gap' } ),
			el( C.TextareaControl, {
				label: __( 'Meta description', 'minimalio-seo' ),
				value: seoDesc,
				placeholder: autoDesc,
				rows: 4,
				onChange: function ( v ) { set( K.description, v ); },
				help: el(
					Fragment,
					null,
					counter( seoDesc, 160 ),
					' ',
					jetpackDesc
						? __( 'Leave empty to keep the description from Jetpack SEO.', 'minimalio-seo' )
						: __( 'Leave empty to use the excerpt.', 'minimalio-seo' )
				),
				__nextHasNoMarginBottom: true,
			} ),

			/* Indexing (never offered for the homepage) */
			isFront ? null : el( 'div', { className: 'minimalio-seo-panel-gap' } ),
			isFront
				? null
				: el( C.ToggleControl, {
						label: __( 'Hide from search engines (noindex)', 'minimalio-seo' ),
						checked: isNoindex,
						help: isNoindex
							? __( 'This page will not appear in Google and is left out of the sitemap.', 'minimalio-seo' )
							: __( 'This page can appear in search results.', 'minimalio-seo' ),
						onChange: function ( v ) {
							set( K.robots, v === defaultNoindex ? '' : v ? 'noindex' : 'index' );
						},
						__nextHasNoMarginBottom: true,
				  } ),

			/* Social */
			el( 'h3', { className: 'minimalio-seo-panel-heading' }, __( 'Social sharing', 'minimalio-seo' ) ),
			el( 'p', { className: 'minimalio-seo-panel-help' }, __( 'Image shown when this page is shared on Facebook, LinkedIn, X, Pinterest, WhatsApp… Defaults to the featured image. Recommended: 1200 × 630 px.', 'minimalio-seo' ) ),
			imageControl,
			el( 'div', { className: 'minimalio-seo-panel-gap' } ),
			el( C.TextControl, {
				label: __( 'Social title', 'minimalio-seo' ),
				value: get( K.ogTitle ),
				placeholder: state.title,
				onChange: function ( v ) { set( K.ogTitle, v ); },
				__nextHasNoMarginBottom: true,
			} ),
			el( 'div', { className: 'minimalio-seo-panel-gap' } ),
			el( C.TextareaControl, {
				label: __( 'Social description', 'minimalio-seo' ),
				value: get( K.ogDescription ),
				placeholder: previewDesc,
				rows: 3,
				onChange: function ( v ) { set( K.ogDescription, v ); },
				__nextHasNoMarginBottom: true,
			} ),

			/* Advanced */
			el( 'h3', { className: 'minimalio-seo-panel-heading' }, __( 'Advanced', 'minimalio-seo' ) ),
			el( C.ToggleControl, {
				label: __( 'Nofollow links on this page', 'minimalio-seo' ),
				checked: get( K.nofollow ) === '1',
				onChange: function ( v ) { set( K.nofollow, v ? '1' : '' ); },
				__nextHasNoMarginBottom: true,
			} ),
			el( 'div', { className: 'minimalio-seo-panel-gap' } ),
			el( C.TextControl, {
				label: __( 'Canonical URL', 'minimalio-seo' ),
				type: 'url',
				value: get( K.canonical ),
				placeholder: state.link || '',
				help: __( 'Only needed when this content duplicates another URL.', 'minimalio-seo' ),
				onChange: function ( v ) { set( K.canonical, v ); },
				__nextHasNoMarginBottom: true,
			} )
		);
	}

	wp.plugins.registerPlugin( 'minimalio-seo', { render: SeoPanel, icon: 'search' } );
} )( window.wp, window.minimalioSeoEditor );
