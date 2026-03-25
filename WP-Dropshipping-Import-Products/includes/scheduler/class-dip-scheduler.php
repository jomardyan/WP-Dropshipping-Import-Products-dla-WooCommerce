<?php
defined( 'ABSPATH' ) || exit;

/**
 * Action Scheduler integration for recurring feed synchronisation.
 * All scheduled actions are grouped under 'dip' for easy management.
 */
class DIP_Scheduler {

	private const GROUP = 'dip';
	private const HOOK  = 'dip_run_sync';

	/** Register the Action Scheduler callback. */
	public static function init(): void {
		add_action( self::HOOK, [ __CLASS__, 'handle_sync' ] );
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
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}
		as_unschedule_all_actions( self::HOOK, [], self::GROUP );
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
