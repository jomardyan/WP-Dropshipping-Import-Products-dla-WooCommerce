<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin page: Global plugin settings.
 */
class DIP_Admin_Settings {

	private const OPTION_KEY = 'dip_global_settings';

	public static function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'dip' ) );
		}

		$settings = self::get();
		?>
		<div class="wrap dip-wrap">
			<h1><?php esc_html_e( 'Import Products — Settings', 'dip' ); ?></h1>
			<hr class="wp-header-end">

			<?php if ( isset( $_GET['dip_saved'] ) ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Settings saved.', 'dip' ); ?></p>
			</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'dip_save_settings', 'dip_settings_nonce' ); ?>
				<input type="hidden" name="action" value="dip_save_settings">

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="dip_timeout"><?php esc_html_e( 'HTTP Request Timeout (s)', 'dip' ); ?></label>
						</th>
						<td>
							<input type="number" id="dip_timeout" name="dip_timeout" min="10" max="600"
								value="<?php echo absint( $settings['timeout'] ?? 60 ); ?>">
							<p class="description"><?php esc_html_e( 'Timeout in seconds for fetching remote feed files.', 'dip' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="dip_log_retention"><?php esc_html_e( 'Log Retention (days)', 'dip' ); ?></label>
						</th>
						<td>
							<input type="number" id="dip_log_retention" name="dip_log_retention" min="1" max="365"
								value="<?php echo absint( $settings['log_retention'] ?? 30 ); ?>">
							<p class="description"><?php esc_html_e( 'Delete import log entries older than this many days. Set to 0 to keep forever.', 'dip' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Debug Mode', 'dip' ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" name="dip_debug_mode" value="1"
									<?php checked( ! empty( $settings['debug_mode'] ) ); ?>>
								<?php esc_html_e( 'Enable verbose logging (logs all records, including skipped)', 'dip' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Delete Data on Uninstall', 'dip' ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" name="dip_delete_on_uninstall" value="1"
									<?php checked( ! empty( $settings['delete_on_uninstall'] ) ); ?>>
								<?php esc_html_e( 'Remove all plugin data (feeds, logs, settings) when the plugin is deleted', 'dip' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'dip' ); ?></button>
				</p>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Tools', 'dip' ); ?></h2>
			<p>
				<?php printf(
					/* translators: %1$s: plugin version, %2$s: DB version */
					esc_html__( 'Plugin version: %1$s | DB schema version: %2$s', 'dip' ),
					esc_html( DIP_VERSION ),
					esc_html( (string) get_option( 'dip_db_version', '—' ) )
				); ?>
			</p>
		</div>
		<?php
	}

	public static function handle_save(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'dip' ) );
		}

		check_admin_referer( 'dip_save_settings', 'dip_settings_nonce' );

		$settings = [
			'timeout'             => absint( $_POST['dip_timeout']             ?? 60 ),
			'log_retention'       => absint( $_POST['dip_log_retention']       ?? 30 ),
			'debug_mode'          => ! empty( $_POST['dip_debug_mode'] ),
			'delete_on_uninstall' => ! empty( $_POST['dip_delete_on_uninstall'] ),
		];

		update_option( self::OPTION_KEY, $settings, false );

		wp_redirect( admin_url( 'admin.php?page=dip-settings&dip_saved=1' ) );
		exit;
	}

	/** @return array<string,mixed> */
	public static function get(): array {
		return (array) get_option( self::OPTION_KEY, [] );
	}
}
