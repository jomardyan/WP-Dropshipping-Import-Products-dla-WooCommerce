<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin page: Feed list, add/edit feed form with field mapping, price rules,
 * conditional logic, schedule settings, and update behaviour.
 */
class DIP_Admin_Feeds {

	// ── Router ────────────────────────────────────────────────────────────────

	public static function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'dip' ) );
		}

		$action  = sanitize_key( $_GET['dip_action'] ?? '' );
		$feed_id = absint( $_GET['feed_id'] ?? 0 );

		if ( 'edit' === $action || 'new' === $action ) {
			$feed = $feed_id ? DIP_DB::get_feed( $feed_id ) : null;
			self::render_edit_form( $feed );
		} else {
			self::render_list();
		}
	}

	// ── List view ─────────────────────────────────────────────────────────────

	private static function render_list(): void {
		$feeds = DIP_DB::get_feeds();
		?>
		<div class="wrap dip-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Import Products — Feeds', 'dip' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=dip-feeds&dip_action=new' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New Feed', 'dip' ); ?>
			</a>
			<hr class="wp-header-end">

			<?php self::render_notices(); ?>

			<?php if ( empty( $feeds ) ) : ?>
				<p><?php esc_html_e( 'No feeds configured yet. Add your first feed to start importing products.', 'dip' ); ?></p>
			<?php else : ?>
			<table class="wp-list-table widefat fixed striped dip-feeds-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Name', 'dip' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Source URL', 'dip' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Type', 'dip' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'dip' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Last Run', 'dip' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'dip' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $feeds as $feed ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $feed['name'] ); ?></strong></td>
						<td><code><?php echo esc_html( wp_trim_words( $feed['source_url'], 8, '…' ) ); ?></code></td>
						<td><?php echo esc_html( strtoupper( $feed['source_type'] ) ); ?></td>
						<td>
							<span class="dip-status dip-status--<?php echo esc_attr( $feed['status'] ); ?>">
								<?php echo esc_html( self::status_label( $feed['status'] ) ); ?>
							</span>
						</td>
						<td><?php echo $feed['last_run_at'] ? esc_html( $feed['last_run_at'] ) : '&#8212;'; ?></td>
						<td class="dip-actions">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=dip-feeds&dip_action=edit&feed_id=' . (int) $feed['id'] ) ); ?>">
								<?php esc_html_e( 'Edit', 'dip' ); ?>
							</a>
							&nbsp;|&nbsp;
							<button type="button"
								class="button-link dip-run-import"
								data-feed-id="<?php echo (int) $feed['id']; ?>"
								data-nonce="<?php echo esc_attr( wp_create_nonce( 'dip_admin_nonce' ) ); ?>">
								<?php esc_html_e( 'Run Import', 'dip' ); ?>
							</button>
							&nbsp;|&nbsp;
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=dip-logs&feed_id=' . (int) $feed['id'] ) ); ?>">
								<?php esc_html_e( 'Logs', 'dip' ); ?>
							</a>
							&nbsp;|&nbsp;
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
								<?php wp_nonce_field( 'dip_delete_feed_' . (int) $feed['id'], 'dip_delete_nonce' ); ?>
								<input type="hidden" name="action"  value="dip_delete_feed">
								<input type="hidden" name="feed_id" value="<?php echo (int) $feed['id']; ?>">
								<button type="submit" class="button-link dip-delete-feed" style="color:#a00">
									<?php esc_html_e( 'Delete', 'dip' ); ?>
								</button>
							</form>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
		<?php
	}

	// ── Edit / Add form ───────────────────────────────────────────────────────

	/** @param array<string,mixed>|null $feed */
	private static function render_edit_form( ?array $feed ): void {
		$is_new   = null === $feed;
		$feed_id  = $is_new ? 0 : (int) $feed['id'];
		$settings = $is_new ? [] : ( json_decode( $feed['settings'] ?? '{}', true ) ?? [] );
		$mapping  = $is_new ? [] : ( json_decode( $feed['mapping']  ?? '{}', true ) ?? [] );

		$back_url = admin_url( 'admin.php?page=dip-feeds' );
		$title    = $is_new ? __( 'Add New Feed', 'dip' ) : __( 'Edit Feed', 'dip' );
		?>
		<div class="wrap dip-wrap">
			<h1><?php echo esc_html( $title ); ?></h1>
			<a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Back to feeds', 'dip' ); ?></a>
			<hr class="wp-header-end">

			<?php self::render_notices(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="dip-feed-form">
				<?php wp_nonce_field( 'dip_save_feed_' . $feed_id, 'dip_feed_nonce' ); ?>
				<input type="hidden" name="action"  value="dip_save_feed">
				<input type="hidden" name="feed_id" value="<?php echo $feed_id; ?>">

				<!-- ── Basic Settings ─────────────────────────────────────── -->
				<h2><?php esc_html_e( 'Basic Settings', 'dip' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="dip_name"><?php esc_html_e( 'Feed Name', 'dip' ); ?></label></th>
						<td>
							<input type="text" id="dip_name" name="dip_name" class="regular-text"
								value="<?php echo esc_attr( $feed['name'] ?? '' ); ?>" required>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dip_source_url"><?php esc_html_e( 'Source URL', 'dip' ); ?></label></th>
						<td>
							<input type="url" id="dip_source_url" name="dip_source_url" class="large-text"
								value="<?php echo esc_attr( $feed['source_url'] ?? '' ); ?>" required>
							<p class="description"><?php esc_html_e( 'Remote URL or absolute local path to the XML or CSV file.', 'dip' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dip_source_type"><?php esc_html_e( 'Source Type', 'dip' ); ?></label></th>
						<td>
							<select id="dip_source_type" name="dip_source_type">
								<option value="xml" <?php selected( $feed['source_type'] ?? 'xml', 'xml' ); ?>>XML</option>
								<option value="csv" <?php selected( $feed['source_type'] ?? 'xml', 'csv' ); ?>>CSV</option>
							</select>
							<?php if ( ! $is_new ) : ?>
							<button type="button" class="button" id="dip-detect-fields">
								<?php esc_html_e( 'Detect Fields', 'dip' ); ?>
							</button>
							<button type="button" class="button" id="dip-preview-feed">
								<?php esc_html_e( 'Preview (5 rows)', 'dip' ); ?>
							</button>
							<?php endif; ?>
						</td>
					</tr>
					<tr id="dip-xml-node-row" <?php echo ( ( $feed['source_type'] ?? 'xml' ) === 'csv' ) ? 'style="display:none"' : ''; ?>>
						<th scope="row"><label for="dip_xml_item_node"><?php esc_html_e( 'XML Item Node', 'dip' ); ?></label></th>
						<td>
							<input type="text" id="dip_xml_item_node" name="dip_xml_item_node" class="regular-text"
								value="<?php echo esc_attr( $settings['xml_item_node'] ?? '' ); ?>"
								placeholder="<?php esc_attr_e( 'e.g. product (auto-detected if empty)', 'dip' ); ?>">
						</td>
					</tr>
					<tr id="dip-csv-delimiter-row" <?php echo ( ( $feed['source_type'] ?? 'xml' ) !== 'csv' ) ? 'style="display:none"' : ''; ?>>
						<th scope="row"><label for="dip_csv_delimiter"><?php esc_html_e( 'CSV Delimiter', 'dip' ); ?></label></th>
						<td>
							<select id="dip_csv_delimiter" name="dip_csv_delimiter">
								<?php
								$saved_delim = $settings['csv_delimiter'] ?? ',';
								$delimiters  = [ ',' => __( 'Comma (,)', 'dip' ), ';' => __( 'Semicolon (;)', 'dip' ), "\t" => __( 'Tab', 'dip' ), '|' => __( 'Pipe (|)', 'dip' ) ];
								foreach ( $delimiters as $val => $label ) {
									printf(
										'<option value="%s"%s>%s</option>',
										esc_attr( $val ),
										selected( $saved_delim, $val, false ),
										esc_html( $label )
									);
								}
								?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dip_status"><?php esc_html_e( 'Feed Status', 'dip' ); ?></label></th>
						<td>
							<select id="dip_status" name="dip_status">
								<option value="active" <?php selected( $feed['status'] ?? 'active', 'active' ); ?>><?php esc_html_e( 'Active', 'dip' ); ?></option>
								<option value="paused" <?php selected( $feed['status'] ?? 'active', 'paused' ); ?>><?php esc_html_e( 'Paused', 'dip' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<!-- ── Field Mapping ──────────────────────────────────────── -->
				<h2><?php esc_html_e( 'Field Mapping', 'dip' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Map source feed fields to WooCommerce product fields. Drag rows to reorder.', 'dip' ); ?>
				</p>

				<div id="dip-mapping-builder">
					<table class="dip-mapping-table widefat" aria-label="<?php esc_attr_e( 'Field mapping', 'dip' ); ?>">
						<thead>
							<tr>
								<th scope="col" style="width:30px"></th>
								<th scope="col"><?php esc_html_e( 'WooCommerce Field', 'dip' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Source Field', 'dip' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Default Value', 'dip' ); ?></th>
								<th scope="col" style="width:60px"><?php esc_html_e( 'Remove', 'dip' ); ?></th>
							</tr>
						</thead>
						<tbody id="dip-mapping-rows">
							<?php foreach ( $mapping as $target => $cfg ) : ?>
							<tr class="dip-mapping-row">
								<td class="dip-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'dip' ); ?>">&#9776;</td>
								<td>
									<select name="dip_mapping_target[]" class="dip-select-target">
										<option value=""><?php esc_html_e( '— Select field —', 'dip' ); ?></option>
										<?php foreach ( DIP_Field_Mapper::wc_target_fields() as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $target, $key ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<input type="text" name="dip_mapping_source[]" class="dip-input-source regular-text"
										value="<?php echo esc_attr( $cfg['source'] ?? '' ); ?>"
										placeholder="<?php esc_attr_e( 'source.field', 'dip' ); ?>">
								</td>
								<td>
									<input type="text" name="dip_mapping_default[]" class="dip-input-default"
										value="<?php echo esc_attr( $cfg['default'] ?? '' ); ?>">
								</td>
								<td>
									<button type="button" class="button-link dip-remove-row" aria-label="<?php esc_attr_e( 'Remove mapping row', 'dip' ); ?>">&times;</button>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<button type="button" class="button" id="dip-add-mapping"><?php esc_html_e( '+ Add Mapping', 'dip' ); ?></button>
				</div>

				<!-- ── Preview Area ───────────────────────────────────────── -->
				<div id="dip-preview-area" style="display:none">
					<h3><?php esc_html_e( 'Feed Preview', 'dip' ); ?></h3>
					<div id="dip-preview-content" aria-live="polite"></div>
				</div>

				<!-- ── Product Matching ───────────────────────────────────── -->
				<h2><?php esc_html_e( 'Product Matching', 'dip' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="dip_match_method"><?php esc_html_e( 'Match By', 'dip' ); ?></label></th>
						<td>
							<select id="dip_match_method" name="dip_match_method">
								<?php
								$match_methods  = [
									'sku'    => __( 'SKU', 'dip' ),
									'ean'    => __( 'EAN / GTIN', 'dip' ),
									'name'   => __( 'Product Name', 'dip' ),
									'custom' => __( 'Custom Meta Key', 'dip' ),
								];
								$saved_method = $settings['match']['method'] ?? 'sku';
								foreach ( $match_methods as $val => $label ) {
									printf(
										'<option value="%s"%s>%s</option>',
										esc_attr( $val ),
										selected( $saved_method, $val, false ),
										esc_html( $label )
									);
								}
								?>
							</select>
						</td>
					</tr>
					<tr id="dip-custom-meta-key-row" <?php echo 'custom' !== ( $settings['match']['method'] ?? 'sku' ) ? 'style="display:none"' : ''; ?>>
						<th scope="row"><label for="dip_match_meta_key"><?php esc_html_e( 'Custom Meta Key', 'dip' ); ?></label></th>
						<td>
							<input type="text" id="dip_match_meta_key" name="dip_match_meta_key" class="regular-text"
								value="<?php echo esc_attr( $settings['match']['meta_key'] ?? '_dip_custom_id' ); ?>">
						</td>
					</tr>
				</table>

				<!-- ── Update Behaviour ───────────────────────────────────── -->
				<h2><?php esc_html_e( 'Update Behaviour', 'dip' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Create New As Draft', 'dip' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="dip_create_as_draft" value="1"
									<?php checked( ! empty( $settings['create_as_draft'] ) ); ?>>
								<?php esc_html_e( 'Create newly imported products with draft status', 'dip' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Selective Field Update', 'dip' ); ?></th>
						<td>
							<p class="description"><?php esc_html_e( 'Choose which fields to update on existing products. Leave all unchecked to update all fields.', 'dip' ); ?></p>
							<?php
							$update_fields = $settings['update_fields'] ?? [];
							foreach ( DIP_Field_Mapper::wc_target_fields() as $key => $label ) :
								?>
								<label style="display:inline-block;margin-right:12px">
									<input type="checkbox" name="dip_update_fields[]" value="<?php echo esc_attr( $key ); ?>"
										<?php checked( in_array( $key, (array) $update_fields, true ) ); ?>>
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
				</table>

				<!-- ── Price Rules ────────────────────────────────────────── -->
				<h2><?php esc_html_e( 'Price Rules', 'dip' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Rules are applied in order to the mapped regular price.', 'dip' ); ?></p>
				<div id="dip-price-rules-builder">
					<div id="dip-price-rules-rows">
						<?php foreach ( $settings['price_rules'] ?? [] as $i => $rule ) : ?>
						<div class="dip-price-rule-row">
							<select name="dip_price_rule_type[]">
								<?php foreach ( DIP_Price_Rules::rule_types() as $val => $label ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $rule['type'] ?? '', $val ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
								<?php endforeach; ?>
							</select>
							<input type="number" step="0.01" name="dip_price_rule_value[]"
								value="<?php echo esc_attr( $rule['value'] ?? '' ); ?>"
								placeholder="<?php esc_attr_e( 'Value', 'dip' ); ?>">
							<input type="number" step="1" name="dip_price_rule_precision[]"
								value="<?php echo esc_attr( $rule['precision'] ?? 2 ); ?>"
								placeholder="<?php esc_attr_e( 'Precision', 'dip' ); ?>"
								style="width:70px"
								<?php echo 'round' !== ( $rule['type'] ?? '' ) ? 'class="dip-precision-field" style="display:none"' : ''; ?>>
							<button type="button" class="button-link dip-remove-row" aria-label="<?php esc_attr_e( 'Remove rule', 'dip' ); ?>">&times;</button>
						</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="button" id="dip-add-price-rule"><?php esc_html_e( '+ Add Price Rule', 'dip' ); ?></button>
				</div>

				<!-- ── Conditional Logic ──────────────────────────────────── -->
				<h2><?php esc_html_e( 'Conditional Logic', 'dip' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Skip import if ANY condition fails. Leave empty to import all records.', 'dip' ); ?></p>
				<div id="dip-conditions-builder">
					<div id="dip-conditions-rows">
						<?php foreach ( $settings['conditions'] ?? [] as $cond ) : ?>
						<div class="dip-condition-row">
							<input type="text" name="dip_cond_field[]" class="regular-text"
								value="<?php echo esc_attr( $cond['field'] ?? '' ); ?>"
								placeholder="<?php esc_attr_e( 'Field name', 'dip' ); ?>">
							<select name="dip_cond_operator[]">
								<?php
								$operators = [
									'=='           => __( 'equals', 'dip' ),
									'!='           => __( 'not equals', 'dip' ),
									'>'            => __( 'greater than', 'dip' ),
									'<'            => __( 'less than', 'dip' ),
									'>='           => __( 'greater or equal', 'dip' ),
									'<='           => __( 'less or equal', 'dip' ),
									'contains'     => __( 'contains', 'dip' ),
									'not_contains' => __( 'does not contain', 'dip' ),
									'empty'        => __( 'is empty', 'dip' ),
									'not_empty'    => __( 'is not empty', 'dip' ),
								];
								foreach ( $operators as $val => $label ) {
									printf(
										'<option value="%s"%s>%s</option>',
										esc_attr( $val ),
										selected( $cond['operator'] ?? '==', $val, false ),
										esc_html( $label )
									);
								}
								?>
							</select>
							<input type="text" name="dip_cond_value[]"
								value="<?php echo esc_attr( $cond['value'] ?? '' ); ?>"
								placeholder="<?php esc_attr_e( 'Value', 'dip' ); ?>">
							<button type="button" class="button-link dip-remove-row" aria-label="<?php esc_attr_e( 'Remove condition', 'dip' ); ?>">&times;</button>
						</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="button" id="dip-add-condition"><?php esc_html_e( '+ Add Condition', 'dip' ); ?></button>
				</div>

				<!-- ── Schedule ───────────────────────────────────────────── -->
				<h2><?php esc_html_e( 'Scheduled Sync', 'dip' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="dip_schedule_interval"><?php esc_html_e( 'Sync Interval', 'dip' ); ?></label></th>
						<td>
							<select id="dip_schedule_interval" name="dip_schedule_interval">
								<option value="0"><?php esc_html_e( '— No automatic sync —', 'dip' ); ?></option>
								<?php foreach ( DIP_Scheduler::interval_options() as $seconds => $label ) : ?>
								<option value="<?php echo (int) $seconds; ?>" <?php selected( (int) ( $settings['schedule_interval'] ?? 0 ), $seconds ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
								<?php endforeach; ?>
							</select>
							<?php if ( $feed_id ) :
								$next_run = DIP_Scheduler::get_next_run( $feed_id );
								if ( $next_run ) : ?>
								<p class="description">
									<?php
									printf(
										/* translators: %s: next scheduled run time */
										esc_html__( 'Next scheduled run: %s', 'dip' ),
										esc_html( $next_run )
									);
									?>
								</p>
								<?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php $is_new ? esc_html_e( 'Add Feed', 'dip' ) : esc_html_e( 'Save Feed', 'dip' ); ?>
					</button>
					<?php if ( ! $is_new ) : ?>
					<button type="button" class="button dip-run-import" data-feed-id="<?php echo $feed_id; ?>">
						<?php esc_html_e( 'Run Import Now', 'dip' ); ?>
					</button>
					<?php endif; ?>
				</p>
			</form>
		</div>
		<?php
	}

	// ── Form handlers ─────────────────────────────────────────────────────────

	public static function handle_save(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'dip' ) );
		}

		$feed_id = absint( $_POST['feed_id'] ?? 0 );
		check_admin_referer( 'dip_save_feed_' . $feed_id, 'dip_feed_nonce' );

		// ── Sanitise basic fields ────────────────────────────────────────────
		$name        = sanitize_text_field( wp_unslash( $_POST['dip_name'] ?? '' ) );
		$source_url  = esc_url_raw( wp_unslash( $_POST['dip_source_url'] ?? '' ) );
		$source_type = sanitize_key( $_POST['dip_source_type'] ?? 'xml' );
		$status      = sanitize_key( $_POST['dip_status'] ?? 'active' );

		if ( empty( $name ) || empty( $source_url ) ) {
			wp_redirect( add_query_arg( 'dip_error', 'missing_fields', wp_get_referer() ?: admin_url( 'admin.php?page=dip-feeds' ) ) );
			exit;
		}

		// ── Build mapping ────────────────────────────────────────────────────
		$targets  = array_map( 'sanitize_key',         (array) ( $_POST['dip_mapping_target']  ?? [] ) );
		$sources  = array_map( 'sanitize_text_field',  (array) ( $_POST['dip_mapping_source']  ?? [] ) );
		$defaults = array_map( 'sanitize_text_field',  (array) ( $_POST['dip_mapping_default'] ?? [] ) );

		$mapping = [];
		foreach ( $targets as $i => $target ) {
			if ( '' !== $target ) {
				$mapping[ $target ] = [
					'source'  => $sources[ $i ]  ?? '',
					'default' => $defaults[ $i ] ?? '',
				];
			}
		}

		// ── Build price rules ────────────────────────────────────────────────
		$rule_types      = array_map( 'sanitize_key',               (array) ( $_POST['dip_price_rule_type']      ?? [] ) );
		$rule_values     = array_map( 'floatval',                    (array) ( $_POST['dip_price_rule_value']     ?? [] ) );
		$rule_precisions = array_map( 'absint',                      (array) ( $_POST['dip_price_rule_precision'] ?? [] ) );

		$price_rules = [];
		foreach ( $rule_types as $i => $type ) {
			if ( '' !== $type ) {
				$rule = [ 'type' => $type, 'value' => $rule_values[ $i ] ?? 0 ];
				if ( 'round' === $type ) {
					$rule['precision'] = $rule_precisions[ $i ] ?? 2;
				}
				$price_rules[] = $rule;
			}
		}

		// ── Build conditions ─────────────────────────────────────────────────
		$cond_fields    = array_map( 'sanitize_text_field', (array) ( $_POST['dip_cond_field']    ?? [] ) );
		$cond_operators = array_map( 'sanitize_key',        (array) ( $_POST['dip_cond_operator'] ?? [] ) );
		$cond_values    = array_map( 'sanitize_text_field', (array) ( $_POST['dip_cond_value']    ?? [] ) );

		$conditions = [];
		foreach ( $cond_fields as $i => $field ) {
			if ( '' !== $field ) {
				$conditions[] = [
					'field'    => $field,
					'operator' => $cond_operators[ $i ] ?? '==',
					'value'    => $cond_values[ $i ]    ?? '',
				];
			}
		}

		// ── Build settings ───────────────────────────────────────────────────
		$update_fields     = array_map( 'sanitize_key', (array) ( $_POST['dip_update_fields'] ?? [] ) );
		$match_method      = sanitize_key( $_POST['dip_match_method']      ?? 'sku' );
		$match_meta_key    = sanitize_key( $_POST['dip_match_meta_key']    ?? '_dip_custom_id' );
		$create_as_draft   = ! empty( $_POST['dip_create_as_draft'] );
		$schedule_interval = absint( $_POST['dip_schedule_interval'] ?? 0 );
		$xml_item_node     = sanitize_text_field( wp_unslash( $_POST['dip_xml_item_node'] ?? '' ) );
		$csv_delimiter     = wp_unslash( $_POST['dip_csv_delimiter'] ?? ',' );

		$settings = [
			'match'             => [ 'method' => $match_method, 'meta_key' => $match_meta_key ],
			'create_as_draft'   => $create_as_draft,
			'update_fields'     => $update_fields ?: null,
			'price_rules'       => $price_rules,
			'conditions'        => $conditions,
			'schedule_interval' => $schedule_interval,
			'xml_item_node'     => $xml_item_node,
			'csv_delimiter'     => $csv_delimiter,
		];

		// ── Persist ──────────────────────────────────────────────────────────
		$saved_id = DIP_DB::save_feed( [
			'id'          => $feed_id ?: null,
			'name'        => $name,
			'source_url'  => $source_url,
			'source_type' => $source_type,
			'status'      => $status,
			'mapping'     => wp_json_encode( $mapping ),
			'settings'    => wp_json_encode( $settings ),
		] );

		// ── Scheduler ────────────────────────────────────────────────────────
		if ( $schedule_interval > 0 ) {
			DIP_Scheduler::schedule( $saved_id, $schedule_interval );
		} else {
			DIP_Scheduler::unschedule( $saved_id );
		}

		$redirect = admin_url( 'admin.php?page=dip-feeds&dip_action=edit&feed_id=' . $saved_id . '&dip_saved=1' );
		wp_redirect( $redirect );
		exit;
	}

	public static function handle_delete(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'dip' ) );
		}

		$feed_id = absint( $_POST['feed_id'] ?? 0 );
		check_admin_referer( 'dip_delete_feed_' . $feed_id, 'dip_delete_nonce' );

		if ( $feed_id ) {
			DIP_Scheduler::unschedule( $feed_id );
			DIP_DB::delete_feed( $feed_id );
		}

		wp_redirect( admin_url( 'admin.php?page=dip-feeds&dip_deleted=1' ) );
		exit;
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private static function render_notices(): void {
		if ( isset( $_GET['dip_saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Feed saved.', 'dip' ) . '</p></div>';
		}
		if ( isset( $_GET['dip_deleted'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Feed deleted.', 'dip' ) . '</p></div>';
		}
		if ( isset( $_GET['dip_error'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Please fill in all required fields.', 'dip' ) . '</p></div>';
		}
	}

	private static function status_label( string $status ): string {
		return match ( $status ) {
			'active' => __( 'Active', 'dip' ),
			'paused' => __( 'Paused', 'dip' ),
			default  => ucfirst( $status ),
		};
	}
}
