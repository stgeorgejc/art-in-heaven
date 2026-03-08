<?php
/**
 * Admin Analytics View — Consolidated dashboard (replaces reports.php + stats.php).
 *
 * Five tabs: Overview, Art Pieces, Bidders, Notifications, Export.
 *
 * @var array    $art_pieces          Art pieces with stats from get_all_with_stats()
 * @var array    $engagement_metrics  Push/engagement data from get_engagement_metrics()
 * @var stdClass $stats               Reporting stats from get_reporting_stats()
 * @var stdClass $bid_stats           Bid stats from get_stats()
 * @var stdClass $payment_stats       Payment stats from get_payment_stats()
 * @var string|null $last_bid_time    Last bid timestamp
 * @var stdClass $registrant_counts   Registrant funnel counts (total, logged_in, has_bids)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ensure data vars are set with safe defaults.
if ( ! isset( $art_pieces ) ) {
	$art_pieces = array();
}
if ( ! isset( $engagement_metrics ) ) {
	$engagement_metrics = array();
}
if ( ! isset( $stats ) ) {
	$stats = new stdClass();
}
if ( ! isset( $bid_stats ) ) {
	$bid_stats = new stdClass();
}
if ( ! isset( $payment_stats ) ) {
	$payment_stats = new stdClass();
}
if ( ! isset( $last_bid_time ) ) {
	$last_bid_time = null;
}
if ( ! isset( $bid_distribution ) ) {
	$bid_distribution = array();
}
if ( ! isset( $top_by_revenue ) ) {
	$top_by_revenue = array();
}
if ( ! isset( $revenue_by_method ) ) {
	$revenue_by_method = array();
}
if ( ! isset( $revenue_by_piece ) ) {
	$revenue_by_piece = array();
}
if ( ! isset( $collection_rate ) ) {
	$collection_rate = new stdClass();
}
if ( ! isset( $avg_order_value ) ) {
	$avg_order_value = 0.0;
}
if ( ! isset( $projected_revenue ) ) {
	$projected_revenue = 0.0;
}
if ( ! isset( $live_data ) ) {
	$live_data = array();
}

// Ensure stats has all required properties.
$stats->total_pieces        = isset( $stats->total_pieces ) ? $stats->total_pieces : 0;
$stats->total_bids          = isset( $stats->total_bids ) ? $stats->total_bids : 0;
$stats->unique_bidders      = isset( $stats->unique_bidders ) ? $stats->unique_bidders : 0;
$stats->active_count        = isset( $stats->active_count ) ? $stats->active_count : 0;
$stats->draft_count         = isset( $stats->draft_count ) ? $stats->draft_count : 0;
$stats->ended_count         = isset( $stats->ended_count ) ? $stats->ended_count : 0;
$stats->pieces_with_bids    = isset( $stats->pieces_with_bids ) ? $stats->pieces_with_bids : 0;
$stats->total_starting_value = isset( $stats->total_starting_value ) ? $stats->total_starting_value : 0;
$stats->highest_bid         = isset( $stats->highest_bid ) ? $stats->highest_bid : 0;
$stats->average_bid         = isset( $stats->average_bid ) ? $stats->average_bid : 0;
$stats->top_pieces          = isset( $stats->top_pieces ) ? $stats->top_pieces : array();

// Ensure payment_stats has all required properties.
$payment_stats->total_orders    = isset( $payment_stats->total_orders ) ? $payment_stats->total_orders : 0;
$payment_stats->paid_orders     = isset( $payment_stats->paid_orders ) ? $payment_stats->paid_orders : 0;
$payment_stats->pending_orders  = isset( $payment_stats->pending_orders ) ? $payment_stats->pending_orders : 0;
$payment_stats->total_collected = isset( $payment_stats->total_collected ) ? $payment_stats->total_collected : 0;
$payment_stats->total_pending   = isset( $payment_stats->total_pending ) ? $payment_stats->total_pending : 0;

// Ensure bid_stats has all required properties.
$bid_stats->total_bid_value  = isset( $bid_stats->total_bid_value ) ? $bid_stats->total_bid_value : 0;
$bid_stats->total_bids       = isset( $bid_stats->total_bids ) ? $bid_stats->total_bids : 0;
$bid_stats->winning_bids     = isset( $bid_stats->winning_bids ) ? $bid_stats->winning_bids : 0;
$bid_stats->outbid_bids      = isset( $bid_stats->outbid_bids ) ? $bid_stats->outbid_bids : 0;
$bid_stats->rejected_bids    = isset( $bid_stats->rejected_bids ) ? $bid_stats->rejected_bids : 0;
$bid_stats->unique_bidders   = isset( $bid_stats->unique_bidders ) ? $bid_stats->unique_bidders : 0;
$bid_stats->unique_art_pieces = isset( $bid_stats->unique_art_pieces ) ? $bid_stats->unique_art_pieces : 0;
$bid_stats->highest_bid      = isset( $bid_stats->highest_bid ) ? $bid_stats->highest_bid : 0;
$bid_stats->average_bid      = isset( $bid_stats->average_bid ) ? $bid_stats->average_bid : 0;

// Engagement sub-arrays.
$funnel          = isset( $engagement_metrics['funnel'] ) ? $engagement_metrics['funnel'] : array();
$bid_attribution = isset( $engagement_metrics['bid_attribution'] ) ? $engagement_metrics['bid_attribution'] : array();
$push_bidders    = isset( $engagement_metrics['push_bidders'] ) ? $engagement_metrics['push_bidders'] : 0;
$total_bidders_db = isset( $engagement_metrics['total_bidders'] ) ? $engagement_metrics['total_bidders'] : 0;

$push_bids  = isset( $bid_attribution['push'] ) ? $bid_attribution['push'] : 0;
$organic_bids = isset( $bid_attribution['organic'] ) ? $bid_attribution['organic'] : 0;
$total_attributed_bids = $push_bids + $organic_bids;

// Initialize notification breakdown vars used by Chart.js script block on all tabs.
$notif_breakdown = array();
$type_labels     = array(
	'outbid' => __( 'Outbid', 'art-in-heaven' ),
	'winner' => __( 'Winner', 'art-in-heaven' ),
);

// Compute overview numbers from art_pieces.
$total_bidders_sum  = 0;
$total_bids_sum     = 0;
$active_with_bids   = 0;
$total_pieces_count = count( $art_pieces );
$pieces_with_bids_count = 0;

foreach ( $art_pieces as $piece ) {
	$total_bidders_sum += $piece->unique_bidders;
	$total_bids_sum    += $piece->total_bids;
	if ( $piece->total_bids > 0 ) {
		$pieces_with_bids_count++;
		if ( $piece->seconds_remaining > 0 ) {
			$active_with_bids++;
		}
	}
}

// Inventory Health chart data.
$sold_count     = 0;
$active_bids    = 0;
$active_no_bids = 0;
$unsold_count   = 0;
$single_bid_count = 0;

foreach ( $art_pieces as $p ) {
	$is_ended = ( $p->seconds_remaining <= 0 && $p->status !== 'draft' );
	$has_bids = ( $p->total_bids > 0 );
	if ( $is_ended && $has_bids ) {
		$sold_count++;
	} elseif ( ! $is_ended && $p->status === 'active' && $has_bids ) {
		$active_bids++;
	} elseif ( ! $is_ended && $p->status === 'active' && ! $has_bids ) {
		$active_no_bids++;
	} elseif ( $is_ended && ! $has_bids ) {
		$unsold_count++;
	}
	if ( $p->total_bids === 1 ) {
		$single_bid_count++;
	}
}

// Last bid display text.
$last_bid_display = '&#8212;';
if ( $last_bid_time ) {
	$bid_dt    = new DateTime( $last_bid_time, wp_timezone() );
	$now_dt    = new DateTime( 'now', wp_timezone() );
	$time_diff = $now_dt->getTimestamp() - $bid_dt->getTimestamp();
	if ( $time_diff < 60 ) {
		$last_bid_display = __( 'Just now', 'art-in-heaven' );
	} elseif ( $time_diff < 3600 ) {
		/* translators: %d: number of minutes */
		$last_bid_display = sprintf( __( '%d min ago', 'art-in-heaven' ), floor( $time_diff / 60 ) );
	} elseif ( $time_diff < 86400 ) {
		/* translators: %d: number of hours */
		$last_bid_display = sprintf( __( '%d hr ago', 'art-in-heaven' ), floor( $time_diff / 3600 ) );
	} else {
		/* translators: %d: number of days */
		$last_bid_display = sprintf( __( '%d days ago', 'art-in-heaven' ), floor( $time_diff / 86400 ) );
	}
}

// Tabs and active tab.
$tabs = array(
	'overview'      => __( 'Overview', 'art-in-heaven' ),
	'art-pieces'    => __( 'Art Pieces', 'art-in-heaven' ),
	'bidders'       => __( 'Bidders', 'art-in-heaven' ),
	'notifications' => __( 'Notifications', 'art-in-heaven' ),
	'export'        => __( 'Export', 'art-in-heaven' ),
);
if ( AIH_Roles::can_view_financial() ) {
	// Insert Revenue tab after Overview.
	$tabs = array_slice( $tabs, 0, 1, true )
		+ array( 'revenue' => __( 'Revenue', 'art-in-heaven' ) )
		+ array_slice( $tabs, 1, null, true );
}
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview';
if ( ! isset( $tabs[ $active_tab ] ) ) {
	$active_tab = 'overview';
}

// Bid rate (% of pieces that have received at least one bid).
$sell_through = $stats->total_pieces > 0 ? round( ( $stats->pieces_with_bids / $stats->total_pieces ) * 100 ) : 0;

// Total revenue.
$total_revenue = floatval( $payment_stats->total_collected ) + floatval( $payment_stats->total_pending );

// Avg bids per piece.
$avg_bids_per_piece = $stats->pieces_with_bids > 0
	? number_format( $stats->total_bids / max( intval( $stats->pieces_with_bids ), 1 ), 1 )
	: '0.0';

// Revenue vs starting.
$rev_vs_starting = $stats->total_starting_value > 0
	? round( ( $bid_stats->total_bid_value / max( floatval( $stats->total_starting_value ), 1 ) ) * 100 )
	: 0;

// Build timeline data arrays for Chart.js (needed on Overview tab).
// Data comes in 5-minute intervals; format labels as short times (e.g. "2:15 PM").
$timeline_hours   = array();
$timeline_push    = array();
$timeline_organic = array();
$bids_by_interval = isset( $engagement_metrics['bids_by_interval'] ) ? $engagement_metrics['bids_by_interval'] : array();
$interval_data    = array();
foreach ( $bids_by_interval as $row ) {
	if ( ! isset( $interval_data[ $row->time_bucket ] ) ) {
		$interval_data[ $row->time_bucket ] = array( 'push' => 0, 'organic' => 0 );
	}
	if ( $row->source === 'push' ) {
		$interval_data[ $row->time_bucket ]['push'] = (int) $row->cnt;
	} else {
		$interval_data[ $row->time_bucket ]['organic'] += (int) $row->cnt;
	}
}
ksort( $interval_data );
foreach ( $interval_data as $bucket => $counts ) {
	$dt = DateTime::createFromFormat( 'Y-m-d H:i', $bucket, wp_timezone() );
	$timeline_hours[]   = $dt ? $dt->format( 'g:i A' ) : $bucket;
	$timeline_push[]    = $counts['push'];
	$timeline_organic[] = $counts['organic'];
}

// Notification type breakdown (needed in Notifications tab + Chart.js).
$notif_types = isset( $engagement_metrics['notif_types'] ) ? $engagement_metrics['notif_types'] : array();
foreach ( $notif_types as $row ) {
	$nt = $row->notif_type;
	if ( ! isset( $notif_breakdown[ $nt ] ) ) {
		$notif_breakdown[ $nt ] = array( 'sent' => 0, 'delivered' => 0, 'clicked' => 0 );
	}
	if ( $row->event_type === 'push_sent' ) {
		$notif_breakdown[ $nt ]['sent'] = (int) $row->cnt;
	} elseif ( $row->event_type === 'push_delivered' ) {
		$notif_breakdown[ $nt ]['delivered'] = (int) $row->cnt;
	} elseif ( $row->event_type === 'push_clicked' ) {
		$notif_breakdown[ $nt ]['clicked'] = (int) $row->cnt;
	}
}
?>

<div class="wrap aih-admin-wrap">
	<h1><?php _e( 'Analytics', 'art-in-heaven' ); ?></h1>

	<nav class="nav-tab-wrapper" style="margin-bottom: 20px;">
		<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_key ) ); ?>"
			   class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( $active_tab === 'overview' ) : ?>
	<!-- ========== OVERVIEW TAB ========== -->

	<!-- Needs Attention Alerts -->
	<?php if ( ! empty( $live_data['alerts'] ) ) : ?>
	<div class="aih-alerts-panel" id="aih-alerts-panel">
		<?php foreach ( $live_data['alerts'] as $alert ) : ?>
			<div class="notice notice-warning inline">
				<p>
					<strong><?php echo esc_html( $alert['title'] ); ?></strong>
					<span class="aih-alert-count">(<?php echo intval( $alert['count'] ); ?>)</span>
				</p>
			</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<!-- Hero Stat Cards -->
	<?php
	AIH_Admin::open_stat_grid();
	AIH_Admin::render_stat_card( array(
		'value'    => $sell_through . '%',
		'label'    => __( 'Bid Rate', 'art-in-heaven' ),
		/* translators: 1: pieces with bids, 2: total pieces */
		'sublabel' => sprintf( __( '%1$d of %2$d pieces', 'art-in-heaven' ), intval( $stats->pieces_with_bids ), intval( $stats->total_pieces ) ),
		'variant'  => 'bids',
		'link'     => admin_url( 'admin.php?page=art-in-heaven-analytics&tab=art-pieces' ),
		'stat_key' => 'sell-through',
	) );
	if ( AIH_Roles::can_view_financial() ) {
		AIH_Admin::render_stat_card( array(
			'value'    => '$' . number_format( $total_revenue, 2 ),
			'label'    => __( 'Total Revenue', 'art-in-heaven' ),
			'variant'  => 'money',
			'link'     => admin_url( 'admin.php?page=art-in-heaven-orders' ),
			'stat_key' => 'total-revenue',
		) );
	}
	AIH_Admin::render_stat_card( array(
		'value'    => number_format( intval( $stats->unique_bidders ) ),
		'label'    => __( 'Unique Bidders', 'art-in-heaven' ),
		'link'     => admin_url( 'admin.php?page=art-in-heaven-analytics&tab=bidders' ),
		'stat_key' => 'unique-bidders',
	) );
	AIH_Admin::render_stat_card( array(
		'value'    => number_format( intval( $stats->active_count ) ),
		'label'    => __( 'Active Auctions', 'art-in-heaven' ),
		'variant'  => 'active',
		'link'     => admin_url( 'admin.php?page=art-in-heaven-art&tab=active_bids' ),
		'stat_key' => 'active-auctions',
	) );
	AIH_Admin::close_stat_grid();
	?>

	<!-- Auction Pulse -->
	<?php
	$pulse = isset( $live_data['pulse'] ) ? $live_data['pulse'] : array( 'bids_5m' => 0, 'bids_15m' => 0, 'bids_60m' => 0, 'status' => 'cooling' );
	$pulse_label_map = array( 'hot' => __( 'Hot', 'art-in-heaven' ), 'warm' => __( 'Warm', 'art-in-heaven' ), 'cooling' => __( 'Cooling', 'art-in-heaven' ) );
	?>
	<div class="postbox aih-auction-pulse" id="aih-auction-pulse">
		<h2 class="hndle">
			<span><?php _e( 'Auction Pulse', 'art-in-heaven' ); ?></span>
			<span class="aih-pulse-dot <?php echo esc_attr( $pulse['status'] ); ?>" title="<?php echo esc_attr( $pulse_label_map[ $pulse['status'] ] ?? '' ); ?>"></span>
			<span class="aih-pulse-label"><?php echo esc_html( $pulse_label_map[ $pulse['status'] ] ?? '' ); ?></span>
		</h2>
		<div class="inside">
			<?php
			AIH_Admin::open_stat_grid();
			AIH_Admin::render_stat_card( array(
				'value'    => number_format( intval( $pulse['bids_5m'] ) ),
				'label'    => __( 'Last 5 min', 'art-in-heaven' ),
				'stat_key' => 'pulse-5m',
			) );
			AIH_Admin::render_stat_card( array(
				'value'    => number_format( intval( $pulse['bids_15m'] ) ),
				'label'    => __( 'Last 15 min', 'art-in-heaven' ),
				'stat_key' => 'pulse-15m',
			) );
			AIH_Admin::render_stat_card( array(
				'value'    => number_format( intval( $pulse['bids_60m'] ) ),
				'label'    => __( 'Last 60 min', 'art-in-heaven' ),
				'stat_key' => 'pulse-60m',
			) );
			AIH_Admin::close_stat_grid();
			?>
		</div>
	</div>

	<!-- Auction Health Cards -->
	<?php
	AIH_Admin::open_stat_grid();
	AIH_Admin::render_stat_card( array(
		'value'    => esc_html( $avg_bids_per_piece ),
		'label'    => __( 'Avg Bids / Piece', 'art-in-heaven' ),
		'stat_key' => 'avg-bids',
	) );
	if ( AIH_Roles::can_view_financial() ) {
		AIH_Admin::render_stat_card( array(
			'value'    => $rev_vs_starting . '%',
			'label'    => __( 'Revenue vs Starting', 'art-in-heaven' ),
			'sublabel' => __( 'of asking price', 'art-in-heaven' ),
			'stat_key' => 'rev-vs-starting',
		) );
	}
	AIH_Admin::render_stat_card( array(
		'value'    => number_format( $single_bid_count ),
		'label'    => __( 'Single-Bid Pieces', 'art-in-heaven' ),
		'stat_key' => 'single-bid',
	) );
	AIH_Admin::render_stat_card( array(
		'value_html' => $last_bid_display,
		'label'      => __( 'Last Bid', 'art-in-heaven' ),
		'detail'     => $last_bid_time ? AIH_Status::format_db_date( $last_bid_time, 'M j, g:i a' ) : '',
		'variant'    => 'last-bid',
		'stat_key'   => 'last-bid',
	) );
	AIH_Admin::render_stat_card( array(
		'value'    => ( isset( $live_data['overview']['repeat_bidder_rate'] ) ? intval( $live_data['overview']['repeat_bidder_rate'] ) : 0 ) . '%',
		'label'    => __( 'Repeat Bidders', 'art-in-heaven' ),
		'sublabel' => sprintf(
			/* translators: 1: repeat bidders, 2: total bidders */
			__( '%1$d of %2$d bidders', 'art-in-heaven' ),
			isset( $live_data['overview']['repeat_bidders'] ) ? intval( $live_data['overview']['repeat_bidders'] ) : 0,
			isset( $live_data['overview']['total_bidders'] ) ? intval( $live_data['overview']['total_bidders'] ) : 0
		),
		'stat_key' => 'repeat-bidders',
	) );
	AIH_Admin::close_stat_grid();
	?>

	<!-- Charts -->
	<div class="aih-chart-row">
		<!-- Inventory Health -->
		<div class="postbox">
			<h2 class="hndle"><span><?php _e( 'Inventory Health', 'art-in-heaven' ); ?></span></h2>
			<div class="inside">
				<div style="position: relative; height: 80px;">
					<canvas id="aih-inventory-chart"></canvas>
				</div>
			</div>
		</div>

		<!-- Bid Attribution -->
		<div class="postbox" style="max-width: 500px;">
			<h2 class="hndle"><span><?php _e( 'Bid Attribution', 'art-in-heaven' ); ?></span></h2>
			<div class="inside">
				<div style="position: relative; height: 260px;">
					<canvas id="aih-attribution-chart"></canvas>
				</div>
			</div>
		</div>
	</div>

	<!-- Bidding Activity Timeline -->
	<div class="postbox" style="margin-top: 24px;">
		<h2 class="hndle"><span><?php _e( 'Bidding Activity Timeline', 'art-in-heaven' ); ?></span></h2>
		<div class="inside">
			<div style="position: relative; height: 260px;">
				<canvas id="aih-timeline-chart"></canvas>
			</div>
		</div>
	</div>

	<?php if ( AIH_Roles::can_view_financial() ) : ?>
	<!-- Top 10 by Revenue -->
	<?php if ( ! empty( $top_by_revenue ) ) : ?>
	<div class="postbox" style="margin-top: 24px;">
		<h2 class="hndle"><span><?php _e( 'Top 10 Art Pieces by Revenue', 'art-in-heaven' ); ?></span></h2>
		<div class="inside">
			<div style="position: relative; height: <?php echo max( 200, count( $top_by_revenue ) * 32 ); ?>px;">
				<canvas id="aih-top-revenue-chart"></canvas>
			</div>
		</div>
	</div>
	<?php endif; ?>
	<?php endif; ?>

	<!-- Countdown Urgency Board -->
	<?php
	$urgency_items = isset( $live_data['urgency'] ) ? $live_data['urgency'] : array();
	if ( ! empty( $urgency_items ) ) :
	?>
	<div class="postbox aih-urgency-board" id="aih-urgency-board" style="margin-top: 24px;">
		<h2 class="hndle"><span><?php _e( 'Ending Soon (< 2 hours)', 'art-in-heaven' ); ?></span></h2>
		<div class="inside">
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php _e( 'Art ID', 'art-in-heaven' ); ?></th>
						<th><?php _e( 'Title', 'art-in-heaven' ); ?></th>
						<th><?php _e( 'Time Left', 'art-in-heaven' ); ?></th>
						<th><?php _e( 'Bids', 'art-in-heaven' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $urgency_items as $item ) :
						$mins = (int) floor( $item['seconds_remaining'] / 60 );
						$hrs  = (int) floor( $mins / 60 );
						$rem  = $mins % 60;
						$time_str = $hrs > 0 ? sprintf( '%dh %dm', $hrs, $rem ) : sprintf( '%dm', $mins );
					?>
					<tr class="<?php echo $item['total_bids'] === 0 ? 'aih-urgency-zero' : ''; ?>">
						<td><?php echo esc_html( $item['art_id'] ); ?></td>
						<td><?php echo esc_html( $item['title'] ); ?></td>
						<td><?php echo esc_html( $time_str ); ?></td>
						<td><?php echo intval( $item['total_bids'] ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php endif; ?>

	<!-- Live Bid Feed -->
	<?php
	$bid_feed_items = isset( $live_data['bid_feed'] ) ? $live_data['bid_feed'] : array();
	if ( ! empty( $bid_feed_items ) ) :
	?>
	<div class="postbox aih-bid-feed" id="aih-bid-feed" style="margin-top: 24px;">
		<h2 class="hndle"><span><?php _e( 'Live Bid Feed', 'art-in-heaven' ); ?></span></h2>
		<div class="inside">
			<ul class="aih-bid-feed-list">
				<?php foreach ( $bid_feed_items as $entry ) : ?>
				<li>
					<span class="aih-feed-time"><?php echo esc_html( $entry['time_ago'] ); ?></span>
					<span class="aih-feed-bidder"><?php echo esc_html( $entry['bidder_masked'] ); ?></span>
					<?php _e( 'bid on', 'art-in-heaven' ); ?>
					<strong><?php echo esc_html( $entry['piece_title'] ); ?></strong>
					<?php if ( isset( $entry['amount'] ) ) : ?>
						<span class="aih-feed-amount">$<?php echo esc_html( number_format( floatval( $entry['amount'] ), 2 ) ); ?></span>
					<?php endif; ?>
				</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
	<?php endif; ?>

	<!-- Summary Tables -->
	<div class="aih-report-sections">
		<div class="aih-report-section">
			<h2>
				<?php _e( 'Art Piece Statistics', 'art-in-heaven' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=art-in-heaven-art' ) ); ?>" class="page-title-action"><?php _e( 'View All', 'art-in-heaven' ); ?></a>
			</h2>
			<table class="widefat">
				<tr><th><?php _e( 'Active Pieces', 'art-in-heaven' ); ?></th><td><?php echo intval( $stats->active_count ); ?></td></tr>
				<tr><th><?php _e( 'Draft Pieces', 'art-in-heaven' ); ?></th><td><?php echo intval( $stats->draft_count ); ?></td></tr>
				<tr><th><?php _e( 'Ended Pieces', 'art-in-heaven' ); ?></th><td><?php echo intval( $stats->ended_count ); ?></td></tr>
				<tr><th><?php _e( 'Pieces with Bids', 'art-in-heaven' ); ?></th><td><?php echo intval( $stats->pieces_with_bids ); ?></td></tr>
				<tr><th><?php _e( 'Total Starting Value', 'art-in-heaven' ); ?></th><td>$<?php echo number_format( floatval( $stats->total_starting_value ), 2 ); ?></td></tr>
				<?php if ( AIH_Roles::can_view_bids() ) : ?>
				<tr><th><?php _e( 'Highest Bid', 'art-in-heaven' ); ?></th><td>$<?php echo number_format( floatval( $stats->highest_bid ), 2 ); ?></td></tr>
				<tr><th><?php _e( 'Average Bid', 'art-in-heaven' ); ?></th><td>$<?php echo number_format( floatval( $stats->average_bid ), 2 ); ?></td></tr>
				<?php endif; ?>
			</table>
		</div>
		<?php if ( AIH_Roles::can_view_financial() ) : ?>
		<div class="aih-report-section">
			<h2>
				<?php _e( 'Payment Statistics', 'art-in-heaven' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=art-in-heaven-orders' ) ); ?>" class="page-title-action"><?php _e( 'View Orders', 'art-in-heaven' ); ?></a>
			</h2>
			<table class="widefat">
				<tr><th><?php _e( 'Total Orders', 'art-in-heaven' ); ?></th><td><?php echo intval( $payment_stats->total_orders ); ?></td></tr>
				<tr><th><?php _e( 'Paid Orders', 'art-in-heaven' ); ?></th><td><?php echo intval( $payment_stats->paid_orders ); ?></td></tr>
				<tr><th><?php _e( 'Pending Orders', 'art-in-heaven' ); ?></th><td><?php echo intval( $payment_stats->pending_orders ); ?></td></tr>
				<tr><th><?php _e( 'Total Collected', 'art-in-heaven' ); ?></th><td>$<?php echo number_format( floatval( $payment_stats->total_collected ), 2 ); ?></td></tr>
				<tr><th><?php _e( 'Total Pending', 'art-in-heaven' ); ?></th><td>$<?php echo number_format( floatval( $payment_stats->total_pending ), 2 ); ?></td></tr>
			</table>
		</div>
		<?php endif; ?>
	</div>

	<?php elseif ( $active_tab === 'revenue' ) : ?>
	<!-- ========== REVENUE TAB ========== -->
	<h2>
		<?php _e( 'Revenue & Payments', 'art-in-heaven' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=art-in-heaven-orders' ) ); ?>" class="page-title-action"><?php _e( 'View Orders', 'art-in-heaven' ); ?></a>
	</h2>

	<!-- Revenue Hero Cards -->
	<?php
	$total_revenue   = floatval( $payment_stats->total_collected ) + floatval( $payment_stats->total_pending );
	$collection_pct  = isset( $collection_rate->total_items ) && $collection_rate->total_items > 0
		? round( ( $collection_rate->paid_items / $collection_rate->total_items ) * 100 )
		: 0;

	AIH_Admin::open_stat_grid();
	AIH_Admin::render_stat_card( array(
		'value'   => '$' . number_format( $total_revenue, 2 ),
		'label'   => __( 'Total Revenue', 'art-in-heaven' ),
		'variant' => 'money',
	) );
	AIH_Admin::render_stat_card( array(
		'value'   => '$' . number_format( floatval( $payment_stats->total_collected ), 2 ),
		'label'   => __( 'Collected', 'art-in-heaven' ),
		'variant' => 'money',
		'link'    => admin_url( 'admin.php?page=art-in-heaven-orders&tab=paid' ),
	) );
	AIH_Admin::render_stat_card( array(
		'value'   => '$' . number_format( floatval( $payment_stats->total_pending ), 2 ),
		'label'   => __( 'Pending', 'art-in-heaven' ),
		'variant' => 'nobids',
		'link'    => admin_url( 'admin.php?page=art-in-heaven-orders&tab=pending' ),
	) );
	AIH_Admin::render_stat_card( array(
		'value'   => '$' . number_format( $projected_revenue, 2 ),
		'label'   => __( 'Projected (Active)', 'art-in-heaven' ),
		'sublabel' => __( 'sum of current highest bids', 'art-in-heaven' ),
	) );
	AIH_Admin::close_stat_grid();
	?>

	<?php
	AIH_Admin::open_stat_grid();
	AIH_Admin::render_stat_card( array(
		'value' => '$' . number_format( $avg_order_value, 2 ),
		'label' => __( 'Avg Order Value', 'art-in-heaven' ),
	) );
	AIH_Admin::render_stat_card( array(
		'value'   => $collection_pct . '%',
		'label'   => __( 'Collection Rate', 'art-in-heaven' ),
		'sublabel' => sprintf(
			/* translators: 1: paid items, 2: total items */
			__( '%1$d of %2$d items paid', 'art-in-heaven' ),
			isset( $collection_rate->paid_items ) ? $collection_rate->paid_items : 0,
			isset( $collection_rate->total_items ) ? $collection_rate->total_items : 0
		),
		'variant' => $collection_pct >= 80 ? 'active' : ( $collection_pct >= 50 ? 'bids' : 'nobids' ),
	) );
	AIH_Admin::render_stat_card( array(
		'value' => number_format( intval( $payment_stats->paid_orders ) ),
		'label' => __( 'Paid Orders', 'art-in-heaven' ),
	) );
	AIH_Admin::render_stat_card( array(
		'value'   => number_format( intval( $payment_stats->pending_orders ) ),
		'label'   => __( 'Pending Orders', 'art-in-heaven' ),
		'variant' => intval( $payment_stats->pending_orders ) > 0 ? 'nobids' : '',
	) );
	AIH_Admin::close_stat_grid();
	?>

	<!-- Revenue by Payment Method -->
	<?php if ( ! empty( $revenue_by_method ) ) : ?>
	<div class="aih-chart-row">
		<div class="postbox" style="max-width: 500px;">
			<h2 class="hndle"><span><?php _e( 'Revenue by Payment Method', 'art-in-heaven' ); ?></span></h2>
			<div class="inside">
				<div style="position: relative; height: 260px;">
					<canvas id="aih-revenue-method-chart"></canvas>
				</div>
			</div>
		</div>
		<div class="postbox" style="flex: 1;">
			<h2 class="hndle"><span><?php _e( 'Payment Method Breakdown', 'art-in-heaven' ); ?></span></h2>
			<div class="inside">
				<table class="widefat">
					<thead>
						<tr>
							<th><?php _e( 'Method', 'art-in-heaven' ); ?></th>
							<th><?php _e( 'Orders', 'art-in-heaven' ); ?></th>
							<th><?php _e( 'Amount', 'art-in-heaven' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $revenue_by_method as $method ) : ?>
						<tr>
							<td><strong><?php echo esc_html( ucfirst( $method->payment_method ?: __( 'Unknown', 'art-in-heaven' ) ) ); ?></strong></td>
							<td><?php echo intval( $method->order_count ); ?></td>
							<td>$<?php echo number_format( floatval( $method->method_total ), 2 ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<!-- Revenue by Art Piece -->
	<?php if ( ! empty( $revenue_by_piece ) ) : ?>
	<div class="aih-report-section" style="margin-top: 24px;">
		<h2><?php _e( 'Sold Art Pieces', 'art-in-heaven' ); ?></h2>
		<p class="description">
			<?php
			$total_uplift = 0;
			$uplift_count = 0;
			foreach ( $revenue_by_piece as $rp ) {
				if ( floatval( $rp->starting_bid ) > 0 ) {
					$total_uplift += ( floatval( $rp->sold_price ) - floatval( $rp->starting_bid ) ) / floatval( $rp->starting_bid ) * 100;
					$uplift_count++;
				}
			}
			$avg_uplift = $uplift_count > 0 ? round( $total_uplift / $uplift_count ) : 0;
			printf(
				/* translators: 1: count of sold pieces, 2: average uplift percentage */
				__( '%1$d pieces sold. Average uplift from starting bid: %2$d%%', 'art-in-heaven' ),
				count( $revenue_by_piece ),
				$avg_uplift
			);
			?>
		</p>
		<div class="aih-table-wrap">
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php _e( 'Art Piece', 'art-in-heaven' ); ?></th>
					<th style="width: 100px;"><?php _e( 'Art ID', 'art-in-heaven' ); ?></th>
					<th style="width: 120px;"><?php _e( 'Starting Bid', 'art-in-heaven' ); ?></th>
					<th style="width: 120px;"><?php _e( 'Sold Price', 'art-in-heaven' ); ?></th>
					<th style="width: 100px;"><?php _e( 'Uplift', 'art-in-heaven' ); ?></th>
					<th style="width: 100px;"><?php _e( 'Payment', 'art-in-heaven' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $revenue_by_piece as $rp ) :
					$start  = floatval( $rp->starting_bid );
					$sold   = floatval( $rp->sold_price );
					$uplift = $start > 0 ? round( ( $sold - $start ) / $start * 100 ) : 0;
				?>
				<tr>
					<td><?php echo esc_html( $rp->title ); ?></td>
					<td><code><?php echo esc_html( $rp->art_id ); ?></code></td>
					<td>$<?php echo number_format( $start, 2 ); ?></td>
					<td><strong>$<?php echo number_format( $sold, 2 ); ?></strong></td>
					<td>
						<?php if ( $uplift > 0 ) : ?>
							<span style="color: #065f46; font-weight: 600;">+<?php echo $uplift; ?>%</span>
						<?php elseif ( $uplift === 0 ) : ?>
							<span style="color: #6b7280;">0%</span>
						<?php else : ?>
							<span style="color: #991b1b;"><?php echo $uplift; ?>%</span>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $rp->payment_status === 'paid' ) : ?>
							<span class="aih-status-badge active"><?php _e( 'Paid', 'art-in-heaven' ); ?></span>
						<?php else : ?>
							<span class="aih-status-badge draft"><?php _e( 'Pending', 'art-in-heaven' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		</div>
	</div>
	<?php endif; ?>

	<?php elseif ( $active_tab === 'art-pieces' ) : ?>
	<!-- ========== ART PIECES TAB ========== -->
	<h2>
		<?php _e( 'Statistics by Tier (Active Art Pieces)', 'art-in-heaven' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=art-in-heaven-bids' ) ); ?>" class="page-title-action"><?php _e( 'View All Bids', 'art-in-heaven' ); ?></a>
	</h2>
	<p class="description"><?php _e( 'Click column headers to sort. Shows individual art pieces sorted by tier.', 'art-in-heaven' ); ?></p>

	<?php
	$active_pieces = array_filter( $art_pieces, function( $piece ) {
		return $piece->status === 'active' && $piece->seconds_remaining > 0;
	} );

	$unique_tiers = array();
	foreach ( $active_pieces as $piece ) {
		$t = ! empty( $piece->tier ) ? $piece->tier : __( 'No Tier', 'art-in-heaven' );
		$unique_tiers[ $t ] = true;
	}
	ksort( $unique_tiers );
	?>
	<div style="margin: 12px 0;">
		<label for="aih-tier-filter"><strong><?php _e( 'Filter by Tier:', 'art-in-heaven' ); ?></strong></label>
		<select id="aih-tier-filter" style="margin-left: 6px; min-width: 160px;">
			<option value=""><?php _e( 'All Tiers', 'art-in-heaven' ); ?></option>
			<?php foreach ( array_keys( $unique_tiers ) as $tier_option ) : ?>
				<option value="<?php echo esc_attr( $tier_option ); ?>"><?php echo esc_html( $tier_option ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>

	<?php
	$sorted_pieces = $active_pieces;
	usort( $sorted_pieces, function( $a, $b ) {
		$tier_a = ! empty( $a->tier ) ? $a->tier : 'ZZZ';
		$tier_b = ! empty( $b->tier ) ? $b->tier : 'ZZZ';
		$cmp    = strcmp( $tier_a, $tier_b );
		if ( $cmp !== 0 ) {
			return $cmp;
		}
		return strcmp( $a->art_id, $b->art_id );
	} );

	$active_bids_sum = 0;
	$tier_stats      = array();
	foreach ( $active_pieces as $piece ) {
		$tier = ! empty( $piece->tier ) ? $piece->tier : __( 'No Tier', 'art-in-heaven' );
		if ( ! isset( $tier_stats[ $tier ] ) ) {
			$tier_stats[ $tier ] = array( 'count' => 0, 'bids' => 0, 'bidders' => 0, 'value' => 0, 'with_bids' => 0 );
		}
		$tier_stats[ $tier ]['count']++;
		$tier_stats[ $tier ]['bids']    += $piece->total_bids;
		$tier_stats[ $tier ]['bidders'] += $piece->unique_bidders;
		$tier_stats[ $tier ]['value']   += floatval( $piece->current_bid ?: $piece->starting_bid );
		if ( $piece->total_bids > 0 ) {
			$tier_stats[ $tier ]['with_bids']++;
		}
		$active_bids_sum += $piece->total_bids;
	}
	?>

	<div class="aih-table-wrap">
	<table class="wp-list-table widefat fixed striped aih-tier-table" id="aih-tier-pivot">
		<thead>
			<tr>
				<th class="sortable" data-sort="tier" style="cursor:pointer; width: 100px;"><?php _e( 'Tier', 'art-in-heaven' ); ?> <span class="aih-sort-icon">&#8693;</span></th>
				<th class="sortable" data-sort="art_id" style="cursor:pointer; width: 80px;"><?php _e( 'Art ID', 'art-in-heaven' ); ?> <span class="aih-sort-icon">&#8693;</span></th>
				<th class="sortable" data-sort="title" style="cursor:pointer;"><?php _e( 'Title', 'art-in-heaven' ); ?> <span class="aih-sort-icon">&#8693;</span></th>
				<th style="width: 120px;"><?php _e( 'Artist', 'art-in-heaven' ); ?></th>
				<th class="sortable" data-sort="total_bids" style="cursor:pointer; width: 80px;"><?php _e( 'Bids', 'art-in-heaven' ); ?> <span class="aih-sort-icon">&#8693;</span></th>
				<th style="width: 80px;"><?php _e( 'With Bids', 'art-in-heaven' ); ?></th>
				<th class="sortable" data-sort="bid_rate" style="cursor:pointer; width: 90px;"><?php _e( 'Bid Rate', 'art-in-heaven' ); ?> <span class="aih-sort-icon">&#8693;</span></th>
				<th class="sortable" data-sort="unique_bidders" style="cursor:pointer; width: 90px;"><?php _e( 'Bidders', 'art-in-heaven' ); ?> <span class="aih-sort-icon">&#8693;</span></th>
				<th class="sortable" data-sort="current_bid" style="cursor:pointer; width: 100px;"><?php _e( 'Current Bid', 'art-in-heaven' ); ?> <span class="aih-sort-icon">&#8693;</span></th>
				<th class="sortable" data-sort="end_closing" style="cursor:pointer; width: 140px;"><?php _e( 'End Closing Time', 'art-in-heaven' ); ?> <span class="aih-sort-icon">&#8693;</span></th>
				<th style="width: 80px;"><?php _e( 'Status', 'art-in-heaven' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $sorted_pieces as $piece ) :
				$tier           = ! empty( $piece->tier ) ? $piece->tier : __( 'No Tier', 'art-in-heaven' );
				$piece_bid_rate = $active_bids_sum > 0 ? round( ( $piece->total_bids / $active_bids_sum ) * 100, 1 ) : 0;
			?>
			<tr data-tier="<?php echo esc_attr( $tier ); ?>"
				data-art_id="<?php echo esc_attr( $piece->art_id ); ?>"
				data-title="<?php echo esc_attr( strtolower( $piece->title ) ); ?>"
				data-total_bids="<?php echo intval( $piece->total_bids ); ?>"
				data-bid_rate="<?php echo esc_attr( $piece_bid_rate ); ?>"
				data-unique_bidders="<?php echo intval( $piece->unique_bidders ); ?>"
				data-current_bid="<?php echo floatval( $piece->current_bid ?: $piece->starting_bid ); ?>"
				data-end_closing="<?php echo esc_attr( $piece->auction_end ); ?>">
				<td><strong style="color: #1c1c1c;"><?php echo esc_html( $tier ); ?></strong></td>
				<td><code><?php echo esc_html( $piece->art_id ); ?></code></td>
				<td>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=art-in-heaven-art&stats=1&id=' . intval( $piece->id ) ) ); ?>">
						<?php echo esc_html( $piece->title ); ?>
					</a>
				</td>
				<td><small><?php echo esc_html( $piece->artist ); ?></small></td>
				<td>
					<span style="font-weight: 600; color: <?php echo $piece->total_bids > 0 ? '#4a7c59' : '#8a8a8a'; ?>;">
						<?php echo intval( $piece->total_bids ); ?>
					</span>
				</td>
				<td>
					<?php if ( $piece->total_bids > 0 ) : ?>
						<span style="color: #4a7c59; font-weight: 600;"><?php _e( 'Yes', 'art-in-heaven' ); ?></span>
					<?php else : ?>
						<span style="color: #a63d40;"><?php _e( 'No', 'art-in-heaven' ); ?></span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $piece_bid_rate > 0 ) : ?>
						<span style="font-weight: 600;"><?php echo esc_html( $piece_bid_rate ); ?>%</span>
					<?php else : ?>
						<span style="color: #9ca3af;">0%</span>
					<?php endif; ?>
				</td>
				<td><?php echo intval( $piece->unique_bidders ); ?></td>
				<td><strong>$<?php echo number_format( floatval( $piece->current_bid ?: $piece->starting_bid ), 0 ); ?></strong></td>
				<td>
					<small><?php echo esc_html( AIH_Status::format_db_date( $piece->auction_end, 'M j, g:i A' ) ); ?></small>
				</td>
				<td>
					<?php if ( $piece->status === 'active' && $piece->seconds_remaining > 0 ) : ?>
					<span class="aih-status-badge active"><?php _e( 'Active', 'art-in-heaven' ); ?></span>
					<?php elseif ( $piece->status === 'draft' ) : ?>
					<span class="aih-status-badge draft"><?php _e( 'Draft', 'art-in-heaven' ); ?></span>
					<?php else : ?>
					<span class="aih-status-badge ended"><?php _e( 'Ended', 'art-in-heaven' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	</div>

	<!-- Tier Summary -->
	<h2 style="margin-top: 30px;"><?php _e( 'Tier Summary', 'art-in-heaven' ); ?></h2>
	<div class="aih-table-wrap">
	<table class="wp-list-table widefat fixed striped" style="max-width: 900px;">
		<thead>
			<tr>
				<th><?php _e( 'Tier', 'art-in-heaven' ); ?></th>
				<th><?php _e( 'Pieces', 'art-in-heaven' ); ?></th>
				<th><?php _e( 'With Bids', 'art-in-heaven' ); ?></th>
				<th><?php _e( 'Total Bids', 'art-in-heaven' ); ?></th>
				<th><?php _e( 'Bid Rate', 'art-in-heaven' ); ?></th>
				<th><?php _e( 'Total Value', 'art-in-heaven' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			ksort( $tier_stats );
			foreach ( $tier_stats as $tier => $tier_data ) :
				$tier_bid_rate = $active_bids_sum > 0 ? round( ( $tier_data['bids'] / $active_bids_sum ) * 100, 1 ) : 0;
			?>
			<tr>
				<td><strong><?php echo esc_html( $tier ); ?></strong></td>
				<td><?php echo intval( $tier_data['count'] ); ?></td>
				<td>
					<?php if ( $tier_data['with_bids'] > 0 ) : ?>
						<span style="color: #4a7c59; font-weight: 600;"><?php echo intval( $tier_data['with_bids'] ); ?> <?php _e( 'Yes', 'art-in-heaven' ); ?></span>
					<?php else : ?>
						<span style="color: #a63d40;"><?php _e( 'No', 'art-in-heaven' ); ?></span>
					<?php endif; ?>
					<span style="color: #9ca3af;"> / <?php echo intval( $tier_data['count'] - $tier_data['with_bids'] ); ?> <?php _e( 'No', 'art-in-heaven' ); ?></span>
				</td>
				<td style="font-weight: 600;"><?php echo intval( $tier_data['bids'] ); ?></td>
				<td>
					<div style="display: flex; align-items: center; gap: 8px;">
						<div style="flex: 1; max-width: 100px; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
							<div style="width: <?php echo esc_attr( $tier_bid_rate ); ?>%; height: 100%; background: #b8956b;"></div>
						</div>
						<span style="min-width: 45px;"><?php echo esc_html( $tier_bid_rate ); ?>%</span>
					</div>
				</td>
				<td>$<?php echo number_format( floatval( $tier_data['value'] ), 0 ); ?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	</div>

	<!-- Bid Distribution -->
	<?php if ( ! empty( $bid_distribution ) ) : ?>
	<div class="postbox" style="margin-top: 24px;">
		<h2 class="hndle"><span><?php _e( 'Bid Distribution by Amount', 'art-in-heaven' ); ?></span></h2>
		<div class="inside">
			<div style="position: relative; height: 260px;">
				<canvas id="aih-bid-distribution-chart"></canvas>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<!-- All Art Pieces table -->
	<h2 style="margin-top: 30px;"><?php _e( 'All Art Pieces', 'art-in-heaven' ); ?></h2>
	<div class="aih-table-wrap">
	<table class="wp-list-table widefat fixed striped aih-stats-table">
		<thead>
			<tr>
				<th class="aih-col-title"><?php _e( 'Art Piece', 'art-in-heaven' ); ?></th>
				<th class="aih-col-id"><?php _e( 'ID', 'art-in-heaven' ); ?></th>
				<th><?php _e( 'Status', 'art-in-heaven' ); ?></th>
				<th class="aih-col-stat"><?php _e( 'Unique Bidders', 'art-in-heaven' ); ?></th>
				<th class="aih-col-stat"><?php _e( 'Total Bids', 'art-in-heaven' ); ?></th>
				<th class="aih-col-stat"><?php _e( 'With Bids', 'art-in-heaven' ); ?></th>
				<th class="aih-col-stat"><?php _e( 'Bid Rate', 'art-in-heaven' ); ?></th>
				<th class="aih-col-stat"><?php _e( 'Time Since Last Bid', 'art-in-heaven' ); ?></th>
				<th class="aih-col-stat"><?php _e( 'Current Bid', 'art-in-heaven' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $art_pieces ) ) : ?>
				<tr><td colspan="9"><?php _e( 'No art pieces found.', 'art-in-heaven' ); ?></td></tr>
			<?php else : ?>
				<?php
				foreach ( $art_pieces as $piece ) :
					$piece_bid_rate = $total_bids_sum > 0 ? round( ( $piece->total_bids / $total_bids_sum ) * 100, 1 ) : 0;
				?>
					<tr>
						<td class="aih-col-title">
							<strong><?php echo esc_html( $piece->title ); ?></strong>
							<br><small><?php echo esc_html( $piece->artist ); ?></small>
						</td>
						<td class="aih-col-id"><code><?php echo esc_html( $piece->art_id ); ?></code></td>
						<td>
							<?php if ( $piece->status === 'active' && $piece->seconds_remaining > 0 ) : ?>
								<span class="aih-status-badge active"><?php _e( 'Active', 'art-in-heaven' ); ?></span>
							<?php else : ?>
								<span class="aih-status-badge ended"><?php _e( 'Ended', 'art-in-heaven' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="aih-col-stat">
							<span class="aih-stat-value <?php echo $piece->unique_bidders > 0 ? 'has-bids' : ''; ?>">
								<?php echo intval( $piece->unique_bidders ); ?>
							</span>
						</td>
						<td class="aih-col-stat">
							<span class="aih-stat-value <?php echo $piece->total_bids > 0 ? 'has-bids' : ''; ?>">
								<?php echo intval( $piece->total_bids ); ?>
							</span>
						</td>
						<td class="aih-col-stat">
							<?php if ( $piece->total_bids > 0 ) : ?>
								<span style="color: #4a7c59; font-weight: 600;"><?php _e( 'Yes', 'art-in-heaven' ); ?></span>
							<?php else : ?>
								<span style="color: #a63d40;"><?php _e( 'No', 'art-in-heaven' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="aih-col-stat">
							<?php if ( $piece_bid_rate > 0 ) : ?>
								<span style="font-weight: 600;"><?php echo esc_html( $piece_bid_rate ); ?>%</span>
							<?php else : ?>
								<span style="color: #9ca3af;">0%</span>
							<?php endif; ?>
						</td>
						<td class="aih-col-stat">
							<?php
							if ( $piece->last_bid_time ) {
								echo '<span class="aih-time-ago">';
								$bid_dt = new DateTime( $piece->last_bid_time, wp_timezone() );
								$now_dt = new DateTime( 'now', wp_timezone() );
								echo esc_html( human_time_diff( $bid_dt->getTimestamp(), $now_dt->getTimestamp() ) );
								echo ' ' . esc_html__( 'ago', 'art-in-heaven' );
								echo '</span>';
							} else {
								echo '<span class="aih-no-bids">' . esc_html__( 'No bids yet', 'art-in-heaven' ) . '</span>';
							}
							?>
						</td>
						<td class="aih-col-stat">
							<span class="aih-bid-amount">$<?php echo number_format( floatval( $piece->current_bid ), 2 ); ?></span>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	</div>

	<div class="aih-export-section">
		<h2><?php _e( 'Export Data', 'art-in-heaven' ); ?></h2>
		<p><?php _e( 'Export engagement data for further analysis:', 'art-in-heaven' ); ?></p>
		<button type="button" id="aih-export-csv" class="button">
			<span class="dashicons dashicons-download"></span>
			<?php _e( 'Export to CSV', 'art-in-heaven' ); ?>
		</button>
	</div>

	<?php elseif ( $active_tab === 'bidders' ) : ?>
	<!-- ========== BIDDERS TAB ========== -->
	<h2>
		<?php _e( 'Bidder Engagement', 'art-in-heaven' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=art-in-heaven-bidders' ) ); ?>" class="page-title-action"><?php _e( 'View All Registrants', 'art-in-heaven' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=art-in-heaven-bids' ) ); ?>" class="page-title-action"><?php _e( 'View All Bids', 'art-in-heaven' ); ?></a>
	</h2>

	<!-- Bidder funnel cards will use data from render function -->
	<?php if ( isset( $registrant_counts ) ) : ?>
	<?php
	AIH_Admin::open_stat_grid();
	AIH_Admin::render_stat_card( array(
		'value' => number_format( intval( $registrant_counts->total ) ),
		'label' => __( 'Registered', 'art-in-heaven' ),
		'link'  => admin_url( 'admin.php?page=art-in-heaven-bidders&tab=all' ),
	) );
	AIH_Admin::render_stat_card( array(
		'value' => number_format( intval( $registrant_counts->logged_in ) ),
		'label' => __( 'Logged In', 'art-in-heaven' ),
		'link'  => admin_url( 'admin.php?page=art-in-heaven-bidders&tab=all' ),
	) );
	AIH_Admin::render_stat_card( array(
		'value'   => number_format( intval( $registrant_counts->has_bids ) ),
		'label'   => __( 'Placed Bids', 'art-in-heaven' ),
		'variant' => 'bids',
		'link'    => admin_url( 'admin.php?page=art-in-heaven-bidders&tab=logged_in_has_bids' ),
	) );
	AIH_Admin::close_stat_grid();
	?>
	<?php endif; ?>

	<!-- Conversion Funnel -->
	<?php if ( isset( $registrant_counts ) && $registrant_counts->total > 0 ) : ?>
	<div class="aih-chart-row">
		<div class="postbox" style="max-width: 700px;">
			<h2 class="hndle"><span><?php _e( 'Conversion Funnel', 'art-in-heaven' ); ?></span></h2>
			<div class="inside">
				<div style="position: relative; height: 260px;">
					<canvas id="aih-conversion-funnel-chart"></canvas>
				</div>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<h2><?php _e( 'Bidder Engagement Comparison', 'art-in-heaven' ); ?></h2>
	<p class="description"><?php _e( 'Compares engagement patterns between push-enabled and non-push bidders.', 'art-in-heaven' ); ?></p>

	<!-- Comparison Chart -->
	<div class="aih-chart-row">
		<div class="postbox" style="max-width: 600px;">
			<h2 class="hndle"><span><?php _e( 'Push vs Non-Push Comparison', 'art-in-heaven' ); ?></span></h2>
			<div class="inside">
				<div style="position: relative; height: 260px;">
					<canvas id="aih-bidder-comparison-chart"></canvas>
				</div>
			</div>
		</div>
	</div>

	<!-- Top Bidders Table -->
	<h2 style="margin-top: 30px;"><?php _e( 'Top Bidders by Activity', 'art-in-heaven' ); ?></h2>
	<div class="aih-table-wrap">
	<table class="wp-list-table widefat fixed striped" style="max-width: 900px;">
		<thead>
			<tr>
				<th style="width: 60px;">#</th>
				<th><?php _e( 'Bidder', 'art-in-heaven' ); ?></th>
				<th style="width: 100px;"><?php _e( 'Total Bids', 'art-in-heaven' ); ?></th>
				<th style="width: 120px;"><?php _e( 'Items Bid On', 'art-in-heaven' ); ?></th>
				<th style="width: 130px;"><?php _e( 'Last Bid', 'art-in-heaven' ); ?></th>
				<th style="width: 100px;"><?php _e( 'Push Enabled', 'art-in-heaven' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			$bidder_data = isset( $engagement_metrics['bidder_engagement'] ) ? $engagement_metrics['bidder_engagement'] : array();
			if ( empty( $bidder_data ) ) :
			?>
				<tr><td colspan="6"><?php _e( 'No bidder data available yet.', 'art-in-heaven' ); ?></td></tr>
			<?php else : ?>
				<?php
				$rank = 0;
				foreach ( $bidder_data as $bidder ) :
					$rank++;
				?>
				<tr>
					<td><?php echo intval( $rank ); ?></td>
					<td><code><?php echo esc_html( substr( $bidder->bidder_id, 0, 4 ) . '****' ); ?></code></td>
					<td style="font-weight: 600;"><?php echo intval( $bidder->total_bids ); ?></td>
					<td><?php echo intval( $bidder->pieces_bid_on ); ?></td>
					<td>
						<?php
						if ( $bidder->last_bid_time ) {
							echo '<span class="aih-time-ago">';
							echo esc_html( human_time_diff( strtotime( $bidder->last_bid_time ), time() ) );
							echo ' ' . esc_html__( 'ago', 'art-in-heaven' );
							echo '</span>';
						}
						?>
					</td>
					<td>
						<?php if ( $bidder->has_push ) : ?>
							<span style="color: #4a7c59; font-weight: 600;">&#10003; <?php _e( 'Yes', 'art-in-heaven' ); ?></span>
						<?php else : ?>
							<span style="color: #8a8a8a;"><?php _e( 'No', 'art-in-heaven' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	</div>

	<!-- Engagement vs Pressure Signals -->
	<div class="postbox" style="margin-top: 24px;">
		<h2 class="hndle"><span><?php _e( 'Engagement vs Pressure Signals', 'art-in-heaven' ); ?></span></h2>
		<div class="inside">
			<table class="wp-list-table widefat fixed striped" style="max-width: 900px;">
				<thead>
					<tr>
						<th style="width: 200px;"><?php _e( 'Signal', 'art-in-heaven' ); ?></th>
						<th colspan="2"><?php _e( 'Value', 'art-in-heaven' ); ?></th>
						<th><?php _e( 'Interpretation', 'art-in-heaven' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong><?php _e( 'Bid Breadth (Push)', 'art-in-heaven' ); ?></strong></td>
						<td colspan="2">
							<?php
							$push_breadth    = isset( $engagement_metrics['push_bidder_breadth'] ) ? floatval( $engagement_metrics['push_bidder_breadth'] ) : 0;
							$nonpush_breadth = isset( $engagement_metrics['nonpush_bidder_breadth'] ) ? floatval( $engagement_metrics['nonpush_bidder_breadth'] ) : 0;
							/* translators: 1: push breadth, 2: non-push breadth */
							printf( __( '%1$s items (push) vs %2$s items (no push)', 'art-in-heaven' ), esc_html( $push_breadth ), esc_html( $nonpush_breadth ) );
							?>
						</td>
						<td>
							<?php
							if ( $push_breadth > 0 && $nonpush_breadth > 0 ) {
								if ( $push_breadth > $nonpush_breadth * 1.2 ) {
									_e( 'Push users explore more items — notifications drive discovery', 'art-in-heaven' );
								} elseif ( $nonpush_breadth > $push_breadth * 1.2 ) {
									_e( 'Non-push users explore more — push may narrow focus', 'art-in-heaven' );
								} else {
									_e( 'Similar breadth — push does not significantly change exploration', 'art-in-heaven' );
								}
							} else {
								_e( 'Insufficient data', 'art-in-heaven' );
							}
							?>
						</td>
					</tr>
					<tr>
						<td><strong><?php _e( 'Bid Depth (Push)', 'art-in-heaven' ); ?></strong></td>
						<td colspan="2">
							<?php
							$push_depth    = isset( $engagement_metrics['push_bidder_depth'] ) ? floatval( $engagement_metrics['push_bidder_depth'] ) : 0;
							$nonpush_depth = isset( $engagement_metrics['nonpush_bidder_depth'] ) ? floatval( $engagement_metrics['nonpush_bidder_depth'] ) : 0;
							/* translators: 1: push depth, 2: non-push depth */
							printf( __( '%1$s bids (push) vs %2$s bids (no push)', 'art-in-heaven' ), esc_html( $push_depth ), esc_html( $nonpush_depth ) );
							?>
						</td>
						<td>
							<?php
							if ( $push_depth > 0 && $nonpush_depth > 0 ) {
								if ( $push_depth > $nonpush_depth * 1.2 ) {
									_e( 'Push users bid more frequently — notifications drive urgency', 'art-in-heaven' );
								} elseif ( $nonpush_depth > $push_depth * 1.2 ) {
									_e( 'Non-push users bid more — push may create complacency', 'art-in-heaven' );
								} else {
									_e( 'Similar depth — push does not significantly increase bid frequency', 'art-in-heaven' );
								}
							} else {
								_e( 'Insufficient data', 'art-in-heaven' );
							}
							?>
						</td>
					</tr>
					<tr>
						<td><strong><?php _e( 'Push Opt-in Rate', 'art-in-heaven' ); ?></strong></td>
						<td colspan="2">
							<?php
							$granted         = isset( $funnel['permission_granted'] ) ? $funnel['permission_granted'] : 0;
							$denied          = isset( $funnel['permission_denied'] ) ? $funnel['permission_denied'] : 0;
							$total_decisions = $granted + $denied;
							$opt_in_rate     = $total_decisions > 0 ? round( ( $granted / $total_decisions ) * 100 ) : 0;
							/* translators: 1: opt-in percentage, 2: granted count, 3: total decisions */
							printf( __( '%1$d%% (%2$d granted / %3$d total decisions)', 'art-in-heaven' ), $opt_in_rate, $granted, $total_decisions );
							?>
						</td>
						<td>
							<?php
							if ( $total_decisions > 0 ) {
								if ( $opt_in_rate >= 70 ) {
									_e( 'High opt-in — users see value in notifications', 'art-in-heaven' );
								} elseif ( $opt_in_rate >= 40 ) {
									_e( 'Moderate opt-in — consider timing/messaging', 'art-in-heaven' );
								} else {
									_e( 'Low opt-in — users may find notifications intrusive', 'art-in-heaven' );
								}
							} else {
								_e( 'No permission decisions recorded yet', 'art-in-heaven' );
							}
							?>
						</td>
					</tr>
					<tr>
						<td><strong><?php _e( 'Notification Click Rate', 'art-in-heaven' ); ?></strong></td>
						<td colspan="2">
							<?php
							$delivered  = isset( $funnel['push_delivered'] ) ? $funnel['push_delivered'] : 0;
							$clicked    = isset( $funnel['push_clicked'] ) ? $funnel['push_clicked'] : 0;
							$click_rate = $delivered > 0 ? round( ( $clicked / $delivered ) * 100 ) : 0;
							/* translators: 1: click percentage, 2: click count, 3: delivered count */
							printf( __( '%1$d%% (%2$d clicks / %3$d delivered)', 'art-in-heaven' ), $click_rate, $clicked, $delivered );
							?>
						</td>
						<td>
							<?php
							if ( $delivered > 0 ) {
								if ( $click_rate >= 40 ) {
									_e( 'High click rate — notifications are relevant and useful', 'art-in-heaven' );
								} elseif ( $click_rate >= 15 ) {
									_e( 'Healthy click rate — above industry average', 'art-in-heaven' );
								} else {
									_e( 'Low click rate — notifications may not be compelling', 'art-in-heaven' );
								}
							} else {
								_e( 'No notifications delivered yet', 'art-in-heaven' );
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<?php elseif ( $active_tab === 'notifications' ) : ?>
	<!-- ========== NOTIFICATIONS TAB ========== -->
	<h2>
		<?php _e( 'Push Notification Analytics', 'art-in-heaven' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=art-in-heaven-bidders' ) ); ?>" class="page-title-action"><?php _e( 'View Registrants', 'art-in-heaven' ); ?></a>
	</h2>

	<?php
	AIH_Admin::open_stat_grid();
	AIH_Admin::render_stat_card( array(
		'value' => number_format( isset( $funnel['push_sent'] ) ? intval( $funnel['push_sent'] ) : 0 ),
		'label' => __( 'Notifications Sent', 'art-in-heaven' ),
	) );
	AIH_Admin::render_stat_card( array(
		'value' => number_format( isset( $funnel['push_delivered'] ) ? intval( $funnel['push_delivered'] ) : 0 ),
		'label' => __( 'Delivered', 'art-in-heaven' ),
	) );
	AIH_Admin::render_stat_card( array(
		'value' => number_format( isset( $funnel['push_clicked'] ) ? intval( $funnel['push_clicked'] ) : 0 ),
		'label' => __( 'Clicked', 'art-in-heaven' ),
	) );
	AIH_Admin::render_stat_card( array(
		'value' => number_format( isset( $funnel['push_expired'] ) ? intval( $funnel['push_expired'] ) : 0 ),
		'label' => __( 'Expired/Failed', 'art-in-heaven' ),
	) );
	AIH_Admin::close_stat_grid();
	?>

	<!-- Notification Charts -->
	<div class="aih-chart-row">
		<div class="postbox" style="max-width: 600px;">
			<h2 class="hndle"><span><?php _e( 'Delivery Funnel', 'art-in-heaven' ); ?></span></h2>
			<div class="inside">
				<div style="position: relative; height: 260px;">
					<canvas id="aih-notif-funnel-chart"></canvas>
				</div>
			</div>
		</div>
		<div class="postbox" style="max-width: 600px;">
			<h2 class="hndle"><span><?php _e( 'By Notification Type', 'art-in-heaven' ); ?></span></h2>
			<div class="inside">
				<div style="position: relative; height: 260px;">
					<canvas id="aih-notif-type-chart"></canvas>
				</div>
			</div>
		</div>
	</div>

	<!-- Permission Decision Sources -->
	<h2 style="margin-top: 30px;"><?php _e( 'Permission Decision Sources', 'art-in-heaven' ); ?></h2>
	<p class="description"><?php _e( 'Where users were prompted to enable/disable notifications.', 'art-in-heaven' ); ?></p>
	<div class="aih-table-wrap">
	<table class="wp-list-table widefat fixed striped" style="max-width: 600px;">
		<thead>
			<tr>
				<th><?php _e( 'Source', 'art-in-heaven' ); ?></th>
				<th><?php _e( 'Granted', 'art-in-heaven' ); ?></th>
				<th><?php _e( 'Denied', 'art-in-heaven' ); ?></th>
				<th><?php _e( 'Opt-in Rate', 'art-in-heaven' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			$perm_by_source     = array();
			$permission_sources = isset( $engagement_metrics['permission_sources'] ) ? $engagement_metrics['permission_sources'] : array();
			foreach ( $permission_sources as $row ) {
				$src = $row->source;
				if ( ! isset( $perm_by_source[ $src ] ) ) {
					$perm_by_source[ $src ] = array( 'granted' => 0, 'denied' => 0 );
				}
				if ( $row->event_type === 'push_permission_granted' ) {
					$perm_by_source[ $src ]['granted'] = (int) $row->cnt;
				} else {
					$perm_by_source[ $src ]['denied'] = (int) $row->cnt;
				}
			}

			$source_labels = array(
				'bell'      => __( 'Bell Icon', 'art-in-heaven' ),
				'after_bid' => __( 'After Placing Bid', 'art-in-heaven' ),
			);

			if ( empty( $perm_by_source ) ) :
			?>
				<tr><td colspan="4"><?php _e( 'No permission decisions recorded yet.', 'art-in-heaven' ); ?></td></tr>
			<?php else : ?>
				<?php
				foreach ( $perm_by_source as $src => $counts ) :
					$total_src = $counts['granted'] + $counts['denied'];
					$rate      = $total_src > 0 ? round( ( $counts['granted'] / $total_src ) * 100 ) : 0;
				?>
				<tr>
					<td><strong><?php echo esc_html( isset( $source_labels[ $src ] ) ? $source_labels[ $src ] : $src ); ?></strong></td>
					<td style="color: #4a7c59; font-weight: 600;"><?php echo intval( $counts['granted'] ); ?></td>
					<td style="color: #a63d40;"><?php echo intval( $counts['denied'] ); ?></td>
					<td>
						<div style="display: flex; align-items: center; gap: 8px;">
							<div style="flex: 1; max-width: 80px; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
								<div style="width: <?php echo esc_attr( $rate ); ?>%; height: 100%; background: #4a7c59;"></div>
							</div>
							<span><?php echo intval( $rate ); ?>%</span>
						</div>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	</div>

	<!-- Notification Type Breakdown -->
	<h2 style="margin-top: 30px;"><?php _e( 'Notification Type Breakdown', 'art-in-heaven' ); ?></h2>
	<div class="aih-table-wrap">
	<table class="wp-list-table widefat fixed striped" style="max-width: 700px;">
		<thead>
			<tr>
				<th><?php _e( 'Type', 'art-in-heaven' ); ?></th>
				<th><?php _e( 'Sent', 'art-in-heaven' ); ?></th>
				<th><?php _e( 'Delivered', 'art-in-heaven' ); ?></th>
				<th><?php _e( 'Clicked', 'art-in-heaven' ); ?></th>
				<th><?php _e( 'Click Rate', 'art-in-heaven' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $notif_breakdown ) ) : ?>
				<tr><td colspan="5"><?php _e( 'No notification data yet.', 'art-in-heaven' ); ?></td></tr>
			<?php else : ?>
				<?php
				foreach ( $notif_breakdown as $nt => $counts ) :
					$cr = $counts['delivered'] > 0 ? round( ( $counts['clicked'] / $counts['delivered'] ) * 100 ) : 0;
				?>
				<tr>
					<td><strong><?php echo esc_html( isset( $type_labels[ $nt ] ) ? $type_labels[ $nt ] : ucfirst( $nt ) ); ?></strong></td>
					<td><?php echo intval( $counts['sent'] ); ?></td>
					<td><?php echo intval( $counts['delivered'] ); ?></td>
					<td style="font-weight: 600;"><?php echo intval( $counts['clicked'] ); ?></td>
					<td>
						<div style="display: flex; align-items: center; gap: 8px;">
							<div style="flex: 1; max-width: 80px; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
								<div style="width: <?php echo esc_attr( $cr ); ?>%; height: 100%; background: #b8956b;"></div>
							</div>
							<span><?php echo intval( $cr ); ?>%</span>
						</div>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	</div>

	<?php elseif ( $active_tab === 'export' ) : ?>
	<!-- ========== EXPORT TAB ========== -->

	<!-- CSV Exports -->
	<div class="aih-report-section">
		<h2><?php _e( 'Export as CSV', 'art-in-heaven' ); ?></h2>
		<p><?php _e( 'Download auction data as CSV files for spreadsheet analysis.', 'art-in-heaven' ); ?></p>
		<p>
			<button type="button" class="button aih-csv-export-btn" data-type="winners" data-format="csv">
				<span class="dashicons dashicons-download"></span>
				<?php _e( 'Winners List', 'art-in-heaven' ); ?>
			</button>
			<button type="button" class="button aih-csv-export-btn" data-type="financial" data-format="csv">
				<span class="dashicons dashicons-download"></span>
				<?php _e( 'Financial Summary', 'art-in-heaven' ); ?>
			</button>
			<button type="button" class="button aih-csv-export-btn" data-type="bidders" data-format="csv">
				<span class="dashicons dashicons-download"></span>
				<?php _e( 'Bidder List', 'art-in-heaven' ); ?>
			</button>
			<button type="button" class="button aih-csv-export-btn" data-type="bids" data-format="csv">
				<span class="dashicons dashicons-download"></span>
				<?php _e( 'Bid History', 'art-in-heaven' ); ?>
			</button>
		</p>
	</div>

	<!-- JSON Exports -->
	<div class="aih-report-section">
		<h2><?php _e( 'Export as JSON (Backup)', 'art-in-heaven' ); ?></h2>
		<p><?php _e( 'Download auction data as JSON files for backup or analysis.', 'art-in-heaven' ); ?></p>
		<p>
			<button type="button" class="button aih-export-btn" data-type="art"><?php _e( 'Export Art Pieces', 'art-in-heaven' ); ?></button>
			<button type="button" class="button aih-export-btn" data-type="bids"><?php _e( 'Export Bids', 'art-in-heaven' ); ?></button>
			<button type="button" class="button aih-export-btn" data-type="bidders"><?php _e( 'Export Bidders', 'art-in-heaven' ); ?></button>
			<button type="button" class="button aih-export-btn" data-type="orders"><?php _e( 'Export Orders', 'art-in-heaven' ); ?></button>
		</p>
	</div>

	<!-- Top 10 Pieces -->
	<div class="aih-report-section">
		<h2>
			<?php _e( 'Top 10 Art Pieces by Bids', 'art-in-heaven' ); ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', 'art-pieces' ) ); ?>" class="page-title-action"><?php _e( 'View All', 'art-in-heaven' ); ?></a>
		</h2>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php _e( 'Title', 'art-in-heaven' ); ?></th>
					<th><?php _e( 'Artist', 'art-in-heaven' ); ?></th>
					<th><?php _e( 'Bids', 'art-in-heaven' ); ?></th>
					<th><?php _e( 'Highest Bid', 'art-in-heaven' ); ?></th>
					<th><?php _e( 'Starting Bid', 'art-in-heaven' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $stats->top_pieces ) ) : ?>
					<?php foreach ( $stats->top_pieces as $piece ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=art-in-heaven-add&edit=' . intval( $piece->id ) ) ); ?>"><?php echo esc_html( $piece->title ); ?></a></td>
						<td><?php echo esc_html( $piece->artist ); ?></td>
						<td><?php echo intval( $piece->bid_count ); ?></td>
						<td>$<?php echo number_format( floatval( $piece->highest_bid ?: 0 ), 2 ); ?></td>
						<td>$<?php echo number_format( floatval( $piece->starting_bid ), 2 ); ?></td>
					</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="5"><?php _e( 'No art pieces with bids yet.', 'art-in-heaven' ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<?php endif; ?>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {

	// ===== Art Pieces tab: tier filter + sorting =====
	$('#aih-tier-filter').on('change', function() {
		var selected = $(this).val();
		$('#aih-tier-pivot tbody tr').each(function() {
			if (!selected || $(this).attr('data-tier') === selected) {
				$(this).show();
			} else {
				$(this).hide();
			}
		});
	});

	var $tierTable = $('#aih-tier-pivot');
	var $tierTbody = $tierTable.find('tbody');
	var currentSort = { col: null, dir: 'asc' };

	$tierTable.find('th.sortable').on('click', function() {
		var $th = $(this);
		var sortKey = $th.data('sort');
		if (currentSort.col === sortKey) {
			currentSort.dir = currentSort.dir === 'asc' ? 'desc' : 'asc';
		} else {
			currentSort.col = sortKey;
			currentSort.dir = 'asc';
		}
		$tierTable.find('th.sortable').removeClass('sorted-asc sorted-desc');
		$th.addClass('sorted-' + currentSort.dir);

		var $rows = $tierTbody.find('tr').get();
		$rows.sort(function(a, b) {
			var aVal = $(a).data(sortKey);
			var bVal = $(b).data(sortKey);
			if (sortKey === 'tier' || sortKey === 'art_id' || sortKey === 'title' || sortKey === 'end_closing') {
				aVal = String(aVal || '').toLowerCase();
				bVal = String(bVal || '').toLowerCase();
				if (aVal < bVal) return currentSort.dir === 'asc' ? -1 : 1;
				if (aVal > bVal) return currentSort.dir === 'asc' ? 1 : -1;
				return 0;
			} else {
				aVal = parseFloat(aVal) || 0;
				bVal = parseFloat(bVal) || 0;
				return currentSort.dir === 'asc' ? aVal - bVal : bVal - aVal;
			}
		});
		$.each($rows, function(i, row) { $tierTbody.append(row); });
	});

	// ===== CSV Export (Art Pieces tab) =====
	$('#aih-export-csv').on('click', function() {
		var data = [];
		data.push(['Art ID', 'Title', 'Artist', 'Tier', 'Status', 'Unique Bidders', 'Total Bids', 'Current Bid', 'End Closing Time']);
		$('#aih-tier-pivot tbody tr').each(function() {
			var $row = $(this);
			data.push([
				$row.data('art_id'),
				$row.find('td:eq(2) a').text().trim(),
				$row.find('td:eq(3)').text().trim(),
				$row.data('tier'),
				$row.find('.aih-status-badge').text().trim(),
				$row.data('unique_bidders'),
				$row.data('total_bids'),
				$row.data('current_bid'),
				$row.data('end_closing') || ''
			]);
		});
		var csv = data.map(function(row) {
			return row.map(function(cell) {
				return '"' + String(cell).replace(/"/g, '""') + '"';
			}).join(',');
		}).join('\n');
		var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
		var link = document.createElement('a');
		link.href = URL.createObjectURL(blob);
		link.download = 'art-in-heaven-analytics-art-pieces-' + new Date().toISOString().slice(0, 10) + '.csv';
		link.click();
	});

	// ===== CSV Export (Export tab) =====
	$('.aih-csv-export-btn').on('click', function() {
		var type = $(this).data('type');
		var $btn = $(this).prop('disabled', true);
		var originalText = $btn.text();
		$btn.text('<?php echo esc_js( __( 'Exporting...', 'art-in-heaven' ) ); ?>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'aih_admin_export_data',
				nonce: '<?php echo esc_js( wp_create_nonce( 'aih_admin_nonce' ) ); ?>',
				type: type,
				format: 'csv'
			},
			success: function(response) {
				if (response.success && response.data.data) {
					var blob = new Blob([response.data.data], { type: 'text/csv;charset=utf-8;' });
					var url = URL.createObjectURL(blob);
					var a = document.createElement('a');
					a.href = url;
					a.download = 'art-in-heaven-' + type + '-' + new Date().toISOString().slice(0, 10) + '.csv';
					a.click();
				}
			},
			complete: function() {
				$btn.prop('disabled', false).text(originalText);
			}
		});
	});

	// ===== JSON Export (Export tab) =====
	$('.aih-export-btn').on('click', function() {
		var type = $(this).data('type');
		var $btn = $(this).prop('disabled', true).text('<?php echo esc_js( __( 'Exporting...', 'art-in-heaven' ) ); ?>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'aih_admin_export_data',
				nonce: '<?php echo esc_js( wp_create_nonce( 'aih_admin_nonce' ) ); ?>',
				type: type
			},
			success: function(response) {
				if (response.success) {
					var blob = new Blob([JSON.stringify(response.data.data, null, 2)], { type: 'application/json' });
					var url = URL.createObjectURL(blob);
					var a = document.createElement('a');
					a.href = url;
					a.download = 'art-in-heaven-' + type + '-' + new Date().toISOString().slice(0, 10) + '.json';
					a.click();
				}
			},
			complete: function() {
				$btn.prop('disabled', false).text('Export ' + type.charAt(0).toUpperCase() + type.slice(1));
			}
		});
	});

	// ===== Chart.js Visualizations =====
	if (typeof Chart === 'undefined') return;

	window.aihCharts = {};

	var chartColors = {
		gold:       '#b8956b',
		green:      '#4a7c59',
		red:        '#a63d40',
		blue:       '#3b82f6',
		gray:       '#6b7280',
		lightGreen: 'rgba(74, 124, 89, 0.2)',
		lightBlue:  'rgba(59, 130, 246, 0.2)',
		lightGold:  'rgba(184, 149, 107, 0.2)'
	};

	// -- Revenue tab: Payment Method doughnut --
	var revenueMethodCanvas = document.getElementById('aih-revenue-method-chart');
	if (revenueMethodCanvas) {
		<?php
		$method_labels = array();
		$method_values = array();
		$method_colors = array( '#059669', '#2563eb', '#d97706', '#8b5cf6', '#ec4899', '#6b7280' );
		if ( ! empty( $revenue_by_method ) ) {
			foreach ( $revenue_by_method as $i => $m ) {
				$method_labels[] = ucfirst( $m->payment_method ?: __( 'Unknown', 'art-in-heaven' ) );
				$method_values[] = floatval( $m->method_total );
			}
		}
		?>
		new Chart(revenueMethodCanvas, {
			type: 'doughnut',
			data: {
				labels: <?php echo wp_json_encode( $method_labels ); ?>,
				datasets: [{
					data: <?php echo wp_json_encode( $method_values ); ?>,
					backgroundColor: <?php echo wp_json_encode( array_slice( $method_colors, 0, count( $method_labels ) ) ); ?>
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { position: 'bottom' },
					tooltip: {
						callbacks: {
							label: function(ctx) {
								var val = ctx.parsed;
								var total = ctx.dataset.data.reduce(function(a, b) { return a + b; }, 0);
								var pct = total > 0 ? Math.round(val / total * 100) : 0;
								return ctx.label + ': $' + val.toLocaleString(undefined, {minimumFractionDigits: 2}) + ' (' + pct + '%)';
							}
						}
					}
				}
			}
		});
	}

	// -- Overview tab: Inventory Health (horizontal stacked bar) --
	var inventoryCanvas = document.getElementById('aih-inventory-chart');
	if (inventoryCanvas) {
		window.aihCharts.inventory = new Chart(inventoryCanvas, {
			type: 'bar',
			data: {
				labels: ['<?php echo esc_js( __( 'Auction Inventory', 'art-in-heaven' ) ); ?>'],
				datasets: [
					{ label: '<?php echo esc_js( __( 'Sold', 'art-in-heaven' ) ); ?>',            data: [<?php echo intval( $sold_count ); ?>],     backgroundColor: '#059669' },
					{ label: '<?php echo esc_js( __( 'Active + Bids', 'art-in-heaven' ) ); ?>',    data: [<?php echo intval( $active_bids ); ?>],     backgroundColor: '#2563eb' },
					{ label: '<?php echo esc_js( __( 'Active No Bids', 'art-in-heaven' ) ); ?>',   data: [<?php echo intval( $active_no_bids ); ?>],  backgroundColor: '#d97706' },
					{ label: '<?php echo esc_js( __( 'Unsold', 'art-in-heaven' ) ); ?>',           data: [<?php echo intval( $unsold_count ); ?>],    backgroundColor: '#dc2626' }
				]
			},
			options: {
				indexAxis: 'y',
				responsive: true,
				maintainAspectRatio: false,
				scales: {
					x: { stacked: true },
					y: { stacked: true, display: false }
				},
				plugins: { legend: { position: 'bottom' } }
			}
		});
	}

	// -- Overview tab: Bid Attribution (doughnut) --
	var attrCanvas = document.getElementById('aih-attribution-chart');
	if (attrCanvas) {
		window.aihCharts.attribution = new Chart(attrCanvas, {
			type: 'doughnut',
			data: {
				labels: ['<?php echo esc_js( __( 'Push-Attributed', 'art-in-heaven' ) ); ?>', '<?php echo esc_js( __( 'Organic', 'art-in-heaven' ) ); ?>'],
				datasets: [{
					data: [<?php echo intval( $push_bids ); ?>, <?php echo intval( $organic_bids ); ?>],
					backgroundColor: [chartColors.gold, chartColors.green],
					borderWidth: 2
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { position: 'bottom' } }
			}
		});
	}

	// -- Overview tab: Bidding Activity Timeline (line) --
	var timelineCanvas = document.getElementById('aih-timeline-chart');
	if (timelineCanvas) {
		window.aihCharts.timeline = new Chart(timelineCanvas, {
			type: 'line',
			data: {
				labels: <?php echo wp_json_encode( $timeline_hours ); ?>,
				datasets: [
					{
						label: '<?php echo esc_js( __( 'Organic Bids', 'art-in-heaven' ) ); ?>',
						data: <?php echo wp_json_encode( $timeline_organic ); ?>,
						borderColor: chartColors.green,
						backgroundColor: chartColors.lightGreen,
						fill: true,
						tension: 0.3
					},
					{
						label: '<?php echo esc_js( __( 'Push-Attributed Bids', 'art-in-heaven' ) ); ?>',
						data: <?php echo wp_json_encode( $timeline_push ); ?>,
						borderColor: chartColors.gold,
						backgroundColor: chartColors.lightGold,
						fill: true,
						tension: 0.3
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { position: 'bottom' } },
				scales: {
					x: { ticks: { maxTicksLimit: 24, maxRotation: 45, minRotation: 0 } },
					y: { beginAtZero: true, ticks: { precision: 0 } }
				}
			}
		});
	}

	// -- Bidders tab: Push vs Non-Push Comparison --
	var compCanvas = document.getElementById('aih-bidder-comparison-chart');
	if (compCanvas) {
		new Chart(compCanvas, {
			type: 'bar',
			data: {
				labels: ['<?php echo esc_js( __( 'Avg Items Bid On', 'art-in-heaven' ) ); ?>', '<?php echo esc_js( __( 'Avg Total Bids', 'art-in-heaven' ) ); ?>'],
				datasets: [
					{
						label: '<?php echo esc_js( __( 'Push Bidders', 'art-in-heaven' ) ); ?>',
						data: [
							<?php echo floatval( $engagement_metrics['push_bidder_breadth'] ?? 0 ); ?>,
							<?php echo floatval( $engagement_metrics['push_bidder_depth'] ?? 0 ); ?>
						],
						backgroundColor: chartColors.gold,
						borderRadius: 4
					},
					{
						label: '<?php echo esc_js( __( 'Non-Push Bidders', 'art-in-heaven' ) ); ?>',
						data: [
							<?php echo floatval( $engagement_metrics['nonpush_bidder_breadth'] ?? 0 ); ?>,
							<?php echo floatval( $engagement_metrics['nonpush_bidder_depth'] ?? 0 ); ?>
						],
						backgroundColor: chartColors.green,
						borderRadius: 4
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { position: 'bottom' } },
				scales: { y: { beginAtZero: true } }
			}
		});
	}

	// -- Notifications tab: Delivery Funnel --
	var notifFunnelCanvas = document.getElementById('aih-notif-funnel-chart');
	if (notifFunnelCanvas) {
		new Chart(notifFunnelCanvas, {
			type: 'bar',
			data: {
				labels: ['<?php echo esc_js( __( 'Sent', 'art-in-heaven' ) ); ?>', '<?php echo esc_js( __( 'Delivered', 'art-in-heaven' ) ); ?>', '<?php echo esc_js( __( 'Clicked', 'art-in-heaven' ) ); ?>', '<?php echo esc_js( __( 'Expired', 'art-in-heaven' ) ); ?>'],
				datasets: [{
					label: '<?php echo esc_js( __( 'Count', 'art-in-heaven' ) ); ?>',
					data: [
						<?php echo intval( $funnel['push_sent'] ?? 0 ); ?>,
						<?php echo intval( $funnel['push_delivered'] ?? 0 ); ?>,
						<?php echo intval( $funnel['push_clicked'] ?? 0 ); ?>,
						<?php echo intval( $funnel['push_expired'] ?? 0 ); ?>
					],
					backgroundColor: [chartColors.blue, chartColors.green, chartColors.gold, chartColors.red],
					borderRadius: 4
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { display: false } },
				scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
			}
		});
	}

	// -- Notifications tab: By Type --
	var notifTypeCanvas = document.getElementById('aih-notif-type-chart');
	if (notifTypeCanvas) {
		<?php
		$type_chart_data = array();
		foreach ( $notif_breakdown as $nt => $counts ) {
			$label             = isset( $type_labels[ $nt ] ) ? $type_labels[ $nt ] : ucfirst( $nt );
			$type_chart_data[] = array(
				'label'     => $label,
				'sent'      => $counts['sent'],
				'delivered' => $counts['delivered'],
				'clicked'   => $counts['clicked'],
			);
		}
		?>
		var typeData = <?php echo wp_json_encode( $type_chart_data ); ?>;
		if (typeData.length > 0) {
			new Chart(notifTypeCanvas, {
				type: 'bar',
				data: {
					labels: typeData.map(function(d) { return d.label; }),
					datasets: [
						{ label: '<?php echo esc_js( __( 'Sent', 'art-in-heaven' ) ); ?>',      data: typeData.map(function(d) { return d.sent; }),      backgroundColor: chartColors.blue,  borderRadius: 4 },
						{ label: '<?php echo esc_js( __( 'Delivered', 'art-in-heaven' ) ); ?>',   data: typeData.map(function(d) { return d.delivered; }), backgroundColor: chartColors.green, borderRadius: 4 },
						{ label: '<?php echo esc_js( __( 'Clicked', 'art-in-heaven' ) ); ?>',     data: typeData.map(function(d) { return d.clicked; }),   backgroundColor: chartColors.gold,  borderRadius: 4 }
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: { legend: { position: 'bottom' } },
					scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
				}
			});
		}
	}

	// -- Overview tab: Top 10 by Revenue (horizontal bar) --
	var topRevCanvas = document.getElementById('aih-top-revenue-chart');
	if (topRevCanvas) {
		var topRevData = <?php echo wp_json_encode( array_map( function( $p ) {
			return array(
				'label' => $p->title . ' (' . $p->art_id . ')',
				'value' => floatval( $p->highest_bid ),
				'bids'  => intval( $p->bid_count ),
			);
		}, $top_by_revenue ) ); ?>;
		window.aihCharts.topRevenue = new Chart(topRevCanvas, {
			type: 'bar',
			data: {
				labels: topRevData.map(function(d) { return d.label; }),
				datasets: [{
					label: '<?php echo esc_js( __( 'Highest Bid ($)', 'art-in-heaven' ) ); ?>',
					data: topRevData.map(function(d) { return d.value; }),
					backgroundColor: chartColors.gold,
					borderRadius: 4
				}]
			},
			options: {
				indexAxis: 'y',
				responsive: true,
				maintainAspectRatio: false,
				layout: { padding: { left: 8 } },
				plugins: {
					legend: { display: false },
					tooltip: {
						callbacks: {
							label: function(ctx) {
								var item = topRevData[ctx.dataIndex];
								return '$' + item.value.toLocaleString() + ' (' + item.bids + ' <?php echo esc_js( __( 'bids', 'art-in-heaven' ) ); ?>)';
							}
						}
					}
				},
				scales: {
					x: { beginAtZero: true, ticks: { callback: function(v) { return '$' + v.toLocaleString(); } } },
					y: {
						ticks: {
							callback: function(value) {
								var label = (this && typeof this.getLabelForValue === 'function') ? this.getLabelForValue(value) : '';
								label = label || '';
								return label.length > 30 ? label.substring(0, 30) + '…' : label;
							},
							font: { size: 11 }
						}
					}
				}
			}
		});
	}

	// -- Art Pieces tab: Bid Distribution histogram --
	var bidDistCanvas = document.getElementById('aih-bid-distribution-chart');
	if (bidDistCanvas) {
		var distData = <?php echo wp_json_encode( array_map( function( $row ) {
			return array(
				'bracket' => $row->bracket,
				'count'   => intval( $row->cnt ),
			);
		}, $bid_distribution ) ); ?>;
		new Chart(bidDistCanvas, {
			type: 'bar',
			data: {
				labels: distData.map(function(d) { return d.bracket; }),
				datasets: [{
					label: '<?php echo esc_js( __( 'Number of Bids', 'art-in-heaven' ) ); ?>',
					data: distData.map(function(d) { return d.count; }),
					backgroundColor: chartColors.green,
					borderRadius: 4
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { display: false } },
				scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
			}
		});
	}

	// -- Bidders tab: Conversion Funnel --
	var funnelCanvas = document.getElementById('aih-conversion-funnel-chart');
	if (funnelCanvas) {
		new Chart(funnelCanvas, {
			type: 'bar',
			data: {
				labels: [
					'<?php echo esc_js( __( 'Registered', 'art-in-heaven' ) ); ?>',
					'<?php echo esc_js( __( 'Logged In', 'art-in-heaven' ) ); ?>',
					'<?php echo esc_js( __( 'Placed Bids', 'art-in-heaven' ) ); ?>'
				],
				datasets: [{
					label: '<?php echo esc_js( __( 'Count', 'art-in-heaven' ) ); ?>',
					data: [
						<?php echo isset( $registrant_counts ) ? intval( $registrant_counts->total ) : 0; ?>,
						<?php echo isset( $registrant_counts ) ? intval( $registrant_counts->logged_in ) : 0; ?>,
						<?php echo isset( $registrant_counts ) ? intval( $registrant_counts->has_bids ) : 0; ?>
					],
					backgroundColor: [chartColors.blue, chartColors.gold, chartColors.green],
					borderRadius: 4
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { display: false },
					tooltip: {
						callbacks: {
							label: function(ctx) {
								var total = <?php echo isset( $registrant_counts ) ? intval( $registrant_counts->total ) : 1; ?>;
								var pct = total > 0 ? Math.round((ctx.raw / total) * 100) : 0;
								return ctx.raw.toLocaleString() + ' (' + pct + '%)';
							}
						}
					}
				},
				scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
			}
		});
	}
});
</script>
