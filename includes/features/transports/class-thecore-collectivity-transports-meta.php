<?php
/**
 * Transport meta boxes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Transports_Meta {
	/**
	 * Nonce config.
	 */
	const NONCE_ACTION = 'bellevue_transport_meta_action';
	const NONCE_NAME   = 'bellevue_transport_meta_nonce';

	/**
	 * Line meta keys.
	 */
	const META_LINE_CODE        = '_bellevue_transport_line_code';
	const META_ROUTE_LABEL      = '_bellevue_transport_route_label';
	const META_FREQUENCY_LABEL  = '_bellevue_transport_frequency_label';
	const META_HOURS_LABEL      = '_bellevue_transport_hours_label';
	const META_DATA_SOURCE      = '_bellevue_transport_data_source';
	const META_GTFS_ROUTE_IDS   = '_bellevue_transport_gtfs_route_ids';
	const META_GTFS_SHORT_NAMES = '_bellevue_transport_gtfs_short_names';
	const META_PRIMARY_STOP_IDS = '_bellevue_transport_primary_stop_ids';
	const META_REFERENCE_STOPS  = '_bellevue_transport_reference_stops';
	const META_GTFS_PROVIDER_KEY = '_bellevue_transport_gtfs_provider_key';
	const META_GTFS_SYNC_ROUTE_KEY = '_bellevue_transport_gtfs_sync_route_key';
	const META_GTFS_SYNC_STOP_KEY  = '_bellevue_transport_gtfs_sync_stop_key';
	const META_IS_ACCESSIBLE    = '_bellevue_transport_is_accessible';
	const META_SERVICE_DAYS     = '_bellevue_transport_service_days';
	const META_EXTERNAL_URL     = '_bellevue_transport_external_url';
	const META_SEARCH_KEYWORDS  = '_bellevue_transport_search_keywords';
	const META_SORT_ORDER       = '_bellevue_transport_sort_order';

	/**
	 * Place meta keys.
	 */
	const META_PLACE_SUBTITLE    = '_bellevue_transport_place_subtitle';
	const META_ADDRESS           = '_bellevue_transport_address';
	const META_LATITUDE          = '_bellevue_transport_latitude';
	const META_LONGITUDE         = '_bellevue_transport_longitude';
	const META_GTFS_STOP_IDS     = '_bellevue_transport_gtfs_stop_ids';
	const META_RELATED_LINES     = '_bellevue_transport_related_lines';
	const META_PARKING_TYPE      = '_bellevue_transport_parking_type';
	const META_TOTAL_PLACES      = '_bellevue_transport_total_places';
	const META_AVAILABLE_PLACES  = '_bellevue_transport_available_places';
	const META_PMR_PLACES        = '_bellevue_transport_pmr_places';
	const META_IS_FREE           = '_bellevue_transport_is_free';
	const META_BIKES_AVAILABLE   = '_bellevue_transport_bikes_available';
	const META_SLOTS_TOTAL       = '_bellevue_transport_slots_total';
	const META_ELECTRIC_BIKES    = '_bellevue_transport_electric_bikes';
	const META_TRAIN_DEPARTURES  = '_bellevue_transport_train_departures';
	const META_TRAIN_INFORMATION = '_bellevue_transport_train_information';

	/**
	 * Data sources.
	 */
	const DATA_SOURCE_MANUAL      = 'manual';
	const DATA_SOURCE_GTFS        = 'gtfs';
	const DATA_SOURCE_IDFM_GTFS   = 'idfm_gtfs';
	const DATA_SOURCE_CSV         = 'csv';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'add_meta_boxes', array( $this, 'register_metaboxes' ) );
		add_action( 'save_post_' . TheCore_Collectivity_Transports_Post_Types::POST_TYPE_LINE, array( $this, 'save_line_metabox' ), 10, 2 );
		add_action( 'save_post_' . TheCore_Collectivity_Transports_Post_Types::POST_TYPE_PLACE, array( $this, 'save_place_metabox' ), 10, 2 );
	}

	/**
	 * Register metaboxes.
	 */
	public function register_metaboxes() {
		add_meta_box(
			'bellevue-transport-line-settings',
			__( 'Parametres de la ligne', 'bellevue' ),
			array( $this, 'render_line_metabox' ),
			TheCore_Collectivity_Transports_Post_Types::POST_TYPE_LINE,
			'normal',
			'default'
		);

		add_meta_box(
			'bellevue-transport-place-settings',
			__( 'Parametres du lieu', 'bellevue' ),
			array( $this, 'render_place_metabox' ),
			TheCore_Collectivity_Transports_Post_Types::POST_TYPE_PLACE,
			'normal',
			'default'
		);
	}

	/**
	 * Render line metabox.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_line_metabox( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$line_code       = (string) get_post_meta( $post->ID, self::META_LINE_CODE, true );
		$route_label     = (string) get_post_meta( $post->ID, self::META_ROUTE_LABEL, true );
		$frequency_label = (string) get_post_meta( $post->ID, self::META_FREQUENCY_LABEL, true );
		$hours_label     = (string) get_post_meta( $post->ID, self::META_HOURS_LABEL, true );
		$data_source     = (string) get_post_meta( $post->ID, self::META_DATA_SOURCE, true );
		$gtfs_provider_key = (string) get_post_meta( $post->ID, self::META_GTFS_PROVIDER_KEY, true );
		$gtfs_route_ids  = (string) get_post_meta( $post->ID, self::META_GTFS_ROUTE_IDS, true );
		$gtfs_short_names = (string) get_post_meta( $post->ID, self::META_GTFS_SHORT_NAMES, true );
			$primary_stop_ids = (string) get_post_meta( $post->ID, self::META_PRIMARY_STOP_IDS, true );
			$is_accessible   = (string) get_post_meta( $post->ID, self::META_IS_ACCESSIBLE, true );
			$service_days    = get_post_meta( $post->ID, self::META_SERVICE_DAYS, true );
			$external_url    = (string) get_post_meta( $post->ID, self::META_EXTERNAL_URL, true );
			$search_keywords = (string) get_post_meta( $post->ID, self::META_SEARCH_KEYWORDS, true );
			$sort_order      = (string) get_post_meta( $post->ID, self::META_SORT_ORDER, true );
			$schedule_repository   = new TheCore_Collectivity_Transports_Schedule_Repository();
			$gtfs_sources          = $schedule_repository->get_gtfs_sources();
			$reference_stop_map    = $schedule_repository->get_line_reference_stops( $post->ID );
			$reference_stop_config = $schedule_repository->get_line_reference_stop_options( $post->ID );
			$service_days    = is_array( $service_days ) ? $service_days : array();
			$data_source     = self::normalize_data_source( $data_source );
		?>
		<p>
			<label for="bellevue-transport-line-code"><strong><?php esc_html_e( 'Code de ligne', 'bellevue' ); ?></strong></label><br />
			<input type="text" id="bellevue-transport-line-code" name="bellevue_transport_line_code" class="regular-text" value="<?php echo esc_attr( $line_code ); ?>" />
		</p>
		<p>
			<label for="bellevue-transport-route-label"><strong><?php esc_html_e( 'Libelle du trajet', 'bellevue' ); ?></strong></label><br />
			<input type="text" id="bellevue-transport-route-label" name="bellevue_transport_route_label" class="widefat" value="<?php echo esc_attr( $route_label ); ?>" />
		</p>
		<p>
			<label for="bellevue-transport-frequency-label"><strong><?php esc_html_e( 'Frequence', 'bellevue' ); ?></strong></label><br />
			<input type="text" id="bellevue-transport-frequency-label" name="bellevue_transport_frequency_label" class="regular-text" value="<?php echo esc_attr( $frequency_label ); ?>" />
		</p>
		<p>
			<label for="bellevue-transport-hours-label"><strong><?php esc_html_e( 'Horaires', 'bellevue' ); ?></strong></label><br />
			<input type="text" id="bellevue-transport-hours-label" name="bellevue_transport_hours_label" class="widefat" value="<?php echo esc_attr( $hours_label ); ?>" />
		</p>
		<hr />
		<p><strong><?php esc_html_e( 'Source horaires', 'bellevue' ); ?></strong></p>
		<p>
			<label for="bellevue-transport-data-source"><strong><?php esc_html_e( 'Mode de gestion', 'bellevue' ); ?></strong></label><br />
			<select id="bellevue-transport-data-source" name="bellevue_transport_data_source" class="regular-text">
				<option value="<?php echo esc_attr( self::DATA_SOURCE_MANUAL ); ?>" <?php selected( self::DATA_SOURCE_MANUAL, $data_source ); ?>><?php esc_html_e( 'Manuel', 'bellevue' ); ?></option>
				<option value="<?php echo esc_attr( self::DATA_SOURCE_GTFS ); ?>" <?php selected( self::DATA_SOURCE_GTFS, $data_source ); ?>><?php esc_html_e( 'GTFS', 'bellevue' ); ?></option>
				<option value="<?php echo esc_attr( self::DATA_SOURCE_CSV ); ?>" <?php selected( self::DATA_SOURCE_CSV, $data_source ); ?>><?php esc_html_e( 'CSV', 'bellevue' ); ?></option>
			</select>
		</p>
		<p>
			<label for="bellevue-transport-gtfs-provider-key"><strong><?php esc_html_e( 'Source GTFS', 'bellevue' ); ?></strong></label><br />
			<select id="bellevue-transport-gtfs-provider-key" name="bellevue_transport_gtfs_provider_key" class="regular-text">
				<option value=""><?php esc_html_e( 'Aucune source', 'bellevue' ); ?></option>
				<?php foreach ( $gtfs_sources as $gtfs_source ) : ?>
					<option value="<?php echo esc_attr( $gtfs_source['provider_key'] ); ?>" <?php selected( $gtfs_provider_key, $gtfs_source['provider_key'] ); ?>><?php echo esc_html( $gtfs_source['provider_label'] . ' (' . $gtfs_source['provider_key'] . ')' ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="bellevue-transport-gtfs-route-ids"><strong><?php esc_html_e( 'GTFS route_id', 'bellevue' ); ?></strong></label><br />
			<textarea id="bellevue-transport-gtfs-route-ids" name="bellevue_transport_gtfs_route_ids" class="widefat" rows="3"><?php echo esc_textarea( $gtfs_route_ids ); ?></textarea>
			<span class="description"><?php esc_html_e( 'Un identifiant par ligne ou separe par virgule.', 'bellevue' ); ?></span>
		</p>
		<p>
			<label for="bellevue-transport-gtfs-short-names"><strong><?php esc_html_e( 'GTFS route_short_name', 'bellevue' ); ?></strong></label><br />
			<textarea id="bellevue-transport-gtfs-short-names" name="bellevue_transport_gtfs_short_names" class="widefat" rows="3"><?php echo esc_textarea( $gtfs_short_names ); ?></textarea>
			<span class="description"><?php esc_html_e( 'Ex. 2228, 211, RER A.', 'bellevue' ); ?></span>
		</p>
			<p>
				<label for="bellevue-transport-primary-stop-ids"><strong><?php esc_html_e( 'GTFS stop_id suivis', 'bellevue' ); ?></strong></label><br />
				<textarea id="bellevue-transport-primary-stop-ids" name="bellevue_transport_primary_stop_ids" class="widefat" rows="3"><?php echo esc_textarea( $primary_stop_ids ); ?></textarea>
				<span class="description"><?php esc_html_e( 'Stops de reference pour importer et afficher les horaires de cette ligne.', 'bellevue' ); ?></span>
			</p>
			<p><strong><?php esc_html_e( 'Arrets de reference par direction', 'bellevue' ); ?></strong></p>
			<?php if ( ! empty( $reference_stop_config['directions'] ) ) : ?>
				<?php foreach ( $reference_stop_config['directions'] as $direction_config ) : ?>
					<?php
					$direction_key         = isset( $direction_config['key'] ) ? (string) $direction_config['key'] : '';
					$direction_key_encoded = rawurlencode( $direction_key );
					$current_reference     = isset( $reference_stop_map[ $direction_key ] ) ? (string) $reference_stop_map[ $direction_key ] : '';
					$field_id              = 'bellevue-transport-reference-stop-' . md5( $direction_key );
					?>
					<p>
						<label for="<?php echo esc_attr( $field_id ); ?>"><strong><?php echo esc_html( $direction_config['label'] ); ?></strong></label><br />
						<select id="<?php echo esc_attr( $field_id ); ?>" name="bellevue_transport_reference_stops[<?php echo esc_attr( $direction_key_encoded ); ?>]" class="widefat">
							<option value=""><?php esc_html_e( 'Aucun arrêt de référence', 'bellevue' ); ?></option>
							<?php foreach ( $direction_config['stopOptions'] as $stop_option ) : ?>
								<option value="<?php echo esc_attr( $stop_option['stopId'] ); ?>" <?php selected( $current_reference, $stop_option['stopId'] ); ?>><?php echo esc_html( $stop_option['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<span class="description"><?php esc_html_e( 'Cet arrêt exact sera préselectionné dans l interface pour cette direction.', 'bellevue' ); ?></span>
					</p>
				<?php endforeach; ?>
			<?php else : ?>
				<p><em><?php esc_html_e( 'Les directions apparaîtront ici une fois les horaires GTFS importés pour la ligne.', 'bellevue' ); ?></em></p>
			<?php endif; ?>
			<p>
				<strong><?php esc_html_e( 'Jours de service', 'bellevue' ); ?></strong><br />
				<label><input type="checkbox" name="bellevue_transport_service_days[]" value="semaine" <?php checked( in_array( 'semaine', $service_days, true ) ); ?> /> <?php esc_html_e( 'Lundi - Vendredi', 'bellevue' ); ?></label><br />
			<label><input type="checkbox" name="bellevue_transport_service_days[]" value="samedi" <?php checked( in_array( 'samedi', $service_days, true ) ); ?> /> <?php esc_html_e( 'Samedi', 'bellevue' ); ?></label><br />
			<label><input type="checkbox" name="bellevue_transport_service_days[]" value="dimanche" <?php checked( in_array( 'dimanche', $service_days, true ) ); ?> /> <?php esc_html_e( 'Dimanche', 'bellevue' ); ?></label>
		</p>
		<p>
			<label><input type="checkbox" name="bellevue_transport_is_accessible" value="1" <?php checked( '1', $is_accessible ); ?> /> <?php esc_html_e( 'Accessible PMR', 'bellevue' ); ?></label>
		</p>
		<p>
			<label for="bellevue-transport-external-url"><strong><?php esc_html_e( 'Lien externe', 'bellevue' ); ?></strong></label><br />
			<input type="url" id="bellevue-transport-external-url" name="bellevue_transport_external_url" class="widefat" value="<?php echo esc_attr( $external_url ); ?>" />
		</p>
		<p>
			<label for="bellevue-transport-search-keywords"><strong><?php esc_html_e( 'Mots-cles de recherche', 'bellevue' ); ?></strong></label><br />
			<input type="text" id="bellevue-transport-search-keywords" name="bellevue_transport_search_keywords" class="widefat" value="<?php echo esc_attr( $search_keywords ); ?>" />
		</p>
		<p>
			<label for="bellevue-transport-sort-order"><strong><?php esc_html_e( 'Ordre d affichage', 'bellevue' ); ?></strong></label><br />
			<input type="number" id="bellevue-transport-sort-order" name="bellevue_transport_sort_order" class="small-text" value="<?php echo esc_attr( $sort_order ); ?>" step="1" />
		</p>
		<?php
	}

	/**
	 * Render place metabox.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_place_metabox( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$subtitle          = (string) get_post_meta( $post->ID, self::META_PLACE_SUBTITLE, true );
		$address           = (string) get_post_meta( $post->ID, self::META_ADDRESS, true );
		$latitude          = (string) get_post_meta( $post->ID, self::META_LATITUDE, true );
		$longitude         = (string) get_post_meta( $post->ID, self::META_LONGITUDE, true );
		$gtfs_stop_ids     = (string) get_post_meta( $post->ID, self::META_GTFS_STOP_IDS, true );
		$gtfs_provider_key = (string) get_post_meta( $post->ID, self::META_GTFS_PROVIDER_KEY, true );
		$is_accessible     = (string) get_post_meta( $post->ID, self::META_IS_ACCESSIBLE, true );
		$external_url      = (string) get_post_meta( $post->ID, self::META_EXTERNAL_URL, true );
		$search_keywords   = (string) get_post_meta( $post->ID, self::META_SEARCH_KEYWORDS, true );
		$sort_order        = (string) get_post_meta( $post->ID, self::META_SORT_ORDER, true );
		$related_lines     = get_post_meta( $post->ID, self::META_RELATED_LINES, true );
		$parking_type      = (string) get_post_meta( $post->ID, self::META_PARKING_TYPE, true );
		$total_places      = (string) get_post_meta( $post->ID, self::META_TOTAL_PLACES, true );
		$available_places  = (string) get_post_meta( $post->ID, self::META_AVAILABLE_PLACES, true );
		$pmr_places        = (string) get_post_meta( $post->ID, self::META_PMR_PLACES, true );
		$is_free           = (string) get_post_meta( $post->ID, self::META_IS_FREE, true );
		$bikes_available   = (string) get_post_meta( $post->ID, self::META_BIKES_AVAILABLE, true );
		$slots_total       = (string) get_post_meta( $post->ID, self::META_SLOTS_TOTAL, true );
		$electric_bikes    = (string) get_post_meta( $post->ID, self::META_ELECTRIC_BIKES, true );
		$train_departures  = (string) get_post_meta( $post->ID, self::META_TRAIN_DEPARTURES, true );
		$train_information = (string) get_post_meta( $post->ID, self::META_TRAIN_INFORMATION, true );
		$related_lines     = is_array( $related_lines ) ? array_map( 'intval', $related_lines ) : array();
		$lines             = $this->get_available_lines();
		$schedule_repository = new TheCore_Collectivity_Transports_Schedule_Repository();
		$gtfs_sources        = $schedule_repository->get_gtfs_sources();
		?>
		<p>
			<em><?php esc_html_e( 'Assignez le mode de transport dans la boite de taxonomie "Modes".', 'bellevue' ); ?></em>
		</p>
		<p>
			<label for="bellevue-transport-place-subtitle"><strong><?php esc_html_e( 'Sous-titre', 'bellevue' ); ?></strong></label><br />
			<input type="text" id="bellevue-transport-place-subtitle" name="bellevue_transport_place_subtitle" class="widefat" value="<?php echo esc_attr( $subtitle ); ?>" />
		</p>
		<p>
			<label for="bellevue-transport-address"><strong><?php esc_html_e( 'Adresse / description courte', 'bellevue' ); ?></strong></label><br />
			<input type="text" id="bellevue-transport-address" name="bellevue_transport_address" class="widefat" value="<?php echo esc_attr( $address ); ?>" />
		</p>
		<p>
			<label for="bellevue-transport-latitude"><strong><?php esc_html_e( 'Latitude', 'bellevue' ); ?></strong></label><br />
			<input type="text" id="bellevue-transport-latitude" name="bellevue_transport_latitude" class="regular-text" value="<?php echo esc_attr( $latitude ); ?>" />
		</p>
		<p>
			<label for="bellevue-transport-longitude"><strong><?php esc_html_e( 'Longitude', 'bellevue' ); ?></strong></label><br />
			<input type="text" id="bellevue-transport-longitude" name="bellevue_transport_longitude" class="regular-text" value="<?php echo esc_attr( $longitude ); ?>" />
		</p>
		<p>
			<label for="bellevue-transport-gtfs-stop-ids"><strong><?php esc_html_e( 'GTFS stop_id', 'bellevue' ); ?></strong></label><br />
			<textarea id="bellevue-transport-gtfs-stop-ids" name="bellevue_transport_gtfs_stop_ids" class="widefat" rows="3"><?php echo esc_textarea( $gtfs_stop_ids ); ?></textarea>
			<span class="description"><?php esc_html_e( 'Stops suivis pour ce lieu. Un par ligne ou separes par virgule.', 'bellevue' ); ?></span>
		</p>
		<p>
			<label for="bellevue-transport-place-gtfs-provider-key"><strong><?php esc_html_e( 'Source GTFS', 'bellevue' ); ?></strong></label><br />
			<select id="bellevue-transport-place-gtfs-provider-key" name="bellevue_transport_gtfs_provider_key" class="regular-text">
				<option value=""><?php esc_html_e( 'Aucune source', 'bellevue' ); ?></option>
				<?php foreach ( $gtfs_sources as $gtfs_source ) : ?>
					<option value="<?php echo esc_attr( $gtfs_source['provider_key'] ); ?>" <?php selected( $gtfs_provider_key, $gtfs_source['provider_key'] ); ?>><?php echo esc_html( $gtfs_source['provider_label'] . ' (' . $gtfs_source['provider_key'] . ')' ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label><input type="checkbox" name="bellevue_transport_is_accessible" value="1" <?php checked( '1', $is_accessible ); ?> /> <?php esc_html_e( 'Accessible PMR', 'bellevue' ); ?></label>
		</p>
		<p>
			<label for="bellevue-transport-related-lines"><strong><?php esc_html_e( 'Lignes reliees (arret de bus)', 'bellevue' ); ?></strong></label><br />
			<select id="bellevue-transport-related-lines" name="bellevue_transport_related_lines[]" class="widefat" multiple size="6">
				<?php foreach ( $lines as $line ) : ?>
					<?php $provider_label = (string) get_post_meta( $line->ID, self::META_GTFS_PROVIDER_KEY, true ); ?>
					<option value="<?php echo esc_attr( $line->ID ); ?>" <?php selected( in_array( (int) $line->ID, $related_lines, true ) ); ?>><?php echo esc_html( get_the_title( $line ) . ( $provider_label ? ' [' . $provider_label . ']' : '' ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<hr />
		<p><strong><?php esc_html_e( 'Donnees parking', 'bellevue' ); ?></strong></p>
		<p>
			<label for="bellevue-transport-parking-type"><?php esc_html_e( 'Type de stationnement', 'bellevue' ); ?></label><br />
			<input type="text" id="bellevue-transport-parking-type" name="bellevue_transport_parking_type" class="regular-text" value="<?php echo esc_attr( $parking_type ); ?>" />
		</p>
		<p>
			<label for="bellevue-transport-total-places"><?php esc_html_e( 'Nombre total de places', 'bellevue' ); ?></label><br />
			<input type="number" id="bellevue-transport-total-places" name="bellevue_transport_total_places" class="small-text" value="<?php echo esc_attr( $total_places ); ?>" min="0" step="1" />
		</p>
		<p>
			<label for="bellevue-transport-available-places"><?php esc_html_e( 'Places disponibles', 'bellevue' ); ?></label><br />
			<input type="number" id="bellevue-transport-available-places" name="bellevue_transport_available_places" class="small-text" value="<?php echo esc_attr( $available_places ); ?>" min="0" step="1" />
		</p>
		<p>
			<label for="bellevue-transport-pmr-places"><?php esc_html_e( 'Places PMR', 'bellevue' ); ?></label><br />
			<input type="number" id="bellevue-transport-pmr-places" name="bellevue_transport_pmr_places" class="small-text" value="<?php echo esc_attr( $pmr_places ); ?>" min="0" step="1" />
		</p>
		<p>
			<label><input type="checkbox" name="bellevue_transport_is_free" value="1" <?php checked( '1', $is_free ); ?> /> <?php esc_html_e( 'Gratuit', 'bellevue' ); ?></label>
		</p>
		<hr />
		<p><strong><?php esc_html_e( 'Donnees velo', 'bellevue' ); ?></strong></p>
		<p>
			<label for="bellevue-transport-bikes-available"><?php esc_html_e( 'Velos disponibles', 'bellevue' ); ?></label><br />
			<input type="number" id="bellevue-transport-bikes-available" name="bellevue_transport_bikes_available" class="small-text" value="<?php echo esc_attr( $bikes_available ); ?>" min="0" step="1" />
		</p>
		<p>
			<label for="bellevue-transport-slots-total"><?php esc_html_e( 'Capacite totale', 'bellevue' ); ?></label><br />
			<input type="number" id="bellevue-transport-slots-total" name="bellevue_transport_slots_total" class="small-text" value="<?php echo esc_attr( $slots_total ); ?>" min="0" step="1" />
		</p>
		<p>
			<label for="bellevue-transport-electric-bikes"><?php esc_html_e( 'Velos electriques', 'bellevue' ); ?></label><br />
			<input type="number" id="bellevue-transport-electric-bikes" name="bellevue_transport_electric_bikes" class="small-text" value="<?php echo esc_attr( $electric_bikes ); ?>" min="0" step="1" />
		</p>
		<hr />
		<p><strong><?php esc_html_e( 'Donnees train', 'bellevue' ); ?></strong></p>
		<p>
			<label for="bellevue-transport-train-departures"><?php esc_html_e( 'Prochains departs (une heure par ligne)', 'bellevue' ); ?></label><br />
			<textarea id="bellevue-transport-train-departures" name="bellevue_transport_train_departures" class="widefat" rows="4"><?php echo esc_textarea( $train_departures ); ?></textarea>
		</p>
		<p>
			<label for="bellevue-transport-train-information"><?php esc_html_e( 'Informations complementaires (une ligne par info)', 'bellevue' ); ?></label><br />
			<textarea id="bellevue-transport-train-information" name="bellevue_transport_train_information" class="widefat" rows="4"><?php echo esc_textarea( $train_information ); ?></textarea>
		</p>
		<hr />
		<p>
			<label for="bellevue-transport-place-url"><strong><?php esc_html_e( 'Lien externe', 'bellevue' ); ?></strong></label><br />
			<input type="url" id="bellevue-transport-place-url" name="bellevue_transport_external_url" class="widefat" value="<?php echo esc_attr( $external_url ); ?>" />
		</p>
		<p>
			<label for="bellevue-transport-place-keywords"><strong><?php esc_html_e( 'Mots-cles de recherche', 'bellevue' ); ?></strong></label><br />
			<input type="text" id="bellevue-transport-place-keywords" name="bellevue_transport_search_keywords" class="widefat" value="<?php echo esc_attr( $search_keywords ); ?>" />
		</p>
		<p>
			<label for="bellevue-transport-place-sort-order"><strong><?php esc_html_e( 'Ordre d affichage', 'bellevue' ); ?></strong></label><br />
			<input type="number" id="bellevue-transport-place-sort-order" name="bellevue_transport_sort_order" class="small-text" value="<?php echo esc_attr( $sort_order ); ?>" step="1" />
		</p>
		<?php
	}

	/**
	 * Save line metabox.
	 *
	 * @param int     $post_id Post id.
	 * @param WP_Post $post    Post object.
	 */
	public function save_line_metabox( $post_id, $post ) {
		if ( ! $this->can_save( $post_id, $post ) ) {
			return;
		}

		update_post_meta( $post_id, self::META_LINE_CODE, sanitize_text_field( wp_unslash( $_POST['bellevue_transport_line_code'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_ROUTE_LABEL, sanitize_text_field( wp_unslash( $_POST['bellevue_transport_route_label'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_FREQUENCY_LABEL, sanitize_text_field( wp_unslash( $_POST['bellevue_transport_frequency_label'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_HOURS_LABEL, sanitize_text_field( wp_unslash( $_POST['bellevue_transport_hours_label'] ?? '' ) ) );
		$data_source = self::normalize_data_source( wp_unslash( $_POST['bellevue_transport_data_source'] ?? self::DATA_SOURCE_MANUAL ) );
		update_post_meta( $post_id, self::META_DATA_SOURCE, $data_source );
		update_post_meta( $post_id, self::META_GTFS_PROVIDER_KEY, sanitize_key( wp_unslash( $_POST['bellevue_transport_gtfs_provider_key'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_GTFS_ROUTE_IDS, sanitize_textarea_field( wp_unslash( $_POST['bellevue_transport_gtfs_route_ids'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_GTFS_SHORT_NAMES, sanitize_textarea_field( wp_unslash( $_POST['bellevue_transport_gtfs_short_names'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_PRIMARY_STOP_IDS, sanitize_textarea_field( wp_unslash( $_POST['bellevue_transport_primary_stop_ids'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_REFERENCE_STOPS, wp_json_encode( $this->sanitize_reference_stop_map( $_POST['bellevue_transport_reference_stops'] ?? array() ) ) );
		update_post_meta( $post_id, self::META_IS_ACCESSIBLE, isset( $_POST['bellevue_transport_is_accessible'] ) ? '1' : '0' );
		update_post_meta( $post_id, self::META_EXTERNAL_URL, esc_url_raw( wp_unslash( $_POST['bellevue_transport_external_url'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_SEARCH_KEYWORDS, sanitize_text_field( wp_unslash( $_POST['bellevue_transport_search_keywords'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_SORT_ORDER, intval( wp_unslash( $_POST['bellevue_transport_sort_order'] ?? 0 ) ) );

		$service_days = isset( $_POST['bellevue_transport_service_days'] ) ? (array) wp_unslash( $_POST['bellevue_transport_service_days'] ) : array();
		$service_days = array_values( array_intersect( array_map( 'sanitize_key', $service_days ), array( 'semaine', 'samedi', 'dimanche' ) ) );
		update_post_meta( $post_id, self::META_SERVICE_DAYS, $service_days );
	}

	/**
	 * Save place metabox.
	 *
	 * @param int     $post_id Post id.
	 * @param WP_Post $post    Post object.
	 */
	public function save_place_metabox( $post_id, $post ) {
		if ( ! $this->can_save( $post_id, $post ) ) {
			return;
		}

		update_post_meta( $post_id, self::META_PLACE_SUBTITLE, sanitize_text_field( wp_unslash( $_POST['bellevue_transport_place_subtitle'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_ADDRESS, sanitize_text_field( wp_unslash( $_POST['bellevue_transport_address'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_LATITUDE, sanitize_text_field( wp_unslash( $_POST['bellevue_transport_latitude'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_LONGITUDE, sanitize_text_field( wp_unslash( $_POST['bellevue_transport_longitude'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_GTFS_PROVIDER_KEY, sanitize_key( wp_unslash( $_POST['bellevue_transport_gtfs_provider_key'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_GTFS_STOP_IDS, sanitize_textarea_field( wp_unslash( $_POST['bellevue_transport_gtfs_stop_ids'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_IS_ACCESSIBLE, isset( $_POST['bellevue_transport_is_accessible'] ) ? '1' : '0' );
		update_post_meta( $post_id, self::META_EXTERNAL_URL, esc_url_raw( wp_unslash( $_POST['bellevue_transport_external_url'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_SEARCH_KEYWORDS, sanitize_text_field( wp_unslash( $_POST['bellevue_transport_search_keywords'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_SORT_ORDER, intval( wp_unslash( $_POST['bellevue_transport_sort_order'] ?? 0 ) ) );
		update_post_meta( $post_id, self::META_PARKING_TYPE, sanitize_text_field( wp_unslash( $_POST['bellevue_transport_parking_type'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_TOTAL_PLACES, intval( wp_unslash( $_POST['bellevue_transport_total_places'] ?? 0 ) ) );
		update_post_meta( $post_id, self::META_AVAILABLE_PLACES, intval( wp_unslash( $_POST['bellevue_transport_available_places'] ?? 0 ) ) );
		update_post_meta( $post_id, self::META_PMR_PLACES, intval( wp_unslash( $_POST['bellevue_transport_pmr_places'] ?? 0 ) ) );
		update_post_meta( $post_id, self::META_IS_FREE, isset( $_POST['bellevue_transport_is_free'] ) ? '1' : '0' );
		update_post_meta( $post_id, self::META_BIKES_AVAILABLE, intval( wp_unslash( $_POST['bellevue_transport_bikes_available'] ?? 0 ) ) );
		update_post_meta( $post_id, self::META_SLOTS_TOTAL, intval( wp_unslash( $_POST['bellevue_transport_slots_total'] ?? 0 ) ) );
		update_post_meta( $post_id, self::META_ELECTRIC_BIKES, intval( wp_unslash( $_POST['bellevue_transport_electric_bikes'] ?? 0 ) ) );
		update_post_meta( $post_id, self::META_TRAIN_DEPARTURES, sanitize_textarea_field( wp_unslash( $_POST['bellevue_transport_train_departures'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_TRAIN_INFORMATION, sanitize_textarea_field( wp_unslash( $_POST['bellevue_transport_train_information'] ?? '' ) ) );

		$related_lines = isset( $_POST['bellevue_transport_related_lines'] ) ? (array) wp_unslash( $_POST['bellevue_transport_related_lines'] ) : array();
		$related_lines = array_values( array_filter( array_map( 'intval', $related_lines ) ) );
		update_post_meta( $post_id, self::META_RELATED_LINES, $related_lines );
	}

	/**
	 * Determine if metabox data can be saved.
	 *
	 * @param int     $post_id Post id.
	 * @param WP_Post $post    Post object.
	 * @return bool
	 */
	private function can_save( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return false;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		return $post instanceof WP_Post;
	}

	/**
	 * Get available line posts for place metabox.
	 *
	 * @return WP_Post[]
	 */
	private function get_available_lines() {
		return get_posts(
			array(
				'post_type'      => TheCore_Collectivity_Transports_Post_Types::POST_TYPE_LINE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * Sanitize posted reference stop mapping.
	 *
	 * @param mixed $value Raw posted value.
	 * @return array
	 */
	private function sanitize_reference_stop_map( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$reference_stops = array();
		foreach ( $value as $direction_key => $stop_id ) {
			$direction_key = rawurldecode( sanitize_text_field( (string) $direction_key ) );
			$stop_id       = sanitize_text_field( wp_unslash( (string) $stop_id ) );
			if ( '' === $direction_key || '' === $stop_id ) {
				continue;
			}

			$reference_stops[ $direction_key ] = $stop_id;
		}

		return $reference_stops;
	}

	/**
	 * Normalize a posted or stored data source.
	 *
	 * @param mixed $value Raw source value.
	 * @return string
	 */
	public static function normalize_data_source( $value ) {
		$value = sanitize_key( (string) $value );
		if ( self::is_gtfs_source( $value ) ) {
			return self::DATA_SOURCE_GTFS;
		}

		if ( in_array( $value, array( self::DATA_SOURCE_MANUAL, self::DATA_SOURCE_CSV ), true ) ) {
			return $value;
		}

		return self::DATA_SOURCE_MANUAL;
	}

	/**
	 * Determine whether a data source uses GTFS.
	 *
	 * @param mixed $value Raw source value.
	 * @return bool
	 */
	public static function is_gtfs_source( $value ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, array( self::DATA_SOURCE_GTFS, self::DATA_SOURCE_IDFM_GTFS ), true );
	}
}
