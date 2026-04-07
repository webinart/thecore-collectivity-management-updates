<?php
/**
 * Procedures meta boxes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Procedures_Meta {
	/**
	 * Nonce config.
	 */
	const NONCE_ACTION = 'tccm_procedure_meta_action';
	const NONCE_NAME   = 'tccm_procedure_meta_nonce';

	/**
	 * Meta keys.
	 */
	const META_SUMMARY                = '_tccm_proc_summary';
	const META_DELAY_LABEL            = '_tccm_proc_delay_label';
	const META_FEE_LABEL              = '_tccm_proc_fee_label';
	const META_AVAILABLE_ONLINE       = '_tccm_proc_available_online';
	const META_APPOINTMENT_REQUIRED   = '_tccm_proc_appointment_required';
	const META_PRIMARY_CTA_LABEL      = '_tccm_proc_primary_cta_label';
	const META_PRIMARY_CTA_URL        = '_tccm_proc_primary_cta_url';
	const META_SECONDARY_CTA_LABEL    = '_tccm_proc_secondary_cta_label';
	const META_SECONDARY_CTA_URL      = '_tccm_proc_secondary_cta_url';
	const META_REQUIRED_DOCUMENTS     = '_tccm_proc_required_documents';
	const META_STEPS                  = '_tccm_proc_steps';
	const META_CASES                  = '_tccm_proc_cases';
	const META_FAQ                    = '_tccm_proc_faq';
	const META_RELATED_DOCUMENT_IDS   = '_tccm_proc_related_document_ids';
	const META_SORT_ORDER             = '_tccm_proc_sort_order';
	const META_FEATURED               = '_tccm_proc_featured';

	/**
	 * Whether repeater script has already been rendered.
	 *
	 * @var bool
	 */
	private static $repeater_script_rendered = false;

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
		add_action( 'save_post_' . TheCore_Collectivity_Procedures_Post_Type::POST_TYPE, array( $this, 'save_metabox' ), 10, 2 );
	}

	/**
	 * Register metabox.
	 */
	public function register_metabox() {
		add_meta_box(
			'tccm-procedure-settings',
			__( 'Parametres de la demarche', 'thecore-collectivity-management' ),
			array( $this, 'render_metabox' ),
			TheCore_Collectivity_Procedures_Post_Type::POST_TYPE,
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
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$summary              = (string) get_post_meta( $post->ID, self::META_SUMMARY, true );
		$delay_label          = (string) get_post_meta( $post->ID, self::META_DELAY_LABEL, true );
		$fee_label            = (string) get_post_meta( $post->ID, self::META_FEE_LABEL, true );
		$available_online     = (string) get_post_meta( $post->ID, self::META_AVAILABLE_ONLINE, true );
		$appointment_required = (string) get_post_meta( $post->ID, self::META_APPOINTMENT_REQUIRED, true );
		$primary_cta_label    = (string) get_post_meta( $post->ID, self::META_PRIMARY_CTA_LABEL, true );
		$primary_cta_url      = (string) get_post_meta( $post->ID, self::META_PRIMARY_CTA_URL, true );
		$secondary_cta_label  = (string) get_post_meta( $post->ID, self::META_SECONDARY_CTA_LABEL, true );
		$secondary_cta_url    = (string) get_post_meta( $post->ID, self::META_SECONDARY_CTA_URL, true );
		$required_documents   = $this->get_array_meta( $post->ID, self::META_REQUIRED_DOCUMENTS );
		$steps                = $this->get_array_meta( $post->ID, self::META_STEPS );
		$cases                = $this->get_array_meta( $post->ID, self::META_CASES );
		$faq_rows             = get_post_meta( $post->ID, self::META_FAQ, true );
		$related_document_ids = get_post_meta( $post->ID, self::META_RELATED_DOCUMENT_IDS, true );
		$sort_order           = (string) get_post_meta( $post->ID, self::META_SORT_ORDER, true );
		$featured             = (string) get_post_meta( $post->ID, self::META_FEATURED, true );
		$available_documents  = $this->get_available_documents();

		$faq_rows             = is_array( $faq_rows ) ? $faq_rows : array();
		$related_document_ids = is_array( $related_document_ids ) ? array_map( 'intval', $related_document_ids ) : array();
		?>
		<p>
			<label for="tccm-proc-summary"><strong><?php esc_html_e( 'Resume', 'thecore-collectivity-management' ); ?></strong></label><br />
			<textarea id="tccm-proc-summary" name="tccm_proc_summary" class="widefat" rows="3"><?php echo esc_textarea( $summary ); ?></textarea>
		</p>
		<p>
			<label for="tccm-proc-delay"><strong><?php esc_html_e( 'Delai', 'thecore-collectivity-management' ); ?></strong></label><br />
			<input type="text" id="tccm-proc-delay" name="tccm_proc_delay_label" class="regular-text" value="<?php echo esc_attr( $delay_label ); ?>" />
		</p>
		<p>
			<label for="tccm-proc-fee"><strong><?php esc_html_e( 'Cout / tarif', 'thecore-collectivity-management' ); ?></strong></label><br />
			<input type="text" id="tccm-proc-fee" name="tccm_proc_fee_label" class="regular-text" value="<?php echo esc_attr( $fee_label ); ?>" />
		</p>
		<p>
			<label><input type="checkbox" name="tccm_proc_available_online" value="1" <?php checked( '1', $available_online ); ?> /> <?php esc_html_e( 'Disponible en ligne', 'thecore-collectivity-management' ); ?></label><br />
			<label><input type="checkbox" name="tccm_proc_appointment_required" value="1" <?php checked( '1', $appointment_required ); ?> /> <?php esc_html_e( 'Rendez-vous requis', 'thecore-collectivity-management' ); ?></label><br />
			<label><input type="checkbox" name="tccm_proc_featured" value="1" <?php checked( '1', $featured ); ?> /> <?php esc_html_e( 'Mettre en avant', 'thecore-collectivity-management' ); ?></label>
		</p>
		<hr />
		<p><strong><?php esc_html_e( 'CTA', 'thecore-collectivity-management' ); ?></strong></p>
		<p>
			<label for="tccm-proc-primary-cta-label"><?php esc_html_e( 'Label CTA principal', 'thecore-collectivity-management' ); ?></label><br />
			<input type="text" id="tccm-proc-primary-cta-label" name="tccm_proc_primary_cta_label" class="regular-text" value="<?php echo esc_attr( $primary_cta_label ); ?>" />
		</p>
		<p>
			<label for="tccm-proc-primary-cta-url"><?php esc_html_e( 'URL CTA principal', 'thecore-collectivity-management' ); ?></label><br />
			<input type="url" id="tccm-proc-primary-cta-url" name="tccm_proc_primary_cta_url" class="widefat" value="<?php echo esc_attr( $primary_cta_url ); ?>" />
		</p>
		<p>
			<label for="tccm-proc-secondary-cta-label"><?php esc_html_e( 'Label CTA secondaire', 'thecore-collectivity-management' ); ?></label><br />
			<input type="text" id="tccm-proc-secondary-cta-label" name="tccm_proc_secondary_cta_label" class="regular-text" value="<?php echo esc_attr( $secondary_cta_label ); ?>" />
		</p>
		<p>
			<label for="tccm-proc-secondary-cta-url"><?php esc_html_e( 'URL CTA secondaire', 'thecore-collectivity-management' ); ?></label><br />
			<input type="url" id="tccm-proc-secondary-cta-url" name="tccm_proc_secondary_cta_url" class="widefat" value="<?php echo esc_attr( $secondary_cta_url ); ?>" />
		</p>
		<hr />
		<?php $this->render_line_repeater( 'tccm-proc-docs', __( 'Pieces a fournir', 'thecore-collectivity-management' ), 'tccm_proc_required_documents', $required_documents, __( 'Ajouter une piece', 'thecore-collectivity-management' ) ); ?>
		<?php $this->render_line_repeater( 'tccm-proc-steps', __( 'Etapes', 'thecore-collectivity-management' ), 'tccm_proc_steps', $steps, __( 'Ajouter une etape', 'thecore-collectivity-management' ) ); ?>
		<?php $this->render_line_repeater( 'tccm-proc-cases', __( 'Cas concernes', 'thecore-collectivity-management' ), 'tccm_proc_cases', $cases, __( 'Ajouter un cas', 'thecore-collectivity-management' ) ); ?>
		<?php $this->render_faq_repeater( $faq_rows ); ?>
		<hr />
		<p>
			<label for="tccm-proc-related-docs"><strong><?php esc_html_e( 'Documents lies', 'thecore-collectivity-management' ); ?></strong></label><br />
			<select id="tccm-proc-related-docs" name="tccm_proc_related_document_ids[]" class="widefat" multiple size="8">
				<?php foreach ( $available_documents as $document ) : ?>
					<option value="<?php echo esc_attr( $document->ID ); ?>" <?php selected( in_array( (int) $document->ID, $related_document_ids, true ) ); ?>><?php echo esc_html( get_the_title( $document ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="tccm-proc-sort-order"><strong><?php esc_html_e( 'Ordre d affichage', 'thecore-collectivity-management' ); ?></strong></label><br />
			<input type="number" id="tccm-proc-sort-order" name="tccm_proc_sort_order" class="small-text" value="<?php echo esc_attr( $sort_order ); ?>" min="0" step="1" />
		</p>
		<?php
		$this->render_repeater_script();
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

		if ( TheCore_Collectivity_Procedures_Post_Type::POST_TYPE !== $post->post_type ) {
			return;
		}

		$this->update_or_delete_meta( $post_id, self::META_SUMMARY, isset( $_POST['tccm_proc_summary'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tccm_proc_summary'] ) ) : '' );
		$this->update_or_delete_meta( $post_id, self::META_DELAY_LABEL, isset( $_POST['tccm_proc_delay_label'] ) ? sanitize_text_field( wp_unslash( $_POST['tccm_proc_delay_label'] ) ) : '' );
		$this->update_or_delete_meta( $post_id, self::META_FEE_LABEL, isset( $_POST['tccm_proc_fee_label'] ) ? sanitize_text_field( wp_unslash( $_POST['tccm_proc_fee_label'] ) ) : '' );
		$this->update_or_delete_meta( $post_id, self::META_PRIMARY_CTA_LABEL, isset( $_POST['tccm_proc_primary_cta_label'] ) ? sanitize_text_field( wp_unslash( $_POST['tccm_proc_primary_cta_label'] ) ) : '' );
		$this->update_or_delete_meta( $post_id, self::META_PRIMARY_CTA_URL, isset( $_POST['tccm_proc_primary_cta_url'] ) ? esc_url_raw( wp_unslash( $_POST['tccm_proc_primary_cta_url'] ) ) : '' );
		$this->update_or_delete_meta( $post_id, self::META_SECONDARY_CTA_LABEL, isset( $_POST['tccm_proc_secondary_cta_label'] ) ? sanitize_text_field( wp_unslash( $_POST['tccm_proc_secondary_cta_label'] ) ) : '' );
		$this->update_or_delete_meta( $post_id, self::META_SECONDARY_CTA_URL, isset( $_POST['tccm_proc_secondary_cta_url'] ) ? esc_url_raw( wp_unslash( $_POST['tccm_proc_secondary_cta_url'] ) ) : '' );

		update_post_meta( $post_id, self::META_AVAILABLE_ONLINE, isset( $_POST['tccm_proc_available_online'] ) ? '1' : '0' );
		update_post_meta( $post_id, self::META_APPOINTMENT_REQUIRED, isset( $_POST['tccm_proc_appointment_required'] ) ? '1' : '0' );
		update_post_meta( $post_id, self::META_FEATURED, isset( $_POST['tccm_proc_featured'] ) ? '1' : '0' );
		update_post_meta( $post_id, self::META_SORT_ORDER, isset( $_POST['tccm_proc_sort_order'] ) ? intval( wp_unslash( $_POST['tccm_proc_sort_order'] ) ) : 0 );

		$this->update_array_meta( $post_id, self::META_REQUIRED_DOCUMENTS, isset( $_POST['tccm_proc_required_documents'] ) ? wp_unslash( $_POST['tccm_proc_required_documents'] ) : array() );
		$this->update_array_meta( $post_id, self::META_STEPS, isset( $_POST['tccm_proc_steps'] ) ? wp_unslash( $_POST['tccm_proc_steps'] ) : array() );
		$this->update_array_meta( $post_id, self::META_CASES, isset( $_POST['tccm_proc_cases'] ) ? wp_unslash( $_POST['tccm_proc_cases'] ) : array() );
		$this->save_faq_meta( $post_id );

		$related_documents = isset( $_POST['tccm_proc_related_document_ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['tccm_proc_related_document_ids'] ) ) : array();
		$related_documents = array_values( array_filter( array_unique( $related_documents ) ) );
		if ( empty( $related_documents ) ) {
			delete_post_meta( $post_id, self::META_RELATED_DOCUMENT_IDS );
		} else {
			update_post_meta( $post_id, self::META_RELATED_DOCUMENT_IDS, $related_documents );
		}
	}

	/**
	 * Render a simple line repeater.
	 *
	 * @param string $id         Block id.
	 * @param string $label      UI label.
	 * @param string $field_name Field name.
	 * @param array  $values     Values.
	 * @param string $add_label  Add label.
	 */
	private function render_line_repeater( $id, $label, $field_name, $values, $add_label ) {
		$values = empty( $values ) ? array( '' ) : array_values( $values );
		?>
		<div class="tccm-repeater" id="<?php echo esc_attr( $id ); ?>">
			<p><strong><?php echo esc_html( $label ); ?></strong></p>
			<div class="tccm-repeater__rows" data-tccm-repeater-rows>
				<?php foreach ( $values as $value ) : ?>
					<div class="tccm-repeater__row" style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
						<input type="text" name="<?php echo esc_attr( $field_name ); ?>[]" class="widefat" value="<?php echo esc_attr( $value ); ?>" />
						<button type="button" class="button" data-tccm-remove-row><?php esc_html_e( 'Supprimer', 'thecore-collectivity-management' ); ?></button>
					</div>
				<?php endforeach; ?>
			</div>
			<template>
				<div class="tccm-repeater__row" style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
					<input type="text" name="<?php echo esc_attr( $field_name ); ?>[]" class="widefat" value="" />
					<button type="button" class="button" data-tccm-remove-row><?php esc_html_e( 'Supprimer', 'thecore-collectivity-management' ); ?></button>
				</div>
			</template>
			<p><button type="button" class="button" data-tccm-add-row><?php echo esc_html( $add_label ); ?></button></p>
		</div>
		<?php
	}

	/**
	 * Render FAQ repeater.
	 *
	 * @param array $faq_rows Existing rows.
	 */
	private function render_faq_repeater( $faq_rows ) {
		$faq_rows = empty( $faq_rows ) ? array( array( 'question' => '', 'answer' => '' ) ) : $faq_rows;
		?>
		<div class="tccm-repeater" id="tccm-proc-faq">
			<p><strong><?php esc_html_e( 'FAQ', 'thecore-collectivity-management' ); ?></strong></p>
			<div class="tccm-repeater__rows" data-tccm-repeater-rows>
				<?php foreach ( $faq_rows as $row ) : ?>
					<div class="tccm-repeater__row" style="border:1px solid #dcdcde;padding:12px;margin-bottom:12px;">
						<p>
							<label><strong><?php esc_html_e( 'Question', 'thecore-collectivity-management' ); ?></strong></label><br />
							<input type="text" name="tccm_proc_faq_question[]" class="widefat" value="<?php echo esc_attr( isset( $row['question'] ) ? $row['question'] : '' ); ?>" />
						</p>
						<p>
							<label><strong><?php esc_html_e( 'Reponse', 'thecore-collectivity-management' ); ?></strong></label><br />
							<textarea name="tccm_proc_faq_answer[]" class="widefat" rows="3"><?php echo esc_textarea( isset( $row['answer'] ) ? $row['answer'] : '' ); ?></textarea>
						</p>
						<p><button type="button" class="button" data-tccm-remove-row><?php esc_html_e( 'Supprimer cette entree', 'thecore-collectivity-management' ); ?></button></p>
					</div>
				<?php endforeach; ?>
			</div>
			<template>
				<div class="tccm-repeater__row" style="border:1px solid #dcdcde;padding:12px;margin-bottom:12px;">
					<p>
						<label><strong><?php esc_html_e( 'Question', 'thecore-collectivity-management' ); ?></strong></label><br />
						<input type="text" name="tccm_proc_faq_question[]" class="widefat" value="" />
					</p>
					<p>
						<label><strong><?php esc_html_e( 'Reponse', 'thecore-collectivity-management' ); ?></strong></label><br />
						<textarea name="tccm_proc_faq_answer[]" class="widefat" rows="3"></textarea>
					</p>
					<p><button type="button" class="button" data-tccm-remove-row><?php esc_html_e( 'Supprimer cette entree', 'thecore-collectivity-management' ); ?></button></p>
				</div>
			</template>
			<p><button type="button" class="button" data-tccm-add-row><?php esc_html_e( 'Ajouter une entree FAQ', 'thecore-collectivity-management' ); ?></button></p>
		</div>
		<?php
	}

	/**
	 * Render repeater JS once.
	 */
	private function render_repeater_script() {
		if ( self::$repeater_script_rendered ) {
			return;
		}

		self::$repeater_script_rendered = true;
		?>
		<script>
		document.addEventListener('click', function(event) {
			var addButton = event.target.closest('[data-tccm-add-row]');
			if (addButton) {
				var repeater = addButton.closest('.tccm-repeater');
				if (!repeater) {
					return;
				}
				var template = repeater.querySelector('template');
				var rows = repeater.querySelector('[data-tccm-repeater-rows]');
				if (!template || !rows) {
					return;
				}
				rows.appendChild(template.content.firstElementChild.cloneNode(true));
				return;
			}

			var removeButton = event.target.closest('[data-tccm-remove-row]');
			if (!removeButton) {
				return;
			}
			var row = removeButton.closest('.tccm-repeater__row');
			var rowsContainer = removeButton.closest('[data-tccm-repeater-rows]');
			if (!row || !rowsContainer) {
				return;
			}
			if (rowsContainer.children.length <= 1) {
				var inputs = row.querySelectorAll('input, textarea');
				inputs.forEach(function(input) { input.value = ''; });
				return;
			}
			row.remove();
		});
		</script>
		<?php
	}

	/**
	 * Get available documents for relation field.
	 *
	 * @return WP_Post[]
	 */
	private function get_available_documents() {
		return get_posts(
			array(
				'post_type'      => TheCore_Collectivity_Documents_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * Normalize simple array meta.
	 *
	 * @param int    $post_id  Post id.
	 * @param string $meta_key Meta key.
	 * @return array
	 */
	private function get_array_meta( $post_id, $meta_key ) {
		$value = get_post_meta( $post_id, $meta_key, true );
		return is_array( $value ) ? array_values( array_map( 'strval', $value ) ) : array();
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
	 * Sanitize and persist a line array meta.
	 *
	 * @param int    $post_id  Post id.
	 * @param string $meta_key Meta key.
	 * @param array  $values   Raw values.
	 */
	private function update_array_meta( $post_id, $meta_key, $values ) {
		$values = is_array( $values ) ? $values : array();
		$values = array_filter(
			array_map(
				'sanitize_text_field',
				array_map( 'trim', $values )
			)
		);

		if ( empty( $values ) ) {
			delete_post_meta( $post_id, $meta_key );
			return;
		}

		update_post_meta( $post_id, $meta_key, array_values( $values ) );
	}

	/**
	 * Save FAQ rows.
	 *
	 * @param int $post_id Post id.
	 */
	private function save_faq_meta( $post_id ) {
		$questions = isset( $_POST['tccm_proc_faq_question'] ) ? (array) wp_unslash( $_POST['tccm_proc_faq_question'] ) : array();
		$answers   = isset( $_POST['tccm_proc_faq_answer'] ) ? (array) wp_unslash( $_POST['tccm_proc_faq_answer'] ) : array();
		$rows      = array();
		$count     = max( count( $questions ), count( $answers ) );

		for ( $index = 0; $index < $count; $index++ ) {
			$question = isset( $questions[ $index ] ) ? sanitize_text_field( trim( $questions[ $index ] ) ) : '';
			$answer   = isset( $answers[ $index ] ) ? sanitize_textarea_field( trim( $answers[ $index ] ) ) : '';

			if ( '' === $question && '' === $answer ) {
				continue;
			}

			$rows[] = array(
				'question' => $question,
				'answer'   => $answer,
			);
		}

		if ( empty( $rows ) ) {
			delete_post_meta( $post_id, self::META_FAQ );
			return;
		}

		update_post_meta( $post_id, self::META_FAQ, $rows );
	}
}
