<?php
/**
 * Events module — admin metabox for structured fields + save handling.
 *
 * The event CPT uses the classic editor UI. We add a metabox for the
 * structured fields (dates, location, banner, etc.).
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_events_register_metabox() {
	add_meta_box(
		'owbn_board_event_details',
		__( 'Event Details', 'owbn-board' ),
		'owbn_board_events_render_metabox',
		'owbn_event',
		'normal',
		'high'
	);
}

function owbn_board_events_render_metabox( $post ) {
	wp_nonce_field( 'owbn_board_events_save_meta', 'owbn_board_events_nonce' );
	wp_enqueue_media();

	$meta       = owbn_board_events_get_meta( $post->ID );
	$banner_id  = ! empty( $meta['banner_image_id'] ) ? (int) $meta['banner_image_id'] : 0;
	$banner_url = $banner_id ? wp_get_attachment_image_url( $banner_id, 'medium' ) : '';

	$tz_options = class_exists( 'DateTimeZone' ) ? DateTimeZone::listIdentifiers() : [];
	$tz_value   = $meta['timezone'] ?: 'UTC';
	?>
	<table class="form-table">
		<tr>
			<th><label for="owbn_event_tagline"><?php esc_html_e( 'Tagline', 'owbn-board' ); ?></label></th>
			<td>
				<input type="text" id="owbn_event_tagline" name="owbn_event_tagline" class="large-text" value="<?php echo esc_attr( $meta['tagline'] ); ?>" maxlength="255" />
				<p class="description"><?php esc_html_e( 'Short hook shown in the tile.', 'owbn-board' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="owbn_event_start_dt"><?php esc_html_e( 'Start Date/Time', 'owbn-board' ); ?></label></th>
			<td>
				<input type="datetime-local" id="owbn_event_start_dt" name="owbn_event_start_dt" value="<?php echo esc_attr( owbn_board_events_to_local_input( $meta['start_dt'] ) ); ?>" required />
			</td>
		</tr>
		<tr>
			<th><label for="owbn_event_end_dt"><?php esc_html_e( 'End Date/Time', 'owbn-board' ); ?></label></th>
			<td>
				<input type="datetime-local" id="owbn_event_end_dt" name="owbn_event_end_dt" value="<?php echo esc_attr( owbn_board_events_to_local_input( $meta['end_dt'] ) ); ?>" />
			</td>
		</tr>
		<tr>
			<th><label for="owbn_event_timezone"><?php esc_html_e( 'Timezone', 'owbn-board' ); ?></label></th>
			<td>
				<select id="owbn_event_timezone" name="owbn_event_timezone">
					<?php foreach ( $tz_options as $tz ) : ?>
						<option value="<?php echo esc_attr( $tz ); ?>" <?php selected( $tz_value, $tz ); ?>><?php echo esc_html( $tz ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th><label for="owbn_event_location"><?php esc_html_e( 'Location', 'owbn-board' ); ?></label></th>
			<td><input type="text" id="owbn_event_location" name="owbn_event_location" class="large-text" value="<?php echo esc_attr( $meta['location'] ); ?>" /></td>
		</tr>
		<tr>
			<th><label for="owbn_event_host_scope"><?php esc_html_e( 'Host Scope', 'owbn-board' ); ?></label></th>
			<td>
				<input type="text" id="owbn_event_host_scope" name="owbn_event_host_scope" class="regular-text" value="<?php echo esc_attr( $meta['host_scope'] ); ?>" placeholder="chronicle/mckn, coordinator/vampire, exec/hc" />
				<p class="description"><?php esc_html_e( 'ASC role path of the hosting entity.', 'owbn-board' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Banner Image', 'owbn-board' ); ?></th>
			<td>
				<input type="hidden" id="owbn_event_banner_image_id" name="owbn_event_banner_image_id" value="<?php echo (int) $banner_id; ?>" />
				<div id="owbn-event-banner-preview">
					<?php if ( $banner_url ) : ?>
						<img src="<?php echo esc_url( $banner_url ); ?>" alt="" style="max-width:300px;height:auto;" />
					<?php endif; ?>
				</div>
				<p>
					<button type="button" class="button" id="owbn-event-banner-pick"><?php esc_html_e( 'Select Banner', 'owbn-board' ); ?></button>
					<button type="button" class="button" id="owbn-event-banner-clear"><?php esc_html_e( 'Remove', 'owbn-board' ); ?></button>
				</p>
			</td>
		</tr>
		<tr>
			<th><label for="owbn_event_registration_url"><?php esc_html_e( 'Registration URL', 'owbn-board' ); ?></label></th>
			<td><input type="url" id="owbn_event_registration_url" name="owbn_event_registration_url" class="large-text" value="<?php echo esc_attr( $meta['registration_url'] ); ?>" /></td>
		</tr>
		<tr>
			<th><label for="owbn_event_registration_fee"><?php esc_html_e( 'Registration Fee', 'owbn-board' ); ?></label></th>
			<td>
				<input type="text" id="owbn_event_registration_fee" name="owbn_event_registration_fee" class="regular-text" value="<?php echo esc_attr( $meta['registration_fee'] ); ?>" placeholder="$25 USD, Free, etc." />
			</td>
		</tr>
		<tr>
			<th><label for="owbn_event_max_attendees"><?php esc_html_e( 'Max Attendees', 'owbn-board' ); ?></label></th>
			<td><input type="number" id="owbn_event_max_attendees" name="owbn_event_max_attendees" value="<?php echo esc_attr( $meta['max_attendees'] ); ?>" min="0" /></td>
		</tr>
		<tr>
			<th><label for="owbn_event_website"><?php esc_html_e( 'Website', 'owbn-board' ); ?></label></th>
			<td><input type="url" id="owbn_event_website" name="owbn_event_website" class="large-text" value="<?php echo esc_attr( $meta['website'] ); ?>" /></td>
		</tr>
	</table>

	<script>
	(function ($) {
		$(function () {
			var frame;
			$('#owbn-event-banner-pick').on('click', function (e) {
				e.preventDefault();
				if (frame) { frame.open(); return; }
				frame = wp.media({
					title: '<?php echo esc_js( __( 'Select Banner Image', 'owbn-board' ) ); ?>',
					button: { text: '<?php echo esc_js( __( 'Use this image', 'owbn-board' ) ); ?>' },
					library: { type: 'image' },
					multiple: false
				});
				frame.on('select', function () {
					var att = frame.state().get('selection').first().toJSON();
					$('#owbn_event_banner_image_id').val(att.id);
					var previewUrl = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
					$('#owbn-event-banner-preview').html('<img src="' + previewUrl + '" alt="" style="max-width:300px;height:auto;" />');
				});
				frame.open();
			});
			$('#owbn-event-banner-clear').on('click', function (e) {
				e.preventDefault();
				$('#owbn_event_banner_image_id').val(0);
				$('#owbn-event-banner-preview').empty();
			});
		});
	})(jQuery);
	</script>
	<?php
}

/**
 * Save metabox fields on post save.
 */
function owbn_board_events_save_post( $post_id, $post ) {
	if ( 'owbn_event' !== $post->post_type ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST['owbn_board_events_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['owbn_board_events_nonce'] ), 'owbn_board_events_save_meta' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( ! owbn_board_events_user_can_create( get_current_user_id() ) ) {
		if ( 'publish' === $post->post_status ) {
			wp_update_post( [ 'ID' => $post_id, 'post_status' => 'pending' ] );
		}
		return;
	}

	$tz_input = isset( $_POST['owbn_event_timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['owbn_event_timezone'] ) ) : 'UTC';

	$data = [
		'tagline'          => isset( $_POST['owbn_event_tagline'] ) ? sanitize_text_field( wp_unslash( $_POST['owbn_event_tagline'] ) ) : '',
		'start_dt'         => owbn_board_events_from_local_input( wp_unslash( $_POST['owbn_event_start_dt'] ?? '' ), $tz_input ),
		'end_dt'           => owbn_board_events_from_local_input( wp_unslash( $_POST['owbn_event_end_dt'] ?? '' ), $tz_input ),
		'timezone'         => $tz_input,
		'location'         => isset( $_POST['owbn_event_location'] ) ? sanitize_text_field( wp_unslash( $_POST['owbn_event_location'] ) ) : '',
		'host_scope'       => isset( $_POST['owbn_event_host_scope'] ) ? sanitize_text_field( wp_unslash( $_POST['owbn_event_host_scope'] ) ) : '',
		'banner_image_id'  => isset( $_POST['owbn_event_banner_image_id'] ) ? absint( $_POST['owbn_event_banner_image_id'] ) : 0,
		'registration_url' => isset( $_POST['owbn_event_registration_url'] ) ? esc_url_raw( wp_unslash( $_POST['owbn_event_registration_url'] ) ) : '',
		'registration_fee' => isset( $_POST['owbn_event_registration_fee'] ) ? sanitize_text_field( wp_unslash( $_POST['owbn_event_registration_fee'] ) ) : '',
		'max_attendees'    => isset( $_POST['owbn_event_max_attendees'] ) ? absint( $_POST['owbn_event_max_attendees'] ) : 0,
		'website'          => isset( $_POST['owbn_event_website'] ) ? esc_url_raw( wp_unslash( $_POST['owbn_event_website'] ) ) : '',
	];

	owbn_board_events_save_meta( $post_id, $data );
}

/**
 * Convert a stored UTC datetime to a datetime-local input value in the given timezone.
 */
function owbn_board_events_to_local_input( $utc_dt ) {
	if ( empty( $utc_dt ) ) {
		return '';
	}
	try {
		$dt = new DateTime( $utc_dt, new DateTimeZone( 'UTC' ) );
		return $dt->format( 'Y-m-d\TH:i' );
	} catch ( Exception $e ) {
		return '';
	}
}

/**
 * Convert a datetime-local input value (in given tz) to UTC datetime string.
 */
function owbn_board_events_from_local_input( $input, $tz_name ) {
	$input = (string) $input;
	if ( empty( $input ) ) {
		return '';
	}
	try {
		$tz = new DateTimeZone( $tz_name );
		$dt = new DateTime( $input, $tz );
		$dt->setTimezone( new DateTimeZone( 'UTC' ) );
		return $dt->format( 'Y-m-d H:i:s' );
	} catch ( Exception $e ) {
		return '';
	}
}
