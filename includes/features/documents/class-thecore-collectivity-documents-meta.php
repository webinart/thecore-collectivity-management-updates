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
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
		add_action( 'save_post_' . TheCore_Collectivity_Documents_Post_Type::POST_TYPE, array( $this, 'save_metabox' ), 10, 2 );
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
			<label><input type="checkbox" name="tccm_doc_featured" value="1" <?php checked( '1', $featured ); ?> /> <?php esc_html_e( 'Mettre en avant', 'thecore-collectivity-management' ); ?></label>
		</p>
		<p>
			<label for="tccm-doc-sort-order"><?php esc_html_e( 'Ordre d affichage', 'thecore-collectivity-management' ); ?></label><br />
			<input type="number" id="tccm-doc-sort-order" name="tccm_doc_sort_order" class="small-text" value="<?php echo esc_attr( $sort_order ); ?>" min="0" step="1" />
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
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( TheCore_Collectivity_Documents_Post_Type::POST_TYPE !== $post->post_type ) {
			return;
		}

		$this->update_or_delete_meta( $post_id, self::META_SUMMARY, isset( $_POST['tccm_doc_summary'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tccm_doc_summary'] ) ) : '' );
		$this->update_or_delete_meta( $post_id, self::META_EXTERNAL_URL, isset( $_POST['tccm_doc_external_url'] ) ? esc_url_raw( wp_unslash( $_POST['tccm_doc_external_url'] ) ) : '' );
		$this->update_or_delete_meta( $post_id, self::META_REFERENCE, isset( $_POST['tccm_doc_reference'] ) ? sanitize_text_field( wp_unslash( $_POST['tccm_doc_reference'] ) ) : '' );
		$this->update_or_delete_meta( $post_id, self::META_VERSION_LABEL, isset( $_POST['tccm_doc_version_label'] ) ? sanitize_text_field( wp_unslash( $_POST['tccm_doc_version_label'] ) ) : '' );
		$this->update_or_delete_meta( $post_id, self::META_EFFECTIVE_DATE, $this->sanitize_date_input( isset( $_POST['tccm_doc_effective_date'] ) ? wp_unslash( $_POST['tccm_doc_effective_date'] ) : '' ) );
		$this->update_or_delete_meta( $post_id, self::META_UPDATED_AT, $this->sanitize_date_input( isset( $_POST['tccm_doc_updated_at'] ) ? wp_unslash( $_POST['tccm_doc_updated_at'] ) : '' ) );
		update_post_meta( $post_id, self::META_SORT_ORDER, isset( $_POST['tccm_doc_sort_order'] ) ? intval( wp_unslash( $_POST['tccm_doc_sort_order'] ) ) : 0 );
		update_post_meta( $post_id, self::META_FEATURED, isset( $_POST['tccm_doc_featured'] ) ? '1' : '0' );

		$attachment_id = isset( $_POST['tccm_doc_attachment_id'] ) ? intval( wp_unslash( $_POST['tccm_doc_attachment_id'] ) ) : 0;
		if ( $attachment_id > 0 ) {
			update_post_meta( $post_id, self::META_ATTACHMENT_ID, $attachment_id );
		} else {
			delete_post_meta( $post_id, self::META_ATTACHMENT_ID );
		}

		$related_procedures = isset( $_POST['tccm_doc_related_procedure_ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['tccm_doc_related_procedure_ids'] ) ) : array();
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
	 * @param string $value    Value.
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
	 * @param string $value Raw value.
	 * @return string
	 */
	private function sanitize_date_input( $value ) {
		$value = trim( sanitize_text_field( $value ) );
		if ( '' === $value ) {
			return '';
		}

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return '';
		}

		return $value;
	}
}
