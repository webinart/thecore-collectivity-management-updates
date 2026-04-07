<?php
/**
 * Maps meta boxes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Maps_Meta {
	/**
	 * Nonce config.
	 */
	const NONCE_ACTION = 'tccm_map_meta_action';
	const NONCE_NAME   = 'tccm_map_meta_nonce';

	/**
	 * Geometry types.
	 */
	const GEOMETRY_POINT = 'point';
	const GEOMETRY_ROUTE = 'route';

	/**
	 * Shared meta keys.
	 */
	const META_GEOMETRY_TYPE       = '_tccm_map_geometry_type';
	const META_SUBTITLE            = '_tccm_map_subtitle';
	const META_SUMMARY             = '_tccm_map_summary';
	const META_CTA_LABEL           = '_tccm_map_cta_label';
	const META_CTA_URL             = '_tccm_map_cta_url';
	const META_DISPLAY_ORDER       = '_tccm_map_display_order';
	const META_ACCENT_COLOR        = '_tccm_map_accent_color';
	const META_ICON_KEY            = '_tccm_map_icon_key';
	const META_ACCESSIBILITY_NOTES = '_tccm_map_accessibility_notes';
	const META_WEBSITE_URL         = '_tccm_map_website_url';
	const META_SOCIAL_LINKS        = '_tccm_map_social_links';
	const META_PRIMARY_POSTS       = '_tccm_map_primary_posts';
	const META_SECONDARY_POSTS     = '_tccm_map_secondary_posts';

	/**
	 * Point meta keys.
	 */
	const META_LATITUDE  = '_tccm_map_latitude';
	const META_LONGITUDE = '_tccm_map_longitude';
	const META_ADDRESS   = '_tccm_map_address';

	/**
	 * Route meta keys.
	 */
	const META_GEOJSON         = '_tccm_map_geojson';
	const META_DISTANCE_LABEL  = '_tccm_map_distance_label';
	const META_DURATION_LABEL  = '_tccm_map_duration_label';
	const META_DIFFICULTY      = '_tccm_map_difficulty';
	const META_RELATED_POINT_IDS = '_tccm_map_related_point_ids';

	/**
	 * Supported social networks.
	 */
	const SOCIAL_NETWORKS = array(
		'facebook'  => 'Facebook',
		'instagram' => 'Instagram',
		'x'         => 'X',
		'linkedin'  => 'LinkedIn',
		'youtube'   => 'YouTube',
		'tiktok'    => 'TikTok',
	);

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'add_meta_boxes', array( $this, 'register_metaboxes' ) );
		add_action( 'save_post_' . TheCore_Collectivity_Maps_Post_Type::POST_TYPE, array( $this, 'save_metabox' ), 10, 2 );
	}

	/**
	 * Register metaboxes.
	 *
	 * @return void
	 */
	public function register_metaboxes() {
		add_meta_box(
			'tccm-map-settings',
			__( 'Paramètres cartographiques', 'thecore-collectivity-management' ),
			array( $this, 'render_metabox' ),
			TheCore_Collectivity_Maps_Post_Type::POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Render metabox.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_metabox( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$geometry_type       = (string) get_post_meta( $post->ID, self::META_GEOMETRY_TYPE, true );
		$geometry_type       = in_array( $geometry_type, array( self::GEOMETRY_POINT, self::GEOMETRY_ROUTE ), true ) ? $geometry_type : self::GEOMETRY_POINT;
		$subtitle            = (string) get_post_meta( $post->ID, self::META_SUBTITLE, true );
		$summary             = (string) get_post_meta( $post->ID, self::META_SUMMARY, true );
		$cta_label           = (string) get_post_meta( $post->ID, self::META_CTA_LABEL, true );
		$cta_url             = (string) get_post_meta( $post->ID, self::META_CTA_URL, true );
		$display_order       = (string) get_post_meta( $post->ID, self::META_DISPLAY_ORDER, true );
		$accent_color        = (string) get_post_meta( $post->ID, self::META_ACCENT_COLOR, true );
		$icon_key            = (string) get_post_meta( $post->ID, self::META_ICON_KEY, true );
		$accessibility_notes = (string) get_post_meta( $post->ID, self::META_ACCESSIBILITY_NOTES, true );
		$website_url         = (string) get_post_meta( $post->ID, self::META_WEBSITE_URL, true );
		$social_links        = $this->normalize_social_links_meta( get_post_meta( $post->ID, self::META_SOCIAL_LINKS, true ) );
		$latitude            = (string) get_post_meta( $post->ID, self::META_LATITUDE, true );
		$longitude           = (string) get_post_meta( $post->ID, self::META_LONGITUDE, true );
		$address             = (string) get_post_meta( $post->ID, self::META_ADDRESS, true );
		$geojson             = (string) get_post_meta( $post->ID, self::META_GEOJSON, true );
		$distance_label      = (string) get_post_meta( $post->ID, self::META_DISTANCE_LABEL, true );
		$duration_label      = (string) get_post_meta( $post->ID, self::META_DURATION_LABEL, true );
		$difficulty          = (string) get_post_meta( $post->ID, self::META_DIFFICULTY, true );
		$related_point_ids   = get_post_meta( $post->ID, self::META_RELATED_POINT_IDS, true );
		$related_point_ids   = is_array( $related_point_ids ) ? array_map( 'intval', $related_point_ids ) : array();
		$points              = $this->get_available_points( $post->ID );
		$available_posts     = $this->get_available_posts();
		$primary_posts       = $this->normalize_related_posts_meta( get_post_meta( $post->ID, self::META_PRIMARY_POSTS, true ), true );
		$secondary_posts     = $this->normalize_related_posts_meta( get_post_meta( $post->ID, self::META_SECONDARY_POSTS, true ), false );
		?>
		<div class="tccm-map-meta">
			<p>
				<label for="tccm-map-geometry-type"><strong><?php esc_html_e( 'Type cartographique', 'thecore-collectivity-management' ); ?></strong></label><br />
				<select id="tccm-map-geometry-type" name="tccm_map_geometry_type" class="regular-text" data-map-geometry-switch>
					<option value="<?php echo esc_attr( self::GEOMETRY_POINT ); ?>" <?php selected( self::GEOMETRY_POINT, $geometry_type ); ?>><?php esc_html_e( 'Lieu', 'thecore-collectivity-management' ); ?></option>
					<option value="<?php echo esc_attr( self::GEOMETRY_ROUTE ); ?>" <?php selected( self::GEOMETRY_ROUTE, $geometry_type ); ?>><?php esc_html_e( 'Parcours', 'thecore-collectivity-management' ); ?></option>
				</select>
			</p>
			<p>
				<label for="tccm-map-subtitle"><strong><?php esc_html_e( 'Sous-titre', 'thecore-collectivity-management' ); ?></strong></label><br />
				<input type="text" id="tccm-map-subtitle" name="tccm_map_subtitle" class="widefat" value="<?php echo esc_attr( $subtitle ); ?>" />
			</p>
			<p>
				<label for="tccm-map-summary"><strong><?php esc_html_e( 'Résumé court', 'thecore-collectivity-management' ); ?></strong></label><br />
				<textarea id="tccm-map-summary" name="tccm_map_summary" class="widefat" rows="3"><?php echo esc_textarea( $summary ); ?></textarea>
			</p>
			<div class="tccm-map-meta__grid">
				<p>
					<label for="tccm-map-cta-label"><strong><?php esc_html_e( 'Libellé CTA', 'thecore-collectivity-management' ); ?></strong></label><br />
					<input type="text" id="tccm-map-cta-label" name="tccm_map_cta_label" class="widefat" value="<?php echo esc_attr( $cta_label ); ?>" />
				</p>
				<p>
					<label for="tccm-map-cta-url"><strong><?php esc_html_e( 'URL CTA', 'thecore-collectivity-management' ); ?></strong></label><br />
					<input type="url" id="tccm-map-cta-url" name="tccm_map_cta_url" class="widefat" value="<?php echo esc_attr( $cta_url ); ?>" />
				</p>
				<p>
					<label for="tccm-map-display-order"><strong><?php esc_html_e( 'Ordre d’affichage', 'thecore-collectivity-management' ); ?></strong></label><br />
					<input type="number" id="tccm-map-display-order" name="tccm_map_display_order" class="small-text" value="<?php echo esc_attr( $display_order ); ?>" step="1" />
				</p>
				<p>
					<label for="tccm-map-accent-color"><strong><?php esc_html_e( 'Couleur d’accent', 'thecore-collectivity-management' ); ?></strong></label><br />
					<input type="text" id="tccm-map-accent-color" name="tccm_map_accent_color" class="regular-text" value="<?php echo esc_attr( $accent_color ); ?>" placeholder="#2F855A" />
				</p>
				<p>
					<label for="tccm-map-icon-key"><strong><?php esc_html_e( 'Clé icône', 'thecore-collectivity-management' ); ?></strong></label><br />
					<input type="text" id="tccm-map-icon-key" name="tccm_map_icon_key" class="regular-text" value="<?php echo esc_attr( $icon_key ); ?>" placeholder="chapelle" />
				</p>
			</div>
			<p>
				<label for="tccm-map-accessibility-notes"><strong><?php esc_html_e( 'Notes d’accessibilité', 'thecore-collectivity-management' ); ?></strong></label><br />
				<textarea id="tccm-map-accessibility-notes" name="tccm_map_accessibility_notes" class="widefat" rows="3"><?php echo esc_textarea( $accessibility_notes ); ?></textarea>
				<span class="description"><?php esc_html_e( 'Complément libre pour préciser l’accessibilité. Le filtre binaire passe par la taxonomie "Accessibilité".', 'thecore-collectivity-management' ); ?></span>
			</p>

			<div class="tccm-map-meta__section" data-map-geometry-section="<?php echo esc_attr( self::GEOMETRY_POINT ); ?>">
				<hr />
				<h3><?php esc_html_e( 'Coordonnées du lieu', 'thecore-collectivity-management' ); ?></h3>
				<div class="tccm-map-meta__grid">
					<p>
						<label for="tccm-map-latitude"><strong><?php esc_html_e( 'Latitude', 'thecore-collectivity-management' ); ?></strong></label><br />
						<input type="text" id="tccm-map-latitude" name="tccm_map_latitude" class="regular-text" value="<?php echo esc_attr( $latitude ); ?>" />
					</p>
					<p>
						<label for="tccm-map-longitude"><strong><?php esc_html_e( 'Longitude', 'thecore-collectivity-management' ); ?></strong></label><br />
						<input type="text" id="tccm-map-longitude" name="tccm_map_longitude" class="regular-text" value="<?php echo esc_attr( $longitude ); ?>" />
					</p>
				</div>
				<p>
					<label for="tccm-map-address"><strong><?php esc_html_e( 'Adresse / repère', 'thecore-collectivity-management' ); ?></strong></label><br />
					<input type="text" id="tccm-map-address" name="tccm_map_address" class="widefat" value="<?php echo esc_attr( $address ); ?>" />
				</p>
				<p>
					<label for="tccm-map-website-url"><strong><?php esc_html_e( 'Site internet', 'thecore-collectivity-management' ); ?></strong></label><br />
					<input type="url" id="tccm-map-website-url" name="tccm_map_website_url" class="widefat" value="<?php echo esc_attr( $website_url ); ?>" placeholder="https://..." />
				</p>

				<div class="tccm-map-social-links">
					<div class="tccm-map-social-links__header">
						<h4><?php esc_html_e( 'Réseaux sociaux', 'thecore-collectivity-management' ); ?></h4>
						<button type="button" class="button button-secondary" data-add-social-link><?php esc_html_e( 'Ajouter un réseau', 'thecore-collectivity-management' ); ?></button>
					</div>
					<div class="tccm-map-social-links__rows" data-social-links-rows>
						<?php
						foreach ( $social_links as $index => $row ) {
							$this->render_social_link_row( $index, $row );
						}
						?>
					</div>
					<template id="tccm-map-social-link-template">
						<?php $this->render_social_link_row( '__INDEX__', array() ); ?>
					</template>
				</div>
			</div>

			<div class="tccm-map-meta__section" data-map-geometry-section="<?php echo esc_attr( self::GEOMETRY_ROUTE ); ?>">
				<hr />
				<h3><?php esc_html_e( 'Trace du parcours', 'thecore-collectivity-management' ); ?></h3>
				<p>
					<label for="tccm-map-geojson"><strong><?php esc_html_e( 'GeoJSON', 'thecore-collectivity-management' ); ?></strong></label><br />
					<textarea id="tccm-map-geojson" name="tccm_map_geojson" class="widefat" rows="10"><?php echo esc_textarea( $geojson ); ?></textarea>
					<span class="description"><?php esc_html_e( 'Collez un LineString, MultiLineString, Feature ou FeatureCollection GeoJSON.', 'thecore-collectivity-management' ); ?></span>
				</p>
				<div class="tccm-map-meta__grid">
					<p>
						<label for="tccm-map-distance"><strong><?php esc_html_e( 'Distance', 'thecore-collectivity-management' ); ?></strong></label><br />
						<input type="text" id="tccm-map-distance" name="tccm_map_distance_label" class="regular-text" value="<?php echo esc_attr( $distance_label ); ?>" placeholder="4,2 km" />
					</p>
					<p>
					<label for="tccm-map-duration"><strong><?php esc_html_e( 'Durée', 'thecore-collectivity-management' ); ?></strong></label><br />
						<input type="text" id="tccm-map-duration" name="tccm_map_duration_label" class="regular-text" value="<?php echo esc_attr( $duration_label ); ?>" placeholder="1 h 30" />
					</p>
					<p>
						<label for="tccm-map-difficulty"><strong><?php esc_html_e( 'Difficulté', 'thecore-collectivity-management' ); ?></strong></label><br />
						<input type="text" id="tccm-map-difficulty" name="tccm_map_difficulty" class="regular-text" value="<?php echo esc_attr( $difficulty ); ?>" placeholder="<?php esc_attr_e( 'Facile', 'thecore-collectivity-management' ); ?>" />
					</p>
				</div>
				<p>
					<label for="tccm-map-related-points"><strong><?php esc_html_e( 'Points liés', 'thecore-collectivity-management' ); ?></strong></label><br />
					<select id="tccm-map-related-points" name="tccm_map_related_point_ids[]" class="widefat" multiple size="8">
						<?php foreach ( $points as $point ) : ?>
							<option value="<?php echo esc_attr( $point->ID ); ?>" <?php selected( in_array( (int) $point->ID, $related_point_ids, true ) ); ?>><?php echo esc_html( get_the_title( $point ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			</div>

			<hr />
			<div class="tccm-map-meta__section">
				<h3><?php esc_html_e( 'Ressources éditoriales liées', 'thecore-collectivity-management' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Associez des publications standards à cet élément cartographique. Les publications principales sont affichées comme boutons dans la vignette, les publications liées comme liens secondaires.', 'thecore-collectivity-management' ); ?></p>

				<div class="tccm-map-related-posts">
					<div class="tccm-map-related-posts__group">
						<div class="tccm-map-related-posts__header">
							<h4><?php esc_html_e( 'Publications principales', 'thecore-collectivity-management' ); ?></h4>
							<button type="button" class="button button-secondary" data-add-related-post="<?php echo esc_attr( self::META_PRIMARY_POSTS ); ?>"><?php esc_html_e( 'Ajouter une publication principale', 'thecore-collectivity-management' ); ?></button>
						</div>
						<div class="tccm-map-related-posts__rows" data-related-posts-rows="<?php echo esc_attr( self::META_PRIMARY_POSTS ); ?>">
							<?php
							foreach ( $primary_posts as $index => $row ) {
								$this->render_related_post_row( self::META_PRIMARY_POSTS, $index, $row, $available_posts, true );
							}
							?>
						</div>
					</div>

					<div class="tccm-map-related-posts__group">
						<div class="tccm-map-related-posts__header">
							<h4><?php esc_html_e( 'Publications liées', 'thecore-collectivity-management' ); ?></h4>
							<button type="button" class="button button-secondary" data-add-related-post="<?php echo esc_attr( self::META_SECONDARY_POSTS ); ?>"><?php esc_html_e( 'Ajouter une publication liée', 'thecore-collectivity-management' ); ?></button>
						</div>
						<div class="tccm-map-related-posts__rows" data-related-posts-rows="<?php echo esc_attr( self::META_SECONDARY_POSTS ); ?>">
							<?php
							foreach ( $secondary_posts as $index => $row ) {
								$this->render_related_post_row( self::META_SECONDARY_POSTS, $index, $row, $available_posts, false );
							}
							?>
						</div>
					</div>
				</div>

				<template id="tccm-map-related-post-template-primary">
					<?php $this->render_related_post_row( self::META_PRIMARY_POSTS, '__INDEX__', array(), $available_posts, true ); ?>
				</template>
				<template id="tccm-map-related-post-template-secondary">
					<?php $this->render_related_post_row( self::META_SECONDARY_POSTS, '__INDEX__', array(), $available_posts, false ); ?>
				</template>
			</div>
		</div>
		<style>
			.tccm-map-meta__grid {
				display: grid;
				grid-template-columns: repeat(2, minmax(0, 1fr));
				gap: 0 16px;
			}
			.tccm-map-related-posts {
				display: grid;
				gap: 16px;
				margin-top: 16px;
			}
			.tccm-map-related-posts__group {
				border: 1px solid #dcdcde;
				border-radius: 8px;
				padding: 12px;
				background: #fff;
			}
			.tccm-map-related-posts__header {
				display: flex;
				align-items: center;
				justify-content: space-between;
				gap: 12px;
				margin-bottom: 12px;
			}
			.tccm-map-related-posts__header h4 {
				margin: 0;
			}
			.tccm-map-related-posts__rows {
				display: grid;
				gap: 10px;
			}
			.tccm-map-related-posts__row {
				display: grid;
				grid-template-columns: minmax(0, 1.5fr) minmax(180px, 1fr) 90px auto;
				gap: 10px;
				align-items: end;
				padding: 10px;
				border: 1px solid #e2e4e7;
				border-radius: 6px;
				background: #f8f9fa;
			}
			.tccm-map-related-posts__row.is-secondary {
				grid-template-columns: minmax(0, 1.5fr) 90px auto;
			}
			.tccm-map-related-posts__field label {
				display: block;
				margin-bottom: 4px;
				font-weight: 600;
			}
			.tccm-map-related-posts__field .widefat,
			.tccm-map-related-posts__field .regular-text,
			.tccm-map-related-posts__field .small-text {
				margin: 0;
			}
			.tccm-map-related-posts__remove {
				align-self: center;
			}
			.tccm-map-related-post-picker {
				position: relative;
			}
			.tccm-map-related-post-picker__hidden {
				display: none;
			}
			.tccm-map-related-post-picker__suggestions {
				position: absolute;
				z-index: 20;
				top: calc(100% + 4px);
				left: 0;
				right: 0;
				max-height: 220px;
				overflow: auto;
				border: 1px solid #dcdcde;
				border-radius: 6px;
				background: #fff;
				box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
			}
			.tccm-map-related-post-picker__suggestion {
				display: block;
				width: 100%;
				padding: 8px 10px;
				border: 0;
				background: transparent;
				text-align: left;
				cursor: pointer;
			}
			.tccm-map-related-post-picker__suggestion:hover,
			.tccm-map-related-post-picker__suggestion:focus-visible {
				background: #f0f6fc;
			}
			.tccm-map-social-links {
				margin-top: 18px;
			}
			.tccm-map-social-links__header {
				display: flex;
				align-items: center;
				justify-content: space-between;
				gap: 12px;
				margin-bottom: 12px;
			}
			.tccm-map-social-links__header h4 {
				margin: 0;
			}
			.tccm-map-social-links__rows {
				display: grid;
				gap: 10px;
			}
			.tccm-map-social-links__row {
				display: grid;
				grid-template-columns: 180px minmax(0, 1fr) auto;
				gap: 10px;
				align-items: end;
				padding: 10px;
				border: 1px solid #e2e4e7;
				border-radius: 6px;
				background: #f8f9fa;
			}
			.tccm-map-social-links__field label {
				display: block;
				margin-bottom: 4px;
				font-weight: 600;
			}
			@media (max-width: 782px) {
				.tccm-map-meta__grid {
					grid-template-columns: 1fr;
				}
				.tccm-map-related-posts__header,
				.tccm-map-related-posts__row,
				.tccm-map-related-posts__row.is-secondary,
				.tccm-map-social-links__header,
				.tccm-map-social-links__row {
					grid-template-columns: 1fr;
				}
			}
		</style>
		<script>
			(function() {
				const availablePosts = <?php echo wp_json_encode( $this->get_available_posts_payload( $available_posts ) ); ?>;
				const select = document.getElementById('tccm-map-geometry-type');
				if (!select) {
					return;
				}
				const sections = document.querySelectorAll('[data-map-geometry-section]');
				const sync = function() {
					const value = select.value || 'point';
					sections.forEach(function(section) {
						section.style.display = section.getAttribute('data-map-geometry-section') === value ? '' : 'none';
					});
				};
				select.addEventListener('change', sync);
				sync();

				const createRow = function(metaKey) {
					const rows = document.querySelector('[data-related-posts-rows=\"' + metaKey + '\"]');
					const template = document.getElementById('tccm-map-related-post-template-' + (metaKey === '<?php echo esc_js( self::META_PRIMARY_POSTS ); ?>' ? 'primary' : 'secondary'));
					if (!rows || !template) {
						return;
					}

					const index = rows.querySelectorAll('[data-related-post-row]').length;
					const html = template.innerHTML.replace(/__INDEX__/g, String(index));
					rows.insertAdjacentHTML('beforeend', html);
					const newRow = rows.lastElementChild;
					if (newRow) {
						initPostPickers(newRow);
						const input = newRow.querySelector('[data-related-post-search]');
						if (input) {
							input.focus();
						}
					}
				};

				document.querySelectorAll('[data-add-related-post]').forEach(function(button) {
					button.addEventListener('click', function() {
						createRow(button.getAttribute('data-add-related-post'));
					});
				});

				const initPostPickers = function(scope) {
					const pickers = (scope || document).querySelectorAll('[data-related-post-picker]');
					pickers.forEach(function(picker) {
						if (picker.dataset.ready === '1') {
							return;
						}
						picker.dataset.ready = '1';

						const input = picker.querySelector('[data-related-post-search]');
						const hidden = picker.querySelector('[data-related-post-id]');
						const suggestions = picker.querySelector('[data-related-post-suggestions]');
						if (!input || !hidden || !suggestions) {
							return;
						}

						const renderSuggestions = function(query) {
							const term = (query || '').trim().toLowerCase();
							if (!term) {
								suggestions.hidden = true;
								suggestions.innerHTML = '';
								return;
							}

						const matches = availablePosts.filter(function(post) {
							return post.search.indexOf(term) !== -1;
						}).slice(0, 8);

						if (!matches.length) {
							suggestions.hidden = true;
							suggestions.innerHTML = '';
							return;
						}

						suggestions.innerHTML = '';
						matches.forEach(function(post) {
							const button = document.createElement('button');
							button.type = 'button';
							button.className = 'tccm-map-related-post-picker__suggestion';
							button.setAttribute('data-post-id', String(post.id));
							button.setAttribute('data-post-title', post.title);
							button.textContent = post.title;
							suggestions.appendChild(button);
						});
						suggestions.hidden = false;
					};

						input.addEventListener('input', function() {
							hidden.value = '';
							renderSuggestions(input.value);
						});

						input.addEventListener('focus', function() {
							renderSuggestions(input.value);
						});

						suggestions.addEventListener('click', function(event) {
							const button = event.target.closest('[data-post-id]');
							if (!button) {
								return;
							}
							input.value = button.getAttribute('data-post-title') || '';
							hidden.value = button.getAttribute('data-post-id') || '';
							suggestions.hidden = true;
							suggestions.innerHTML = '';
						});
					});
				};

				const initSocialRows = function() {
					const addButton = document.querySelector('[data-add-social-link]');
					const rows = document.querySelector('[data-social-links-rows]');
					const template = document.getElementById('tccm-map-social-link-template');
					if (!addButton || !rows || !template) {
						return;
					}

					addButton.addEventListener('click', function() {
						const index = rows.querySelectorAll('[data-social-link-row]').length;
						const html = template.innerHTML.replace(/__INDEX__/g, String(index));
						rows.insertAdjacentHTML('beforeend', html);
					});
				};

				document.addEventListener('click', function(event) {
					const removeButton = event.target.closest('[data-remove-related-post]');
					if (!removeButton) {
						const removeSocialButton = event.target.closest('[data-remove-social-link]');
						if (removeSocialButton) {
							const socialRow = removeSocialButton.closest('[data-social-link-row]');
							if (socialRow) {
								socialRow.remove();
							}
						}
						return;
					}

					const row = removeButton.closest('[data-related-post-row]');
					if (row) {
						row.remove();
					}
				});

				document.addEventListener('click', function(event) {
					if (event.target.closest('[data-related-post-picker]')) {
						return;
					}

					document.querySelectorAll('[data-related-post-suggestions]').forEach(function(node) {
						node.hidden = true;
						node.innerHTML = '';
					});
				});

				initPostPickers(document);
				initSocialRows();
			}());
		</script>
		<?php
	}

	/**
	 * Save metabox fields.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save_metabox( $post_id, $post ) {
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( empty( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$geometry_type = isset( $_POST['tccm_map_geometry_type'] ) ? sanitize_key( wp_unslash( $_POST['tccm_map_geometry_type'] ) ) : self::GEOMETRY_POINT;
		if ( ! in_array( $geometry_type, array( self::GEOMETRY_POINT, self::GEOMETRY_ROUTE ), true ) ) {
			$geometry_type = self::GEOMETRY_POINT;
		}

		update_post_meta( $post_id, self::META_GEOMETRY_TYPE, $geometry_type );
		update_post_meta( $post_id, self::META_SUBTITLE, sanitize_text_field( wp_unslash( $_POST['tccm_map_subtitle'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_SUMMARY, sanitize_textarea_field( wp_unslash( $_POST['tccm_map_summary'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_CTA_LABEL, sanitize_text_field( wp_unslash( $_POST['tccm_map_cta_label'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_CTA_URL, esc_url_raw( wp_unslash( $_POST['tccm_map_cta_url'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_DISPLAY_ORDER, (string) intval( wp_unslash( $_POST['tccm_map_display_order'] ?? 0 ) ) );
		update_post_meta( $post_id, self::META_ACCENT_COLOR, $this->sanitize_hex_color( wp_unslash( $_POST['tccm_map_accent_color'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_ICON_KEY, sanitize_key( wp_unslash( $_POST['tccm_map_icon_key'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_ACCESSIBILITY_NOTES, sanitize_textarea_field( wp_unslash( $_POST['tccm_map_accessibility_notes'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_WEBSITE_URL, esc_url_raw( wp_unslash( $_POST['tccm_map_website_url'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_SOCIAL_LINKS, $this->sanitize_social_links( $_POST['tccm_map_social_links'] ?? array() ) );

		update_post_meta( $post_id, self::META_LATITUDE, sanitize_text_field( wp_unslash( $_POST['tccm_map_latitude'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_LONGITUDE, sanitize_text_field( wp_unslash( $_POST['tccm_map_longitude'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_ADDRESS, sanitize_text_field( wp_unslash( $_POST['tccm_map_address'] ?? '' ) ) );

		update_post_meta( $post_id, self::META_GEOJSON, $this->sanitize_geojson( wp_unslash( $_POST['tccm_map_geojson'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_DISTANCE_LABEL, sanitize_text_field( wp_unslash( $_POST['tccm_map_distance_label'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_DURATION_LABEL, sanitize_text_field( wp_unslash( $_POST['tccm_map_duration_label'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_DIFFICULTY, sanitize_text_field( wp_unslash( $_POST['tccm_map_difficulty'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_RELATED_POINT_IDS, $this->sanitize_related_point_ids( $post_id, $_POST['tccm_map_related_point_ids'] ?? array() ) );
		update_post_meta( $post_id, self::META_PRIMARY_POSTS, $this->sanitize_related_posts( $_POST[ self::META_PRIMARY_POSTS ] ?? array(), true ) );
		update_post_meta( $post_id, self::META_SECONDARY_POSTS, $this->sanitize_related_posts( $_POST[ self::META_SECONDARY_POSTS ] ?? array(), false ) );
	}

	/**
	 * Sanitize related point ids.
	 *
	 * @param int   $post_id Current post id.
	 * @param mixed $value   Raw value.
	 * @return array
	 */
	private function sanitize_related_point_ids( $post_id, $value ) {
		$ids = is_array( $value ) ? array_map( 'intval', wp_unslash( $value ) ) : array();
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		$ids = array_values(
			array_filter(
				$ids,
				static function ( $id ) use ( $post_id ) {
					return $id > 0 && $id !== (int) $post_id;
				}
			)
		);

		return $ids;
	}

	/**
	 * Normalize raw post relationship rows for rendering.
	 *
	 * @param mixed $value      Raw meta value.
	 * @param bool  $with_label Whether a label field is expected.
	 * @return array
	 */
	private function normalize_related_posts_meta( $value, $with_label ) {
		$rows = is_array( $value ) ? $value : array();
		$normalized = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$post_id = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;
			if ( $post_id <= 0 ) {
				continue;
			}

			$normalized[] = array(
				'post_id' => $post_id,
				'label'   => $with_label ? (string) ( $row['label'] ?? '' ) : '',
				'order'   => isset( $row['order'] ) ? (int) $row['order'] : 0,
			);
		}

		return $normalized;
	}

	/**
	 * Normalize raw social links meta for rendering.
	 *
	 * @param mixed $value Raw meta value.
	 * @return array
	 */
	private function normalize_social_links_meta( $value ) {
		$rows = is_array( $value ) ? $value : array();
		$normalized = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$network = isset( $row['network'] ) ? sanitize_key( (string) $row['network'] ) : '';
			$url     = isset( $row['url'] ) ? esc_url_raw( (string) $row['url'] ) : '';

			if ( '' === $network || '' === $url || ! isset( self::SOCIAL_NETWORKS[ $network ] ) ) {
				continue;
			}

			$normalized[] = array(
				'network' => $network,
				'url'     => $url,
			);
		}

		return $normalized;
	}

	/**
	 * Sanitize related publication rows.
	 *
	 * @param mixed $value      Raw request value.
	 * @param bool  $with_label Whether a label field is expected.
	 * @return array
	 */
	private function sanitize_related_posts( $value, $with_label ) {
		$rows = is_array( $value ) ? $value : array();
		$clean = array();
		$seen  = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$post_id = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;
			if ( $post_id <= 0 || isset( $seen[ $post_id ] ) ) {
				continue;
			}

			$post = get_post( $post_id );
			if ( ! $post || 'post' !== $post->post_type ) {
				continue;
			}

			$seen[ $post_id ] = true;
			$clean[] = array(
				'post_id' => $post_id,
				'label'   => $with_label ? sanitize_text_field( $row['label'] ?? '' ) : '',
				'order'   => isset( $row['order'] ) ? (int) $row['order'] : 0,
			);
		}

		usort(
			$clean,
			static function ( $left, $right ) {
				return ( $left['order'] <=> $right['order'] ) ?: ( $left['post_id'] <=> $right['post_id'] );
			}
		);

		return $clean;
	}

	/**
	 * Sanitize social links rows.
	 *
	 * @param mixed $value Raw request value.
	 * @return array
	 */
	private function sanitize_social_links( $value ) {
		$rows = is_array( $value ) ? $value : array();
		$clean = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$network = isset( $row['network'] ) ? sanitize_key( (string) $row['network'] ) : '';
			$url     = isset( $row['url'] ) ? esc_url_raw( (string) $row['url'] ) : '';

			if ( '' === $network || '' === $url || ! isset( self::SOCIAL_NETWORKS[ $network ] ) ) {
				continue;
			}

			$clean[] = array(
				'network' => $network,
				'url'     => $url,
			);
		}

		return $clean;
	}

	/**
	 * Normalize GeoJSON if valid.
	 *
	 * @param string $value Raw JSON string.
	 * @return string
	 */
	private function sanitize_geojson( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		$decoded = json_decode( $value, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return $value;
		}

		return (string) wp_json_encode( $decoded );
	}

	/**
	 * Sanitize color value.
	 *
	 * @param string $value Raw color.
	 * @return string
	 */
	private function sanitize_hex_color( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		$sanitized = sanitize_hex_color( $value );

		return $sanitized ? $sanitized : '';
	}

	/**
	 * Get available point items for route relationships.
	 *
	 * @param int $current_post_id Current post id.
	 * @return WP_Post[]
	 */
	private function get_available_points( $current_post_id ) {
		return get_posts(
			array(
				'post_type'      => TheCore_Collectivity_Maps_Post_Type::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'post__not_in'   => array( (int) $current_post_id ),
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'key'     => self::META_GEOMETRY_TYPE,
						'value'   => self::GEOMETRY_POINT,
						'compare' => '=',
					),
				),
			)
		);
	}

	/**
	 * Get standard posts available for map relationships.
	 *
	 * @return WP_Post[]
	 */
	private function get_available_posts() {
		return get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
	}

	/**
	 * Build lightweight searchable payload for admin post pickers.
	 *
	 * @param WP_Post[] $available_posts Available posts.
	 * @return array
	 */
	private function get_available_posts_payload( array $available_posts ) {
		$payload = array();

		foreach ( $available_posts as $available_post ) {
			$title = get_the_title( $available_post );
			$payload[] = array(
				'id'     => (int) $available_post->ID,
				'title'  => $title,
				'search' => function_exists( 'mb_strtolower' ) ? mb_strtolower( $title ) : strtolower( $title ),
			);
		}

		return $payload;
	}

	/**
	 * Render one related post row.
	 *
	 * @param string    $meta_key        Meta key.
	 * @param int|string $index          Row index.
	 * @param array     $row             Row values.
	 * @param WP_Post[] $available_posts Available posts.
	 * @param bool      $with_label      Whether to render the label field.
	 * @return void
	 */
	private function render_related_post_row( $meta_key, $index, array $row, array $available_posts, $with_label ) {
		$post_id = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;
		$label   = isset( $row['label'] ) ? (string) $row['label'] : '';
		$order   = isset( $row['order'] ) ? (int) $row['order'] : 0;
		$row_css = $with_label ? 'tccm-map-related-posts__row' : 'tccm-map-related-posts__row is-secondary';
		$post_title = '';
		foreach ( $available_posts as $available_post ) {
			if ( (int) $available_post->ID === $post_id ) {
				$post_title = get_the_title( $available_post );
				break;
			}
		}
		?>
		<div class="<?php echo esc_attr( $row_css ); ?>" data-related-post-row>
			<div class="tccm-map-related-posts__field">
				<label><?php esc_html_e( 'Publication', 'thecore-collectivity-management' ); ?></label>
				<div class="tccm-map-related-post-picker" data-related-post-picker>
					<input type="hidden" data-related-post-id name="<?php echo esc_attr( $meta_key ); ?>[<?php echo esc_attr( $index ); ?>][post_id]" value="<?php echo esc_attr( $post_id ); ?>" />
					<input type="search" class="widefat" data-related-post-search value="<?php echo esc_attr( $post_title ); ?>" placeholder="<?php esc_attr_e( 'Rechercher une publication', 'thecore-collectivity-management' ); ?>" autocomplete="off" />
					<div class="tccm-map-related-post-picker__suggestions" data-related-post-suggestions hidden></div>
				</div>
			</div>
			<?php if ( $with_label ) : ?>
				<div class="tccm-map-related-posts__field">
					<label><?php esc_html_e( 'Libellé du bouton', 'thecore-collectivity-management' ); ?></label>
					<input type="text" class="widefat" name="<?php echo esc_attr( $meta_key ); ?>[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $label ); ?>" placeholder="<?php esc_attr_e( 'Découvrir', 'thecore-collectivity-management' ); ?>" />
				</div>
			<?php endif; ?>
			<div class="tccm-map-related-posts__field">
				<label><?php esc_html_e( 'Ordre', 'thecore-collectivity-management' ); ?></label>
				<input type="number" class="small-text" name="<?php echo esc_attr( $meta_key ); ?>[<?php echo esc_attr( $index ); ?>][order]" value="<?php echo esc_attr( $order ); ?>" step="1" />
			</div>
			<div class="tccm-map-related-posts__remove">
				<button type="button" class="button-link-delete" data-remove-related-post><?php esc_html_e( 'Retirer', 'thecore-collectivity-management' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * Render one social link row.
	 *
	 * @param int|string $index Row index.
	 * @param array      $row   Row values.
	 * @return void
	 */
	private function render_social_link_row( $index, array $row ) {
		$network = isset( $row['network'] ) ? sanitize_key( (string) $row['network'] ) : '';
		$url     = isset( $row['url'] ) ? (string) $row['url'] : '';
		?>
		<div class="tccm-map-social-links__row" data-social-link-row>
			<div class="tccm-map-social-links__field">
				<label><?php esc_html_e( 'Réseau', 'thecore-collectivity-management' ); ?></label>
				<select class="widefat" name="tccm_map_social_links[<?php echo esc_attr( $index ); ?>][network]">
					<option value=""><?php esc_html_e( 'Sélectionner un réseau', 'thecore-collectivity-management' ); ?></option>
					<?php foreach ( self::SOCIAL_NETWORKS as $slug => $name ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $network, $slug ); ?>><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="tccm-map-social-links__field">
				<label><?php esc_html_e( 'Lien', 'thecore-collectivity-management' ); ?></label>
				<input type="url" class="widefat" name="tccm_map_social_links[<?php echo esc_attr( $index ); ?>][url]" value="<?php echo esc_attr( $url ); ?>" placeholder="https://..." />
			</div>
			<div class="tccm-map-related-posts__remove">
				<button type="button" class="button-link-delete" data-remove-social-link><?php esc_html_e( 'Retirer', 'thecore-collectivity-management' ); ?></button>
			</div>
		</div>
		<?php
	}
}
