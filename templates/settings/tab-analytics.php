<?php
/**
 * Settings tab: Analytics.
 *
 * @package PluginStage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$range = isset( $_GET['range'] ) ? absint( wp_unslash( $_GET['range'] ) ) : 30;
$valid = array( 7, 14, 30, 90, 0 );
if ( ! in_array( $range, $valid, true ) ) {
	$range = 30;
}

$data = PluginStage_Session_Log::instance()->get_analytics( $range );

$range_url = function ( $r ) use ( $range ) {
	return add_query_arg(
		array(
			'page'  => PluginStage_Settings::SLUG,
			'tab'   => 'analytics',
			'range' => $r,
		),
		admin_url( 'admin.php' )
	);
};

$format_duration = function ( $sec ) {
	$sec = max( 0, (int) $sec );
	if ( $sec < 60 ) {
		return $sec . 's';
	}
	$m = floor( $sec / 60 );
	$s = $sec % 60;
	if ( $m < 60 ) {
		return $m . 'm ' . $s . 's';
	}
	$h = floor( $m / 60 );
	$m = $m % 60;
	return $h . 'h ' . $m . 'm';
};

$range_label = 0 === $range ? __( 'All time', 'pluginstage' ) : sprintf( __( 'Last %d days', 'pluginstage' ), $range );
?>

<style>
.ps-analytics-range { margin: 0 0 20px; }
.ps-analytics-range a,
.ps-analytics-range strong { margin-right: 12px; }
.ps-cards { display: flex; flex-wrap: wrap; gap: 16px; margin: 0 0 24px; }
.ps-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px 24px; min-width: 150px; flex: 1; }
.ps-card__value { font-size: 28px; font-weight: 700; color: #1d2327; line-height: 1.2; }
.ps-card__label { font-size: 13px; color: #646970; margin-top: 4px; }
.ps-card--active .ps-card__value { color: #00a32a; }
.ps-section { margin: 0 0 32px; }
.ps-section h3 { margin: 0 0 12px; }
.ps-bar-chart { display: flex; align-items: flex-end; gap: 2px; height: 120px; border-bottom: 1px solid #dcdcde; padding-bottom: 4px; max-width: 100%; overflow-x: auto; }
.ps-bar { background: #2271b1; min-width: 8px; flex: 1; border-radius: 2px 2px 0 0; position: relative; cursor: default; max-width: 28px; }
.ps-bar:hover { background: #135e96; }
.ps-bar__tip { display: none; position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); background: #1d2327; color: #fff; padding: 4px 8px; border-radius: 3px; font-size: 11px; white-space: nowrap; margin-bottom: 4px; }
.ps-bar:hover .ps-bar__tip { display: block; }
.ps-bar-labels { display: flex; gap: 2px; max-width: 100%; overflow-x: auto; }
.ps-bar-labels span { flex: 1; text-align: center; font-size: 10px; color: #646970; max-width: 28px; min-width: 8px; overflow: hidden; }
.ps-table-small { max-width: 600px; }
.ps-table-small td, .ps-table-small th { padding: 6px 10px; }
.ps-pct-bar { display: inline-block; height: 14px; background: #2271b1; border-radius: 2px; vertical-align: middle; margin-right: 6px; }
</style>

<div class="ps-analytics-range">
	<strong><?php echo esc_html( $range_label ); ?>:</strong>
	<?php foreach ( array( 7, 14, 30, 90, 0 ) as $r ) :
		$lbl = 0 === $r ? __( 'All', 'pluginstage' ) : sprintf( __( '%dd', 'pluginstage' ), $r );
		if ( $r === $range ) :
			echo '<strong>' . esc_html( $lbl ) . '</strong>';
		else :
			echo '<a href="' . esc_url( $range_url( $r ) ) . '">' . esc_html( $lbl ) . '</a>';
		endif;
	endforeach; ?>
</div>

<div class="ps-cards">
	<div class="ps-card">
		<div class="ps-card__value"><?php echo esc_html( number_format_i18n( $data['total_sessions'] ) ); ?></div>
		<div class="ps-card__label"><?php esc_html_e( 'Total sessions', 'pluginstage' ); ?></div>
	</div>
	<div class="ps-card ps-card--active">
		<div class="ps-card__value"><?php echo esc_html( number_format_i18n( $data['active_now'] ) ); ?></div>
		<div class="ps-card__label"><?php esc_html_e( 'Active now', 'pluginstage' ); ?></div>
	</div>
	<div class="ps-card">
		<div class="ps-card__value"><?php echo esc_html( number_format_i18n( $data['unique_ips'] ) ); ?></div>
		<div class="ps-card__label"><?php esc_html_e( 'Unique visitors (IPs)', 'pluginstage' ); ?></div>
	</div>
	<div class="ps-card">
		<div class="ps-card__value"><?php echo esc_html( $format_duration( $data['avg_duration_sec'] ) ); ?></div>
		<div class="ps-card__label"><?php esc_html_e( 'Avg. session duration', 'pluginstage' ); ?></div>
	</div>
	<div class="ps-card">
		<div class="ps-card__value"><?php echo esc_html( (string) $data['avg_pages'] ); ?></div>
		<div class="ps-card__label"><?php esc_html_e( 'Avg. pages per session', 'pluginstage' ); ?></div>
	</div>
	<div class="ps-card">
		<div class="ps-card__value"><?php echo esc_html( number_format_i18n( $data['total_pageviews'] ) ); ?></div>
		<div class="ps-card__label"><?php esc_html_e( 'Total page views', 'pluginstage' ); ?></div>
	</div>
</div>

<?php if ( ! empty( $data['daily'] ) ) : ?>
<div class="ps-section">
	<h3><?php esc_html_e( 'Sessions per day', 'pluginstage' ); ?></h3>
	<?php
	$max_daily = 1;
	foreach ( $data['daily'] as $d ) {
		if ( (int) $d['cnt'] > $max_daily ) {
			$max_daily = (int) $d['cnt'];
		}
	}
	?>
	<div class="ps-bar-chart">
		<?php foreach ( $data['daily'] as $d ) :
			$pct = round( ( (int) $d['cnt'] / $max_daily ) * 100 );
			$pct = max( 2, $pct );
			$lbl = date_i18n( 'M j', strtotime( $d['day'] ) );
		?>
			<div class="ps-bar" style="height:<?php echo esc_attr( $pct ); ?>%;"><span class="ps-bar__tip"><?php echo esc_html( $lbl . ': ' . $d['cnt'] ); ?></span></div>
		<?php endforeach; ?>
	</div>
	<div class="ps-bar-labels">
		<?php foreach ( $data['daily'] as $d ) : ?>
			<span><?php echo esc_html( date_i18n( 'j', strtotime( $d['day'] ) ) ); ?></span>
		<?php endforeach; ?>
	</div>
</div>
<?php endif; ?>

<?php if ( ! empty( $data['by_hour'] ) ) : ?>
<div class="ps-section">
	<h3><?php esc_html_e( 'Sessions by hour of day (UTC)', 'pluginstage' ); ?></h3>
	<?php
	$hourly_map = array_fill( 0, 24, 0 );
	foreach ( $data['by_hour'] as $h ) {
		$hourly_map[ (int) $h['hr'] ] = (int) $h['cnt'];
	}
	$max_hr = max( 1, max( $hourly_map ) );
	?>
	<div class="ps-bar-chart">
		<?php for ( $hr = 0; $hr < 24; $hr++ ) :
			$pct = round( ( $hourly_map[ $hr ] / $max_hr ) * 100 );
			$pct = $hourly_map[ $hr ] > 0 ? max( 2, $pct ) : 0;
		?>
			<div class="ps-bar" style="height:<?php echo esc_attr( $pct ); ?>%;<?php echo 0 === $pct ? 'background:#dcdcde;min-height:2px;' : ''; ?>"><span class="ps-bar__tip"><?php echo esc_html( sprintf( '%02d:00 — %d', $hr, $hourly_map[ $hr ] ) ); ?></span></div>
		<?php endfor; ?>
	</div>
	<div class="ps-bar-labels">
		<?php for ( $hr = 0; $hr < 24; $hr++ ) : ?>
			<span><?php echo esc_html( (string) $hr ); ?></span>
		<?php endfor; ?>
	</div>
</div>
<?php endif; ?>

<div style="display:flex;flex-wrap:wrap;gap:32px;">

<?php if ( ! empty( $data['top_pages'] ) ) : ?>
<div class="ps-section" style="flex:1;min-width:280px;">
	<h3><?php esc_html_e( 'Most visited pages', 'pluginstage' ); ?></h3>
	<table class="widefat striped ps-table-small">
		<thead><tr><th><?php esc_html_e( 'Screen / Page', 'pluginstage' ); ?></th><th style="width:80px;"><?php esc_html_e( 'Views', 'pluginstage' ); ?></th><th style="width:120px;"></th></tr></thead>
		<tbody>
			<?php
			$max_pv = max( 1, reset( $data['top_pages'] ) );
			foreach ( $data['top_pages'] as $slug => $cnt ) :
				$bar_w = round( ( $cnt / $max_pv ) * 100 );
			?>
				<tr>
					<td><code><?php echo esc_html( $slug ); ?></code></td>
					<td><?php echo esc_html( number_format_i18n( $cnt ) ); ?></td>
					<td><span class="ps-pct-bar" style="width:<?php echo esc_attr( $bar_w ); ?>%;"></span></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php endif; ?>

<?php if ( ! empty( $data['browsers'] ) ) : ?>
<div class="ps-section" style="flex:1;min-width:250px;">
	<h3><?php esc_html_e( 'Browsers', 'pluginstage' ); ?></h3>
	<table class="widefat striped ps-table-small">
		<thead><tr><th><?php esc_html_e( 'Browser', 'pluginstage' ); ?></th><th style="width:80px;"><?php esc_html_e( 'Sessions', 'pluginstage' ); ?></th><th style="width:70px;">%</th></tr></thead>
		<tbody>
			<?php
			$browser_total = max( 1, array_sum( $data['browsers'] ) );
			foreach ( $data['browsers'] as $name => $cnt ) :
			?>
				<tr>
					<td><?php echo esc_html( $name ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $cnt ) ); ?></td>
					<td><?php echo esc_html( round( $cnt / $browser_total * 100, 1 ) . '%' ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php endif; ?>

</div>

<div style="display:flex;flex-wrap:wrap;gap:32px;margin-top:8px;">

<?php if ( ! empty( $data['by_profile'] ) ) : ?>
<div class="ps-section" style="flex:1;min-width:280px;">
	<h3><?php esc_html_e( 'Sessions by profile', 'pluginstage' ); ?></h3>
	<table class="widefat striped ps-table-small">
		<thead><tr><th><?php esc_html_e( 'Profile', 'pluginstage' ); ?></th><th style="width:80px;"><?php esc_html_e( 'Sessions', 'pluginstage' ); ?></th><th style="width:70px;">%</th></tr></thead>
		<tbody>
			<?php
			$prof_total = max( 1, $data['total_sessions'] );
			foreach ( $data['by_profile'] as $pr ) :
				$pid   = (int) $pr['profile_id'];
				$pname = $pid > 0 ? get_the_title( $pid ) : __( 'Default (no profile)', 'pluginstage' );
			?>
				<tr>
					<td><?php echo esc_html( $pname ); ?></td>
					<td><?php echo esc_html( number_format_i18n( (int) $pr['cnt'] ) ); ?></td>
					<td><?php echo esc_html( round( (int) $pr['cnt'] / $prof_total * 100, 1 ) . '%' ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php endif; ?>

<?php if ( ! empty( $data['top_ips'] ) ) : ?>
<div class="ps-section" style="flex:1;min-width:250px;">
	<h3><?php esc_html_e( 'Top visitor IPs', 'pluginstage' ); ?></h3>
	<table class="widefat striped ps-table-small">
		<thead><tr><th><?php esc_html_e( 'IP address', 'pluginstage' ); ?></th><th style="width:80px;"><?php esc_html_e( 'Sessions', 'pluginstage' ); ?></th></tr></thead>
		<tbody>
			<?php foreach ( $data['top_ips'] as $tip ) : ?>
				<tr>
					<td><code><?php echo esc_html( $tip['ip'] ); ?></code></td>
					<td><?php echo esc_html( number_format_i18n( (int) $tip['cnt'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php endif; ?>

</div>

<?php if ( 0 === $data['total_sessions'] ) : ?>
	<div class="notice notice-info inline" style="margin-top:16px;">
		<p><?php esc_html_e( 'No session data yet. Analytics will populate after demo visitors start using magic links.', 'pluginstage' ); ?></p>
	</div>
<?php endif; ?>
