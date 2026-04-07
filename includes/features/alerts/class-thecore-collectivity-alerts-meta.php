<?php
/**
 * Alerts meta boxes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Alerts_Meta {
	/**
	 * Nonce action and field.
	 */
	const NONCE_ACTION = 'bellevue_alert_meta_action';
	const NONCE_NAME   = 'bellevue_alert_meta_nonce';

	/**
	 * Alert meta keys.
	 */
	const META_ENABLED    = '_bellevue_alert_enabled';
	const META_START      = '_bellevue_alert_start';
	const META_START_TS   = '_bellevue_alert_start_ts';
	const META_END        = '_bellevue_alert_end';
	const META_END_TS     = '_bellevue_alert_end_ts';
	const META_SEVERITY   = '_bellevue_alert_severity';
	const META_MESSAGE    = '_bellevue_alert_message';
	const META_LINK_URL   = '_bellevue_alert_link_url';
	const META_LINK_LABEL = '_bellevue_alert_link_label';
	const META_SORT_ORDER = '_bellevue_alert_sort_order';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
		add_action( 'save_post_' . TheCore_Collectivity_Alerts_Post_Type::POST_TYPE, array( $this, 'save_metabox' ), 10, 2 );
	}

	/**
	 * Register metabox.
	 */
	public function register_metabox() {
		add_meta_box(
			'bellevue-alert-settings',
			__( 'Parametres de l alerte', 'bellevue' ),
			array( $this, 'render_metabox' ),
			TheCore_Collectivity_Alerts_Post_Type::POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Render metabox.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_metabox( $post ) {
		$enabled    = (string) get_post_meta( $post->ID, self::META_ENABLED, true );
		$start      = (string) get_post_meta( $post->ID, self::META_START, true );
		$end        = (string) get_post_meta( $post->ID, self::META_END, true );
		$severity   = (string) get_post_meta( $post->ID, self::META_SEVERITY, true );
		$message    = (string) get_post_meta( $post->ID, self::META_MESSAGE, true );
		$link_url   = (string) get_post_meta( $post->ID, self::META_LINK_URL, true );
		$link_label = (string) get_post_meta( $post->ID, self::META_LINK_LABEL, true );
		$sort_order = (string) get_post_meta( $post->ID, self::META_SORT_ORDER, true );

		if ( '' === $enabled ) {
			$enabled = '1';
		}

		if ( '' === $severity ) {
			$severity = 'warning';
		}

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<p>
			<label>
				<input type="checkbox" name="bellevue_alert_enabled" value="1" <?php checked( '1', $enabled ); ?> />
				<?php esc_html_e( 'Activer cette alerte', 'bellevue' ); ?>
			</label>
		</p>
		<p>
			<label for="bellevue-alert-message"><strong><?php esc_html_e( 'Message', 'bellevue' ); ?></strong></label><br />
			<textarea id="bellevue-alert-message" name="bellevue_alert_message" class="widefat" rows="4"><?php echo esc_textarea( $message ); ?></textarea>
		</p>
		<p>
			<label for="bellevue-alert-severity"><strong><?php esc_html_e( 'Niveau', 'bellevue' ); ?></strong></label><br />
			<select id="bellevue-alert-severity" name="bellevue_alert_severity" class="widefat">
				<option value="info" <?php selected( 'info', $severity ); ?>><?php esc_html_e( 'Information', 'bellevue' ); ?></option>
				<option value="warning" <?php selected( 'warning', $severity ); ?>><?php esc_html_e( 'Attention', 'bellevue' ); ?></option>
				<option value="critical" <?php selected( 'critical', $severity ); ?>><?php esc_html_e( 'Critique', 'bellevue' ); ?></option>
			</select>
		</p>
		<p>
			<label for="bellevue-alert-start"><strong><?php esc_html_e( 'Debut de diffusion', 'bellevue' ); ?></strong></label><br />
			<input type="datetime-local" id="bellevue-alert-start" name="bellevue_alert_start" class="widefat" value="<?php echo esc_attr( $start ); ?>" />
		</p>
		<p>
			<label for="bellevue-alert-end"><strong><?php esc_html_e( 'Fin de diffusion', 'bellevue' ); ?></strong></label><br />
			<input type="datetime-local" id="bellevue-alert-end" name="bellevue_alert_end" class="widefat" value="<?php echo esc_attr( $end ); ?>" />
		</p>
		<p>
			<label for="bellevue-alert-link-url"><strong><?php esc_html_e( 'Lien optionnel', 'bellevue' ); ?></strong></label><br />
			<input type="url" id="bellevue-alert-link-url" name="bellevue_alert_link_url" class="widefat" value="<?php echo esc_attr( $link_url ); ?>" />
		</p>
		<p>
			<label for="bellevue-alert-link-label"><strong><?php esc_html_e( 'Label du lien', 'bellevue' ); ?></strong></label><br />
			<input type="text" id="bellevue-alert-link-label" name="bellevue_alert_link_label" class="widefat" value="<?php echo esc_attr( $link_label ); ?>" />
		</p>
		<p>
			<label for="bellevue-alert-sort-order"><strong><?php esc_html_e( 'Ordre d affichage', 'bellevue' ); ?></strong></label><br />
			<input type="number" id="bellevue-alert-sort-order" name="bellevue_alert_sort_order" class="small-text" value="<?php echo esc_attr( $sort_order ); ?>" step="1" />
		</p>
		<?php
	}

	/**
	 * Save metabox values.
	 *
	 * @param int     $post_id Post id.
	 * @param WP_Post $post    Post object.
	 */
	public function save_metabox( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( TheCore_Collectivity_Alerts_Post_Type::POST_TYPE !== $post->post_type ) {
			return;
		}

		$enabled    = isset( $_POST['bellevue_alert_enabled'] ) ? '1' : '0';
		$start      = $this->sanitize_datetime_local( isset( $_POST['bellevue_alert_start'] ) ? wp_unslash( $_POST['bellevue_alert_start'] ) : '' );
		$end        = $this->sanitize_datetime_local( isset( $_POST['bellevue_alert_end'] ) ? wp_unslash( $_POST['bellevue_alert_end'] ) : '' );
		$severity   = isset( $_POST['bellevue_alert_severity'] ) ? sanitize_key( wp_unslash( $_POST['bellevue_alert_severity'] ) ) : 'warning';
		$message    = isset( $_POST['bellevue_alert_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bellevue_alert_message'] ) ) : '';
		$link_url   = isset( $_POST['bellevue_alert_link_url'] ) ? esc_url_raw( wp_unslash( $_POST['bellevue_alert_link_url'] ) ) : '';
		$link_label = isset( $_POST['bellevue_alert_link_label'] ) ? sanitize_text_field( wp_unslash( $_POST['bellevue_alert_link_label'] ) ) : '';
		$sort_order = isset( $_POST['bellevue_alert_sort_order'] ) ? intval( wp_unslash( $_POST['bellevue_alert_sort_order'] ) ) : 0;

		if ( ! in_array( $severity, array( 'info', 'warning', 'critical' ), true ) ) {
			$severity = 'warning';
		}

		update_post_meta( $post_id, self::META_ENABLED, $enabled );
		update_post_meta( $post_id, self::META_SEVERITY, $severity );
		update_post_meta( $post_id, self::META_MESSAGE, $message );
		update_post_meta( $post_id, self::META_LINK_URL, $link_url );
		update_post_meta( $post_id, self::META_LINK_LABEL, $link_label );
		update_post_meta( $post_id, self::META_SORT_ORDER, $sort_order );

		$this->save_datetime_meta( $post_id, self::META_START, self::META_START_TS, $start );
		$this->save_datetime_meta( $post_id, self::META_END, self::META_END_TS, $end );
	}

	/**
	 * Persist datetime and timestamp meta.
	 *
	 * @param int    $post_id     Post id.
	 * @param string $meta_key    Source meta key.
	 * @param string $meta_ts_key Timestamp meta key.
	 * @param string $value       Date value.
	 */
	private function save_datetime_meta( $post_id, $meta_key, $meta_ts_key, $value ) {
		if ( '' === $value ) {
			delete_post_meta( $post_id, $meta_key );
			delete_post_meta( $post_id, $meta_ts_key );
			return;
		}

		$timestamp = $this->datetime_local_to_timestamp( $value );
		if ( null === $timestamp ) {
			delete_post_meta( $post_id, $meta_key );
			delete_post_meta( $post_id, $meta_ts_key );
			return;
		}

		update_post_meta( $post_id, $meta_key, $value );
		update_post_meta( $post_id, $meta_ts_key, $timestamp );
	}

	/**
	 * Sanitize datetime-local input.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function sanitize_datetime_local( $value ) {
		$value = trim( sanitize_text_field( $value ) );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * Convert datetime-local string to timestamp.
	 *
	 * @param string $value Datetime local value.
	 * @return int|null
	 */
	private function datetime_local_to_timestamp( $value ) {
		$timezone = wp_timezone();
		$date     = date_create_immutable_from_format( 'Y-m-d\TH:i', $value, $timezone );

		if ( false === $date ) {
			return null;
		}

		return $date->getTimestamp();
	}
}
