<?php
defined( 'ABSPATH' ) || exit;

/**
 * Action Scheduler integration for recurring feed synchronisation.
 * All scheduled actions are grouped under 'dip' for easy management.
 */
class DIP_Scheduler {

	private const GROUP        = 'dip';
	private const HOOK         = 'dip_run_sync';
	private const CLEANUP_HOOK = 'dip_cleanup_logs';

	/** Register the Action Scheduler callback and the daily log-cleanup cron. */
	public static function init(): void {
		add_action( self::HOOK,         [ __CLASS__, 'handle_sync'        ] );
		add_action( self::CLEANUP_HOOK, [ __CLASS__, 'handle_log_cleanup' ] );

		// Schedule daily cleanup if not already scheduled.
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CLEANUP_HOOK );
		}
	}

	/**
	 * Schedule a recurring sync for a feed.
	 * Re-schedules if an action already exists (i.e. interval changed).
	 */
	public static function schedule( int $feed_id, int $interval_seconds ): void {
		if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}
		self::unschedule( $feed_id );
		as_schedule_recurring_action(
			time() + 60, // start 1 minute from now
			$interval_seconds,
			self::HOOK,
			[ 'feed_id' => $feed_id ],
			self::GROUP
		);
	}

	/** Cancel all pending scheduled actions for a specific feed. */
	public static function unschedule( int $feed_id ): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}
		as_unschedule_all_actions( self::HOOK, [ 'feed_id' => $feed_id ], self::GROUP );
	}

	/** Cancel all DIP scheduled actions (used on plugin deactivation). */
	public static function unschedule_all(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, [], self::GROUP );
		}
		$ts = wp_next_scheduled( self::CLEANUP_HOOK );
		if ( false !== $ts ) {
			wp_unschedule_event( $ts, self::CLEANUP_HOOK );
		}
	}

	/**
	 * Action Scheduler callback — processes one feed sync run.
	 *
	 * @param int $feed_id
	 */
	public static function handle_sync( int $feed_id ): void {
		DIP_Sync_Runner::run( $feed_id );
	}

	/**
	 * WP-Cron callback — deletes log entries older than the configured retention period.
	 */
	public static function handle_log_cleanup(): void {
		$settings  = (array) get_option( 'dip_global_settings', [] );
		$retention = isset( $settings['log_retention'] ) ? (int) $settings['log_retention'] : 30;
		if ( $retention > 0 ) {
			DIP_DB::delete_old_logs( $retention );
		}
	}

	/**
	 * Return the next scheduled run time (UTC) for a feed, or null if not scheduled.
	 */
	public static function get_next_run( int $feed_id ): ?string {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return null;
		}

		$actions = as_get_scheduled_actions(
			[
				'hook'     => self::HOOK,
				'args'     => [ 'feed_id' => $feed_id ],
				'group'    => self::GROUP,
				'status'   => \ActionScheduler_Store::STATUS_PENDING,
				'per_page' => 1,
			],
			'ARRAY_A'
		);

		if ( empty( $actions ) ) {
			return null;
		}

		$action   = reset( $actions );
		$schedule = $action['schedule'] ?? null;

		if ( $schedule && method_exists( $schedule, 'get_next' ) ) {
			$next = $schedule->get_next( new \DateTime( 'now', new \DateTimeZone( 'UTC' ) ) );
			return $next ? $next->format( 'Y-m-d H:i:s' ) . ' UTC' : null;
		}

		return null;
	}

	/**
	 * Available sync interval options.
	 *
	 * @return array<int,string>  seconds => label
	 */
	public static function interval_options(): array {
		return [
			HOUR_IN_SECONDS          => __( 'Every hour', 'dip' ),
			2 * HOUR_IN_SECONDS      => __( 'Every 2 hours', 'dip' ),
			6 * HOUR_IN_SECONDS      => __( 'Every 6 hours', 'dip' ),
			12 * HOUR_IN_SECONDS     => __( 'Every 12 hours', 'dip' ),
			DAY_IN_SECONDS           => __( 'Every day', 'dip' ),
			2 * DAY_IN_SECONDS       => __( 'Every 2 days', 'dip' ),
			WEEK_IN_SECONDS          => __( 'Every week', 'dip' ),
		];
	}
}
