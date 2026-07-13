/**
 * Digest — block editor registration.
 *
 * Written against the wp.* globals rather than JSX and a bundler, on purpose:
 * the file a reviewer reads is the file the browser runs. There is no build
 * step, no minified vendor blob, and nothing in the theme that cannot be
 * audited by reading it.
 *
 * Every block here is registered with a `save` of null — they are dynamic blocks
 * rendered in PHP (see inc/blocks.php). What is saved to the database is the
 * attributes and an HTML comment, so if the theme is ever removed the content
 * survives as data rather than as broken markup.
 *
 * @package Digest
 * @since   1.0.0
 */

( function ( blocks, element, blockEditor, components, i18n ) {
	'use strict';

	var el = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;

	var useBlockProps = blockEditor.useBlockProps;
	var RichText = blockEditor.RichText;
	var InspectorControls = blockEditor.InspectorControls;

	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var TextareaControl = components.TextareaControl;
	var Button = components.Button;
	var Notice = components.Notice;

	/**
	 * A labelled, reorderable list of plain-text rows.
	 *
	 * Shared by Key Takeaways and Sources, which differ only in their wording.
	 *
	 * @param {Object}   props           Component props.
	 * @param {string[]} props.items     The current rows.
	 * @param {Function} props.onChange  Called with the new array.
	 * @param {string}   props.addLabel  Label for the add button.
	 * @param {string}   props.rowLabel  Accessible label for each row, receives the index.
	 * @param {string}   props.emptyHint Shown when there are no rows yet.
	 * @return {Object} The rendered editor UI.
	 */
	function RowEditor( props ) {
		var items = props.items || [];

		function update( index, value ) {
			var next = items.slice();
			next[ index ] = value;
			props.onChange( next );
		}

		function add() {
			props.onChange( items.concat( [ '' ] ) );
		}

		function remove( index ) {
			props.onChange(
				items.filter( function ( _item, i ) {
					return i !== index;
				} )
			);
		}

		function move( index, delta ) {
			var target = index + delta;

			if ( target < 0 || target >= items.length ) {
				return;
			}

			var next = items.slice();
			var held = next[ index ];
			next[ index ] = next[ target ];
			next[ target ] = held;
			props.onChange( next );
		}

		return el(
			Fragment,
			null,

			items.length === 0
				? el(
						Notice,
						{ status: 'info', isDismissible: false },
						props.emptyHint
				  )
				: null,

			items.map( function ( item, index ) {
				return el(
					'div',
					{ key: index, className: 'digest-row' },

					el( TextareaControl, {
						label: props.rowLabel.replace( '%d', String( index + 1 ) ),
						value: item,
						rows: 2,
						__nextHasNoMarginBottom: true,
						onChange: function ( value ) {
							update( index, value );
						},
					} ),

					el(
						'div',
						{ className: 'digest-row__actions' },

						el(
							Button,
							{
								size: 'small',
								variant: 'tertiary',
								disabled: index === 0,
								onClick: function () {
									move( index, -1 );
								},
							},
							__( 'Up', 'shadow-software-digest-theme-for-wordpress' )
						),

						el(
							Button,
							{
								size: 'small',
								variant: 'tertiary',
								disabled: index === items.length - 1,
								onClick: function () {
									move( index, 1 );
								},
							},
							__( 'Down', 'shadow-software-digest-theme-for-wordpress' )
						),

						el(
							Button,
							{
								size: 'small',
								variant: 'tertiary',
								isDestructive: true,
								onClick: function () {
									remove( index );
								},
							},
							__( 'Remove', 'shadow-software-digest-theme-for-wordpress' )
						)
					)
				);
			} ),

			el(
				Button,
				{ variant: 'secondary', onClick: add },
				props.addLabel
			)
		);
	}

	/* ---------------------------------------------------------------------- *
	 * Short Answer
	 * ---------------------------------------------------------------------- */

	blocks.registerBlockType( 'shadow-digest/short-answer', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			var label =
				attributes.label || __( 'The Short Answer', 'shadow-software-digest-theme-for-wordpress' );

			return el(
				Fragment,
				null,

				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Short Answer', 'shadow-software-digest-theme-for-wordpress' ) },
						el( TextControl, {
							label: __( 'Label', 'shadow-software-digest-theme-for-wordpress' ),
							help: __(
								'The small heading above the answer.',
								'shadow-software-digest-theme-for-wordpress'
							),
							value: attributes.label,
							placeholder: __( 'The Short Answer', 'shadow-software-digest-theme-for-wordpress' ),
							__nextHasNoMarginBottom: true,
							onChange: function ( value ) {
								setAttributes( { label: value } );
							},
						} )
					)
				),

				el(
					'div',
					useBlockProps( {
						className: 'wp-block-digest-short-answer',
					} ),

					el(
						'p',
						{ className: 'wp-block-digest-short-answer__label' },
						label
					),

					el( RichText, {
						tagName: 'p',
						className: 'wp-block-digest-short-answer__body',
						value: attributes.answer,
						allowedFormats: [ 'core/bold', 'core/italic', 'core/link' ],
						placeholder: __(
							'Answer the article’s question in two or three sentences. Write it so it can be quoted on its own.',
							'shadow-software-digest-theme-for-wordpress'
						),
						onChange: function ( value ) {
							setAttributes( { answer: value } );
						},
					} )
				)
			);
		},

		save: function () {
			return null;
		},
	} );

	/* ---------------------------------------------------------------------- *
	 * Key Takeaways
	 * ---------------------------------------------------------------------- */

	blocks.registerBlockType( 'shadow-digest/takeaways', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			var items = attributes.items || [];
			var label = attributes.label || __( 'Key Takeaways', 'shadow-software-digest-theme-for-wordpress' );

			return el(
				Fragment,
				null,

				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Key Takeaways', 'shadow-software-digest-theme-for-wordpress' ) },

						el( TextControl, {
							label: __( 'Label', 'shadow-software-digest-theme-for-wordpress' ),
							value: attributes.label,
							placeholder: __( 'Key Takeaways', 'shadow-software-digest-theme-for-wordpress' ),
							__nextHasNoMarginBottom: true,
							onChange: function ( value ) {
								setAttributes( { label: value } );
							},
						} ),

						el( RowEditor, {
							items: items,
							addLabel: __( 'Add a takeaway', 'shadow-software-digest-theme-for-wordpress' ),
							/* translators: %d: the takeaway's position in the list, e.g. "Takeaway 2". */
							rowLabel: __( 'Takeaway %d', 'shadow-software-digest-theme-for-wordpress' ),
							emptyHint: __(
								'Three or four takeaways read best. One sentence each.',
								'shadow-software-digest-theme-for-wordpress'
							),
							onChange: function ( next ) {
								setAttributes( { items: next } );
							},
						} )
					)
				),

				el(
					'div',
					useBlockProps( { className: 'wp-block-digest-takeaways' } ),

					el(
						'p',
						{ className: 'wp-block-digest-takeaways__label' },
						label
					),

					items.length === 0
						? el(
								'p',
								{ className: 'wp-block-digest-takeaways__item' },
								el(
									'span',
									null,
									__(
										'Add takeaways in the block settings, on the right.',
										'shadow-software-digest-theme-for-wordpress'
									)
								)
						  )
						: el(
								'ul',
								{ className: 'wp-block-digest-takeaways__list' },
								items.map( function ( item, index ) {
									return el(
										'li',
										{
											key: index,
											className:
												'wp-block-digest-takeaways__item',
										},
										el( 'span', null, item )
									);
								} )
						  )
				)
			);
		},

		save: function () {
			return null;
		},
	} );

	/* ---------------------------------------------------------------------- *
	 * Table of Contents
	 *
	 * There is nothing to edit but the label: the list is derived from the
	 * post's headings at render time. The editor preview says so rather than
	 * pretending to show a list it cannot know yet.
	 * ---------------------------------------------------------------------- */

	blocks.registerBlockType( 'shadow-digest/toc', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			var label = attributes.label || __( 'In This Article', 'shadow-software-digest-theme-for-wordpress' );

			return el(
				Fragment,
				null,

				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Table of Contents', 'shadow-software-digest-theme-for-wordpress' ) },
						el( TextControl, {
							label: __( 'Label', 'shadow-software-digest-theme-for-wordpress' ),
							value: attributes.label,
							placeholder: __( 'In This Article', 'shadow-software-digest-theme-for-wordpress' ),
							__nextHasNoMarginBottom: true,
							onChange: function ( value ) {
								setAttributes( { label: value } );
							},
						} )
					)
				),

				el(
					'nav',
					useBlockProps( { className: 'wp-block-digest-toc' } ),

					el(
						'p',
						{ className: 'wp-block-digest-toc__label' },
						label
					),

					el(
						'p',
						{
							className: 'wp-block-digest-toc__list',
							style: { fontStyle: 'italic', opacity: 0.7 },
						},
						__(
							'Built automatically from the headings in this post when it is published.',
							'shadow-software-digest-theme-for-wordpress'
						)
					)
				)
			);
		},

		save: function () {
			return null;
		},
	} );

	/* ---------------------------------------------------------------------- *
	 * FAQ
	 * ---------------------------------------------------------------------- */

	blocks.registerBlockType( 'shadow-digest/faq', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			var items = attributes.items || [];
			var title =
				attributes.title ||
				__( 'Frequently Asked Questions', 'shadow-software-digest-theme-for-wordpress' );

			function update( index, key, value ) {
				var next = items.map( function ( item, i ) {
					if ( i !== index ) {
						return item;
					}

					var copy = Object.assign( {}, item );
					copy[ key ] = value;

					return copy;
				} );

				setAttributes( { items: next } );
			}

			function add() {
				setAttributes( {
					items: items.concat( [ { question: '', answer: '' } ] ),
				} );
			}

			function remove( index ) {
				setAttributes( {
					items: items.filter( function ( _item, i ) {
						return i !== index;
					} ),
				} );
			}

			return el(
				Fragment,
				null,

				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'FAQ', 'shadow-software-digest-theme-for-wordpress' ) },

						el( TextControl, {
							label: __( 'Heading', 'shadow-software-digest-theme-for-wordpress' ),
							value: attributes.title,
							placeholder: __(
								'Frequently Asked Questions',
								'shadow-software-digest-theme-for-wordpress'
							),
							__nextHasNoMarginBottom: true,
							onChange: function ( value ) {
								setAttributes( { title: value } );
							},
						} ),

						el(
							Notice,
							{ status: 'info', isDismissible: false },
							__(
								'These questions also become FAQPage structured data, so search engines can quote them. Write answers that stand on their own.',
								'shadow-software-digest-theme-for-wordpress'
							)
						),

						items.map( function ( item, index ) {
							return el(
								'div',
								{ key: index, className: 'digest-row' },

								el( TextControl, {
									label: __( 'Question', 'shadow-software-digest-theme-for-wordpress' ),
									value: item.question || '',
									__nextHasNoMarginBottom: true,
									onChange: function ( value ) {
										update( index, 'question', value );
									},
								} ),

								el( TextareaControl, {
									label: __( 'Answer', 'shadow-software-digest-theme-for-wordpress' ),
									value: item.answer || '',
									rows: 4,
									__nextHasNoMarginBottom: true,
									onChange: function ( value ) {
										update( index, 'answer', value );
									},
								} ),

								el(
									Button,
									{
										size: 'small',
										variant: 'tertiary',
										isDestructive: true,
										onClick: function () {
											remove( index );
										},
									},
									__( 'Remove question', 'shadow-software-digest-theme-for-wordpress' )
								)
							);
						} ),

						el(
							Button,
							{ variant: 'secondary', onClick: add },
							__( 'Add a question', 'shadow-software-digest-theme-for-wordpress' )
						)
					)
				),

				el(
					'section',
					useBlockProps( { className: 'wp-block-digest-faq' } ),

					el(
						'h2',
						{ className: 'wp-block-digest-faq__title' },
						title
					),

					el( 'div', { className: 'wp-block-digest-faq__rule' } ),

					items.length === 0
						? el(
								'p',
								{ style: { fontStyle: 'italic', opacity: 0.7 } },
								__(
									'Add questions in the block settings, on the right.',
									'shadow-software-digest-theme-for-wordpress'
								)
						  )
						: items.map( function ( item, index ) {
								return el(
									'div',
									{
										key: index,
										className: 'wp-block-digest-faq__item',
									},
									el(
										'h3',
										{ className: 'wp-block-digest-faq__q' },
										item.question ||
											__( 'Untitled question', 'shadow-software-digest-theme-for-wordpress' )
									),
									el(
										'p',
										{ className: 'wp-block-digest-faq__a' },
										item.answer
									)
								);
						  } )
				)
			);
		},

		save: function () {
			return null;
		},
	} );

	/* ---------------------------------------------------------------------- *
	 * Sources
	 * ---------------------------------------------------------------------- */

	blocks.registerBlockType( 'shadow-digest/sources', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			var items = attributes.items || [];
			var label =
				attributes.label || __( 'Sources & References', 'shadow-software-digest-theme-for-wordpress' );

			return el(
				Fragment,
				null,

				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Sources', 'shadow-software-digest-theme-for-wordpress' ) },

						el( TextControl, {
							label: __( 'Label', 'shadow-software-digest-theme-for-wordpress' ),
							value: attributes.label,
							placeholder: __(
								'Sources & References',
								'shadow-software-digest-theme-for-wordpress'
							),
							__nextHasNoMarginBottom: true,
							onChange: function ( value ) {
								setAttributes( { label: value } );
							},
						} ),

						el( RowEditor, {
							items: items,
							addLabel: __( 'Add a source', 'shadow-software-digest-theme-for-wordpress' ),
							/* translators: %d: the source's position in the list, e.g. "Source 3". */
							rowLabel: __( 'Source %d', 'shadow-software-digest-theme-for-wordpress' ),
							emptyHint: __(
								'Cite what the reporting rests on. Links are allowed.',
								'shadow-software-digest-theme-for-wordpress'
							),
							onChange: function ( next ) {
								setAttributes( { items: next } );
							},
						} )
					)
				),

				el(
					'section',
					useBlockProps( { className: 'wp-block-digest-sources' } ),

					el(
						'p',
						{ className: 'wp-block-digest-sources__label' },
						label
					),

					el(
						'ol',
						{ className: 'wp-block-digest-sources__list' },
						items.length === 0
							? el(
									'li',
									{ style: { fontStyle: 'italic', opacity: 0.7 } },
									__(
										'Add sources in the block settings, on the right.',
										'shadow-software-digest-theme-for-wordpress'
									)
							  )
							: items.map( function ( item, index ) {
									return el(
										'li',
										{ key: index },
										// Deliberately rendered as text in the editor: the
										// front end runs it through wp_kses_post(), and a
										// preview that renders raw HTML would mislead.
										item
									);
							  } )
					)
				)
			);
		},

		save: function () {
			return null;
		},
	} );

	/* ---------------------------------------------------------------------- *
	 * Disclosure Table
	 * ---------------------------------------------------------------------- */

	blocks.registerBlockType( 'shadow-digest/disclosure-table', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			var rows = attributes.rows || [];

			var headings = [
				attributes.columnOne || __( 'What to check', 'shadow-software-digest-theme-for-wordpress' ),
				attributes.columnTwo || __( 'Why it matters', 'shadow-software-digest-theme-for-wordpress' ),
				attributes.columnThree || __( 'Partner', 'shadow-software-digest-theme-for-wordpress' ),
			];

			function update( index, key, value ) {
				var next = rows.map( function ( row, i ) {
					if ( i !== index ) {
						return row;
					}

					var copy = Object.assign( {}, row );
					copy[ key ] = value;

					return copy;
				} );

				setAttributes( { rows: next } );
			}

			function add() {
				setAttributes( {
					rows: rows.concat( [
						{ label: '', detail: '', partner: '', url: '' },
					] ),
				} );
			}

			function remove( index ) {
				setAttributes( {
					rows: rows.filter( function ( _row, i ) {
						return i !== index;
					} ),
				} );
			}

			return el(
				Fragment,
				null,

				el(
					InspectorControls,
					null,

					el(
						PanelBody,
						{ title: __( 'Columns', 'shadow-software-digest-theme-for-wordpress' ) },

						el( TextControl, {
							label: __( 'First column', 'shadow-software-digest-theme-for-wordpress' ),
							value: attributes.columnOne,
							placeholder: __( 'What to check', 'shadow-software-digest-theme-for-wordpress' ),
							__nextHasNoMarginBottom: true,
							onChange: function ( value ) {
								setAttributes( { columnOne: value } );
							},
						} ),

						el( TextControl, {
							label: __( 'Second column', 'shadow-software-digest-theme-for-wordpress' ),
							value: attributes.columnTwo,
							placeholder: __( 'Why it matters', 'shadow-software-digest-theme-for-wordpress' ),
							__nextHasNoMarginBottom: true,
							onChange: function ( value ) {
								setAttributes( { columnTwo: value } );
							},
						} ),

						el( TextControl, {
							label: __( 'Third column', 'shadow-software-digest-theme-for-wordpress' ),
							value: attributes.columnThree,
							placeholder: __( 'Partner', 'shadow-software-digest-theme-for-wordpress' ),
							__nextHasNoMarginBottom: true,
							onChange: function ( value ) {
								setAttributes( { columnThree: value } );
							},
						} )
					),

					el(
						PanelBody,
						{ title: __( 'Rows', 'shadow-software-digest-theme-for-wordpress' ) },

						el(
							Notice,
							{ status: 'warning', isDismissible: false },
							__(
								'Partner links are always marked rel="sponsored nofollow" and a disclosure line is always printed — if you leave the disclosure empty, Digest writes one for you.',
								'shadow-software-digest-theme-for-wordpress'
							)
						),

						rows.map( function ( row, index ) {
							return el(
								'div',
								{ key: index, className: 'digest-row' },

								el( TextControl, {
									label: __( 'Label', 'shadow-software-digest-theme-for-wordpress' ),
									value: row.label || '',
									__nextHasNoMarginBottom: true,
									onChange: function ( value ) {
										update( index, 'label', value );
									},
								} ),

								el( TextareaControl, {
									label: __( 'Detail', 'shadow-software-digest-theme-for-wordpress' ),
									value: row.detail || '',
									rows: 3,
									__nextHasNoMarginBottom: true,
									onChange: function ( value ) {
										update( index, 'detail', value );
									},
								} ),

								el( TextControl, {
									label: __( 'Partner name', 'shadow-software-digest-theme-for-wordpress' ),
									value: row.partner || '',
									__nextHasNoMarginBottom: true,
									onChange: function ( value ) {
										update( index, 'partner', value );
									},
								} ),

								el( TextControl, {
									label: __( 'Partner URL', 'shadow-software-digest-theme-for-wordpress' ),
									type: 'url',
									value: row.url || '',
									__nextHasNoMarginBottom: true,
									onChange: function ( value ) {
										update( index, 'url', value );
									},
								} ),

								el(
									Button,
									{
										size: 'small',
										variant: 'tertiary',
										isDestructive: true,
										onClick: function () {
											remove( index );
										},
									},
									__( 'Remove row', 'shadow-software-digest-theme-for-wordpress' )
								)
							);
						} ),

						el(
							Button,
							{ variant: 'secondary', onClick: add },
							__( 'Add a row', 'shadow-software-digest-theme-for-wordpress' )
						)
					),

					el(
						PanelBody,
						{ title: __( 'Disclosure', 'shadow-software-digest-theme-for-wordpress' ) },
						el( TextareaControl, {
							label: __( 'Disclosure line', 'shadow-software-digest-theme-for-wordpress' ),
							help: __(
								'Leave empty and Digest prints a standard affiliate disclosure naming your publication.',
								'shadow-software-digest-theme-for-wordpress'
							),
							value: attributes.disclosure,
							rows: 4,
							__nextHasNoMarginBottom: true,
							onChange: function ( value ) {
								setAttributes( { disclosure: value } );
							},
						} )
					)
				),

				el(
					'div',
					useBlockProps( {
						className: 'wp-block-digest-disclosure-table',
					} ),

					el(
						'table',
						{
							className:
								'wp-block-digest-disclosure-table__table',
						},

						el(
							'thead',
							null,
							el(
								'tr',
								null,
								headings.map( function ( heading, index ) {
									return el(
										'th',
										{ key: index, scope: 'col' },
										heading
									);
								} )
							)
						),

						el(
							'tbody',
							null,
							rows.length === 0
								? el(
										'tr',
										null,
										el(
											'td',
											{
												colSpan: 3,
												style: {
													fontStyle: 'italic',
													opacity: 0.7,
												},
											},
											__(
												'Add rows in the block settings, on the right.',
												'shadow-software-digest-theme-for-wordpress'
											)
										)
								  )
								: rows.map( function ( row, index ) {
										return el(
											'tr',
											{ key: index },
											el( 'td', null, row.label ),
											el( 'td', null, row.detail ),
											el(
												'td',
												null,
												row.partner
													? el(
															'span',
															{
																className:
																	'wp-block-digest-disclosure-table__partner',
															},
															row.partner + ' →'
													  )
													: null
											)
										);
								  } )
						)
					),

					el(
						'p',
						{
							className:
								'wp-block-digest-disclosure-table__note',
						},
						attributes.disclosure ||
							__(
								'A standard affiliate disclosure will be printed here.',
								'shadow-software-digest-theme-for-wordpress'
							)
					)
				)
			);
		},

		save: function () {
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n
);
