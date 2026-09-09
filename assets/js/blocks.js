/* global wp, ludoyaBlocks */
( function ( blocks, element, components, blockEditor, serverSideRender, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var ServerSideRender = serverSideRender;

	/**
	 * One control per attribute, chosen from the attribute's type.
	 *
	 * @param {Object} props   Block props.
	 * @param {Array}  fields  Field definitions: key, label, and optional options.
	 * @return {Object} The inspector panel.
	 */
	function inspector( props, fields ) {
		return el(
			blockEditor.InspectorControls,
			{},
			el(
				components.PanelBody,
				{ title: __( 'Settings', 'ludoya' ), initialOpen: true },
				fields.map( function ( field ) {
					// A select always hands back a string; an attribute declared as a number has to
					// get one back, or the block fails its own attribute validation.
					var isNumber = 'number' === typeof props.attributes[ field.key ];

					if ( field.options ) {
						return el( components.SelectControl, {
							key: field.key,
							label: field.label,
							value: String( props.attributes[ field.key ] ),
							options: field.options.map( function ( option ) {
								return { label: option.label, value: String( option.value ) };
							} ),
							onChange: function ( value ) {
								var update = {};
								update[ field.key ] = isNumber ? parseInt( value, 10 ) || 0 : value;
								props.setAttributes( update );
							},
						} );
					}
					return el( components.TextControl, {
						key: field.key,
						label: field.label,
						type: isNumber ? 'number' : 'text',
						value: props.attributes[ field.key ],
						onChange: function ( value ) {
							var update = {};
							update[ field.key ] = isNumber ? parseInt( value, 10 ) || 0 : value;
							props.setAttributes( update );
						},
					} );
				} )
			)
		);
	}

	/**
	 * Register one server-rendered block.
	 *
	 * @param {string} name   Block name without the namespace.
	 * @param {string} title  Block title.
	 * @param {string} icon   Dashicon name.
	 * @param {Array}  fields Inspector fields.
	 */
	function register( name, title, icon, fields, description, keywords ) {
		blocks.registerBlockType( 'ludoya/' + name, {
			title: title,
			description: description || '',
			keywords: keywords || [],
			icon: icon,
			category: 'widgets',
			edit: function ( props ) {
				return el(
					'div',
					blockEditor.useBlockProps ? blockEditor.useBlockProps() : {},
					inspector( props, fields ),
					el( ServerSideRender, {
						block: 'ludoya/' + name,
						attributes: props.attributes,
					} )
				);
			},
			save: function () {
				return null;
			},
		} );
	}

	register( 'events', __( 'Ludoya events', 'ludoya' ), 'calendar-alt', [
		{ key: 'heading', label: __( 'Heading', 'ludoya' ) },
		{ key: 'limit', label: __( 'How many', 'ludoya' ) },
		{ key: 'past', label: __( 'Past events to include', 'ludoya' ) },
		{
			key: 'type',
			label: __( 'Only this type', 'ludoya' ),
			options: [
				{ label: __( 'Every type', 'ludoya' ), value: '' },
				{ label: __( 'Event', 'ludoya' ), value: 'MEETUP' },
				{ label: __( 'Scheduled game', 'ludoya' ), value: 'PLANNED_PLAY' },
				{ label: __( 'Tournament', 'ludoya' ), value: 'TOURNAMENT' },
				{ label: __( 'Play booth', 'ludoya' ), value: 'PLAY_BOOTH' },
			],
		},
		{
			key: 'include_sub',
			label: __( 'Sub-events', 'ludoya' ),
			options: [
				{ label: __( 'Only top-level events', 'ludoya' ), value: 0 },
				{ label: __( 'Include sub-events', 'ludoya' ), value: 1 },
			],
		},
		{
			key: 'spot',
			label: __( 'Only this table or room', 'ludoya' ),
			options: [ { label: __( 'Anywhere', 'ludoya' ), value: '' } ].concat(
				'undefined' !== typeof ludoyaBlocks && ludoyaBlocks.spots ? ludoyaBlocks.spots : []
			),
		},
		{
			key: 'layout',
			label: __( 'Layout', 'ludoya' ),
			options: [
				{ label: __( 'Cards', 'ludoya' ), value: 'cards' },
				{ label: __( 'List', 'ludoya' ), value: 'list' },
			],
		},
		{ key: 'event_page', label: __( 'Event page (id, slug or URL)', 'ludoya' ) },
	], __( 'Your upcoming events, as cards or a list.', 'ludoya' ), [ __( 'agenda', 'ludoya' ), __( 'calendar', 'ludoya' ), __( 'board games', 'ludoya' ) ] );

	register( 'event', __( 'Ludoya event', 'ludoya' ), 'tickets-alt', [
		{ key: 'id', label: __( 'Event id (empty reads it from the link)', 'ludoya' ) },
		{
			key: 'show_signup',
			label: __( 'Sign-up form', 'ludoya' ),
			options: [
				{ label: __( 'Show it', 'ludoya' ), value: 1 },
				{ label: __( 'Hide it', 'ludoya' ), value: 0 },
			],
		},
	], __( 'One event in full, with its sign-up form.', 'ludoya' ), [ __( 'agenda', 'ludoya' ), __( 'sign-up', 'ludoya' ) ] );

	register( 'signup', __( 'Ludoya sign-up form', 'ludoya' ), 'forms', [
		{ key: 'event', label: __( 'Event id (empty reads it from the link)', 'ludoya' ) },
	], __( 'The sign-up form for an event, on its own.', 'ludoya' ), [ __( 'registration', 'ludoya' ), __( 'form', 'ludoya' ) ] );

	register( 'collection', __( 'Ludoya collection', 'ludoya' ), 'grid-view', [
		{ key: 'heading', label: __( 'Heading', 'ludoya' ) },
		{ key: 'limit', label: __( 'How many games', 'ludoya' ) },
		{ key: 'search', label: __( 'Filter by name', 'ludoya' ) },
		{
			key: 'layout',
			label: __( 'Layout', 'ludoya' ),
			options: [
				{ label: __( 'Grid', 'ludoya' ), value: 'grid' },
				{ label: __( 'List', 'ludoya' ), value: 'list' },
			],
		},
	], __( 'The games your organisation owns.', 'ludoya' ), [ __( 'games', 'ludoya' ), __( 'library', 'ludoya' ), __( 'shelf', 'ludoya' ) ] );

	register( 'stats', __( 'Ludoya stats', 'ludoya' ), 'chart-bar', [
		{ key: 'heading', label: __( 'Heading', 'ludoya' ) },
		{
			key: 'period',
			label: __( 'Period', 'ludoya' ),
			options: [
				{ label: __( 'All time', 'ludoya' ), value: 'ALL_TIME' },
				{ label: __( 'Last year', 'ludoya' ), value: 'ONE_YEAR' },
				{ label: __( 'Last month', 'ludoya' ), value: 'ONE_MONTH' },
				{ label: __( 'Last 30 days', 'ludoya' ), value: 'THIRTY_DAYS' },
				{ label: __( 'Last 7 days', 'ludoya' ), value: 'SEVEN_DAYS' },
			],
		},
		{ key: 'top_games', label: __( 'Games to list', 'ludoya' ) },
	], __( 'How much you have played, and what.', 'ludoya' ), [ __( 'statistics', 'ludoya' ), __( 'plays', 'ludoya' ) ] );

	register( 'locations', __( 'Ludoya locations', 'ludoya' ), 'location', [
		{ key: 'heading', label: __( 'Heading', 'ludoya' ) },
	], __( 'Where your organisation plays.', 'ludoya' ), [ __( 'venues', 'ludoya' ), __( 'map', 'ludoya' ) ] );
}(
	wp.blocks,
	wp.element,
	wp.components,
	wp.blockEditor,
	wp.serverSideRender,
	wp.i18n
) );
