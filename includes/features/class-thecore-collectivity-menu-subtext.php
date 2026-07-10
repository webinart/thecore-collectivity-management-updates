<?php
/**
 * Menu subtext feature.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Menu_Subtext extends TheCore_Collectivity_Abstract_Module {
	/**
	 * Meta key used to store subtext.
	 */
	const META_KEY = '_menu_item_subtext';

	/**
	 * Stable module id.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'menu-subtext';
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'wp_nav_menu_item_custom_fields', array( $this, 'render_field' ), 10, 2 );
		add_action( 'wp_update_nav_menu_item', array( $this, 'save_field' ), 10, 3 );
		add_filter( 'wp_setup_nav_menu_item', array( $this, 'load_subtext_on_item' ) );
		add_filter( 'nav_menu_link_attributes', array( $this, 'add_link_class' ), 10, 2 );
		add_filter( 'nav_menu_item_title', array( $this, 'render_subtext_in_title' ), 10, 2 );
	}

	/**
	 * Render custom field in menu item form.
	 *
	 * @param int     $item_id Menu item id.
	 * @param WP_Post $item    Menu item object.
	 */
	public function render_field( $item_id, $item ) {
		$subtext = get_post_meta( $item_id, self::META_KEY, true );
		?>
		<p class="description description-wide bellevue-field-subtext">
			<label for="edit-menu-item-subtext-<?php echo esc_attr( $item_id ); ?>">
				<?php esc_html_e( 'Sous-texte', 'bellevue' ); ?><br />
				<input
					type="text"
					id="edit-menu-item-subtext-<?php echo esc_attr( $item_id ); ?>"
					class="widefat"
					name="menu-item-subtext[<?php echo esc_attr( $item_id ); ?>]"
					value="<?php echo esc_attr( $subtext ); ?>"
				/>
			</label>
		</p>
		<?php
	}

	/**
	 * Save subtext when menu is updated.
	 *
	 * @param int   $menu_id         Menu id.
	 * @param int   $menu_item_db_id Menu item id.
	 * @param array $args            Menu item args.
	 */
	public function save_field( $menu_id, $menu_item_db_id, $args ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		if ( ! isset( $_POST['menu-item-subtext'] ) || ! is_array( $_POST['menu-item-subtext'] ) ) {
			return;
		}

		if ( ! array_key_exists( $menu_item_db_id, $_POST['menu-item-subtext'] ) ) {
			return;
		}

		$raw_subtext = wp_unslash( $_POST['menu-item-subtext'][ $menu_item_db_id ] );
		$subtext     = sanitize_text_field( $raw_subtext );

		if ( '' === $subtext ) {
			delete_post_meta( $menu_item_db_id, self::META_KEY );
			return;
		}

		update_post_meta( $menu_item_db_id, self::META_KEY, $subtext );
	}

	/**
	 * Attach subtext onto menu item object.
	 *
	 * @param WP_Post $menu_item Menu item object.
	 * @return WP_Post
	 */
	public function load_subtext_on_item( $menu_item ) {
		$menu_item->bellevue_subtext = (string) get_post_meta( $menu_item->ID, self::META_KEY, true );
		return $menu_item;
	}

	/**
	 * Add class to links when subtext is present.
	 *
	 * @param array   $atts Link attributes.
	 * @param WP_Post $item Menu item.
	 * @return array
	 */
	public function add_link_class( $atts, $item ) {
		$subtext = isset( $item->bellevue_subtext ) ? trim( (string) $item->bellevue_subtext ) : '';
		if ( '' === $subtext ) {
			return $atts;
		}

		$current_class = isset( $atts['class'] ) ? $atts['class'] . ' ' : '';
		$atts['class'] = trim( $current_class . 'bellevue-menu-link-has-subtext' );

		return $atts;
	}

	/**
	 * Render subtext under menu item title on frontend.
	 *
	 * @param string  $title Item title.
	 * @param WP_Post $item  Menu item.
	 * @return string
	 */
	public function render_subtext_in_title( $title, $item ) {
		if ( is_admin() ) {
			return $title;
		}

		$subtext = isset( $item->bellevue_subtext ) ? trim( (string) $item->bellevue_subtext ) : '';
		if ( '' === $subtext ) {
			return $title;
		}

		return '<span class="bellevue-menu-item-label">' . $title . '</span><span class="bellevue-menu-item-subtext">' . esc_html( $subtext ) . '</span>';
	}
}
