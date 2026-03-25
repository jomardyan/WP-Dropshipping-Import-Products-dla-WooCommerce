/* global dipAdmin, wp */
( function ( $, config ) {
	'use strict';

	// ── Detect Fields ───────────────────────────────────────────────────────
	$( '#dip-detect-fields' ).on( 'click', function () {
		var $btn = $( this );
		var url  = $( '#dip_source_url' ).val();
		var type = $( '#dip_source_type' ).val();

		if ( ! url ) {
			// translators: shown in browser alert
			alert( config.i18n.noFields );
			return;
		}

		$btn.prop( 'disabled', true ).text( config.i18n.fieldsLoading );

		$.post( config.ajaxUrl, {
			action:      'dip_detect_fields',
			nonce:       config.nonce,
			source_url:  url,
			source_type: type,
		} )
		.done( function ( res ) {
			if ( res.success ) {
				var fields      = res.data.fields || [];
				var item_node   = res.data.item_node || '';
				var delimiter   = res.data.delimiter || '';

				if ( item_node ) {
					$( '#dip_xml_item_node' ).val( item_node );
				}
				if ( delimiter ) {
					$( '#dip_csv_delimiter' ).val( delimiter );
				}

				// Store field list for mapping rows
				$( '#dip-mapping-rows .dip-input-source' ).each( function () {
					var $input = $( this );
					if ( ! $input.data( 'autocomplete-init' ) ) {
						$input.autocomplete( { source: fields, minLength: 0 } );
						$input.data( 'autocomplete-init', true );
					}
				} );

				window.dipSourceFields = fields;
			}
		} )
		.always( function () {
			$btn.prop( 'disabled', false ).text( 'Detect Fields' );
		} );
	} );

	// ── Preview Feed ────────────────────────────────────────────────────────
	$( '#dip-preview-feed' ).on( 'click', function () {
		var $btn  = $( this );
		var url   = $( '#dip_source_url' ).val();
		var type  = $( '#dip_source_type' ).val();
		var $area = $( '#dip-preview-area' );
		var $cont = $( '#dip-preview-content' );

		if ( ! url ) {
			return;
		}

		$btn.prop( 'disabled', true ).text( config.i18n.previewLoading );
		$area.show();
		$cont.html( '<span class="dip-spinner" role="status" aria-label="' + config.i18n.running + '"></span>' );

		$.post( config.ajaxUrl, {
			action:      'dip_preview_feed',
			nonce:       config.nonce,
			source_url:  url,
			source_type: type,
		} )
		.done( function ( res ) {
			if ( res.success ) {
				$cont.html( renderPreviewTable( res.data.records ) );
			} else {
				$cont.html( '<p class="dip-status dip-status--error">' + escapeHtml( res.data.message || config.i18n.error ) + '</p>' );
			}
		} )
		.fail( function () {
			$cont.html( '<p class="dip-status dip-status--error">' + config.i18n.error + '</p>' );
		} )
		.always( function () {
			$btn.prop( 'disabled', false ).text( 'Preview (5 rows)' );
		} );
	} );

	function renderPreviewTable( records ) {
		if ( ! records || ! records.length ) {
			return '<p>' + config.i18n.noFields + '</p>';
		}
		var keys = Object.keys( records[ 0 ] );
		var html = '<table><thead><tr>';
		keys.forEach( function ( k ) {
			html += '<th scope="col">' + escapeHtml( k ) + '</th>';
		} );
		html += '</tr></thead><tbody>';
		records.forEach( function ( row ) {
			html += '<tr>';
			keys.forEach( function ( k ) {
				var val = typeof row[ k ] === 'object' ? JSON.stringify( row[ k ] ) : ( row[ k ] || '' );
				html += '<td>' + escapeHtml( String( val ) ) + '</td>';
			} );
			html += '</tr>';
		} );
		html += '</tbody></table>';
		return html;
	}

	// ── Source type change: toggle XML/CSV-specific rows ────────────────────
	$( '#dip_source_type' ).on( 'change', function () {
		var isCsv = 'csv' === $( this ).val();
		$( '#dip-xml-node-row' ).toggle( ! isCsv );
		$( '#dip-csv-delimiter-row' ).toggle( isCsv );
	} );

	// ── Match method change: toggle custom meta key row ─────────────────────
	$( '#dip_match_method' ).on( 'change', function () {
		$( '#dip-custom-meta-key-row' ).toggle( 'custom' === $( this ).val() );
	} );

	// ── Run Import ──────────────────────────────────────────────────────────
	$( document ).on( 'click', '.dip-run-import', function () {
		var $btn    = $( this );
		var feedId  = $btn.data( 'feed-id' );
		var $status = $( '#dip-import-status' );

		if ( ! feedId ) { return; }

		$btn.prop( 'disabled', true );
		$status.html( '<span class="dip-spinner" aria-hidden="true"></span>' + config.i18n.running );

		$.post( config.ajaxUrl, {
			action:   'dip_run_import',
			nonce:    config.nonce,
			feed_id:  feedId,
		} )
		.done( function ( res ) {
			if ( res.success ) {
				$status.text( config.i18n.done + ' — ' + ( res.data.message || '' ) );
			} else {
				$status.text( config.i18n.error + ': ' + ( res.data.message || '' ) );
			}
		} )
		.fail( function () {
			$status.text( config.i18n.error );
		} )
		.always( function () {
			$btn.prop( 'disabled', false );
		} );
	} );

	// Append status span next to first run button
	$( '.dip-run-import' ).first().after( '<span id="dip-import-status" aria-live="polite"></span>' );

	// ── Delete Feed confirm ─────────────────────────────────────────────────
	$( '.dip-delete-feed' ).on( 'click', function ( e ) {
		if ( ! window.confirm( config.i18n.confirmDelete ) ) {
			e.preventDefault();
		}
	} );

	// ── Field Mapping Builder ───────────────────────────────────────────────

	// Drag-to-sort mapping rows
	$( '#dip-mapping-rows' ).sortable( {
		handle:      '.dip-drag-handle',
		placeholder: 'dip-sort-placeholder',
		axis:        'y',
		tolerance:   'pointer',
	} );

	// Add mapping row
	$( '#dip-add-mapping' ).on( 'click', function () {
		var $tbody = $( '#dip-mapping-rows' );
		var row    = buildMappingRow( '', '', '' );
		$tbody.append( row );
		initSourceAutocomplete( $tbody.find( '.dip-input-source' ).last() );
	} );

	// Remove any row
	$( document ).on( 'click', '.dip-remove-row', function () {
		$( this ).closest( '.dip-mapping-row, .dip-price-rule-row, .dip-condition-row' ).remove();
	} );

	function buildMappingRow( target, source, def ) {
		var targetOptions = buildTargetOptions( target );
		return $( '<tr class="dip-mapping-row">' +
			'<td class="dip-drag-handle" title="' + escapeHtml( config.i18n.dragHint || '' ) + '">&#9776;</td>' +
			'<td><select name="dip_mapping_target[]" class="dip-select-target">' +
				'<option value="">' + escapeHtml( config.i18n.targetField ) + '</option>' +
				targetOptions +
			'</select></td>' +
			'<td><input type="text" name="dip_mapping_source[]" class="dip-input-source regular-text" value="' + escapeHtml( source ) + '" placeholder="source.field"></td>' +
			'<td><input type="text" name="dip_mapping_default[]" class="dip-input-default" value="' + escapeHtml( def ) + '"></td>' +
			'<td><button type="button" class="button-link dip-remove-row" aria-label="' + escapeHtml( config.i18n.remove ) + '">&times;</button></td>' +
		'</tr>' );
	}

	function buildTargetOptions( selected ) {
		var html = '';
		var fields = config.targetFields || {};
		Object.keys( fields ).forEach( function ( key ) {
			html += '<option value="' + escapeHtml( key ) + '"' + ( key === selected ? ' selected' : '' ) + '>' +
				escapeHtml( fields[ key ] ) + '</option>';
		} );
		return html;
	}

	function initSourceAutocomplete( $el ) {
		if ( window.dipSourceFields && window.dipSourceFields.length && $.fn.autocomplete ) {
			$el.autocomplete( { source: window.dipSourceFields, minLength: 0 } );
		}
	}

	// Init autocomplete on existing rows
	$( '#dip-mapping-rows .dip-input-source' ).each( function () {
		initSourceAutocomplete( $( this ) );
	} );

	// ── Price Rules Builder ─────────────────────────────────────────────────

	$( '#dip-add-price-rule' ).on( 'click', function () {
		var ruleTypes   = config.priceRuleTypes || {};
		var typeOptions = '';
		Object.keys( ruleTypes ).forEach( function ( key ) {
			typeOptions += '<option value="' + escapeHtml( key ) + '">' + escapeHtml( ruleTypes[ key ] ) + '</option>';
		} );

		var $row = $( '<div class="dip-price-rule-row">' +
			'<select name="dip_price_rule_type[]">' + typeOptions + '</select>' +
			'<input type="number" step="0.01" name="dip_price_rule_value[]" placeholder="' + escapeHtml( config.i18n.defaultValue || 'Value' ) + '">' +
			'<input type="number" step="1" name="dip_price_rule_precision[]" placeholder="Precision" style="width:70px;display:none" class="dip-precision-field">' +
			'<button type="button" class="button-link dip-remove-row" aria-label="' + escapeHtml( config.i18n.remove ) + '">&times;</button>' +
		'</div>' );

		$row.find( 'select' ).on( 'change', function () {
			$row.find( '.dip-precision-field' ).toggle( 'round' === $( this ).val() );
		} );

		$( '#dip-price-rules-rows' ).append( $row );
	} );

	// Toggle precision field on existing rows
	$( '#dip-price-rules-rows' ).on( 'change', 'select[name="dip_price_rule_type[]"]', function () {
		$( this ).closest( '.dip-price-rule-row' ).find( '.dip-precision-field' ).toggle( 'round' === $( this ).val() );
	} );

	// ── Conditions Builder ──────────────────────────────────────────────────

	$( '#dip-add-condition' ).on( 'click', function () {
		var operators = [
			[ '==',           'equals' ],
			[ '!=',           'not equals' ],
			[ '>',            'greater than' ],
			[ '<',            'less than' ],
			[ '>=',           'greater or equal' ],
			[ '<=',           'less or equal' ],
			[ 'contains',     'contains' ],
			[ 'not_contains', 'does not contain' ],
			[ 'empty',        'is empty' ],
			[ 'not_empty',    'is not empty' ],
		];
		var opOptions = operators.map( function ( o ) {
			return '<option value="' + escapeHtml( o[ 0 ] ) + '">' + escapeHtml( o[ 1 ] ) + '</option>';
		} ).join( '' );

		var $row = $( '<div class="dip-condition-row">' +
			'<input type="text" name="dip_cond_field[]" class="regular-text" placeholder="Field name">' +
			'<select name="dip_cond_operator[]">' + opOptions + '</select>' +
			'<input type="text" name="dip_cond_value[]" placeholder="Value">' +
			'<button type="button" class="button-link dip-remove-row" aria-label="' + escapeHtml( config.i18n.remove ) + '">&times;</button>' +
		'</div>' );

		$( '#dip-conditions-rows' ).append( $row );
	} );

	// ── Utility ─────────────────────────────────────────────────────────────

	function escapeHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

} ( jQuery, window.dipAdmin || {} ) );
