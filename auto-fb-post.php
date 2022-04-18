<?php
/*
 * Plugin Name: Auto Facebook Post
 * Description: automatic post woocmmerce produts as facebook posts
 * Version: 1.0
 * Author: Jose Augusto Salim
 * Author URI: github.com/zedomel
 */

	define( 'HOUR_SECONDS', 3600 );

	require_once 'includes/AutoFacebookPost.php';

if ( is_admin() ) {
	register_activation_hook( __FILE__, 'afp_activation' );
	register_deactivation_hook( __FILE__, 'afp_deactivation' );
	register_uninstall_hook( __FILE__, 'afp_uninstall' );
	add_action( 'admin_menu', 'afp_add_admin_menu' );
	add_action( 'admin_init', 'afp_settings_init' );
	add_action( 'update_option_afp_settings', 'afp_options_update', 2, 10 );
}

	// Add cron schedules filter with upper defined schedule.
	add_filter( 'cron_schedules', 'afp_schedules' );

	// return;
	//

function afp_options_update( $old_options, $value ) {
	// New schedule time: cancel previous cron jobs
	if ( ! isset( $old_options['afp_recurrence_hours'] ) || $old_options['afp_recurrence_hours'] !== $value['afp_recurrence_hours'] ||
		$old_options['afp_scheduled_time']['hour'] !== $value['afp_scheduled_time']['hour'] ||
		$old_options['afp_scheduled_time']['minute'] !== $value['afp_scheduled_time']['minute'] ) {
		wp_clear_scheduled_hook( 'afp_cron_delivery' );
		afp_schedule_post();

		// test
		afp_post_on_facebook();
	}
}

	// Custom function to be called on schedule triggered.
function afp_post_on_facebook() {
	$settings     = get_option( 'afp_settings' );
	$page_id      = $settings['afp_fb_page_id'];
	$app_id       = $settings['afp_fb_app_id'];
	$app_secret   = $settings['afp_fb_app_secret'];
	$access_token = $settings['afp_fb_access_token'];

	if ( ! empty( $page_id ) && ! empty( $app_id ) && ! empty( $app_secret ) && ! empty( $access_token ) ) {
		$args     = [
			'limit'   => 1,
			'orderby' => 'rand',
			'status'  => 'publish',
		];
		$products = wc_get_products( $args );
		if ( ! empty( $products ) ) {
			$fb     = new AutoFacebookPost( $page_id, $app_id, $app_secret, $access_token );
			$result = $fb->post( $products[0] );
			if ( is_wp_error( $result ) ) {
				error_log( print_r( $result, true ) );
			}
		}
	}
}
	add_action( 'afp_cron_delivery', 'afp_post_on_facebook' );

	/**
	 * Plugin activation
	 */
function afp_activation() {
	 // Trigger our method on our custom schedule event.
	if ( ! wp_next_scheduled( 'afp_cron_delivery' ) ) {
		afp_schedule_post();
	}
}

function afp_schedule_post() {
	$settings       = get_option( 'afp_settings' );
	$recurrence     = ! empty( $settings['afp_recurrence_hours'] ) ? ( $settings['afp_recurrence_hours'] * HOUR_SECONDS ) . 's' : '1440s';
	$scheduled_time = $settings['afp_scheduled_time'];
	if ( isset( $scheduled_time['hour'] ) && isset( $scheduled_time['minute'] ) ) {
		$utc                 = date_i18n( 'H:i T', strtotime( sprintf( '%d:%d', $scheduled_time['hour'], $scheduled_time['minute'] ) ) );
		$scheduled_timestamp = strtotime( $utc );
	} else {
		$scheduled_timestamp = time();
	}
	wp_schedule_event( $scheduled_timestamp, $recurrence, 'afp_cron_delivery' );
}

	/**
	 * Plugin deactivation
	 */
function afp_deactivation() {
	// Remove our scheduled hook.
	wp_clear_scheduled_hook( 'afp_cron_delivery' );
}

	/**
	 * Plugin uninstall
	 */
function afp_uninstall() {
	delete_option( 'afp_settings' );
}


	/**
	 * Add a custom schedule to wp.
	 *
	 * @param $schedules array The  existing schedules
	 *
	 * @return mixed The existing + new schedules.
	 */
function afp_schedules( $schedules ) {
	$settings      = get_option( 'afp_settings' );
	$schedule_time = ! empty( $settings['afp_recurrence_hours'] ) ? $settings['afp_recurrence_hours'] * HOUR_SECONDS : 1440;
	$schedule_key  = $schedule_time . 's';
	if ( ! isset( $schedules[ $schedule_key ] ) ) {
		$schedules[ $schedule_key ] = [
			'interval' => intval( $schedule_time ),
			'display'  => sprintf( __( 'Once every %d seconds', 'afp' ), $schedule_time ),
		];
	}
	return $schedules;
}

function afp_add_admin_menu() {
	add_options_page( 'Auto Facebook Post', 'Auto Facebook Post', 'manage_options', 'auto_facebook_post', 'afp_options_page' );
}

function afp_settings_init() {
	register_setting( 'pluginPage', 'afp_settings' );

	add_settings_section(
		'afp_general_section',
		__( 'General settings', 'afp' ),
		'afp_settings_section_callback',
		'pluginPage'
	);

	add_settings_section(
		'afp_feeding_section',
		__( 'Feeding settings', 'afp' ),
		'afp_settings_section_callback',
		'pluginPage'
	);

	add_settings_field(
		'afp_fb_page_id',
		__( 'Facebook Page ID', 'afp' ),
		'afp_text_field_render',
		'pluginPage',
		'afp_general_section',
		[
			'label_for' => 'afp_fb_page_id',
		]
	);

	add_settings_field(
		'afp_fb_app_id',
		__( 'Facebook App ID', 'afp' ),
		'afp_text_field_render',
		'pluginPage',
		'afp_general_section',
		[
			'label_for' => 'afp_fb_app_id',
		]
	);

	add_settings_field(
		'afp_fb_app_secret',
		__( 'Facebook App Secret', 'afp' ),
		'afp_text_field_render',
		'pluginPage',
		'afp_general_section',
		[
			'label_for' => 'afp_fb_app_secret',
		]
	);

	add_settings_field(
		'afp_fb_access_token',
		__( 'Page Access Token', 'afp' ),
		'afp_text_field_render',
		'pluginPage',
		'afp_general_section',
		[
			'label_for' => 'afp_fb_access_token',
		]
	);

	add_settings_field(
		'afp_recurrence_hours',
		__( 'Recurrence', 'afp' ),
		'afp_number_field_render',
		'pluginPage',
		'afp_general_section',
		[
			'label_for' => 'afp_recurrence_hours',
			'min'       => 0,
		]
	);

	add_settings_field(
		'afp_scheduled_time',
		__( 'Scheduled time', 'afp' ),
		'afp_time_field_render',
		'pluginPage',
		'afp_general_section',
		[
			'label_for' => 'afp_scheduled_time',
			'desc'      => __( 'Set a time to schedule automatic posts (HH:MM)', 'afp' ),
		]
	);

	// Feeding options
	add_settings_field(
		'afp_fb_hashtags',
		__( 'Hashtags', 'afp' ),
		'afp_text_field_render',
		'pluginPage',
		'afp_feeding_section',
		[
			'label_for' => 'afp_fb_hashtags',
			'desc'      => __( 'Enter your hashtags separated by whitespace (e.g. one, two, three).', 'afp' ),
		]
	);

	add_settings_field(
		'afp_fb_use_top_allhashtags',
		__( 'Use Top AllHashtags', 'afp' ),
		'afp_checkbox_field_render',
		'pluginPage',
		'afp_feeding_section',
		[
			'label_for' => 'afp_fb_use_top_allhashtags',
			'desc'      => __( 'If checked AllHashtags.com will be used to retrive top hashtags based on produts tags', 'afp' ),
		]
	);

	add_settings_field(
		'afp_fb_feed_age_max',
		__( 'Max. age', 'afp' ),
		'afp_number_field_render',
		'pluginPage',
		'afp_feeding_section',
		[
			'label_for' => 'afp_fb_feed_age_max',
			'min'       => 0,
			'max'       => 65,
			'desc'      => __( 'Maximum age. Must be 65 or lower.', 'afp' ),
		]
	);

	add_settings_field(
		'afp_fb_feed_age_min',
		__( 'Min. age', 'afp' ),
		'afp_number_field_render',
		'pluginPage',
		'afp_feeding_section',
		[
			'label_for' => 'afp_fb_feed_age_min',
			'min'       => 13,
			'max'       => 65,
			'desc'      => __( 'Must be 13 or higher.', 'afp' ),
		]
	);

	add_settings_field(
		'afp_fb_feed_college_years',
		__( 'College years', 'afp' ),
		'afp_text_field_render',
		'pluginPage',
		'afp_feeding_section',
		[
			'label_for' => 'afp_fb_feed_college_years',
			'desc'      => __( 'Graduation year from college', 'afp' ),
		]
	);

	add_settings_field(
		'afp_fb_feed_genders',
		__( 'Genders', 'afp' ),
		'afp_select_field_render',
		'pluginPage',
		'afp_feeding_section',
		[
			'label_for' => 'afp_fb_feed_genders',
			'options'   => [
				'0' => __( 'All', 'afp' ),
				'1' => __( 'Male viewers', 'afp' ),
				'2' => __( 'Female viewers', 'afp' ),
			],
			'desc'      => __( 'Target specific genders. Default is to target both.', 'afp' ),
		]
	);

	add_settings_field(
		'afp_fb_feed_relationship_statuses',
		__( 'Relationship statuses', 'afp' ),
		'afp_select_field_render',
		'pluginPage',
		'afp_feeding_section',
		[
			'label_for' => 'afp_fb_feed_relationship_statuses',
			'options'   => [
				'1' => __( 'Single', 'afp' ),
				'2' => __( 'In a relantionship', 'afp' ),
				'3' => __( 'Married', 'afp' ),
				'4' => __( 'Engaged', 'afp' ),
			],
			'multiple'  => true,
			'desc'      => __( 'Targeting based on relationship status. Default is all types.', 'afp' ),
		]
	);

	add_settings_field(
		'afp_fb_feed_education_statuses',
		__( 'Education statuses', 'afp' ),
		'afp_select_field_render',
		'pluginPage',
		'afp_feeding_section',
		[
			'label_for' => 'afp_fb_feed_education_statuses',
			'options'   => [
				'1' => __( 'High school', 'afp' ),
				'2' => __( 'Undergraduate', 'afp' ),
				'3' => __( 'Alum', 'afp' ),
			],
			'multiple'  => true,
			'desc'      => __( 'Targeting based on education level.', 'afp' ),
		]
	);

	add_settings_field(
		'afp_fb_feed_countries',
		__( 'Relationship statuses', 'afp' ),
		'afp_select_field_render',
		'pluginPage',
		'afp_feeding_section',
		[
			'label_for' => 'afp_fb_feed_countries',
			'options'   => WC()->countries->get_countries(),
			'multiple'  => true,
			'desc'      => __( 'Targeting based on country. Default to all', 'afp' ),
		]
	);
}

function afp_time_field_render( $args ) {
	$options = get_option( 'afp_settings' );
	$key     = $args['label_for']; ?>
	<div class="scheduled-time">
		<input style="width: 50px;" type='number' min="0" max="23" name="afp_settings[<?php echo $key; ?>][hour]" value="<?php echo isset( $options[ $key ] ) ? $options[ $key ]['hour'] : ''; ?>">
		<span>:</span>
		<input style="width: 50px;" type='number' min="0" max="59" name="afp_settings[<?php echo $key; ?>][minute]" value="<?php echo isset( $options[ $key ] ) ? $options[ $key ]['minute'] : ''; ?>">
	</div>
		<?php
		if ( isset( $args['desc'] ) ) :
			?>
		<p class="description" id="tagline-description"><?php echo $args['desc']; ?></p>
			<?php
			endif;
}

function afp_checkbox_field_render( $args ) {
	$options = get_option( 'afp_settings' );
	$key     = $args['label_for'];
	?>
	<input type='checkbox' name='afp_settings[<?php echo $key; ?>]' <?php checked( $options[ $key ], 'yes' ); ?> value='yes'>
		<?php
		if ( isset( $args['desc'] ) ) :
			?>
		<p class="description" id="tagline-description"><?php echo $args['desc']; ?></p>
			<?php
		endif;
}

function afp_select_field_render( $args ) {
	$options = get_option( 'afp_settings' );
	$key     = $args['label_for'];
	?>
	<select name="afp_settings[<?php echo $key; ?>]" <?php echo isset( $args['multiple'] ) && $args['multiple'] ? 'multiple="true"' : ''; ?>>
		<?php foreach ( $args['options'] as $value => $option ) : ?>
		<option value="<?php echo $option; ?>"
			<?php
			if ( isset( $options[ $key ] ) ) {
				if ( ! is_array( $options[ $key ] ) ) {
					selected( $options[ $key ], 1 );
				} elseif ( in_array( $value, $options[ $key ] ) ) {
					echo 'selected="true"';
				}
			}
			?>
				><?php echo $option; ?></option>
	<?php endforeach; ?>
	</select>
		<?php
		if ( isset( $args['desc'] ) ) :
			?>
		<p class="description" id="tagline-description"><?php echo $args['desc']; ?></p>
			<?php
		endif;
}

function afp_number_field_render( $args ) {
	$options = get_option( 'afp_settings' );
	$key     = $args['label_for'];
	?>
	<input type='number' min="<?php echo isset( $args['min'] ) ? $args['min'] : ''; ?>" max="<?php echo isset( $args['max'] ) ? $args['max'] : ''; ?>" name='afp_settings[<?php echo $key; ?>]' value='<?php echo isset( $options[ $key ] ) ? $options[ $key ] : ''; ?>'>
		<?php
		if ( isset( $args['desc'] ) ) :
			?>
		<p class="description" id="tagline-description"><?php echo $args['desc']; ?></p>
			<?php
		endif;
}


function afp_text_field_render( $args ) {
	$options = get_option( 'afp_settings' );
	$key     = $args['label_for']
	?>
	<input type="text" name="afp_settings[<?php echo $key; ?>]" value="<?php echo isset( $options[ $key ] ) ? esc_attr( $options[ $key ] ) : ''; ?>">
		<?php
		if ( isset( $args['desc'] ) ) :
			?>
		<p class="description" id="tagline-description"><?php echo $args['desc']; ?></p>
			<?php
		endif;
}


function afp_settings_section_callback() {
	// echo __('This section description', 'afp');
}


function afp_options_page() {
	$schedule = wp_get_schedule( 'afp_cron_delivery' );
	?>
	<form action='options.php' method='post'>

		<h2>Auto Facebook Post</h2>
	<?php if ( false !== $schedule ) : ?>
			<p><?php echo sprintf( __( 'Scheduled: %s', 'afp' ), $schedule ); ?></p>
			<?php
			endif;
	settings_fields( 'pluginPage' );
	do_settings_sections( 'pluginPage' );
	submit_button();
	?>

	</form>
	<?php
}

