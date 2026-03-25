<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin page: Import logs viewer — shows runs per feed and per-record log entries.
 */
class DIP_Admin_Logs {

	public static function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'dip' ) );
		}

		$feed_id = absint( $_GET['feed_id'] ?? 0 );
		$run_id  = absint( $_GET['run_id']  ?? 0 );

		if ( $run_id ) {
			self::render_log_entries( $run_id, $feed_id );
		} elseif ( $feed_id ) {
			self::render_runs( $feed_id );
		} else {
			self::render_feed_list();
		}
	}

	// ── Feed selection ────────────────────────────────────────────────────────

	private static function render_feed_list(): void {
		$feeds = DIP_DB::get_feeds();
		?>
		<div class="wrap dip-wrap">
			<h1><?php esc_html_e( 'Import Logs', 'dip' ); ?></h1>
			<hr class="wp-header-end">

			<?php if ( empty( $feeds ) ) : ?>
				<p><?php esc_html_e( 'No feeds configured. Add a feed first.', 'dip' ); ?></p>
			<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Feed', 'dip' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Last Run', 'dip' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'dip' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $feeds as $feed ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $feed['name'] ); ?></strong></td>
						<td><?php echo $feed['last_run_at'] ? esc_html( $feed['last_run_at'] ) : '&#8212;'; ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=dip-logs&feed_id=' . (int) $feed['id'] ) ); ?>">
								<?php esc_html_e( 'View Runs', 'dip' ); ?>
							</a>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
		<?php
	}

	// ── Run list ──────────────────────────────────────────────────────────────

	private static function render_runs( int $feed_id ): void {
		$feed = DIP_DB::get_feed( $feed_id );
		if ( ! $feed ) {
			wp_die( esc_html__( 'Feed not found.', 'dip' ) );
		}

		$runs     = DIP_DB::get_runs( $feed_id, 50 );
		$back_url = admin_url( 'admin.php?page=dip-logs' );
		?>
		<div class="wrap dip-wrap">
			<h1>
				<?php
				printf(
					/* translators: %s: feed name */
					esc_html__( 'Import Runs — %s', 'dip' ),
					esc_html( $feed['name'] )
				);
				?>
			</h1>
			<a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'All feeds', 'dip' ); ?></a>
			<hr class="wp-header-end">

			<?php if ( empty( $runs ) ) : ?>
				<p><?php esc_html_e( 'No import runs yet for this feed.', 'dip' ); ?></p>
			<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Run ID', 'dip' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'dip' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Created', 'dip' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Updated', 'dip' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Skipped', 'dip' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Errors', 'dip' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Started', 'dip' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Finished', 'dip' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Logs', 'dip' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $runs as $run ) : ?>
					<tr>
						<td><?php echo (int) $run['id']; ?></td>
						<td>
							<span class="dip-status dip-status--<?php echo esc_attr( $run['status'] ); ?>">
								<?php echo esc_html( ucfirst( $run['status'] ) ); ?>
							</span>
						</td>
						<td><?php echo (int) $run['created_count']; ?></td>
						<td><?php echo (int) $run['updated_count']; ?></td>
						<td><?php echo (int) $run['skipped_count']; ?></td>
						<td><?php echo (int) $run['error_count']; ?></td>
						<td><?php echo esc_html( $run['started_at']  ?? '—' ); ?></td>
						<td><?php echo esc_html( $run['finished_at'] ?? '—' ); ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=dip-logs&feed_id=' . $feed_id . '&run_id=' . (int) $run['id'] ) ); ?>">
								<?php esc_html_e( 'View', 'dip' ); ?>
							</a>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
		<?php
	}

	// ── Log entries ───────────────────────────────────────────────────────────

	private static function render_log_entries( int $run_id, int $feed_id ): void {
		$page   = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$limit  = 100;
		$offset = ( $page - 1 ) * $limit;
		$total  = DIP_DB::get_log_count( $run_id );
		$logs   = DIP_DB::get_logs( $run_id, $limit, $offset );
		$pages  = (int) ceil( $total / $limit );

		$back_url = admin_url( 'admin.php?page=dip-logs&feed_id=' . $feed_id );
		?>
		<div class="wrap dip-wrap">
			<h1>
				<?php
				printf(
					/* translators: %d: run ID */
					esc_html__( 'Log Entries — Run #%d', 'dip' ),
					$run_id
				);
				?>
			</h1>
			<a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Back to runs', 'dip' ); ?></a>
			<hr class="wp-header-end">

			<p><?php printf( esc_html__( 'Total entries: %d', 'dip' ), (int) $total ); ?></p>

			<?php if ( empty( $logs ) ) : ?>
				<p><?php esc_html_e( 'No log entries for this run.', 'dip' ); ?></p>
			<?php else : ?>
			<table class="wp-list-table widefat fixed striped dip-log-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( '#', 'dip' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'dip' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Product', 'dip' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Record', 'dip' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Message', 'dip' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Time', 'dip' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $logs as $entry ) : ?>
					<tr class="dip-log-row dip-log-row--<?php echo esc_attr( $entry['status'] ); ?>">
						<td><?php echo (int) $entry['id']; ?></td>
						<td>
							<span class="dip-status dip-status--<?php echo esc_attr( $entry['status'] ); ?>">
								<?php echo esc_html( ucfirst( $entry['status'] ) ); ?>
							</span>
						</td>
						<td>
							<?php if ( $entry['product_id'] ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( (int) $entry['product_id'] ) ); ?>">
									#<?php echo (int) $entry['product_id']; ?>
								</a>
							<?php else : ?>
								&#8212;
							<?php endif; ?>
						</td>
						<td><?php echo (int) $entry['record_index']; ?></td>
						<td><?php echo esc_html( $entry['message'] ); ?></td>
						<td><?php echo esc_html( $entry['created_at'] ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					echo paginate_links( [
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $page,
						'total'   => $pages,
					] );
					?>
				</div>
			</div>
			<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}
