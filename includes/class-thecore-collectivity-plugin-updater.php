<?php
/**
 * Plugin updater for The Core - Collectivity Management.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Plugin_Updater {
	/**
	 * Settings page slug.
	 */
	const SETTINGS_PAGE_SLUG = 'thecore-collectivity-update-settings';

	/**
	 * Settings save action.
	 */
	const ACTION_SAVE_SETTINGS = 'thecore_collectivity_save_update_settings';

	/**
	 * Stored update channel option name.
	 */
	const OPTION_UPDATE_CHANNEL = 'thecore_collectivity_management_update_channel';

	/**
	 * Default source branch.
	 */
	const DEFAULT_BRANCH = 'main';

	/**
	 * Default manifest branch.
	 */
	const DEFAULT_MANIFEST_BRANCH = 'plugin-updates';

	/**
	 * Default source repository.
	 */
	const DEFAULT_REPOSITORY = 'webinart/thecore-collectivity-management';

	/**
	 * Default distribution repository.
	 *
	 * Public distribution repository hosting manifests and packaged archives.
	 */
	const DEFAULT_DISTRIBUTION_REPOSITORY = 'webinart/thecore-collectivity-management-updates';

	/**
	 * Whether hooks have already been registered.
	 *
	 * @var bool
	 */
	private static $bootstrapped = false;

	/**
	 * Register updater hooks.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		if ( self::$bootstrapped ) {
			return;
		}

		self::$bootstrapped = true;

		$context = self::get_context();

		if ( null === $context || '' === $context['hostname'] ) {
			return;
		}

		add_filter( 'update_plugins_' . $context['hostname'], array( __CLASS__, 'filter_update_response' ), 10, 4 );
		add_filter( 'plugins_api', array( __CLASS__, 'filter_plugin_information' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'normalize_package_source' ), 10, 4 );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ) );
		add_action( 'admin_post_' . self::ACTION_SAVE_SETTINGS, array( __CLASS__, 'handle_save_settings' ) );
		add_filter( 'plugin_action_links_' . THECORE_COLLECTIVITY_MANAGEMENT_BASENAME, array( __CLASS__, 'add_plugin_action_links' ) );
	}

	/**
	 * Register the update settings page.
	 *
	 * @return void
	 */
	public static function register_admin_page() {
		add_options_page(
			__( 'The Core Collectivity', 'thecore-collectivity-management' ),
			__( 'The Core Collectivity', 'thecore-collectivity-management' ),
			'manage_options',
			self::SETTINGS_PAGE_SLUG,
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Add a shortcut to the update settings from the plugins list.
	 *
	 * @param array<int,string> $links Existing action links.
	 * @return array<int,string>
	 */
	public static function add_plugin_action_links( $links ) {
		$url = admin_url( 'options-general.php?page=' . self::SETTINGS_PAGE_SLUG );

		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $url ),
				esc_html__( 'Canal de mise a jour', 'thecore-collectivity-management' )
			)
		);

		return $links;
	}

	/**
	 * Handle settings save.
	 *
	 * @return void
	 */
	public static function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acces refuse.', 'thecore-collectivity-management' ) );
		}

		check_admin_referer( self::ACTION_SAVE_SETTINGS );

		$channel = isset( $_POST['thecore_collectivity_update_channel'] ) ? sanitize_key( wp_unslash( $_POST['thecore_collectivity_update_channel'] ) ) : '';

		if ( ! in_array( $channel, array( 'prod', 'beta' ), true ) ) {
			$channel = 'prod';
		}

		update_option( self::OPTION_UPDATE_CHANNEL, $channel );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => self::SETTINGS_PAGE_SLUG,
					'settings-updated' => 'true',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Render the update settings page.
	 *
	 * @return void
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acces refuse.', 'thecore-collectivity-management' ) );
		}

		$stored_channel    = self::get_saved_channel();
		$effective_channel = self::get_effective_channel();
		$selected_channel  = in_array( $stored_channel, array( 'prod', 'beta' ), true ) ? $stored_channel : $effective_channel;
		$version           = defined( 'THECORE_COLLECTIVITY_MANAGEMENT_VERSION' ) ? THECORE_COLLECTIVITY_MANAGEMENT_VERSION : '';
		$is_forced         = defined( 'THECORE_COLLECTIVITY_MANAGEMENT_UPDATE_CHANNEL' ) && '' !== trim( (string) constant( 'THECORE_COLLECTIVITY_MANAGEMENT_UPDATE_CHANNEL' ) );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'The Core Collectivity', 'thecore-collectivity-management' ); ?></h1>
			<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html__( 'Canal de mise a jour enregistre.', 'thecore-collectivity-management' ); ?></p>
				</div>
			<?php endif; ?>

			<p><?php echo esc_html__( 'Choisissez si ce site doit recevoir les mises a jour stables ou beta du plugin.', 'thecore-collectivity-management' ); ?></p>

			<table class="widefat striped" style="max-width: 760px; margin: 16px 0 24px;">
				<tbody>
					<tr>
						<td style="width: 240px;"><strong><?php echo esc_html__( 'Version locale du plugin', 'thecore-collectivity-management' ); ?></strong></td>
						<td><?php echo esc_html( $version ); ?></td>
					</tr>
					<tr>
						<td><strong><?php echo esc_html__( 'Canal enregistre', 'thecore-collectivity-management' ); ?></strong></td>
						<td><?php echo esc_html( '' !== $stored_channel ? $stored_channel : __( 'Non defini', 'thecore-collectivity-management' ) ); ?></td>
					</tr>
					<tr>
						<td><strong><?php echo esc_html__( 'Canal effectif', 'thecore-collectivity-management' ); ?></strong></td>
						<td><?php echo esc_html( $effective_channel ); ?></td>
					</tr>
				</tbody>
			</table>

			<?php if ( $is_forced ) : ?>
				<div class="notice notice-warning inline">
					<p><?php echo esc_html__( 'Le canal est actuellement force par constante dans la configuration WordPress. Le selecteur ci-dessous reste enregistre, mais cette constante garde la priorite.', 'thecore-collectivity-management' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width: 760px;">
				<?php wp_nonce_field( self::ACTION_SAVE_SETTINGS ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SAVE_SETTINGS ); ?>" />

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="thecore-collectivity-update-channel"><?php echo esc_html__( 'Canal de mise a jour', 'thecore-collectivity-management' ); ?></label>
							</th>
							<td>
								<select id="thecore-collectivity-update-channel" name="thecore_collectivity_update_channel">
									<option value="prod" <?php selected( $selected_channel, 'prod' ); ?>><?php echo esc_html__( 'Production (stable)', 'thecore-collectivity-management' ); ?></option>
									<option value="beta" <?php selected( $selected_channel, 'beta' ); ?>><?php echo esc_html__( 'Beta', 'thecore-collectivity-management' ); ?></option>
								</select>
								<p class="description"><?php echo esc_html__( 'Utilisez beta sur les environnements de test. Utilisez production sur les sites stabilises.', 'thecore-collectivity-management' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Enregistrer', 'thecore-collectivity-management' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Filter the update payload returned by WordPress.
	 *
	 * @param false|array<string,mixed> $update      Existing update payload.
	 * @param array<string,mixed>       $plugin_data Current plugin headers.
	 * @param string                    $plugin_file Plugin basename.
	 * @param array<int,string>         $locales     Installed locales.
	 * @return false|array<string,mixed>
	 */
	public static function filter_update_response( $update, array $plugin_data, $plugin_file, array $locales = array() ) {
		unset( $locales );

		$context = self::get_context();

		if ( null === $context ) {
			return $update;
		}

		if ( $plugin_file !== $context['plugin'] ) {
			return $update;
		}

		$update_uri = isset( $plugin_data['UpdateURI'] ) ? trim( (string) $plugin_data['UpdateURI'] ) : '';

		if ( '' === $update_uri || self::normalize_url( $update_uri ) !== self::normalize_url( $context['update_uri'] ) ) {
			return $update;
		}

		$manifest = self::fetch_remote_manifest( $context['manifest_url'] );

		if ( null !== $manifest ) {
			$payload = self::build_update_payload_from_manifest( $context, $manifest );

			if ( false !== $payload ) {
				return $payload;
			}
		}

		$remote_headers = self::fetch_remote_plugin_headers(
			$context['repository'],
			$context['branch'],
			$context['entry_file']
		);

		if ( null === $remote_headers ) {
			return $update;
		}

		$payload = self::build_update_payload_from_headers( $context, $remote_headers );

		return false !== $payload ? $payload : $update;
	}

	/**
	 * Provide plugin information for the WordPress update details modal.
	 *
	 * @param false|object|array<string,mixed>|WP_Error $result Existing result.
	 * @param string                                    $action Requested API action.
	 * @param object                                    $args   API arguments.
	 * @return false|object|array<string,mixed>|WP_Error
	 */
	public static function filter_plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== (string) $action || ! is_object( $args ) ) {
			return $result;
		}

		$context = self::get_context();

		if ( null === $context ) {
			return $result;
		}

		$requested_slug = isset( $args->slug ) ? sanitize_text_field( (string) $args->slug ) : '';
		$valid_slugs    = array_filter(
			array(
				(string) $context['slug'],
				(string) THECORE_COLLECTIVITY_MANAGEMENT_BASENAME,
				basename( (string) THECORE_COLLECTIVITY_MANAGEMENT_BASENAME ),
			)
		);

		if ( '' === $requested_slug || ! in_array( $requested_slug, $valid_slugs, true ) ) {
			return $result;
		}

		$manifest       = self::fetch_remote_manifest( $context['manifest_url'] );
		$remote_headers = self::fetch_remote_plugin_headers(
			$context['repository'],
			$context['branch'],
			$context['entry_file']
		);

		return self::build_plugin_information( $context, $manifest, $remote_headers );
	}

	/**
	 * Normalize the downloaded package directory to the expected plugin slug.
	 *
	 * @param string|false             $source        Extracted source directory.
	 * @param string                   $remote_source WordPress upgrader remote source.
	 * @param mixed                    $upgrader      Upgrader instance.
	 * @param array<string,mixed>      $hook_extra    Upgrade context.
	 * @return string|false
	 */
	public static function normalize_package_source( $source, $remote_source, $upgrader, array $hook_extra = array() ) {
		unset( $upgrader );

		if ( ! is_string( $source ) || '' === $source || '' === $remote_source ) {
			return $source;
		}

		$context = self::get_context();

		if ( null === $context ) {
			return $source;
		}

		if ( ( $hook_extra['type'] ?? '' ) !== 'plugin' ) {
			return $source;
		}

		$matches_plugin      = ( $hook_extra['plugin'] ?? '' ) === $context['plugin'];
		$matches_bulk_update = isset( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) && in_array( $context['plugin'], $hook_extra['plugins'], true );

		if ( ! $matches_plugin && ! $matches_bulk_update ) {
			return $source;
		}

		$expected_dir = $context['slug'];
		$current_dir  = basename( rtrim( $source, "/\\" ) );

		if ( $current_dir === $expected_dir ) {
			return $source;
		}

		global $wp_filesystem;

		if ( ( ! is_object( $wp_filesystem ) || ! method_exists( $wp_filesystem, 'move' ) ) && function_exists( 'WP_Filesystem' ) ) {
			WP_Filesystem();
		}

		if ( ! is_object( $wp_filesystem ) || ! method_exists( $wp_filesystem, 'move' ) ) {
			return $source;
		}

		$normalized_remote_source = rtrim( $remote_source, "/\\" );
		$target                   = $normalized_remote_source . DIRECTORY_SEPARATOR . $expected_dir;

		if ( method_exists( $wp_filesystem, 'exists' ) && $wp_filesystem->exists( $target ) && method_exists( $wp_filesystem, 'delete' ) ) {
			$wp_filesystem->delete( $target, true );
		}

		$moved = $wp_filesystem->move( $source, $target, true );

		if ( ! $moved ) {
			return $source;
		}

		return $target;
	}

	/**
	 * Parse a GitHub repository from an Update URI.
	 *
	 * @param string $update_uri Update URI.
	 * @return string
	 */
	public static function parse_repository_from_update_uri( $update_uri ) {
		$update_uri = trim( (string) $update_uri );

		if ( '' === $update_uri ) {
			return '';
		}

		$parts = parse_url( $update_uri );

		if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['path'] ) ) {
			return '';
		}

		if ( 'github.com' !== strtolower( (string) $parts['host'] ) ) {
			return '';
		}

		$segments = array_values( array_filter( explode( '/', trim( (string) $parts['path'], '/' ) ) ) );

		if ( count( $segments ) < 2 ) {
			return '';
		}

		return $segments[0] . '/' . $segments[1];
	}

	/**
	 * Normalize a branch name.
	 *
	 * @param string $branch Branch name.
	 * @return string
	 */
	public static function normalize_branch( $branch ) {
		$branch = trim( (string) $branch );

		if ( '' === $branch ) {
			return self::DEFAULT_BRANCH;
		}

		if ( 0 === strpos( $branch, 'refs/heads/' ) ) {
			$branch = substr( $branch, strlen( 'refs/heads/' ) );
		}

		$branch = trim( $branch, '/' );

		return '' !== $branch ? $branch : self::DEFAULT_BRANCH;
	}

	/**
	 * Normalize an update channel.
	 *
	 * @param string $channel Channel name.
	 * @return string
	 */
	public static function normalize_channel( $channel ) {
		$channel = trim( strtolower( (string) $channel ) );
		$channel = preg_replace( '/[^a-z0-9._-]+/', '-', $channel );

		if ( ! is_string( $channel ) ) {
			return 'prod';
		}

		$channel = trim( $channel, '-' );
		$channel = preg_replace( '/-+/', '-', $channel );

		return is_string( $channel ) && '' !== $channel ? $channel : 'prod';
	}

	/**
	 * Derive the default release channel from a version string.
	 *
	 * @param string $version Plugin version.
	 * @return string
	 */
	public static function default_channel_for_version( $version ) {
		return false !== stripos( (string) $version, 'beta' ) ? 'beta' : 'prod';
	}

	/**
	 * Build a package zip URL for a GitHub branch.
	 *
	 * @param string $repository Repository slug.
	 * @param string $branch     Branch name.
	 * @return string
	 */
	public static function build_package_url( $repository, $branch ) {
		$repository = trim( (string) $repository, '/' );
		$branch     = self::normalize_branch( $branch );

		if ( '' === $repository ) {
			return '';
		}

		return 'https://github.com/' . $repository . '/archive/refs/heads/' . self::encode_path_segments( $branch ) . '.zip';
	}

	/**
	 * Build the manifest URL.
	 *
	 * @param string $repository      Distribution repository.
	 * @param string $manifest_branch Manifest branch.
	 * @param string $channel         Release channel.
	 * @return string
	 */
	public static function build_manifest_url( $repository, $manifest_branch, $channel ) {
		$repository      = trim( (string) $repository, '/' );
		$manifest_branch = self::normalize_branch( $manifest_branch );
		$channel         = self::normalize_channel( $channel );

		if ( '' === $repository || '' === $manifest_branch || '' === $channel ) {
			return '';
		}

		return 'https://raw.githubusercontent.com/' . $repository . '/' . self::encode_path_segments( $manifest_branch ) . '/' . rawurlencode( $channel ) . '.json';
	}

	/**
	 * Build a details URL.
	 *
	 * @param string $repository Repository slug.
	 * @return string
	 */
	public static function build_release_index_url( $repository ) {
		$repository = trim( (string) $repository, '/' );

		if ( '' === $repository ) {
			return '';
		}

		return 'https://github.com/' . $repository;
	}

	/**
	 * Build an update payload from a manifest.
	 *
	 * @param array<string,string> $context  Local context.
	 * @param array<string,mixed>  $manifest Remote manifest.
	 * @return false|array<string,string>
	 */
	public static function build_update_payload_from_manifest( array $context, array $manifest ) {
		$local_version  = isset( $context['version'] ) ? (string) $context['version'] : '';
		$remote_version = isset( $manifest['version'] ) ? trim( (string) $manifest['version'] ) : '';
		$package        = isset( $manifest['package'] ) ? trim( (string) $manifest['package'] ) : '';

		if ( '' === $local_version || '' === $remote_version || '' === $package ) {
			return false;
		}

		if ( version_compare( $remote_version, $local_version, '<=' ) ) {
			return false;
		}

		$payload = array(
			'slug'        => isset( $context['slug'] ) ? (string) $context['slug'] : '',
			'version'     => $remote_version,
			'new_version' => $remote_version,
			'url'         => self::pick_manifest_string( $manifest, array( 'details_url', 'url' ), isset( $context['details_url'] ) ? (string) $context['details_url'] : '' ),
			'package'     => $package,
		);

		$requires_php = self::pick_manifest_string( $manifest, array( 'requires_php' ), '' );
		if ( '' !== $requires_php ) {
			$payload['requires_php'] = $requires_php;
		}

		$requires_wp = self::pick_manifest_string( $manifest, array( 'requires_wp', 'requires' ), '' );
		if ( '' !== $requires_wp ) {
			$payload['requires'] = $requires_wp;
		}

		$tested = self::pick_manifest_string( $manifest, array( 'tested' ), '' );
		if ( '' !== $tested ) {
			$payload['tested'] = $tested;
		}

		return $payload;
	}

	/**
	 * Build an update payload from remote plugin headers.
	 *
	 * @param array<string,string> $context        Local context.
	 * @param array<string,string> $remote_headers Remote plugin headers.
	 * @return false|array<string,string>
	 */
	public static function build_update_payload_from_headers( array $context, array $remote_headers ) {
		$local_version  = isset( $context['version'] ) ? (string) $context['version'] : '';
		$remote_version = isset( $remote_headers['Version'] ) ? (string) $remote_headers['Version'] : '';

		if ( '' === $local_version || '' === $remote_version || version_compare( $remote_version, $local_version, '<=' ) ) {
			return false;
		}

		$payload = array(
			'slug'        => isset( $context['slug'] ) ? (string) $context['slug'] : '',
			'version'     => $remote_version,
			'new_version' => $remote_version,
			'url'         => isset( $context['source_details_url'] ) ? (string) $context['source_details_url'] : ( isset( $context['details_url'] ) ? (string) $context['details_url'] : '' ),
			'package'     => self::build_package_url(
				isset( $context['distribution_repository'] ) ? (string) $context['distribution_repository'] : ( isset( $context['repository'] ) ? (string) $context['repository'] : '' ),
				isset( $context['branch'] ) ? (string) $context['branch'] : self::DEFAULT_BRANCH
			),
		);

		if ( ! empty( $remote_headers['RequiresPHP'] ) ) {
			$payload['requires_php'] = (string) $remote_headers['RequiresPHP'];
		}

		if ( ! empty( $remote_headers['RequiresWP'] ) ) {
			$payload['requires'] = (string) $remote_headers['RequiresWP'];
		}

		if ( ! empty( $remote_headers['TestedUpTo'] ) ) {
			$payload['tested'] = (string) $remote_headers['TestedUpTo'];
		}

		return $payload;
	}

	/**
	 * Build one plugin information object for WordPress admin.
	 *
	 * @param array<string,string>            $context        Local updater context.
	 * @param array<string,mixed>|null        $manifest       Remote manifest.
	 * @param array<string,string>|null       $remote_headers Remote plugin headers.
	 * @return object
	 */
	public static function build_plugin_information( array $context, $manifest = null, $remote_headers = null ) {
		$local_headers   = self::read_plugin_headers();
		$remote_headers  = is_array( $remote_headers ) ? $remote_headers : array();
		$manifest        = is_array( $manifest ) ? $manifest : array();
		$version         = self::pick_manifest_string( $manifest, array( 'version' ), $remote_headers['Version'] ?? ( $context['version'] ?? '' ) );
		$requires_php    = self::pick_manifest_string( $manifest, array( 'requires_php' ), $remote_headers['RequiresPHP'] ?? ( $local_headers['RequiresPHP'] ?? '' ) );
		$requires_wp     = self::pick_manifest_string( $manifest, array( 'requires_wp', 'requires' ), $remote_headers['RequiresWP'] ?? ( $local_headers['RequiresWP'] ?? '' ) );
		$tested          = self::pick_manifest_string( $manifest, array( 'tested' ), $remote_headers['TestedUpTo'] ?? ( $local_headers['TestedUpTo'] ?? '' ) );
		$download_link   = self::pick_manifest_string( $manifest, array( 'package' ), self::build_package_url( (string) ( $context['distribution_repository'] ?? '' ), (string) ( $context['branch'] ?? self::DEFAULT_BRANCH ) ) );
		$homepage        = self::pick_manifest_string( $manifest, array( 'details_url', 'url' ), (string) ( $context['details_url'] ?? '' ) );
		$name            = ! empty( $remote_headers['PluginName'] ) ? (string) $remote_headers['PluginName'] : ( ! empty( $local_headers['PluginName'] ) ? (string) $local_headers['PluginName'] : 'The Core - Collectivity Management' );
		$description     = ! empty( $remote_headers['Description'] ) ? (string) $remote_headers['Description'] : ( ! empty( $local_headers['Description'] ) ? (string) $local_headers['Description'] : '' );
		$author_name     = ! empty( $remote_headers['Author'] ) ? (string) $remote_headers['Author'] : ( ! empty( $local_headers['Author'] ) ? (string) $local_headers['Author'] : 'The Core' );
		$plugin_uri      = ! empty( $remote_headers['PluginURI'] ) ? (string) $remote_headers['PluginURI'] : ( ! empty( $local_headers['PluginURI'] ) ? (string) $local_headers['PluginURI'] : $homepage );
		$sections        = self::get_plugin_information_sections( $description, $homepage, $context, $manifest );
		$info            = new stdClass();

		$info->name              = $name;
		$info->slug              = (string) ( $context['slug'] ?? '' );
		$info->plugin_name       = (string) THECORE_COLLECTIVITY_MANAGEMENT_BASENAME;
		$info->version           = $version;
		$info->author            = $author_name ? wp_kses_post( $author_name ) : '';
		$info->author_profile    = '';
		$info->homepage          = esc_url_raw( $plugin_uri ?: $homepage );
		$info->requires          = $requires_wp;
		$info->tested            = $tested;
		$info->requires_php      = $requires_php;
		$info->download_link     = esc_url_raw( $download_link );
		$info->trunk             = esc_url_raw( $download_link );
		$info->last_updated      = current_time( 'mysql' );
		$info->sections          = $sections;
		$info->banners           = array();
		$info->icons             = array();
		$info->external          = false;

		return $info;
	}

	/**
	 * Build the local updater context.
	 *
	 * @return array<string,string>|null
	 */
	private static function get_context() {
		$headers           = self::read_plugin_headers();
		$update_uri        = trim( $headers['UpdateURI'] ?? '' );
		$parsed_repository = self::parse_repository_from_update_uri( $update_uri );
		$repository        = self::constant_value( 'THECORE_COLLECTIVITY_MANAGEMENT_UPDATE_REPOSITORY', '' !== $parsed_repository ? $parsed_repository : self::DEFAULT_REPOSITORY );
		$repository        = self::filter_value( 'thecore_collectivity_management_update_repository', $repository, $update_uri );
		$repository        = trim( $repository, '/' );

		if ( '' === $update_uri || '' === $repository ) {
			return null;
		}

		$distribution_repository = self::constant_value(
			'THECORE_COLLECTIVITY_MANAGEMENT_UPDATE_DISTRIBUTION_REPOSITORY',
			self::DEFAULT_DISTRIBUTION_REPOSITORY
		);
		$distribution_repository = self::filter_value(
			'thecore_collectivity_management_update_distribution_repository',
			$distribution_repository,
			$repository,
			$update_uri
		);
		$distribution_repository = trim( $distribution_repository, '/' );

		if ( '' === $distribution_repository ) {
			$distribution_repository = $repository;
		}

		$branch = self::constant_value( 'THECORE_COLLECTIVITY_MANAGEMENT_UPDATE_BRANCH', self::DEFAULT_BRANCH );
		$branch = self::filter_value( 'thecore_collectivity_management_update_branch', $branch, $repository );
		$branch = self::normalize_branch( $branch );

		$channel = self::get_effective_channel( $headers );
		$channel = self::filter_value( 'thecore_collectivity_management_update_channel', $channel, $branch, $repository );
		$channel = self::normalize_channel( $channel );

		$manifest_branch = self::constant_value(
			'THECORE_COLLECTIVITY_MANAGEMENT_UPDATE_MANIFEST_BRANCH',
			self::DEFAULT_MANIFEST_BRANCH
		);
		$manifest_branch = self::filter_value(
			'thecore_collectivity_management_update_manifest_branch',
			$manifest_branch,
			$distribution_repository,
			$repository,
			$channel
		);
		$manifest_branch = self::normalize_branch( $manifest_branch );

		$manifest_url = self::constant_value(
			'THECORE_COLLECTIVITY_MANAGEMENT_UPDATE_MANIFEST_URL',
			self::build_manifest_url( $distribution_repository, $manifest_branch, $channel )
		);
		$manifest_url = self::filter_value(
			'thecore_collectivity_management_update_manifest_url',
			$manifest_url,
			$distribution_repository,
			$channel,
			$manifest_branch,
			$repository
		);

		$slug = dirname( THECORE_COLLECTIVITY_MANAGEMENT_BASENAME );

		return array(
			'plugin'                  => THECORE_COLLECTIVITY_MANAGEMENT_BASENAME,
			'entry_file'              => basename( THECORE_COLLECTIVITY_MANAGEMENT_BASENAME ),
			'slug'                    => $slug,
			'version'                 => trim( (string) ( $headers['Version'] ?? '' ) ),
			'update_uri'              => $update_uri,
			'repository'              => $repository,
			'distribution_repository' => $distribution_repository,
			'branch'                  => $branch,
			'channel'                 => $channel,
			'manifest_branch'         => $manifest_branch,
			'manifest_url'            => trim( (string) $manifest_url ),
			'hostname'                => (string) parse_url( $update_uri, PHP_URL_HOST ),
			'details_url'             => self::build_release_index_url( $distribution_repository ),
			'source_details_url'      => 'https://github.com/' . $repository . '/tree/' . self::encode_path_segments( $branch ),
		);
	}

	/**
	 * Read plugin headers from the local entry file.
	 *
	 * @return array<string,string>
	 */
	private static function read_plugin_headers() {
		$headers = get_file_data(
			THECORE_COLLECTIVITY_MANAGEMENT_FILE,
			array(
				'PluginName'  => 'Plugin Name',
				'PluginURI'   => 'Plugin URI',
				'Description' => 'Description',
				'Author'      => 'Author',
				'Version'     => 'Version',
				'UpdateURI'   => 'Update URI',
				'RequiresPHP' => 'Requires PHP',
				'RequiresWP'  => 'Requires at least',
				'TestedUpTo'  => 'Tested up to',
			),
			'plugin'
		);

		return is_array( $headers ) ? array_map( 'trim', $headers ) : array();
	}

	/**
	 * Get the stored update channel.
	 *
	 * @return string
	 */
	private static function get_saved_channel() {
		if ( ! function_exists( 'get_option' ) ) {
			return '';
		}

		$channel = get_option( self::OPTION_UPDATE_CHANNEL, '' );
		$channel = self::normalize_channel( $channel );

		return in_array( $channel, array( 'prod', 'beta' ), true ) ? $channel : '';
	}

	/**
	 * Get the effective channel before filters.
	 *
	 * @param array<string,string>|null $headers Optional plugin headers.
	 * @return string
	 */
	private static function get_effective_channel( $headers = null ) {
		if ( null === $headers ) {
			$headers = self::read_plugin_headers();
		}

		$default_channel = self::default_channel_for_version( trim( (string) ( $headers['Version'] ?? '' ) ) );
		$saved_channel   = self::get_saved_channel();

		if ( in_array( $saved_channel, array( 'prod', 'beta' ), true ) ) {
			$channel = $saved_channel;
		} else {
			$channel = $default_channel;
		}

		$channel = self::constant_value( 'THECORE_COLLECTIVITY_MANAGEMENT_UPDATE_CHANNEL', $channel );
		$channel = self::normalize_channel( $channel );

		return in_array( $channel, array( 'prod', 'beta' ), true ) ? $channel : $default_channel;
	}

	/**
	 * Fetch a remote JSON manifest.
	 *
	 * @param string $manifest_url Manifest URL.
	 * @return array<string,mixed>|null
	 */
	private static function fetch_remote_manifest( $manifest_url ) {
		if ( '' === $manifest_url || ! function_exists( 'wp_remote_get' ) || ! function_exists( 'wp_remote_retrieve_response_code' ) || ! function_exists( 'wp_remote_retrieve_body' ) ) {
			return null;
		}

		$response = wp_remote_get(
			$manifest_url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'     => 'application/json',
					'User-Agent' => self::build_user_agent(),
				),
			)
		);

		if ( ! is_array( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );

		if ( ! is_string( $body ) || '' === $body ) {
			return null;
		}

		$manifest = json_decode( $body, true );

		return is_array( $manifest ) ? $manifest : null;
	}

	/**
	 * Fetch remote plugin headers from GitHub.
	 *
	 * @param string $repository Repository slug.
	 * @param string $branch     Branch name.
	 * @param string $entry_file Plugin entry file.
	 * @return array<string,string>|null
	 */
	private static function fetch_remote_plugin_headers( $repository, $branch, $entry_file ) {
		if ( ! function_exists( 'wp_remote_get' ) || ! function_exists( 'wp_remote_retrieve_response_code' ) || ! function_exists( 'wp_remote_retrieve_body' ) ) {
			return null;
		}

		$url = 'https://api.github.com/repos/' . trim( (string) $repository, '/' ) . '/contents/' . rawurlencode( (string) $entry_file ) . '?ref=' . rawurlencode( self::normalize_branch( $branch ) );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => self::build_user_agent(),
				),
			)
		);

		if ( ! is_array( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );

		if ( ! is_string( $body ) || '' === $body ) {
			return null;
		}

		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data['content'] ) || ! is_string( $data['content'] ) ) {
			return null;
		}

		$encoded     = str_replace( array( "\r", "\n" ), '', $data['content'] );
		$plugin_file = base64_decode( $encoded, true );

		if ( ! is_string( $plugin_file ) || '' === $plugin_file ) {
			return null;
		}

		return self::parse_plugin_headers( $plugin_file );
	}

	/**
	 * Parse plugin headers from a raw plugin file.
	 *
	 * @param string $plugin_file Plugin file contents.
	 * @return array<string,string>
	 */
	public static function parse_plugin_headers( $plugin_file ) {
		$headers  = array();
		$patterns = array(
			'PluginName'  => '/^[ \t\/*#@]*Plugin Name:\s*(.+)$/mi',
			'PluginURI'   => '/^[ \t\/*#@]*Plugin URI:\s*(.+)$/mi',
			'Description' => '/^[ \t\/*#@]*Description:\s*(.+)$/mi',
			'Author'      => '/^[ \t\/*#@]*Author:\s*(.+)$/mi',
			'Version'     => '/^[ \t\/*#@]*Version:\s*(.+)$/mi',
			'RequiresPHP' => '/^[ \t\/*#@]*Requires PHP:\s*(.+)$/mi',
			'RequiresWP'  => '/^[ \t\/*#@]*Requires at least:\s*(.+)$/mi',
			'TestedUpTo'  => '/^[ \t\/*#@]*Tested up to:\s*(.+)$/mi',
		);

		foreach ( $patterns as $key => $pattern ) {
			if ( 1 === preg_match( $pattern, $plugin_file, $matches ) && isset( $matches[1] ) ) {
				$headers[ $key ] = trim( (string) $matches[1] );
			}
		}

		return $headers;
	}

	/**
	 * Build plugin information sections for the WordPress modal.
	 *
	 * @param string              $description Plugin description.
	 * @param string              $homepage    Homepage URL.
	 * @param array<string,string> $context    Local updater context.
	 * @param array<string,mixed> $manifest    Remote manifest.
	 * @return array<string,string>
	 */
	private static function get_plugin_information_sections( $description, $homepage, array $context, array $manifest ) {
		$sections = array();

		if ( ! empty( $manifest['sections'] ) && is_array( $manifest['sections'] ) ) {
			foreach ( $manifest['sections'] as $key => $value ) {
				$key   = sanitize_key( (string) $key );
				$value = is_string( $value ) ? trim( $value ) : '';

				if ( '' !== $key && '' !== $value ) {
					$sections[ $key ] = wp_kses_post( $value );
				}
			}
		}

		if ( empty( $sections['description'] ) ) {
			$extra = '';
			if ( '' !== $homepage ) {
				$extra = '<p><a href="' . esc_url( $homepage ) . '" target="_blank" rel="noopener noreferrer">Repository et historique des versions</a></p>';
			}
			$sections['description'] = wpautop( esc_html( $description ) ) . $extra;
		}

		if ( empty( $sections['installation'] ) ) {
			$sections['installation'] =
				'<ol>' .
					'<li>Choisir le canal de mise a jour du plugin dans <strong>Reglages &gt; The Core Collectivity</strong>.</li>' .
					'<li>Verifier les mises a jour disponibles dans l administration WordPress.</li>' .
					'<li>Lancer la mise a jour automatique du plugin.</li>' .
				'</ol>';
		}

		if ( empty( $sections['changelog'] ) ) {
			$version = self::pick_manifest_string( $manifest, array( 'version' ), (string) ( $context['version'] ?? '' ) );
			$channel = ! empty( $context['channel'] ) ? (string) $context['channel'] : 'prod';
			$link    = ! empty( $context['source_details_url'] ) ? (string) $context['source_details_url'] : $homepage;

			$sections['changelog'] =
				'<p><strong>Version publiee:</strong> ' . esc_html( $version ) . '</p>' .
				'<p><strong>Canal:</strong> ' . esc_html( $channel ) . '</p>' .
				( '' !== $link ? '<p><a href="' . esc_url( $link ) . '" target="_blank" rel="noopener noreferrer">Voir le code source et l historique GitHub</a></p>' : '' );
		}

		return $sections;
	}

	/**
	 * Pick the first non-empty string from a manifest.
	 *
	 * @param array<string,mixed> $manifest Manifest data.
	 * @param array<int,string>   $keys     Candidate keys.
	 * @param string              $default  Default value.
	 * @return string
	 */
	private static function pick_manifest_string( array $manifest, array $keys, $default = '' ) {
		foreach ( $keys as $key ) {
			if ( ! isset( $manifest[ $key ] ) ) {
				continue;
			}

			$value = trim( (string) $manifest[ $key ] );

			if ( '' !== $value ) {
				return $value;
			}
		}

		return $default;
	}

	/**
	 * Build a user-agent string for remote requests.
	 *
	 * @return string
	 */
	private static function build_user_agent() {
		$blog_version = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'version' ) : 'unknown';
		$home         = function_exists( 'home_url' ) ? (string) home_url( '/' ) : 'http://localhost';

		return 'WordPress/' . $blog_version . '; ' . $home . '; The Core Collectivity Management Updater';
	}

	/**
	 * Encode a slash-separated path.
	 *
	 * @param string $value Path value.
	 * @return string
	 */
	private static function encode_path_segments( $value ) {
		$segments = explode( '/', trim( (string) $value, '/' ) );
		$segments = array_map( 'rawurlencode', $segments );

		return implode( '/', $segments );
	}

	/**
	 * Normalize a URL for comparison.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function normalize_url( $url ) {
		return rtrim( trim( (string) $url ), '/' );
	}

	/**
	 * Read a non-empty string constant value.
	 *
	 * @param string $constant_name Constant name.
	 * @param string $default       Default value.
	 * @return string
	 */
	private static function constant_value( $constant_name, $default ) {
		if ( defined( $constant_name ) ) {
			$value = constant( $constant_name );

			if ( is_string( $value ) && '' !== trim( $value ) ) {
				return trim( $value );
			}
		}

		return $default;
	}

	/**
	 * Apply a string filter safely.
	 *
	 * @param string $hook_name Filter name.
	 * @param string $value     Base value.
	 * @param mixed  ...$args   Extra args.
	 * @return string
	 */
	private static function filter_value( $hook_name, $value, ...$args ) {
		if ( ! function_exists( 'apply_filters' ) ) {
			return $value;
		}

		$filtered = apply_filters( $hook_name, $value, ...$args );

		return is_string( $filtered ) ? trim( $filtered ) : $value;
	}
}
