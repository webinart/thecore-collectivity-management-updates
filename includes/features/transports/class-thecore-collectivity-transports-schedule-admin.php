<?php
/**
 * Transport schedules admin screen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Transports_Schedule_Admin {
	/**
	 * Admin page slug.
	 */
	const PAGE_SLUG = 'bellevue-transport-schedules';

	/**
	 * Import action.
	 */
	const ACTION_IMPORT_GTFS = 'bellevue_transport_import_gtfs';

	/**
	 * Realtime import action.
	 */
	const ACTION_IMPORT_REALTIME = 'bellevue_transport_import_realtime';

	/**
	 * Save GTFS source config action.
	 */
	const ACTION_SAVE_GTFS_CONFIG = 'bellevue_transport_save_gtfs_config';

	/**
	 * Export GTFS source config action.
	 */
	const ACTION_EXPORT_GTFS_CONFIG = 'bellevue_transport_export_gtfs_config';

	/**
	 * Import GTFS source config action.
	 */
	const ACTION_IMPORT_GTFS_CONFIG = 'bellevue_transport_import_gtfs_config';

	/**
	 * Discover locality action.
	 */
	const ACTION_DISCOVER_GTFS_LOCALITY = 'bellevue_transport_discover_gtfs_locality';

	/**
	 * Schedule repository.
	 *
	 * @var TheCore_Collectivity_Transports_Schedule_Repository
	 */
	private $schedule_repository;

	/**
	 * Realtime repository.
	 *
	 * @var TheCore_Collectivity_Transports_Realtime_Repository
	 */
	private $realtime_repository;

	/**
	 * Importer.
	 *
	 * @var TheCore_Collectivity_Transports_GTFS_Importer
	 */
	private $importer;

	/**
	 * Realtime importer.
	 *
	 * @var TheCore_Collectivity_Transports_Realtime_Importer
	 */
	private $realtime_importer;

	/**
	 * Locality discovery helper.
	 *
	 * @var TheCore_Collectivity_Transports_GTFS_Discovery
	 */
	private $discovery;

	/**
	 * Constructor.
	 *
	 * @param TheCore_Collectivity_Transports_Schedule_Repository  $schedule_repository Schedule repository.
	 * @param TheCore_Collectivity_Transports_Realtime_Repository  $realtime_repository Realtime repository.
	 * @param TheCore_Collectivity_Transports_GTFS_Importer        $importer            GTFS importer.
	 * @param TheCore_Collectivity_Transports_Realtime_Importer    $realtime_importer   Realtime importer.
	 * @param TheCore_Collectivity_Transports_GTFS_Discovery       $discovery           GTFS discovery helper.
	 */
	public function __construct( TheCore_Collectivity_Transports_Schedule_Repository $schedule_repository, TheCore_Collectivity_Transports_Realtime_Repository $realtime_repository, TheCore_Collectivity_Transports_GTFS_Importer $importer, TheCore_Collectivity_Transports_Realtime_Importer $realtime_importer, TheCore_Collectivity_Transports_GTFS_Discovery $discovery ) {
		$this->schedule_repository = $schedule_repository;
		$this->realtime_repository = $realtime_repository;
		$this->importer            = $importer;
		$this->realtime_importer   = $realtime_importer;
		$this->discovery           = $discovery;
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_post_' . self::ACTION_IMPORT_GTFS, array( $this, 'handle_import_gtfs' ) );
		add_action( 'admin_post_' . self::ACTION_IMPORT_REALTIME, array( $this, 'handle_import_realtime' ) );
		add_action( 'admin_post_' . self::ACTION_SAVE_GTFS_CONFIG, array( $this, 'handle_save_gtfs_config' ) );
		add_action( 'admin_post_' . self::ACTION_EXPORT_GTFS_CONFIG, array( $this, 'handle_export_gtfs_config' ) );
		add_action( 'admin_post_' . self::ACTION_IMPORT_GTFS_CONFIG, array( $this, 'handle_import_gtfs_config' ) );
		add_action( 'admin_post_' . self::ACTION_DISCOVER_GTFS_LOCALITY, array( $this, 'handle_discover_gtfs_locality' ) );
	}

	/**
	 * Register schedules submenu.
	 */
	public function register_admin_page() {
		add_submenu_page(
			'edit.php?post_type=' . TheCore_Collectivity_Transports_Post_Types::POST_TYPE_LINE,
			__( 'Horaires', 'bellevue' ),
			__( 'Horaires', 'bellevue' ),
			'edit_posts',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle GTFS import request.
	 */
	public function handle_import_gtfs() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Acces refuse.', 'bellevue' ) );
		}

		check_admin_referer( self::ACTION_IMPORT_GTFS );

		$provider_key = sanitize_key( wp_unslash( $_REQUEST['bellevue_transport_gtfs_provider_key'] ?? '' ) );
		$result       = $this->importer->import( $provider_key );
		$args         = $this->get_admin_redirect_args( $provider_key );

		if ( is_wp_error( $result ) ) {
			$args['bellevue_import_status']  = 'error';
			$args['bellevue_import_message'] = $result->get_error_message();
		} else {
			$args['bellevue_import_status']  = 'success';
			$args['bellevue_import_message'] = sprintf(
				/* translators: %s: provider label */
				__( 'Import GTFS termine pour %s.', 'bellevue' ),
				! empty( $result['provider'] ) ? $result['provider'] : $provider_key
			);
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'edit.php' ) ) );
		exit;
	}

	/**
	 * Handle realtime import request.
	 */
	public function handle_import_realtime() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Acces refuse.', 'bellevue' ) );
		}

		check_admin_referer( self::ACTION_IMPORT_REALTIME );

		$provider_key = sanitize_key( wp_unslash( $_REQUEST['bellevue_transport_gtfs_provider_key'] ?? '' ) );
		$result       = $this->realtime_importer->import( $provider_key );
		$args         = $this->get_admin_redirect_args( $provider_key );

		if ( is_wp_error( $result ) ) {
			$args['bellevue_import_status']  = 'error';
			$args['bellevue_import_message'] = $result->get_error_message();
		} else {
			$args['bellevue_import_status']  = 'success';
			$args['bellevue_import_message'] = sprintf(
				/* translators: %s: provider label */
				__( 'Import temps reel termine pour %s.', 'bellevue' ),
				! empty( $result['provider'] ) ? $result['provider'] : $provider_key
			);
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'edit.php' ) ) );
		exit;
	}

	/**
	 * Persist GTFS source config.
	 */
	public function handle_save_gtfs_config() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Acces refuse.', 'bellevue' ) );
		}

		check_admin_referer( self::ACTION_SAVE_GTFS_CONFIG );

		$raw_sources = isset( $_POST['bellevue_transport_gtfs_sources'] ) && is_array( $_POST['bellevue_transport_gtfs_sources'] )
			? wp_unslash( $_POST['bellevue_transport_gtfs_sources'] )
			: array();

		$sources = $this->sanitize_sources_input( $raw_sources );

		$this->schedule_repository->update_gtfs_sources( $sources );

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'               => TheCore_Collectivity_Transports_Post_Types::POST_TYPE_LINE,
					'page'                    => self::PAGE_SLUG,
					'bellevue_import_status'  => 'success',
					'bellevue_import_message' => __( 'Sources GTFS enregistrees.', 'bellevue' ),
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Export GTFS source config as JSON.
	 *
	 * @return void
	 */
	public function handle_export_gtfs_config() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Acces refuse.', 'bellevue' ) );
		}

		check_admin_referer( self::ACTION_EXPORT_GTFS_CONFIG );

		$payload = array(
			'format'          => 'tccm_gtfs_sources',
			'version'         => 1,
			'exported_at_gmt' => gmdate( 'c' ),
			'site_url'        => home_url( '/' ),
			'sources'         => $this->schedule_repository->get_gtfs_sources(),
		);

		$filename = sprintf(
			'tccm-gtfs-sources-%s.json',
			gmdate( 'Y-m-d-His' )
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * Import GTFS source config from JSON.
	 *
	 * @return void
	 */
	public function handle_import_gtfs_config() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Acces refuse.', 'bellevue' ) );
		}

		check_admin_referer( self::ACTION_IMPORT_GTFS_CONFIG );

		if ( empty( $_FILES['bellevue_transport_gtfs_import_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['bellevue_transport_gtfs_import_file']['tmp_name'] ) ) {
			$this->redirect_with_notice( 'error', __( 'Aucun fichier JSON valide n a été téléversé.', 'bellevue' ) );
		}

		$file_contents = file_get_contents( $_FILES['bellevue_transport_gtfs_import_file']['tmp_name'] );
		if ( ! is_string( $file_contents ) || '' === trim( $file_contents ) ) {
			$this->redirect_with_notice( 'error', __( 'Le fichier importé est vide.', 'bellevue' ) );
		}

		$decoded = json_decode( $file_contents, true );
		if ( ! is_array( $decoded ) ) {
			$this->redirect_with_notice( 'error', __( 'Le fichier JSON est invalide.', 'bellevue' ) );
		}

		$raw_sources = $decoded;
		if ( isset( $decoded['sources'] ) ) {
			$raw_sources = $decoded['sources'];
		}

		if ( ! is_array( $raw_sources ) ) {
			$this->redirect_with_notice( 'error', __( 'Le fichier ne contient pas de liste de sources GTFS.', 'bellevue' ) );
		}

		$sources = $this->sanitize_sources_input( $raw_sources );

		if ( empty( $sources ) ) {
			$this->redirect_with_notice( 'error', __( 'Aucune source GTFS exploitable n a été trouvée dans le fichier.', 'bellevue' ) );
		}

		$this->schedule_repository->update_gtfs_sources( $sources );

		$this->redirect_with_notice(
			'success',
			sprintf(
				/* translators: %d: number of sources imported */
				__( 'Configuration GTFS importée. %d source(s) enregistrée(s).', 'bellevue' ),
				count( $sources )
			)
		);
	}

	/**
	 * Discover and sync a locality from one GTFS source.
	 */
	public function handle_discover_gtfs_locality() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Acces refuse.', 'bellevue' ) );
		}

		check_admin_referer( self::ACTION_DISCOVER_GTFS_LOCALITY );

		$provider_key = sanitize_key( wp_unslash( $_REQUEST['bellevue_transport_gtfs_provider_key'] ?? '' ) );
		$result       = $this->discovery->discover_locality( $provider_key );
		$args         = $this->get_admin_redirect_args( $provider_key );

		if ( is_wp_error( $result ) ) {
			$args['bellevue_import_status']  = 'error';
			$args['bellevue_import_message'] = $result->get_error_message();
		} else {
			$counts                         = isset( $result['counts'] ) && is_array( $result['counts'] ) ? $result['counts'] : array();
			$args['bellevue_import_status'] = 'success';
			$args['bellevue_import_message'] = sprintf(
				/* translators: 1: provider label, 2: line count, 3: place count */
				__( 'Decouverte GTFS terminee pour %1$s. %2$d lignes et %3$d lieux synchronises.', 'bellevue' ),
				! empty( $result['provider'] ) ? $result['provider'] : $provider_key,
				intval( $counts['lines_created'] ?? 0 ) + intval( $counts['lines_updated'] ?? 0 ),
				intval( $counts['places_created'] ?? 0 ) + intval( $counts['places_updated'] ?? 0 )
			);
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'edit.php' ) ) );
		exit;
	}

	/**
	 * Render schedules admin page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$sources            = $this->schedule_repository->get_gtfs_sources();
		$import_statuses    = $this->schedule_repository->get_import_statuses();
		$discovery_statuses = $this->schedule_repository->get_discovery_statuses();
		$realtime_statuses  = $this->realtime_repository->get_realtime_statuses();
		$cron               = wp_next_scheduled( TheCore_Collectivity_Transports_Schedules::CRON_HOOK );
		$realtime_cron      = wp_next_scheduled( TheCore_Collectivity_Transports_Schedules::REALTIME_CRON_HOOK );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Horaires transports', 'bellevue' ); ?></h1>
			<?php $this->render_notice(); ?>

			<div class="notice notice-info inline">
				<p><?php esc_html_e( 'Cette page pilote plusieurs sources GTFS. Chaque source peut decouvrir ses lignes et arrets, puis importer ses horaires sans ecraser les autres operateurs.', 'bellevue' ); ?></p>
			</div>

			<table class="widefat striped" style="max-width:920px; margin-top:20px;">
				<tbody>
					<tr>
						<th style="width:280px;"><?php esc_html_e( 'Cron GTFS', 'bellevue' ); ?></th>
						<td><?php echo esc_html( $cron ? wp_date( 'Y-m-d H:i:s', $cron ) : __( 'Non planifie', 'bellevue' ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Cron temps réel', 'bellevue' ); ?></th>
						<td><?php echo esc_html( $realtime_cron ? wp_date( 'Y-m-d H:i:s', $realtime_cron ) : __( 'Non planifie', 'bellevue' ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Sources configurees', 'bellevue' ); ?></th>
						<td><?php echo esc_html( (string) count( $sources ) ); ?></td>
					</tr>
				</tbody>
			</table>

			<h2 style="margin-top:24px;"><?php esc_html_e( 'Configurer les sources GTFS', 'bellevue' ); ?></h2>
			<div style="display:flex; gap:16px; flex-wrap:wrap; align-items:flex-start; margin:16px 0 24px; max-width:1200px;">
				<div style="background:#fff; border:1px solid #dcdcde; padding:16px; min-width:320px; flex:1;">
					<h3 style="margin-top:0;"><?php esc_html_e( 'Exporter la configuration', 'bellevue' ); ?></h3>
					<p><?php esc_html_e( 'Télécharge un fichier JSON contenant les sources GTFS/temps réel actuellement configurées sur ce site.', 'bellevue' ); ?></p>
					<p>
						<a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => self::ACTION_EXPORT_GTFS_CONFIG ), admin_url( 'admin-post.php' ) ), self::ACTION_EXPORT_GTFS_CONFIG ) ); ?>">
							<?php esc_html_e( 'Exporter la configuration GTFS', 'bellevue' ); ?>
						</a>
					</p>
				</div>
				<div style="background:#fff; border:1px solid #dcdcde; padding:16px; min-width:320px; flex:1;">
					<h3 style="margin-top:0;"><?php esc_html_e( 'Importer une configuration', 'bellevue' ); ?></h3>
					<p><?php esc_html_e( 'Réimporte un fichier JSON précédemment exporté depuis un autre site ou environnement.', 'bellevue' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
						<?php wp_nonce_field( self::ACTION_IMPORT_GTFS_CONFIG ); ?>
						<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_IMPORT_GTFS_CONFIG ); ?>" />
						<p>
							<input type="file" name="bellevue_transport_gtfs_import_file" accept="application/json,.json" required />
						</p>
						<?php submit_button( __( 'Importer la configuration GTFS', 'bellevue' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:1200px;">
				<?php wp_nonce_field( self::ACTION_SAVE_GTFS_CONFIG ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SAVE_GTFS_CONFIG ); ?>" />

				<div id="tccm-gtfs-sources">
					<?php foreach ( $sources as $index => $source ) : ?>
						<?php
						$provider_key     = $source['provider_key'];
						$mapping          = $this->schedule_repository->get_mapping_overview( $provider_key );
						$import_status    = isset( $import_statuses[ $provider_key ] ) ? $import_statuses[ $provider_key ] : array();
						$discovery_status = isset( $discovery_statuses[ $provider_key ] ) ? $discovery_statuses[ $provider_key ] : array();
						$realtime_status  = isset( $realtime_statuses[ $provider_key ] ) ? $realtime_statuses[ $provider_key ] : array();
						$this->render_source_card( $source, $index, $mapping, $import_status, $discovery_status, $realtime_status );
						?>
					<?php endforeach; ?>
				</div>

				<p>
					<button type="button" class="button" id="tccm-add-gtfs-source"><?php esc_html_e( 'Ajouter une source GTFS', 'bellevue' ); ?></button>
				</p>

				<?php submit_button( __( 'Enregistrer les sources GTFS', 'bellevue' ) ); ?>
			</form>
		</div>

		<script type="text/html" id="tmpl-tccm-gtfs-source-card">
			<?php $this->render_source_card( $this->get_empty_source(), '__INDEX__', array(), array(), array(), array() ); ?>
		</script>
		<script>
			document.addEventListener('DOMContentLoaded', function () {
				const container = document.getElementById('tccm-gtfs-sources');
				const addButton = document.getElementById('tccm-add-gtfs-source');
				const template  = document.getElementById('tmpl-tccm-gtfs-source-card');
				if (!container || !addButton || !template) {
					return;
				}

				const refreshIndexes = function () {
					container.querySelectorAll('[data-source-card]').forEach(function (card, index) {
						card.querySelectorAll('[name]').forEach(function (field) {
							field.name = field.name.replace(/bellevue_transport_gtfs_sources\[[^\]]+\]/, 'bellevue_transport_gtfs_sources[' + index + ']');
						});
						card.querySelectorAll('[id]').forEach(function (field) {
							field.id = field.id.replace(/__INDEX__|source-\d+/g, 'source-' + index);
						});
					});
				};

				addButton.addEventListener('click', function () {
					const index = container.querySelectorAll('[data-source-card]').length;
					const markup = template.innerHTML.replace(/__INDEX__/g, index);
					container.insertAdjacentHTML('beforeend', markup);
					refreshIndexes();
				});

				container.addEventListener('click', function (event) {
					const button = event.target.closest('[data-remove-source]');
					if (!button) {
						return;
					}

					event.preventDefault();
					const card = button.closest('[data-source-card]');
					if (!card) {
						return;
					}

					card.remove();
					refreshIndexes();
				});
			});
		</script>
		<?php
	}

	/**
	 * Render one source card.
	 *
	 * @param array $source           Source config.
	 * @param int   $index            Source index.
	 * @param array $mapping          Mapping overview.
	 * @param array $import_status    Import status.
	 * @param array $discovery_status Discovery status.
	 * @param array $realtime_status  Realtime status.
	 */
	private function render_source_card( array $source, $index, array $mapping, array $import_status, array $discovery_status, array $realtime_status ) {
		$provider_key = ! empty( $source['provider_key'] ) ? $source['provider_key'] : '';
		$label        = ! empty( $source['provider_label'] ) ? $source['provider_label'] : __( 'Nouvelle source', 'bellevue' );
		?>
		<div data-source-card style="background:#fff; border:1px solid #dcdcde; padding:18px; margin:0 0 16px; max-width:1200px;">
			<div style="display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:12px;">
				<h3 style="margin:0;"><?php echo esc_html( $label ); ?><?php echo $provider_key ? ' <code>' . esc_html( $provider_key ) . '</code>' : ''; ?></h3>
				<button type="button" class="button-link-delete" data-remove-source><?php esc_html_e( 'Supprimer', 'bellevue' ); ?></button>
			</div>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Activer la source', 'bellevue' ); ?></th>
						<td><label><input type="checkbox" name="bellevue_transport_gtfs_sources[<?php echo esc_attr( $index ); ?>][is_enabled]" value="1" <?php checked( ! empty( $source['is_enabled'] ) ); ?> /> <?php esc_html_e( 'Importee par le cron et disponible dans l admin', 'bellevue' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Fournisseur', 'bellevue' ); ?></th>
						<td><input type="text" name="bellevue_transport_gtfs_sources[<?php echo esc_attr( $index ); ?>][provider_label]" class="regular-text" value="<?php echo esc_attr( $source['provider_label'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cle source', 'bellevue' ); ?></th>
						<td>
							<input type="text" name="bellevue_transport_gtfs_sources[<?php echo esc_attr( $index ); ?>][provider_key]" class="regular-text" value="<?php echo esc_attr( $source['provider_key'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Identifiant unique de la source. Sert a isoler les horaires, lignes et arrets de ce flux.', 'bellevue' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'URL GTFS ou page ressource PAN', 'bellevue' ); ?></th>
						<td>
							<input type="url" name="bellevue_transport_gtfs_sources[<?php echo esc_attr( $index ); ?>][gtfs_url]" class="widefat" value="<?php echo esc_attr( $source['gtfs_url'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Accepte soit une URL directe de ZIP GTFS, soit une page ressource transport.data.gouv.fr du type /resources/12345. La page ressource est recommandee si l URL producteur timeoute.', 'bellevue' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Calcul des directions d arrêt', 'bellevue' ); ?></th>
						<td>
							<select name="bellevue_transport_gtfs_sources[<?php echo esc_attr( $index ); ?>][stop_direction_strategy]" class="regular-text">
								<option value="<?php echo esc_attr( TheCore_Collectivity_Transports_Schedule_Repository::STOP_DIRECTION_STRATEGY_GTFS ); ?>" <?php selected( $source['stop_direction_strategy'], TheCore_Collectivity_Transports_Schedule_Repository::STOP_DIRECTION_STRATEGY_GTFS ); ?>><?php esc_html_e( 'Robuste: terminus calcule depuis le GTFS', 'bellevue' ); ?></option>
								<option value="<?php echo esc_attr( TheCore_Collectivity_Transports_Schedule_Repository::STOP_DIRECTION_STRATEGY_PARSE ); ?>" <?php selected( $source['stop_direction_strategy'], TheCore_Collectivity_Transports_Schedule_Repository::STOP_DIRECTION_STRATEGY_PARSE ); ?>><?php esc_html_e( 'Leger: analyse du libelle de direction', 'bellevue' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Le mode robuste calcule les destinations finales à partir des trips et des terminus GTFS. Le mode léger se contente d analyser le headsign du trip.', 'bellevue' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Activer le temps réel', 'bellevue' ); ?></th>
						<td><label><input type="checkbox" name="bellevue_transport_gtfs_sources[<?php echo esc_attr( $index ); ?>][realtime_is_enabled]" value="1" <?php checked( ! empty( $source['realtime_is_enabled'] ) ); ?> /> <?php esc_html_e( 'Met a jour les prochains departs a partir d un flux temps réel.', 'bellevue' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Format temps réel', 'bellevue' ); ?></th>
						<td>
							<select name="bellevue_transport_gtfs_sources[<?php echo esc_attr( $index ); ?>][realtime_format]" class="regular-text">
								<option value="none" <?php selected( $source['realtime_format'], 'none' ); ?>><?php esc_html_e( 'Aucun', 'bellevue' ); ?></option>
								<option value="gtfs_rt" <?php selected( $source['realtime_format'], 'gtfs_rt' ); ?>>GTFS-RT</option>
								<option value="siri" <?php selected( $source['realtime_format'], 'siri' ); ?>>SIRI</option>
								<option value="siri_lite" <?php selected( $source['realtime_format'], 'siri_lite' ); ?>>SIRI-Lite</option>
							</select>
							<p class="description"><?php esc_html_e( 'GTFS-RT est implemente aujourd hui. SIRI et SIRI-Lite sont déjà prévus dans le design, mais pas encore branchés.', 'bellevue' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'URL TripUpdates', 'bellevue' ); ?></th>
						<td><input type="url" name="bellevue_transport_gtfs_sources[<?php echo esc_attr( $index ); ?>][trip_updates_url]" class="widefat" value="<?php echo esc_attr( $source['trip_updates_url'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'URL ServiceAlerts', 'bellevue' ); ?></th>
						<td><input type="url" name="bellevue_transport_gtfs_sources[<?php echo esc_attr( $index ); ?>][service_alerts_url]" class="widefat" value="<?php echo esc_attr( $source['service_alerts_url'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'URL VehiclePositions', 'bellevue' ); ?></th>
						<td><input type="url" name="bellevue_transport_gtfs_sources[<?php echo esc_attr( $index ); ?>][vehicle_positions_url]" class="widefat" value="<?php echo esc_attr( $source['vehicle_positions_url'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Commune GTFS', 'bellevue' ); ?></th>
						<td><input type="text" name="bellevue_transport_gtfs_sources[<?php echo esc_attr( $index ); ?>][locality_name]" class="regular-text" value="<?php echo esc_attr( $source['locality_name'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Code INSEE', 'bellevue' ); ?></th>
						<td><input type="text" name="bellevue_transport_gtfs_sources[<?php echo esc_attr( $index ); ?>][locality_insee]" class="regular-text" value="<?php echo esc_attr( $source['locality_insee'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Communes supplémentaires', 'bellevue' ); ?></th>
						<td><textarea name="bellevue_transport_gtfs_sources[<?php echo esc_attr( $index ); ?>][extra_localities]" class="large-text" rows="3"><?php echo esc_textarea( implode( "\n", (array) $source['extra_localities'] ) ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Route IDs supplémentaires', 'bellevue' ); ?></th>
						<td><textarea name="bellevue_transport_gtfs_sources[<?php echo esc_attr( $index ); ?>][extra_route_ids]" class="large-text" rows="3"><?php echo esc_textarea( implode( "\n", (array) $source['extra_route_ids'] ) ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Stop IDs supplémentaires', 'bellevue' ); ?></th>
						<td><textarea name="bellevue_transport_gtfs_sources[<?php echo esc_attr( $index ); ?>][extra_stop_ids]" class="large-text" rows="3"><?php echo esc_textarea( implode( "\n", (array) $source['extra_stop_ids'] ) ); ?></textarea></td>
					</tr>
				</tbody>
			</table>

			<table class="widefat striped" style="max-width:920px; margin:16px 0;">
				<tbody>
					<tr>
						<th style="width:280px;"><?php esc_html_e( 'Dernier statut import', 'bellevue' ); ?></th>
						<td><?php echo esc_html( ! empty( $import_status['status'] ) ? $import_status['status'] : __( 'Aucun import', 'bellevue' ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Derniere execution import', 'bellevue' ); ?></th>
						<td><?php echo esc_html( ! empty( $import_status['completed_at'] ) ? $import_status['completed_at'] : '—' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Dernier statut decouverte', 'bellevue' ); ?></th>
						<td><?php echo esc_html( ! empty( $discovery_status['status'] ) ? $discovery_status['status'] : __( 'Aucune decouverte', 'bellevue' ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Derniere execution decouverte', 'bellevue' ); ?></th>
						<td><?php echo esc_html( ! empty( $discovery_status['completed_at'] ) ? $discovery_status['completed_at'] : '—' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Lignes mappees', 'bellevue' ); ?></th>
						<td><?php echo esc_html( (string) ( $mapping['lineCount'] ?? 0 ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Stops suivis', 'bellevue' ); ?></th>
						<td><?php echo esc_html( (string) ( $mapping['stopCount'] ?? 0 ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Dernier statut temps réel', 'bellevue' ); ?></th>
						<td><?php echo esc_html( ! empty( $realtime_status['status'] ) ? $realtime_status['status'] : __( 'Aucun import', 'bellevue' ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Derniere execution temps réel', 'bellevue' ); ?></th>
						<td><?php echo esc_html( ! empty( $realtime_status['completed_at'] ) ? $realtime_status['completed_at'] : '—' ); ?></td>
					</tr>
				</tbody>
			</table>

			<div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px;">
				<a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => self::ACTION_DISCOVER_GTFS_LOCALITY, 'bellevue_transport_gtfs_provider_key' => $provider_key ), admin_url( 'admin-post.php' ) ), self::ACTION_DISCOVER_GTFS_LOCALITY ) ); ?>"><?php esc_html_e( 'Decouvrir et synchroniser', 'bellevue' ); ?></a>
				<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => self::ACTION_IMPORT_GTFS, 'bellevue_transport_gtfs_provider_key' => $provider_key ), admin_url( 'admin-post.php' ) ), self::ACTION_IMPORT_GTFS ) ); ?>"><?php esc_html_e( 'Importer les horaires', 'bellevue' ); ?></a>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => self::ACTION_IMPORT_REALTIME, 'bellevue_transport_gtfs_provider_key' => $provider_key ), admin_url( 'admin-post.php' ) ), self::ACTION_IMPORT_REALTIME ) ); ?>"><?php esc_html_e( 'Importer le temps réel', 'bellevue' ); ?></a>
			</div>

			<?php if ( ! empty( $mapping['lines'] ) ) : ?>
				<table class="widefat striped" style="max-width:920px; margin:0;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Ligne', 'bellevue' ); ?></th>
							<th><?php esc_html_e( 'Route IDs', 'bellevue' ); ?></th>
							<th><?php esc_html_e( 'Codes publics', 'bellevue' ); ?></th>
							<th><?php esc_html_e( 'Stops suivis', 'bellevue' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $mapping['lines'] as $line ) : ?>
							<tr>
								<td><?php echo esc_html( $line['title'] ); ?></td>
								<td><?php echo esc_html( implode( ', ', $line['routeIds'] ) ?: '—' ); ?></td>
								<td><?php echo esc_html( implode( ', ', $line['shortNames'] ) ?: '—' ); ?></td>
								<td><?php echo esc_html( implode( ', ', $line['stopIds'] ) ?: '—' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Build one empty source config.
	 *
	 * @return array
	 */
	private function get_empty_source() {
		return array(
			'provider_label'   => '',
			'provider_key'     => '',
			'gtfs_url'         => '',
			'stop_direction_strategy' => TheCore_Collectivity_Transports_Schedule_Repository::STOP_DIRECTION_STRATEGY_GTFS,
			'realtime_format'  => 'none',
			'trip_updates_url' => '',
			'service_alerts_url' => '',
			'vehicle_positions_url' => '',
			'locality_name'    => '',
			'locality_insee'   => '',
			'extra_localities' => array(),
			'extra_route_ids'  => array(),
			'extra_stop_ids'   => array(),
			'is_enabled'       => true,
			'realtime_is_enabled' => false,
		);
	}

	/**
	 * Render import notice from redirect query args.
	 */
	private function render_notice() {
		$status  = isset( $_GET['bellevue_import_status'] ) ? sanitize_key( wp_unslash( $_GET['bellevue_import_status'] ) ) : '';
		$message = isset( $_GET['bellevue_import_message'] ) ? sanitize_text_field( wp_unslash( $_GET['bellevue_import_message'] ) ) : '';
		if ( '' === $status || '' === $message ) {
			return;
		}

		$class = 'success' === $status ? 'notice-success' : 'notice-error';
		printf( '<div class="notice %1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	/**
	 * Redirect back to admin page with a notice.
	 *
	 * @param string $status  success|error.
	 * @param string $message Notice message.
	 * @return void
	 */
	private function redirect_with_notice( $status, $message ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'               => TheCore_Collectivity_Transports_Post_Types::POST_TYPE_LINE,
					'page'                    => self::PAGE_SLUG,
					'bellevue_import_status'  => sanitize_key( $status ),
					'bellevue_import_message' => sanitize_text_field( $message ),
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Sanitize a list of raw source rows.
	 *
	 * @param array $raw_sources Raw source payload.
	 * @return array
	 */
	private function sanitize_sources_input( array $raw_sources ) {
		$sources = array();

		foreach ( $raw_sources as $source ) {
			if ( ! is_array( $source ) ) {
				continue;
			}

			$sources[] = array(
				'provider_label'         => sanitize_text_field( $source['provider_label'] ?? '' ),
				'provider_key'           => sanitize_key( $source['provider_key'] ?? '' ),
				'gtfs_url'               => esc_url_raw( $source['gtfs_url'] ?? '' ),
				'stop_direction_strategy'=> sanitize_key( $source['stop_direction_strategy'] ?? '' ),
				'realtime_format'        => sanitize_key( $source['realtime_format'] ?? '' ),
				'trip_updates_url'       => esc_url_raw( $source['trip_updates_url'] ?? '' ),
				'service_alerts_url'     => esc_url_raw( $source['service_alerts_url'] ?? '' ),
				'vehicle_positions_url'  => esc_url_raw( $source['vehicle_positions_url'] ?? '' ),
				'locality_name'          => sanitize_text_field( $source['locality_name'] ?? '' ),
				'locality_insee'         => sanitize_text_field( $source['locality_insee'] ?? '' ),
				'extra_localities'       => $this->sanitize_multivalue_import_field( $source['extra_localities'] ?? array() ),
				'extra_route_ids'        => $this->sanitize_multivalue_import_field( $source['extra_route_ids'] ?? array() ),
				'extra_stop_ids'         => $this->sanitize_multivalue_import_field( $source['extra_stop_ids'] ?? array() ),
				'is_enabled'             => ! empty( $source['is_enabled'] ),
				'realtime_is_enabled'    => ! empty( $source['realtime_is_enabled'] ),
			);
		}

		return $sources;
	}

	/**
	 * Sanitize one multi-value import field as newline text.
	 *
	 * @param mixed $value Raw field value.
	 * @return string
	 */
	private function sanitize_multivalue_import_field( $value ) {
		if ( is_array( $value ) ) {
			$value = implode( "\n", array_map( 'sanitize_text_field', $value ) );
		}

		return sanitize_textarea_field( (string) $value );
	}

	/**
	 * Build redirect args for this admin page.
	 *
	 * @param string $provider_key Provider key.
	 * @return array
	 */
	private function get_admin_redirect_args( $provider_key ) {
		$args = array(
			'post_type' => TheCore_Collectivity_Transports_Post_Types::POST_TYPE_LINE,
			'page'      => self::PAGE_SLUG,
		);

		$provider_key = sanitize_key( (string) $provider_key );
		if ( '' !== $provider_key ) {
			$args['provider'] = $provider_key;
		}

		return $args;
	}
}
