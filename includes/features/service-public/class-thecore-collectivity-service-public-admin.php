<?php
/**
 * Service-public admin screen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Service_Public_Admin {
	const PAGE_SLUG     = 'tccm-service-public';
	const ACTION_IMPORT = 'tccm_service_public_import';
	const ACTION_PURGE  = 'tccm_service_public_purge_cache';

	/**
	 * Repository.
	 *
	 * @var TheCore_Collectivity_Service_Public_Repository
	 */
	private $repository;

	/**
	 * Importer.
	 *
	 * @var TheCore_Collectivity_Service_Public_Importer
	 */
	private $importer;

	/**
	 * Constructor.
	 *
	 * @param TheCore_Collectivity_Service_Public_Repository $repository Repository.
	 * @param TheCore_Collectivity_Service_Public_Importer   $importer Importer.
	 */
	public function __construct( TheCore_Collectivity_Service_Public_Repository $repository, TheCore_Collectivity_Service_Public_Importer $importer ) {
		$this->repository = $repository;
		$this->importer   = $importer;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_post_' . self::ACTION_IMPORT, array( $this, 'handle_import' ) );
		add_action( 'admin_post_' . self::ACTION_PURGE, array( $this, 'handle_purge_cache' ) );
	}

	/**
	 * Register admin page.
	 *
	 * @return void
	 */
	public function register_admin_page() {
		add_management_page(
			__( 'Service-public', 'thecore-collectivity-management' ),
			__( 'Service-public', 'thecore-collectivity-management' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle manual import.
	 *
	 * @return void
	 */
	public function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acces refuse.', 'thecore-collectivity-management' ) );
		}

		check_admin_referer( self::ACTION_IMPORT );

		$source_id = isset( $_POST['source_id'] ) ? absint( wp_unslash( $_POST['source_id'] ) ) : 0;
		$result    = $source_id ? $this->importer->import_source( $source_id ) : new WP_Error( 'tccm_sp_no_source', __( 'Source manquante.', 'thecore-collectivity-management' ) );
		$args      = array(
			'page' => self::PAGE_SLUG,
		);

		if ( is_wp_error( $result ) ) {
			$args['tccm_sp_status']  = 'error';
			$args['tccm_sp_message'] = rawurlencode( $result->get_error_message() );
		} else {
			$args['tccm_sp_status']  = 'success';
			$args['tccm_sp_message'] = rawurlencode(
				sprintf(
					/* translators: %d imported items */
					__( '%d contenus Service-public importes.', 'thecore-collectivity-management' ),
					(int) ( $result['imported'] ?? 0 )
				)
			);
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'tools.php' ) ) );
		exit;
	}

	/**
	 * Handle cache purge.
	 *
	 * @return void
	 */
	public function handle_purge_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acces refuse.', 'thecore-collectivity-management' ) );
		}

		check_admin_referer( self::ACTION_PURGE );
		$this->repository->clear_render_cache();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => self::PAGE_SLUG,
					'tccm_sp_status'  => 'success',
					'tccm_sp_message' => rawurlencode( __( 'Cache Service-public vide.', 'thecore-collectivity-management' ) ),
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$sources = $this->repository->get_sources();
		$counts  = $this->repository->get_item_counts();
		$imports = $this->repository->get_recent_imports( 10 );
		$status  = sanitize_key( wp_unslash( $_GET['tccm_sp_status'] ?? '' ) );
		$message = sanitize_text_field( rawurldecode( wp_unslash( $_GET['tccm_sp_message'] ?? '' ) ) );
		?>
		<div class="wrap tccm-service-public-admin">
			<h1><?php esc_html_e( 'Service-public / co-marquage', 'thecore-collectivity-management' ); ?></h1>
			<p><?php esc_html_e( 'Module custom The Core pour importer et afficher localement les contenus DILA depuis data.gouv.', 'thecore-collectivity-management' ); ?></p>

			<?php if ( $message ) : ?>
				<div class="notice notice-<?php echo 'error' === $status ? 'error' : 'success'; ?> is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Sources configurees', 'thecore-collectivity-management' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Audience', 'thecore-collectivity-management' ); ?></th>
						<th><?php esc_html_e( 'Source', 'thecore-collectivity-management' ); ?></th>
						<th><?php esc_html_e( 'Ressource', 'thecore-collectivity-management' ); ?></th>
						<th><?php esc_html_e( 'Contenus importes', 'thecore-collectivity-management' ); ?></th>
						<th><?php esc_html_e( 'Action', 'thecore-collectivity-management' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $sources as $source ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $source['audience'] ); ?></strong></td>
							<td>
								<?php echo esc_html( $source['label'] ); ?><br>
								<a href="<?php echo esc_url( $source['dataset_api_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'API data.gouv', 'thecore-collectivity-management' ); ?></a>
							</td>
							<td>
								<?php echo esc_html( $source['resource_title'] ); ?><br>
								<code><?php echo esc_html( $source['resource_file_name'] ); ?></code>
								<?php if ( ! empty( $source['resource_date'] ) ) : ?>
									<br><small><?php echo esc_html( $source['resource_date'] ); ?></small>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( (string) ( $counts[ $source['audience'] ] ?? 0 ) ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php wp_nonce_field( self::ACTION_IMPORT ); ?>
									<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_IMPORT ); ?>">
									<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $source['id'] ); ?>">
									<?php submit_button( __( 'Importer maintenant', 'thecore-collectivity-management' ), 'secondary', 'submit', false ); ?>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1rem;">
				<?php wp_nonce_field( self::ACTION_PURGE ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_PURGE ); ?>">
				<?php submit_button( __( 'Vider le cache de rendu', 'thecore-collectivity-management' ), 'delete', 'submit', false ); ?>
			</form>

			<h2><?php esc_html_e( 'Derniers imports', 'thecore-collectivity-management' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'thecore-collectivity-management' ); ?></th>
						<th><?php esc_html_e( 'Audience', 'thecore-collectivity-management' ); ?></th>
						<th><?php esc_html_e( 'Statut', 'thecore-collectivity-management' ); ?></th>
						<th><?php esc_html_e( 'Importes', 'thecore-collectivity-management' ); ?></th>
						<th><?php esc_html_e( 'Message', 'thecore-collectivity-management' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $imports ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'Aucun import pour le moment.', 'thecore-collectivity-management' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $imports as $import ) : ?>
							<tr>
								<td><?php echo esc_html( $import['started_at_gmt'] ); ?></td>
								<td><?php echo esc_html( $import['audience'] ); ?></td>
								<td><?php echo esc_html( $import['status'] ); ?></td>
								<td><?php echo esc_html( (string) $import['items_imported'] ); ?></td>
								<td><?php echo esc_html( $import['message'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
