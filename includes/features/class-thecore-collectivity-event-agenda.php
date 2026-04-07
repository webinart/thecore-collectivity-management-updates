<?php
/**
 * Event agenda feature for posts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Event_Agenda {
	/**
	 * Nonce action name.
	 */
	const NONCE_ACTION = 'bellevue_event_metabox_action';

	/**
	 * Nonce field name.
	 */
	const NONCE_NAME = 'bellevue_event_metabox_nonce';

	/**
	 * Post meta keys.
	 */
	const META_ENABLED  = '_event_enabled';
	const META_START    = '_event_start';
	const META_START_TS = '_event_start_ts';
	const META_END      = '_event_end';
	const META_END_TS   = '_event_end_ts';
	const META_SHOW_START_TIME = '_event_show_start_time';
	const META_SHOW_END_TIME   = '_event_show_end_time';

	/**
	 * Elementor custom query id for upcoming agenda posts.
	 */
	const ELEMENTOR_QUERY_ID = 'diary_posts';

	/**
	 * Query var to identify diary queries for custom ordering.
	 */
	const QUERY_VAR_DIARY = 'bellevue_diary_query';

	/**
	 * Event taxonomies.
	 */
	const TAXONOMY_EVENT_TYPE  = 'bellevue_event_type';
	const TAXONOMY_EVENT_PLACE = 'bellevue_event_place';

	/**
	 * Option key to avoid re-seeding default event type terms.
	 */
	const OPTION_EVENT_TYPE_TERMS_SEEDED = 'bellevue_event_type_terms_seeded';

	/**
	 * Shortcode id for displaying an event date.
	 */
	const SHORTCODE_EVENT_DATE = 'bellevue_event_date';

	/**
	 * Shortcode id for displaying an event time.
	 */
	const SHORTCODE_EVENT_TIME = 'bellevue_event_time';

	/**
	 * Shortcode id for displaying event places.
	 */
	const SHORTCODE_EVENT_PLACES = 'bellevue_event_places';

	/**
	 * Shortcode id for displaying event types.
	 */
	const SHORTCODE_EVENT_TYPES = 'bellevue_event_types';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		add_action( 'admin_init', array( $this, 'maybe_seed_default_event_type_terms' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
		add_action( 'save_post', array( $this, 'save_metabox' ), 10, 2 );
		add_action( 'elementor/query/' . self::ELEMENTOR_QUERY_ID, array( $this, 'apply_elementor_diary_posts_query' ) );
		add_filter( 'posts_clauses', array( $this, 'filter_diary_query_clauses' ), 10, 2 );
		add_filter( 'wp_kses_allowed_html', array( $this, 'filter_wp_kses_allowed_html' ), 10, 2 );
		add_shortcode( self::SHORTCODE_EVENT_DATE, array( $this, 'shortcode_event_date' ) );
		add_shortcode( self::SHORTCODE_EVENT_TIME, array( $this, 'shortcode_event_time' ) );
		add_shortcode( self::SHORTCODE_EVENT_TYPES, array( $this, 'shortcode_event_types' ) );
		add_shortcode( self::SHORTCODE_EVENT_PLACES, array( $this, 'shortcode_event_places' ) );
	}

	/**
	 * Register event taxonomies on posts.
	 */
	public function register_taxonomies() {
		$post_types = array( 'post' );

		register_taxonomy(
			self::TAXONOMY_EVENT_TYPE,
			$post_types,
			array(
				'labels'            => array(
					'name'          => __( 'Types d evenement', 'bellevue' ),
					'singular_name' => __( 'Type d evenement', 'bellevue' ),
					'search_items'  => __( 'Rechercher des types d evenement', 'bellevue' ),
					'all_items'     => __( 'Tous les types d evenement', 'bellevue' ),
					'edit_item'     => __( 'Modifier le type d evenement', 'bellevue' ),
					'update_item'   => __( 'Mettre a jour le type d evenement', 'bellevue' ),
					'add_new_item'  => __( 'Ajouter un type d evenement', 'bellevue' ),
					'new_item_name' => __( 'Nouveau type d evenement', 'bellevue' ),
					'menu_name'     => __( 'Types d evenement', 'bellevue' ),
				),
				'public'            => false,
				'publicly_queryable'=> false,
				'show_ui'           => true,
				'show_in_menu'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'hierarchical'      => true,
				'show_tagcloud'     => false,
				'rewrite'           => false,
				'query_var'         => true,
			)
		);

		register_taxonomy(
			self::TAXONOMY_EVENT_PLACE,
			$post_types,
			array(
				'labels'            => array(
					'name'          => __( 'Lieux d evenement', 'bellevue' ),
					'singular_name' => __( 'Lieu d evenement', 'bellevue' ),
					'search_items'  => __( 'Rechercher des lieux', 'bellevue' ),
					'all_items'     => __( 'Tous les lieux', 'bellevue' ),
					'edit_item'     => __( 'Modifier le lieu', 'bellevue' ),
					'update_item'   => __( 'Mettre a jour le lieu', 'bellevue' ),
					'add_new_item'  => __( 'Ajouter un lieu', 'bellevue' ),
					'new_item_name' => __( 'Nouveau lieu', 'bellevue' ),
					'menu_name'     => __( 'Lieux', 'bellevue' ),
				),
				'public'            => false,
				'publicly_queryable'=> false,
				'show_ui'           => true,
				'show_in_menu'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'hierarchical'      => true,
				'show_tagcloud'     => false,
				'rewrite'           => false,
				'query_var'         => true,
			)
		);
	}

	/**
	 * Seed default event type terms once.
	 */
	public function maybe_seed_default_event_type_terms() {
		if ( get_option( self::OPTION_EVENT_TYPE_TERMS_SEEDED ) ) {
			return;
		}

		if ( ! taxonomy_exists( self::TAXONOMY_EVENT_TYPE ) ) {
			return;
		}

		$default_terms = array(
			array(
				'name' => 'Fete',
				'slug' => 'fete',
			),
			array(
				'name' => 'Institutionnel',
				'slug' => 'institutionnel',
			),
			array(
				'name' => 'Culture',
				'slug' => 'culture',
			),
			array(
				'name' => 'Jeunesse',
				'slug' => 'jeunesse',
			),
		);

		$has_error = false;

		foreach ( $default_terms as $term ) {
			$term_slug = isset( $term['slug'] ) ? sanitize_title( $term['slug'] ) : '';
			if ( '' === $term_slug ) {
				continue;
			}

			if ( term_exists( $term_slug, self::TAXONOMY_EVENT_TYPE ) ) {
				continue;
			}

			$inserted = wp_insert_term(
				(string) $term['name'],
				self::TAXONOMY_EVENT_TYPE,
				array(
					'slug' => $term_slug,
				)
			);

			if ( is_wp_error( $inserted ) ) {
				$has_error = true;
			}
		}

		if ( ! $has_error ) {
			update_option( self::OPTION_EVENT_TYPE_TERMS_SEEDED, '1', false );
		}
	}

	/**
	 * Register "Agenda" metabox on posts.
	 */
	public function register_metabox() {
		add_meta_box(
			'bellevue-event-settings',
			__( 'Agenda', 'bellevue' ),
			array( $this, 'render_metabox' ),
			'post',
			'side',
			'default'
		);
	}

	/**
	 * Render metabox fields.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_metabox( $post ) {
		$enabled = (string) get_post_meta( $post->ID, self::META_ENABLED, true );
		$start   = (string) get_post_meta( $post->ID, self::META_START, true );
		$end     = (string) get_post_meta( $post->ID, self::META_END, true );
		$show_start_time = (string) get_post_meta( $post->ID, self::META_SHOW_START_TIME, true );
		$show_end_time   = (string) get_post_meta( $post->ID, self::META_SHOW_END_TIME, true );

		if ( '' === $show_start_time ) {
			$show_start_time = '1';
		}

		if ( '' === $show_end_time ) {
			$show_end_time = '1';
		}

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<p>
			<label for="bellevue-event-enabled">
				<input
					type="checkbox"
					id="bellevue-event-enabled"
					name="bellevue_event_enabled"
					value="1"
					<?php checked( '1', $enabled ); ?>
				/>
				<?php esc_html_e( 'Afficher cet article dans l agenda', 'bellevue' ); ?>
			</label>
		</p>

		<p>
			<label for="bellevue-event-start">
				<strong><?php esc_html_e( 'Debut', 'bellevue' ); ?></strong>
			</label><br />
			<input
				type="datetime-local"
				id="bellevue-event-start"
				name="bellevue_event_start"
				value="<?php echo esc_attr( self::datetime_local_input_value( $start ) ); ?>"
				style="width:100%;"
			/>
		</p>

		<p>
			<label for="bellevue-event-show-start-time">
				<input
					type="checkbox"
					id="bellevue-event-show-start-time"
					name="bellevue_event_show_start_time"
					value="1"
					<?php checked( '1', $show_start_time ); ?>
				/>
				<?php esc_html_e( 'Afficher heure de debut', 'bellevue' ); ?>
			</label>
		</p>

		<p>
			<label for="bellevue-event-end">
				<strong><?php esc_html_e( 'Fin (optionnel)', 'bellevue' ); ?></strong>
			</label><br />
			<input
				type="datetime-local"
				id="bellevue-event-end"
				name="bellevue_event_end"
				value="<?php echo esc_attr( self::datetime_local_input_value( $end ) ); ?>"
				style="width:100%;"
			/>
		</p>

		<p>
			<label for="bellevue-event-show-end-time">
				<input
					type="checkbox"
					id="bellevue-event-show-end-time"
					name="bellevue_event_show_end_time"
					value="1"
					<?php checked( '1', $show_end_time ); ?>
				/>
				<?php esc_html_e( 'Afficher heure de fin', 'bellevue' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Save event fields.
	 *
	 * @param int     $post_id Post id.
	 * @param WP_Post $post    Post object.
	 */
	public function save_metabox( $post_id, $post ) {
		if ( 'post' !== $post->post_type ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$enabled = isset( $_POST['bellevue_event_enabled'] ) ? '1' : '0';
		if ( '1' !== $enabled ) {
			$this->clear_event_metas( $post_id );
			return;
		}

		$start_raw = isset( $_POST['bellevue_event_start'] ) ? wp_unslash( $_POST['bellevue_event_start'] ) : '';
		$end_raw   = isset( $_POST['bellevue_event_end'] ) ? wp_unslash( $_POST['bellevue_event_end'] ) : '';
		$show_start_time = isset( $_POST['bellevue_event_show_start_time'] ) ? '1' : '0';
		$show_end_time   = isset( $_POST['bellevue_event_show_end_time'] ) ? '1' : '0';

		$start = self::normalize_local_datetime( $start_raw );
		$end   = self::normalize_local_datetime( $end_raw );

		$start_ts = self::local_datetime_to_timestamp( $start );
		$end_ts   = self::local_datetime_to_timestamp( $end );

		if ( 0 === $start_ts ) {
			$this->clear_event_metas( $post_id );
			return;
		}

		if ( $end_ts > 0 && $end_ts < $start_ts ) {
			$end    = $start;
			$end_ts = $start_ts;
		}

		update_post_meta( $post_id, self::META_ENABLED, '1' );
		update_post_meta( $post_id, self::META_START, $start );
		update_post_meta( $post_id, self::META_START_TS, $start_ts );
		update_post_meta( $post_id, self::META_SHOW_START_TIME, $show_start_time );
		update_post_meta( $post_id, self::META_SHOW_END_TIME, $show_end_time );

		if ( $end_ts > 0 ) {
			update_post_meta( $post_id, self::META_END, $end );
			update_post_meta( $post_id, self::META_END_TS, $end_ts );
		} else {
			delete_post_meta( $post_id, self::META_END );
			delete_post_meta( $post_id, self::META_END_TS );
		}
	}

	/**
	 * Build query args to list upcoming agenda posts.
	 *
	 * @param array $overrides Optional query overrides.
	 * @return array
	 */
	public static function get_upcoming_events_query_args( $overrides = array() ) {
		$now = (int) current_time( 'timestamp' );

		$defaults = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 10,
			'ignore_sticky_posts' => true,
			'meta_key'            => self::META_START_TS,
			'orderby'             => 'meta_value_num',
			'order'               => 'ASC',
			self::QUERY_VAR_DIARY => 1,
			'meta_query'          => self::build_upcoming_meta_query( $now ),
		);

		return wp_parse_args( $overrides, $defaults );
	}

	/**
	 * Get a WP_Query object for upcoming agenda posts.
	 *
	 * @param array $overrides Optional query overrides.
	 * @return WP_Query
	 */
	public static function get_upcoming_events_query( $overrides = array() ) {
		return new WP_Query( self::get_upcoming_events_query_args( $overrides ) );
	}

	/**
	 * Apply query args to Elementor loop widgets using query id "diary_posts".
	 *
	 * @param WP_Query $query Query instance provided by Elementor.
	 */
	public function apply_elementor_diary_posts_query( $query ) {
		if ( ! $query instanceof WP_Query ) {
			return;
		}

		$now = (int) current_time( 'timestamp' );

		$query->set( 'post_type', 'post' );
		$query->set( 'post_status', 'publish' );
		$query->set( 'ignore_sticky_posts', true );
		$query->set( 'meta_key', self::META_START_TS );
		$query->set( 'orderby', 'meta_value_num' );
		$query->set( 'order', 'ASC' );
		$query->set( self::QUERY_VAR_DIARY, 1 );
		$query->set( 'meta_query', self::build_upcoming_meta_query( $now ) );

		if ( ! $query->get( 'posts_per_page' ) ) {
			$query->set( 'posts_per_page', 10 );
		}
	}

	/**
	 * Apply custom ordering for diary queries.
	 *
	 * Ongoing events are listed first (ordered by end date ASC), then upcoming
	 * events (ordered by start date ASC).
	 *
	 * @param array    $clauses SQL clauses.
	 * @param WP_Query $query   Query object.
	 * @return array
	 */
	public function filter_diary_query_clauses( $clauses, $query ) {
		if ( ! $query instanceof WP_Query ) {
			return $clauses;
		}

		if ( ! (int) $query->get( self::QUERY_VAR_DIARY ) ) {
			return $clauses;
		}

		global $wpdb;

		$now = (int) current_time( 'timestamp' );

		$start_subquery = sprintf(
			"(SELECT CAST(pm_start.meta_value AS UNSIGNED) FROM %s pm_start WHERE pm_start.post_id = %s.ID AND pm_start.meta_key = '%s' LIMIT 1)",
			$wpdb->postmeta,
			$wpdb->posts,
			esc_sql( self::META_START_TS )
		);

		$end_subquery = sprintf(
			"(SELECT CAST(pm_end.meta_value AS UNSIGNED) FROM %s pm_end WHERE pm_end.post_id = %s.ID AND pm_end.meta_key = '%s' LIMIT 1)",
			$wpdb->postmeta,
			$wpdb->posts,
			esc_sql( self::META_END_TS )
		);

		$is_ongoing_sql = sprintf(
			"(%1\$s IS NOT NULL AND %2\$s <= %3\$d AND %1\$s >= %3\$d)",
			$end_subquery,
			$start_subquery,
			$now
		);

		$group_order_sql = "CASE WHEN {$is_ongoing_sql} THEN 0 ELSE 1 END";
		$date_order_sql  = "CASE WHEN {$is_ongoing_sql} THEN {$end_subquery} ELSE {$start_subquery} END";

		$clauses['orderby'] = "{$group_order_sql} ASC, {$date_order_sql} ASC";

		return $clauses;
	}

	/**
	 * Render event date as shortcode output.
	 *
	 * Usage:
	 * - [bellevue_event_date]
	 * - [bellevue_event_date post_id="123"]
	 * - [bellevue_event_date format="parts"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_event_date( $atts ) {
		$atts = shortcode_atts(
			array(
				'post_id' => 0,
				'format'  => 'text',
			),
			$atts,
			self::SHORTCODE_EVENT_DATE
		);

		$post_id = absint( $atts['post_id'] );
		if ( $post_id <= 0 ) {
			$post_id = get_the_ID();
		}

		if ( $post_id <= 0 ) {
			return '';
		}

		$format = sanitize_key( $atts['format'] );
		if ( in_array( $format, array( 'parts', 'blocks' ), true ) ) {
			return self::get_display_date_parts_markup( $post_id );
		}

		return esc_html( self::get_display_date( $post_id ) );
	}

	/**
	 * Render event time as shortcode output.
	 *
	 * Usage:
	 * - [bellevue_event_time]
	 * - [bellevue_event_time post_id="123"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_event_time( $atts ) {
		$atts = shortcode_atts(
			array(
				'post_id' => 0,
			),
			$atts,
			self::SHORTCODE_EVENT_TIME
		);

		$post_id = absint( $atts['post_id'] );
		if ( $post_id <= 0 ) {
			$post_id = get_the_ID();
		}

		if ( $post_id <= 0 ) {
			return '';
		}

		$time_label = self::get_display_time( $post_id );
		if ( '' === $time_label ) {
			return '';
		}

		$clock_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clock h-4 w-4"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>';

		return $clock_icon . ' ' . esc_html( $time_label );
	}

	/**
	 * Render event places as shortcode output.
	 *
	 * Usage:
	 * - [bellevue_event_places]
	 * - [bellevue_event_places post_id="123"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_event_places( $atts ) {
		$atts = shortcode_atts(
			array(
				'post_id' => 0,
			),
			$atts,
			self::SHORTCODE_EVENT_PLACES
		);

		$post_id = absint( $atts['post_id'] );
		if ( $post_id <= 0 ) {
			$post_id = get_the_ID();
		}

		if ( $post_id <= 0 ) {
			return '';
		}

		$places_label = self::get_display_places( $post_id );
		if ( '' === $places_label ) {
			return '';
		}

		$map_pin_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-map-pin h-4 w-4"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path><circle cx="12" cy="10" r="3"></circle></svg>';

		return $map_pin_icon . ' ' . esc_html( $places_label );
	}

	/**
	 * Render event types as shortcode output.
	 *
	 * Usage:
	 * - [bellevue_event_types]
	 * - [bellevue_event_types post_id="123"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_event_types( $atts ) {
		$atts = shortcode_atts(
			array(
				'post_id' => 0,
			),
			$atts,
			self::SHORTCODE_EVENT_TYPES
		);

		$post_id = absint( $atts['post_id'] );
		if ( $post_id <= 0 ) {
			$post_id = get_the_ID();
		}

		if ( $post_id <= 0 ) {
			return '';
		}

		return esc_html( self::get_display_types( $post_id ) );
	}

	/**
	 * Get display label for event date.
	 *
	 * Returns a fixed date if no end date exists, otherwise a date range.
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	public static function get_display_date( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return '';
		}

		$start_ts = (int) get_post_meta( $post_id, self::META_START_TS, true );
		$end_ts   = (int) get_post_meta( $post_id, self::META_END_TS, true );

		if ( $start_ts <= 0 ) {
			return '';
		}

		if ( $end_ts > 0 ) {
			return sprintf(
				/* translators: 1: start date, 2: end date */
				esc_html__( 'Du %1$s au %2$s', 'bellevue' ),
				self::format_datetime_label( $start_ts ),
				self::format_datetime_label( $end_ts )
			);
		}

		return self::format_datetime_label( $start_ts );
	}

	/**
	 * Get display label for event time.
	 *
	 * Rules:
	 * - Start only enabled: "HH:mm"
	 * - Start + End enabled: "HH:mm - HH:mm"
	 * - End only enabled (with end date): "Jusqu'a HH:mm"
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	public static function get_display_time( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return '';
		}

		$start_ts = (int) get_post_meta( $post_id, self::META_START_TS, true );
		$end_ts   = (int) get_post_meta( $post_id, self::META_END_TS, true );

		if ( $start_ts <= 0 ) {
			return '';
		}

		$show_start_time = self::is_time_visibility_enabled( $post_id, self::META_SHOW_START_TIME );
		$show_end_time   = self::is_time_visibility_enabled( $post_id, self::META_SHOW_END_TIME );

		$start_time = self::format_time_label( $start_ts );
		$end_time   = ( $end_ts > 0 ) ? self::format_time_label( $end_ts ) : '';

		if ( $end_ts > 0 ) {
			if ( $show_start_time && $show_end_time ) {
				return $start_time . ' - ' . $end_time;
			}

			if ( $show_start_time ) {
				return $start_time;
			}

			if ( $show_end_time ) {
				return sprintf(
					/* translators: %s is end time (e.g. 18:30). */
					esc_html__( "Jusqu'a %s", 'bellevue' ),
					$end_time
				);
			}

			return '';
		}

		if ( $show_start_time ) {
			return $start_time;
		}

		return '';
	}

	/**
	 * Get display label for event places.
	 *
	 * Multiple places are separated by " / ".
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	public static function get_display_places( $post_id ) {
		return self::get_joined_term_names( $post_id, self::TAXONOMY_EVENT_PLACE );
	}

	/**
	 * Get display label for event types.
	 *
	 * Multiple types are separated by " / ".
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	public static function get_display_types( $post_id ) {
		return self::get_joined_term_names( $post_id, self::TAXONOMY_EVENT_TYPE );
	}

	/**
	 * Get joined term names for a taxonomy.
	 *
	 * @param int    $post_id  Post id.
	 * @param string $taxonomy Taxonomy slug.
	 * @return string
	 */
	private static function get_joined_term_names( $post_id, $taxonomy ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return '';
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return '';
		}

		$terms = get_the_terms( $post_id, $taxonomy );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}

		$labels = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$name = trim( (string) $term->name );
			if ( '' === $name ) {
				continue;
			}

			$labels[] = $name;
		}

		if ( empty( $labels ) ) {
			return '';
		}

		$labels = array_values( array_unique( $labels ) );

		return implode( ' / ', $labels );
	}

	/**
	 * Get event date HTML as structured parts (day/month blocks).
	 *
	 * No time is displayed in this format.
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	public static function get_display_date_parts_markup( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return '';
		}

		$start_ts = (int) get_post_meta( $post_id, self::META_START_TS, true );
		$end_ts   = (int) get_post_meta( $post_id, self::META_END_TS, true );

		if ( $start_ts <= 0 ) {
			return '';
		}

		$start_day   = wp_date( 'j', $start_ts );
		$start_month = self::get_short_month_label( $start_ts );

		if ( self::has_range_display( $start_ts, $end_ts ) ) {
			$end_day   = wp_date( 'j', $end_ts );
			$end_month = self::get_short_month_label( $end_ts );

			return sprintf(
				'<div class="bellevue-event-date bellevue-event-date--parts bellevue-event-date--range">' .
				'<div class="bellevue-event-date__block bellevue-event-date__block--start">' .
				'<div class="bellevue-event-date__day">%1$s</div>' .
				'<div class="bellevue-event-date__month">%2$s</div>' .
				'</div>' .
				'<div class="bellevue-event-date__separator" aria-hidden="true">-</div>' .
				'<div class="bellevue-event-date__block bellevue-event-date__block--end">' .
				'<div class="bellevue-event-date__day">%3$s</div>' .
				'<div class="bellevue-event-date__month">%4$s</div>' .
				'</div>' .
				'</div>',
				esc_html( $start_day ),
				esc_html( $start_month ),
				esc_html( $end_day ),
				esc_html( $end_month )
			);
		}

		return sprintf(
			'<div class="bellevue-event-date bellevue-event-date--parts bellevue-event-date--single">' .
			'<div class="bellevue-event-date__block bellevue-event-date__block--start">' .
			'<div class="bellevue-event-date__day">%1$s</div>' .
			'<div class="bellevue-event-date__month">%2$s</div>' .
			'</div>' .
			'</div>',
			esc_html( $start_day ),
			esc_html( $start_month )
		);
	}

	/**
	 * Clear all agenda metas from a post.
	 *
	 * @param int $post_id Post id.
	 */
	private function clear_event_metas( $post_id ) {
		delete_post_meta( $post_id, self::META_ENABLED );
		delete_post_meta( $post_id, self::META_START );
		delete_post_meta( $post_id, self::META_START_TS );
		delete_post_meta( $post_id, self::META_END );
		delete_post_meta( $post_id, self::META_END_TS );
		delete_post_meta( $post_id, self::META_SHOW_START_TIME );
		delete_post_meta( $post_id, self::META_SHOW_END_TIME );
	}

	/**
	 * Build meta_query for upcoming events.
	 *
	 * @param int $now Current local timestamp.
	 * @return array
	 */
	private static function build_upcoming_meta_query( $now ) {
		return array(
			'relation' => 'OR',
			array(
				'relation' => 'AND',
				array(
					'key'     => self::META_ENABLED,
					'value'   => '1',
					'compare' => '=',
				),
				array(
					'key'     => self::META_END_TS,
					'value'   => $now,
					'type'    => 'NUMERIC',
					'compare' => '>=',
				),
			),
			array(
				'relation' => 'AND',
				array(
					'key'     => self::META_ENABLED,
					'value'   => '1',
					'compare' => '=',
				),
				array(
					'key'     => self::META_END_TS,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => self::META_START_TS,
					'value'   => $now,
					'type'    => 'NUMERIC',
					'compare' => '>=',
				),
			),
		);
	}

	/**
	 * Convert stored datetime format to datetime-local input value.
	 *
	 * @param string $value Datetime in "Y-m-d H:i" format.
	 * @return string
	 */
	private static function datetime_local_input_value( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		return str_replace( ' ', 'T', $value );
	}

	/**
	 * Normalize datetime-local input to "Y-m-d H:i" in site timezone.
	 *
	 * @param string $value Raw datetime input.
	 * @return string
	 */
	private static function normalize_local_datetime( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		$value = str_replace( 'T', ' ', $value );
		$dt    = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $value, wp_timezone() );

		if ( ! $dt ) {
			return '';
		}

		return $dt->format( 'Y-m-d H:i' );
	}

	/**
	 * Convert local datetime string to unix timestamp.
	 *
	 * @param string $value Datetime in "Y-m-d H:i" format.
	 * @return int
	 */
	private static function local_datetime_to_timestamp( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return 0;
		}

		$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $value, wp_timezone() );
		if ( ! $dt ) {
			return 0;
		}

		return (int) $dt->getTimestamp();
	}

	/**
	 * Determine whether a time display switch is enabled for a post.
	 *
	 * If not set yet, defaults to enabled for backward compatibility.
	 *
	 * @param int    $post_id  Post id.
	 * @param string $meta_key Time visibility meta key.
	 * @return bool
	 */
	private static function is_time_visibility_enabled( $post_id, $meta_key ) {
		$value = (string) get_post_meta( (int) $post_id, $meta_key, true );
		if ( '' === $value ) {
			return true;
		}

		return '1' === $value;
	}

	/**
	 * Format timestamp for agenda display.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	private static function format_datetime_label( $timestamp ) {
		$timestamp = (int) $timestamp;
		if ( $timestamp <= 0 ) {
			return '';
		}

		$has_time = '00:00' !== wp_date( 'H:i', $timestamp );
		$format   = $has_time ? 'j F Y \\à\\ H:i' : 'j F Y';

		return wp_date( $format, $timestamp );
	}

	/**
	 * Format timestamp as time label.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	private static function format_time_label( $timestamp ) {
		$timestamp = (int) $timestamp;
		if ( $timestamp <= 0 ) {
			return '';
		}

		return wp_date( 'H:i', $timestamp );
	}

	/**
	 * Whether start/end should be displayed as a range in parts mode.
	 *
	 * @param int $start_ts Start timestamp.
	 * @param int $end_ts   End timestamp.
	 * @return bool
	 */
	private static function has_range_display( $start_ts, $end_ts ) {
		$start_ts = (int) $start_ts;
		$end_ts   = (int) $end_ts;

		if ( $start_ts <= 0 || $end_ts <= 0 ) {
			return false;
		}

		return wp_date( 'Ymd', $start_ts ) !== wp_date( 'Ymd', $end_ts );
	}

	/**
	 * Get month label on 3 letters (ASCII-friendly French style).
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	private static function get_short_month_label( $timestamp ) {
		$month_index = (int) wp_date( 'n', (int) $timestamp );

		$months = array(
			1  => 'Jan',
			2  => 'Fev',
			3  => 'Mar',
			4  => 'Avr',
			5  => 'Mai',
			6  => 'Jui',
			7  => 'Jul',
			8  => 'Aou',
			9  => 'Sep',
			10 => 'Oct',
			11 => 'Nov',
			12 => 'Dec',
		);

		return isset( $months[ $month_index ] ) ? $months[ $month_index ] : '';
	}

	/**
	 * Allow a minimal SVG subset in wp_kses post context.
	 *
	 * This is required for the event time shortcode icon when rendered through
	 * Elementor Pro dynamic tag "Shortcode", which uses wp_kses_post().
	 *
	 * @param array|string $allowed_html Allowed HTML tags/attributes.
	 * @param string       $context      KSES context.
	 * @return array|string
	 */
	public function filter_wp_kses_allowed_html( $allowed_html, $context ) {
		if ( 'post' !== $context || ! is_array( $allowed_html ) ) {
			return $allowed_html;
		}

		$svg_attrs = array(
			'xmlns'            => true,
			'width'            => true,
			'height'           => true,
			'viewbox'          => true,
			'fill'             => true,
			'stroke'           => true,
			'stroke-width'     => true,
			'stroke-linecap'   => true,
			'stroke-linejoin'  => true,
			'class'            => true,
			'aria-hidden'      => true,
			'focusable'        => true,
			'role'             => true,
		);

		$circle_attrs = array(
			'cx'           => true,
			'cy'           => true,
			'r'            => true,
			'fill'         => true,
			'stroke'       => true,
			'stroke-width' => true,
			'class'        => true,
		);

		$polyline_attrs = array(
			'points'          => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'class'           => true,
		);

		$path_attrs = array(
			'd'               => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'class'           => true,
		);

		$allowed_html['svg']      = isset( $allowed_html['svg'] ) ? array_merge( $allowed_html['svg'], $svg_attrs ) : $svg_attrs;
		$allowed_html['circle']   = isset( $allowed_html['circle'] ) ? array_merge( $allowed_html['circle'], $circle_attrs ) : $circle_attrs;
		$allowed_html['polyline'] = isset( $allowed_html['polyline'] ) ? array_merge( $allowed_html['polyline'], $polyline_attrs ) : $polyline_attrs;
		$allowed_html['path']     = isset( $allowed_html['path'] ) ? array_merge( $allowed_html['path'], $path_attrs ) : $path_attrs;

		return $allowed_html;
	}
}
