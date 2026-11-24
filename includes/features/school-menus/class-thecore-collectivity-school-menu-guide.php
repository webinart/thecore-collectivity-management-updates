<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_School_Menu_Guide {
	public static function register( $guides ) {
		$type = get_post_type_object( TheCore_Collectivity_School_Menus_Data::POST_TYPE );
		$markers = get_taxonomy( TheCore_Collectivity_School_Menus_Data::TAXONOMY );
		if ( ! is_array( $guides ) || ! $type || ! $markers ||
			! current_user_can( $type->cap->edit_posts ) || ! current_user_can( $markers->cap->assign_terms ) ) {
			return $guides;
		}

		$guides['school-menu-week'] = array(
			'screen' => 'school-menus',
			'title'  => __( 'Renseigner le menu de la semaine', 'thecore-collectivity-management' ),
			'render' => array( __CLASS__, 'render' ),
		);
		return $guides;
	}

	public static function render() {
		// The workspace editor owns the workflow and its per-user choice of action.
		$action = apply_filters( 'thecore_collectivity_school_menu_guide_action', '' );
		$editor_known = in_array( $action, array( 'draft', 'submit', 'complete', 'publish' ), true );
		$markers = __( 'Associez les mentions et les origines proposées par votre site à l’aliment ou à l’ingrédient concerné. Un même ingrédient peut porter plusieurs repères, par exemple « Bio » et le nom de son producteur.', 'thecore-collectivity-management' );
		$instruction = apply_filters( 'thecore_collectivity_school_menu_guide_markers_instruction', $markers );
		?>
		<ol>
			<li>
				<h2><?php esc_html_e( 'Choisir la semaine', 'thecore-collectivity-management' ); ?></h2>
				<p><?php esc_html_e( 'Dans Menus scolaires, cherchez d’abord la semaine dans la liste. Si un brouillon existe, ouvrez-le pour reprendre la saisie. Pour créer un menu, choisissez la date du lundi de la semaine à préparer.', 'thecore-collectivity-management' ); ?></p>
			</li>
			<li>
				<h2><?php esc_html_e( 'Renseigner les repas', 'thecore-collectivity-management' ); ?></h2>
				<p><?php esc_html_e( 'Pour chaque jour avec repas, saisissez les aliments dans les cases de l’entrée, du plat et de son accompagnement, puis du dessert. Cochez « Végétarien » si cette mention s’applique au repas de la journée.', 'thecore-collectivity-management' ); ?></p>
				<p><?php esc_html_e( 'Pour une journée de fermeture, laissez les cases des aliments vides et choisissez « Pas de service ».', 'thecore-collectivity-management' ); ?></p>
			</li>
			<li>
				<h2><?php esc_html_e( 'Préciser les mentions et les origines', 'thecore-collectivity-management' ); ?></h2>
				<p><?php echo esc_html( is_string( $instruction ) && '' !== trim( $instruction ) ? $instruction : $markers ); ?></p>
			</li>
			<li>
				<h2><?php esc_html_e( 'Relire et enregistrer', 'thecore-collectivity-management' ); ?></h2>
				<?php if ( $editor_known ) : ?>
					<p><?php esc_html_e( 'Ouvrez « Aperçu du menu » pour relire la semaine. Utilisez de nouveau ce bouton pour corriger un aliment. Pour garder votre travail et le reprendre plus tard, choisissez « Enregistrer le brouillon ».', 'thecore-collectivity-management' ); ?></p>
				<?php else : ?>
					<p><?php esc_html_e( 'Vérifiez les dates, les repas et les repères, puis enregistrez le brouillon. Vous pourrez le retrouver dans la liste des semaines.', 'thecore-collectivity-management' ); ?></p>
				<?php endif; ?>
			</li>
			<?php if ( 'publish' === $action && current_user_can( get_post_type_object( TheCore_Collectivity_School_Menus_Data::POST_TYPE )->cap->publish_posts ) ) : ?>
				<li><h2><?php esc_html_e( 'Publier le menu', 'thecore-collectivity-management' ); ?></h2>
					<p><?php esc_html_e( 'Lorsque le menu est prêt à être diffusé aux familles, choisissez « Publier le menu ». La semaine porte alors le statut « Publié ».', 'thecore-collectivity-management' ); ?></p></li>
			<?php elseif ( 'submit' === $action ) : ?>
				<li><h2><?php esc_html_e( 'Envoyer pour validation', 'thecore-collectivity-management' ); ?></h2>
					<p><?php esc_html_e( 'Lorsque la semaine est prête, choisissez « Envoyer pour validation ». Elle porte alors le statut « À valider ». La personne chargée de la validation peut la relire et la publier.', 'thecore-collectivity-management' ); ?></p></li>
			<?php elseif ( 'complete' === $action ) : ?>
				<li><h2><?php esc_html_e( 'Terminer la saisie', 'thecore-collectivity-management' ); ?></h2>
					<p><?php esc_html_e( 'Lorsque la semaine est prête, choisissez « Terminer la saisie ». La semaine porte alors la mention « Saisie terminée » et reste en brouillon.', 'thecore-collectivity-management' ); ?></p></li>
			<?php endif; ?>
		</ol>
		<?php
	}
}
