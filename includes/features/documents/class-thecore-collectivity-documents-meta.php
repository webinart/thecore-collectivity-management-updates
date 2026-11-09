<?php
/**
 * Documents meta boxes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Documents_Meta {
	/**
	 * Nonce config.
	 */
	const NONCE_ACTION = 'tccm_document_meta_action';
	const NONCE_NAME   = 'tccm_document_meta_nonce';

	/**
	 * Meta keys.
	 */
	const META_SUMMARY               = '_tccm_doc_summary';
	const META_ATTACHMENT_ID         = '_tccm_doc_attachment_id';
	const META_EXTERNAL_URL          = '_tccm_doc_external_url';
	const META_DOWNLOAD_LABEL        = '_tccm_doc_download_label';
	const META_READER_URL            = '_tccm_doc_reader_url';
	const META_READER_LABEL          = '_tccm_doc_reader_label';
	const META_PAGE_COUNT            = '_tccm_doc_page_count';
	const META_REFERENCE             = '_tccm_doc_reference';
	const META_VERSION_LABEL         = '_tccm_doc_version_label';
	const META_EFFECTIVE_DATE        = '_tccm_doc_effective_date';
	const META_UPDATED_AT            = '_tccm_doc_updated_at';
	const META_SORT_ORDER            = '_tccm_doc_sort_order';
	const META_FEATURED              = '_tccm_doc_featured';
	const META_RELATED_PROCEDURE_IDS = '_tccm_doc_related_procedure_ids';

	/**
	 * Whether the media script has already been rendered.
	 *
	 * @var bool
	 */
	private static $media_script_rendered = false;

	/**
	 * Prevent document save hooks from re-entering the native order update.
	 *
	 * @var bool
	 */
	private $syncing_sort_order = false;

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register_meta' ), 20 );
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
		add_action( 'save_post_' . TheCore_Collectivity_Documents_Post_Type::POST_TYPE, array( $this, 'save_metabox' ), 10, 2 );
		add_action( 'added_post_meta', array( $this, 'sync_native_sort_order' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'sync_native_sort_order' ), 10, 4 );
	}

	/**
	 * Register the document fields without publishing protected download URLs in REST.
	 */
	public function register_meta() {
		$fields = array(
			self::META_SUMMARY        => array( 'string', 'sanitize_summary_input', '' ),
			self::META_ATTACHMENT_ID  => array( 'integer', 'sanitize_attachment_input', 0 ),
			self::META_EXTERNAL_URL   => array( 'string', 'sanitize_url_input', '' ),
			self::META_DOWNLOAD_LABEL => array( 'string', 'sanitize_text_input', '' ),
			self::META_READER_URL     => array( 'string', 'sanitize_url_input', '' ),
			self::META_READER_LABEL   => array( 'string', 'sanitize_text_input', '' ),
			self::META_PAGE_COUNT     => array( 'integer', 'sanitize_order_input', 0 ),
			self::META_REFERENCE      => array( 'string', 'sanitize_text_input', '' ),
			self::META_VERSION_LABEL  => array( 'string', 'sanitize_text_input', '' ),
			self::META_EFFECTIVE_DATE => array( 'string', 'sanitize_date_input', '' ),
			self::META_UPDATED_AT     => array( 'string', 'sanitize_date_input', '' ),
			self::META_SORT_ORDER     => array( 'integer', 'sanitize_order_input', 0 ),
			self::META_FEATURED       => array( 'string', 'sanitize_featured_input', '0' ),
		);

		foreach ( $fields as $key => $definition ) {
			register_post_meta(
				TheCore_Collectivity_Documents_Post_Type::POST_TYPE,
				$key,
				array(
					'type'              => $definition[0],
					'single'            => true,
					'default'           => $definition[2],
					'sanitize_callback' => array( $this, $definition[1] ),
					'auth_callback'     => array( $this, 'authorize_meta_update' ),
					'show_in_rest'      => array(
						'schema' => array( 'context' => array( 'edit' ) ),
					),
				)
			);
		}
	}

	/**
	 * Restrict registered field writes to users allowed to edit this document.
	 *
	 * @param bool   $allowed Existing permission.
	 * @param string $key     Meta key.
	 * @param int    $post_id Document id.
	 * @return bool
	 */
	public function authorize_meta_update( $allowed, $key, $post_id ) {
		return TheCore_Collectivity_Documents_Post_Type::POST_TYPE === get_post_type( $post_id )
			&& current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Keep the native WordPress order usable by Elementor's standard loop query.
	 *
	 * @param int    $meta_id Meta id.
	 * @param int    $post_id Document id.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Updated value.
	 */
	public function sync_native_sort_order( $meta_id, $post_id, $key, $value ) {
		if ( self::META_SORT_ORDER !== $key || $this->syncing_sort_order ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! ( $post instanceof WP_Post ) || TheCore_Collectivity_Documents_Post_Type::POST_TYPE !== $post->post_type ) {
			return;
		}

		$order = $this->sanitize_order_input( $value );
		if ( $order === (int) $post->menu_order ) {
			return;
		}

		$this->syncing_sort_order = true;
		try {
			wp_update_post( array( 'ID' => (int) $post_id, 'menu_order' => $order ) );
		} finally {
			$this->syncing_sort_order = false;
		}
	}

	/**
	 * Register metabox.
	 */
	public function register_metabox() {
		add_meta_box(
			'tccm-document-settings',
			__( 'Parametres du document', 'thecore-collectivity-management' ),
			array( $this, 'render_metabox' ),
			TheCore_Collectivity_Documents_Post_Type::POST_TYPE,
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
		wp_enqueue_media();
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$summary               = (string) get_post_meta( $post->ID, self::META_SUMMARY, true );
		$attachment_id         = (int) get_post_meta( $post->ID, self::META_ATTACHMENT_ID, true );
		$external_url          = (string) get_post_meta( $post->ID, self::META_EXTERNAL_URL, true );
		$download_label        = (string) get_post_meta( $post->ID, self::META_DOWNLOAD_LABEL, true );
		$reader_url            = (string) get_post_meta( $post->ID, self::META_READER_URL, true );
		$reader_label          = (string) get_post_meta( $post->ID, self::META_READER_LABEL, true );
		$page_count            = (int) get_post_meta( $post->ID, self::META_PAGE_COUNT, true );
		$reference             = (string) get_post_meta( $post->ID, self::META_REFERENCE, true );
		$version_label         = (string) get_post_meta( $post->ID, self::META_VERSION_LABEL, true );
		$effective_date        = (string) get_post_meta( $post->ID, self::META_EFFECTIVE_DATE, true );
		$updated_at            = (string) get_post_meta( $post->ID, self::META_UPDATED_AT, true );
		$sort_order            = (string) get_post_meta( $post->ID, self::META_SORT_ORDER, true );
		$featured              = (string) get_post_meta( $post->ID, self::META_FEATURED, true );
		$related_procedure_ids = get_post_meta( $post->ID, self::META_RELATED_PROCEDURE_IDS, true );
		$related_procedure_ids = is_array( $related_procedure_ids ) ? array_map( 'intval', $related_procedure_ids ) : array();
		$available_procedures  = $this->get_available_procedures();
		$attachment_label      = '';

		if ( $attachment_id > 0 ) {
			$attachment_url = wp_get_attachment_url( $attachment_id );
			$attachment     = get_post( $attachment_id );
			if ( $attachment instanceof WP_Post ) {
				$attachment_label = $attachment->post_title;
			}
			if ( $attachment_url ) {
				$attachment_label = $attachment_label ? $attachment_label . ' - ' . $attachment_url : $attachment_url;
			}
		}
		?>
		<p>
			<label for="tccm-doc-summary"><strong><?php esc_html_e( 'Resume', 'thecore-collectivity-management' ); ?></strong></label><br />
			<textarea id="tccm-doc-summary" name="tccm_doc_summary" class="widefat" rows="3"><?php echo esc_textarea( $summary ); ?></textarea>
		</p>
		<p><strong><?php esc_html_e( 'Source du document', 'thecore-collectivity-management' ); ?></strong></p>
		<input type="hidden" id="tccm-doc-attachment-id" name="tccm_doc_attachment_id" value="<?php echo esc_attr( $attachment_id ); ?>" />
		<p>
			<input type="text" id="tccm-doc-attachment-label" class="widefat" value="<?php echo esc_attr( $attachment_label ); ?>" readonly />
		</p>
		<p>
			<button type="button" class="button" data-tccm-media-pick data-target="#tccm-doc-attachment-id" data-label="#tccm-doc-attachment-label"><?php esc_html_e( 'Choisir un fichier de la mediatheque', 'thecore-collectivity-management' ); ?></button>
			<button type="button" class="button" data-tccm-media-clear data-target="#tccm-doc-attachment-id" data-label="#tccm-doc-attachment-label"><?php esc_html_e( 'Retirer le fichier', 'thecore-collectivity-management' ); ?></button>
		</p>
		<p>
			<label for="tccm-doc-external-url"><?php esc_html_e( 'Ou URL externe', 'thecore-collectivity-management' ); ?></label><br />
			<input type="url" id="tccm-doc-external-url" name="tccm_doc_external_url" class="widefat" value="<?php echo esc_attr( $external_url ); ?>" />
		</p>
		<p>
			<label for="tccm-doc-download-label"><?php esc_html_e( 'Libelle du lien principal', 'thecore-collectivity-management' ); ?></label><br />
			<input type="text" id="tccm-doc-download-label" name="tccm_doc_download_label" class="widefat" value="<?php echo esc_attr( $download_label ); ?>" />
		</p>
		<p>
			<label for="tccm-doc-reader-url"><?php esc_html_e( 'URL de consultation secondaire', 'thecore-collectivity-management' ); ?></label><br />
			<input type="url" id="tccm-doc-reader-url" name="tccm_doc_reader_url" class="widefat" value="<?php echo esc_attr( $reader_url ); ?>" />
		</p>
		<p>
			<label for="tccm-doc-reader-label"><?php esc_html_e( 'Libelle du lien secondaire', 'thecore-collectivity-management' ); ?></label><br />
			<input type="text" id="tccm-doc-reader-label" name="tccm_doc_reader_label" class="widefat" value="<?php echo esc_attr( $reader_label ); ?>" />
		</p>
		<p>
			<label for="tccm-doc-page-count"><?php esc_html_e( 'Nombre de pages (facultatif)', 'thecore-collectivity-management' ); ?></label><br />
			<input type="number" id="tccm-doc-page-count" name="tccm_doc_page_count" class="small-text" value="<?php echo esc_attr( $page_count > 0 ? $page_count : '' ); ?>" min="0" step="1" />
		</p>
		<hr />
		<p>
			<label for="tccm-doc-reference"><strong><?php esc_html_e( 'Reference', 'thecore-collectivity-management' ); ?></strong></label><br />
			<input type="text" id="tccm-doc-reference" name="tccm_doc_reference" class="regular-text" value="<?php echo esc_attr( $reference ); ?>" />
		</p>
		<p>
			<label for="tccm-doc-version"><?php esc_html_e( 'Version', 'thecore-collectivity-management' ); ?></label><br />
			<input type="text" id="tccm-doc-version" name="tccm_doc_version_label" class="regular-text" value="<?php echo esc_attr( $version_label ); ?>" />
		</p>
		<p>
			<label for="tccm-doc-effective-date"><?php esc_html_e( 'Date d effet', 'thecore-collectivity-management' ); ?></label><br />
			<input type="date" id="tccm-doc-effective-date" name="tccm_doc_effective_date" value="<?php echo esc_attr( $effective_date ); ?>" />
		</p>
		<p>
			<label for="tccm-doc-updated-at"><?php esc_html_e( 'Date de mise a jour', 'thecore-collectivity-management' ); ?></label><br />
			<input type="date" id="tccm-doc-updated-at" name="tccm_doc_updated_at" value="<?php echo esc_attr( $updated_at ); ?>" />
		</p>
		<p>
			<input type="hidden" name="tccm_doc_featured" value="0" />
			<label><input type="checkbox" name="tccm_doc_featured" value="1" <?php checked( '1', $featured ); ?> /> <?php esc_html_e( 'Mettre en avant', 'thecore-collectivity-management' ); ?></label>
		</p>
		<p>
			<label for="tccm-doc-sort-order"><?php esc_html_e( 'Ordre d affichage global', 'thecore-collectivity-management' ); ?></label><br />
			<input type="number" id="tccm-doc-sort-order" name="tccm_doc_sort_order" class="small-text" value="<?php echo esc_attr( $sort_order ); ?>" min="0" step="1" />
			<span class="description"><?php esc_html_e( 'Cet ordre s applique dans toutes les collections.', 'thecore-collectivity-management' ); ?></span>
		</p>
		<hr />
		<p>
			<label for="tccm-doc-related-procedures"><strong><?php esc_html_e( 'Demarches liees', 'thecore-collectivity-management' ); ?></strong></label><br />
			<select id="tccm-doc-related-procedures" name="tccm_doc_related_procedure_ids[]" class="widefat" multiple size="8">
				<?php foreach ( $available_procedures as $procedure ) : ?>
					<option value="<?php echo esc_attr( $procedure->ID ); ?>" <?php selected( in_array( (int) $procedure->ID, $related_procedure_ids, true ) ); ?>><?php echo esc_html( get_the_title( $procedure ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
		$this->render_media_script();
	}

	/**
	 * Save metabox.
	 *
	 * @param int     $post_id Post id.
	 * @param WP_Post $post    Post object.
	 */
	public function save_metabox( $post_id, $post ) {
		if ( $this->syncing_sort_order ) {
			return;
		}

		if (
			! isset( $_POST[ self::NONCE_NAME ] )
			|| ! is_string( $_POST[ self::NONCE_NAME ] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION )
		) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! ( $post instanceof WP_Post ) || TheCore_Collectivity_Documents_Post_Type::POST_TYPE !== $post->post_type ) {
			return;
		}

		$fields = array(
			'tccm_doc_summary'        => array( self::META_SUMMARY, 'sanitize_summary_input' ),
			'tccm_doc_external_url'   => array( self::META_EXTERNAL_URL, 'sanitize_url_input' ),
			'tccm_doc_download_label' => array( self::META_DOWNLOAD_LABEL, 'sanitize_text_input' ),
			'tccm_doc_reader_url'     => array( self::META_READER_URL, 'sanitize_url_input' ),
			'tccm_doc_reader_label'   => array( self::META_READER_LABEL, 'sanitize_text_input' ),
			'tccm_doc_page_count'     => array( self::META_PAGE_COUNT, 'sanitize_order_input' ),
			'tccm_doc_reference'      => array( self::META_REFERENCE, 'sanitize_text_input' ),
			'tccm_doc_version_label'  => array( self::META_VERSION_LABEL, 'sanitize_text_input' ),
			'tccm_doc_effective_date' => array( self::META_EFFECTIVE_DATE, 'sanitize_date_input' ),
			'tccm_doc_updated_at'     => array( self::META_UPDATED_AT, 'sanitize_date_input' ),
			'tccm_doc_sort_order'     => array( self::META_SORT_ORDER, 'sanitize_order_input' ),
			'tccm_doc_featured'       => array( self::META_FEATURED, 'sanitize_featured_input' ),
		);

		foreach ( $fields as $input => $definition ) {
			if ( array_key_exists( $input, $_POST ) ) {
				$value = $this->{$definition[1]}( wp_unslash( $_POST[ $input ] ) );
				$this->update_or_delete_meta( $post_id, $definition[0], $value );
			}
		}

		if ( array_key_exists( 'tccm_doc_attachment_id', $_POST ) ) {
			$attachment_id = $this->sanitize_attachment_input( wp_unslash( $_POST['tccm_doc_attachment_id'] ) );
			if ( $attachment_id > 0 ) {
				update_post_meta( $post_id, self::META_ATTACHMENT_ID, $attachment_id );
			} else {
				delete_post_meta( $post_id, self::META_ATTACHMENT_ID );
			}
		}

		$related_procedures = isset( $_POST['tccm_doc_related_procedure_ids'] ) ? (array) wp_unslash( $_POST['tccm_doc_related_procedure_ids'] ) : array();
		$related_procedures = array_map( 'intval', array_filter( $related_procedures, 'is_scalar' ) );
		$related_procedures = array_values( array_filter( array_unique( $related_procedures ) ) );
		if ( empty( $related_procedures ) ) {
			delete_post_meta( $post_id, self::META_RELATED_PROCEDURE_IDS );
		} else {
			update_post_meta( $post_id, self::META_RELATED_PROCEDURE_IDS, $related_procedures );
		}
	}

	/**
	 * Render media script once.
	 */
	private function render_media_script() {
		if ( self::$media_script_rendered ) {
			return;
		}

		self::$media_script_rendered = true;
		?>
		<script>
		document.addEventListener('click', function(event) {
			var pickButton = event.target.closest('[data-tccm-media-pick]');
			if (pickButton) {
				if (typeof wp === 'undefined' || !wp.media) {
					return;
				}
				event.preventDefault();
				var targetField = document.querySelector(pickButton.getAttribute('data-target'));
				var labelField = document.querySelector(pickButton.getAttribute('data-label'));
				var frame = wp.media({
					title: 'Choisir un document',
					button: { text: 'Utiliser ce document' },
					multiple: false,
					library: { type: '' }
				});
				frame.on('select', function() {
					var attachment = frame.state().get('selection').first().toJSON();
					if (targetField) {
						targetField.value = attachment.id || '';
					}
					if (labelField) {
						labelField.value = attachment.title ? attachment.title + ' - ' + attachment.url : attachment.url;
					}
				});
				frame.open();
				return;
			}

			var clearButton = event.target.closest('[data-tccm-media-clear]');
			if (!clearButton) {
				return;
			}
			event.preventDefault();
			var clearTarget = document.querySelector(clearButton.getAttribute('data-target'));
			var clearLabel = document.querySelector(clearButton.getAttribute('data-label'));
			if (clearTarget) {
				clearTarget.value = '';
			}
			if (clearLabel) {
				clearLabel.value = '';
			}
		});
		</script>
		<?php
	}

	/**
	 * Get available procedures for relation field.
	 *
	 * @return WP_Post[]
	 */
	private function get_available_procedures() {
		if ( ! class_exists( 'TheCore_Collectivity_Procedures_Post_Type', false ) ) {
			return array();
		}

		return get_posts(
			array(
				'post_type'      => TheCore_Collectivity_Procedures_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * Update scalar meta or delete when empty.
	 *
	 * @param int    $post_id  Post id.
	 * @param string $meta_key Meta key.
	 * @param string|int $value Value.
	 */
	private function update_or_delete_meta( $post_id, $meta_key, $value ) {
		if ( '' === $value ) {
			delete_post_meta( $post_id, $meta_key );
			return;
		}

		update_post_meta( $post_id, $meta_key, $value );
	}

	/**
	 * Sanitize date input.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_date_input( $value ) {
		$value = $this->sanitize_text_input( $value );
		if ( '' === $value ) {
			return '';
		}

		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts ) || ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * Sanitize a scalar text field.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_text_input( $value ) {
		return is_scalar( $value ) ? trim( sanitize_text_field( (string) $value ) ) : '';
	}

	/**
	 * Sanitize the multiline summary.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_summary_input( $value ) {
		return is_scalar( $value ) ? trim( sanitize_textarea_field( (string) $value ) ) : '';
	}

	/**
	 * Only store absolute public web links, never executable or local file URLs.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_url_input( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = trim( (string) $value );
		$parts = wp_parse_url( $value );
		if (
			! is_array( $parts )
			|| empty( $parts['host'] )
			|| empty( $parts['scheme'] )
			|| ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true )
			|| isset( $parts['user'] )
			|| isset( $parts['pass'] )
		) {
			return '';
		}

		return esc_url_raw( $value, array( 'http', 'https' ) );
	}

	/**
	 * Accept only an actual attachment with a usable web URL.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public function sanitize_attachment_input( $value ) {
		$id = is_scalar( $value ) ? max( 0, intval( $value ) ) : 0;
		if ( $id <= 0 || 'attachment' !== get_post_type( $id ) || '' === $this->sanitize_url_input( wp_get_attachment_url( $id ) ) ) {
			return 0;
		}

		return $id;
	}

	/**
	 * Preserve a non-negative global display order.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public function sanitize_order_input( $value ) {
		return is_scalar( $value ) ? max( 0, intval( $value ) ) : 0;
	}

	/**
	 * Preserve the existing string representation of the featured flag.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_featured_input( $value ) {
		return in_array( $value, array( '1', 1, true ), true ) ? '1' : '0';
	}
}
