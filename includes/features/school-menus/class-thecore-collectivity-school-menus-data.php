<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_School_Menus_Data {
	const POST_TYPE = 'tccm_school_menu';
	const TAXONOMY = 'tccm_food_marker';
	const META = '_tccm_school_menu';
	const START = '_tccm_school_menu_start';
	const END = '_tccm_school_menu_end';

	public static function courses() {
		return array( 'starter' => __( 'Entrée', 'thecore-collectivity-management' ), 'main' => __( 'Plat et accompagnement', 'thecore-collectivity-management' ), 'dessert' => __( 'Dessert', 'thecore-collectivity-management' ) );
	}

	public static function date( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $value ) ) {
			return false;
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );
		return $date && $date->format( 'Y-m-d' ) === $value ? $date : false;
	}

	public static function empty_week( $start = '' ) {
		$date = self::date( $start );
		if ( ! $date || '1' !== $date->format( 'N' ) ) {
			$date = ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( 'monday this week' );
		}
		$value = array( 'version' => 1, 'start' => $date->format( 'Y-m-d' ), 'source_url' => '', 'notice' => '', 'days' => array() );
		for ( $offset = 0; $offset < 5; $offset++ ) {
			$value['days'][] = array( 'date' => $date->modify( '+' . $offset . ' days' )->format( 'Y-m-d' ), 'status' => 'not_specified', 'vegetarian' => false, 'courses' => array_fill_keys( array_keys( self::courses() ), array() ) );
		}
		return $value;
	}

	public static function error( $message ) {
		return new WP_Error( 'tccm_school_menu_invalid', $message );
	}

	public static function normalize( $value, $validate_markers = true ) {
		if ( ! is_array( $value ) || 1 !== ( $value['version'] ?? null ) || ! is_array( $value['days'] ?? null ) || count( $value['days'] ) !== 5 ) {
			return self::error( __( 'Le menu doit contenir les cinq journées de la semaine.', 'thecore-collectivity-management' ) );
		}
		$start = self::date( $value['start'] ?? null );
		if ( ! $start || '1' !== $start->format( 'N' ) ) {
			return self::error( __( 'Choisissez un lundi comme début de semaine.', 'thecore-collectivity-management' ) );
		}
		foreach ( array( 'source_url', 'notice' ) as $field ) {
			if ( ! is_string( $value[ $field ] ?? null ) || mb_strlen( $value[ $field ] ) > ( 'notice' === $field ? 3000 : 2000 ) ) {
				return self::error( __( 'Le lien de l’affiche ou les informations complémentaires sont invalides.', 'thecore-collectivity-management' ) );
			}
		}
		$source = trim( $value['source_url'] );
		if ( '' !== $source && ( ! filter_var( $source, FILTER_VALIDATE_URL ) || ! in_array( strtolower( (string) wp_parse_url( $source, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true ) ) ) {
			return self::error( __( 'Le lien de l’affiche doit être une adresse HTTP ou HTTPS complète.', 'thecore-collectivity-management' ) );
		}
		$result = array( 'version' => 1, 'start' => $value['start'], 'source_url' => $source, 'notice' => sanitize_textarea_field( $value['notice'] ), 'days' => array() );
		foreach ( array( 'source_markers', 'source_badges' ) as $field ) {
			if ( ! array_key_exists( $field, $value ) ) {
				continue;
			}
			if ( ! is_array( $value[ $field ] ) || count( $value[ $field ] ) > 12 ) {
				return self::error( __( 'L’affiche accepte jusqu’à douze repères d’origine.', 'thecore-collectivity-management' ) );
			}
			foreach ( $value[ $field ] as $marker_id ) {
				$marker = $validate_markers && is_int( $marker_id ) ? self::marker( $marker_id ) : null;
				if ( ! is_int( $marker_id ) || $marker_id < 1 || ( $validate_markers && ( ! $marker || 'origin' !== $marker['family'] ) ) ) {
					return self::error( __( 'Vérifiez les repères d’origine mentionnés sur l’affiche.', 'thecore-collectivity-management' ) );
				}
			}
			$result[ $field ] = array_values( array_unique( $value[ $field ] ) );
		}
		foreach ( array_values( $value['days'] ) as $offset => $day ) {
			if ( ! is_array( $day ) || ( $day['date'] ?? '' ) !== $start->modify( '+' . $offset . ' days' )->format( 'Y-m-d' ) || ! is_bool( $day['vegetarian'] ?? null ) || ! in_array( $day['status'] ?? '', array( 'menu', 'not_specified', 'closed' ), true ) || ! is_array( $day['courses'] ?? null ) ) {
				return self::error( __( 'Vérifiez les dates et le statut de chaque journée.', 'thecore-collectivity-management' ) );
			}
			$normalized = array( 'date' => $day['date'], 'status' => $day['status'], 'vegetarian' => $day['vegetarian'], 'courses' => array() );
			$count = 0;
			foreach ( self::courses() as $course => $label ) {
				$items = $day['courses'][ $course ] ?? null;
				if ( ! is_array( $items ) || count( $items ) > 12 ) {
					return self::error( __( 'Chaque composante accepte jusqu’à douze éléments.', 'thecore-collectivity-management' ) );
				}
				$normalized['courses'][ $course ] = array();
				foreach ( $items as $item ) {
					if ( ! is_array( $item ) || ! is_array( $item['parts'] ?? null ) || ! $item['parts'] || count( $item['parts'] ) > 12 ) {
						return self::error( __( 'Chaque élément doit contenir un à douze fragments ou ingrédients.', 'thecore-collectivity-management' ) );
					}
					$parts = array();
					foreach ( $item['parts'] as $part ) {
						if ( ! is_array( $part ) || ! is_string( $part['text'] ?? null ) || '' === trim( $part['text'] ) || mb_strlen( $part['text'] ) > 500 || ! is_array( $part['markers'] ?? null ) || count( $part['markers'] ) > 12 ) {
							return self::error( __( 'Vérifiez le texte et les repères de chaque aliment.', 'thecore-collectivity-management' ) );
						}
						$text = sanitize_text_field( $part['text'] );
						if ( '' === $text ) {
							return self::error( __( 'Le nom d’un aliment ne peut pas être vide.', 'thecore-collectivity-management' ) );
						}
						$markers = array();
						foreach ( $part['markers'] as $marker_id ) {
							if ( ! is_int( $marker_id ) || $marker_id < 1 || ( $validate_markers && ! self::marker( $marker_id ) ) ) {
								return self::error( __( 'Un repère n’existe plus. Vérifiez les repères sélectionnés.', 'thecore-collectivity-management' ) );
							}
							$markers[] = $marker_id;
						}
						$parts[] = array( 'text' => $text, 'markers' => array_values( array_unique( $markers ) ) );
					}
					$normalized['courses'][ $course ][] = array( 'parts' => $parts );
					$count++;
				}
			}
			if ( ( 'menu' === $day['status'] && ! $count ) || ( 'menu' !== $day['status'] && ( $count || $day['vegetarian'] ) ) ) {
				return self::error( __( 'Une journée avec repas doit contenir au moins un aliment. Retirez les aliments avant de déclarer une journée sans menu.', 'thecore-collectivity-management' ) );
			}
			$result['days'][] = $normalized;
		}
		return $result;
	}

	public static function marker( $marker_id ) {
		$term = get_term( $marker_id, self::TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}
		$family = get_term_meta( $term->term_id, '_tccm_marker_family', true );
		$logo = (int) get_term_meta( $term->term_id, '_tccm_marker_logo', true );
		$crop = $logo ? self::logo_crop( $logo, get_term_meta( $term->term_id, '_tccm_marker_logo_crop', true ) ) : null;
		return array( 'id' => (int) $term->term_id, 'name' => $term->name, 'family' => 'origin' === $family ? 'origin' : 'preparation', 'logo' => $logo && wp_attachment_is_image( $logo ) ? wp_get_attachment_image_url( $logo, $crop ? 'full' : 'thumbnail' ) : '', 'crop' => $crop, 'icon' => get_term_meta( $term->term_id, '_tccm_marker_icon', true ) );
	}

	public static function logo_crop( $attachment_id, $rectangle ) {
		if ( ! is_array( $rectangle ) || ! wp_attachment_is_image( $attachment_id ) ) {
			return null;
		}
		foreach ( array( 'x', 'y', 'width', 'height' ) as $field ) {
			if ( ! is_int( $rectangle[ $field ] ?? null ) || $rectangle[ $field ] < ( in_array( $field, array( 'width', 'height' ), true ) ? 1 : 0 ) ) {
				return null;
			}
		}
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $metadata ) || $rectangle['x'] + $rectangle['width'] > ( $metadata['width'] ?? 0 ) || $rectangle['y'] + $rectangle['height'] > ( $metadata['height'] ?? 0 ) ) {
			return null;
		}
		return array_merge( array_intersect_key( $rectangle, array_flip( array( 'x', 'y', 'width', 'height' ) ) ), array( 'image_width' => (int) $metadata['width'], 'image_height' => (int) $metadata['height'] ) );
	}

	public static function markers() {
		$terms = get_terms( array( 'taxonomy' => self::TAXONOMY, 'hide_empty' => false, 'number' => 300, 'orderby' => 'name' ) );
		return is_wp_error( $terms ) ? array() : array_values( array_filter( array_map( static function ( $term ) { return self::marker( $term->term_id ); }, $terms ) ) );
	}

	public static function get( $post_id ) {
		$value = self::normalize( get_post_meta( $post_id, self::META, true ), false );
		return is_wp_error( $value ) ? null : $value;
	}

	public static function update( $post_id, $value ) {
		if ( self::POST_TYPE !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'tccm_school_menu_forbidden', __( 'Vous ne pouvez pas modifier ce menu.', 'thecore-collectivity-management' ) );
		}
		$value = self::normalize( $value );
		if ( is_wp_error( $value ) ) {
			return $value;
		}
		if ( in_array( get_post_status( $post_id ), array( 'publish', 'private', 'future' ), true ) && self::conflict( $post_id, $value['start'] ) ) {
			return new WP_Error( 'tccm_school_menu_duplicate', __( 'Un menu publié ou programmé existe déjà pour cette semaine.', 'thecore-collectivity-management' ) );
		}
		update_post_meta( $post_id, self::META, wp_slash( $value ) );
		if ( get_post_meta( $post_id, self::META, true ) !== $value ) {
			return new WP_Error( 'tccm_school_menu_save_failed', __( 'Le menu n’a pas pu être enregistré.', 'thecore-collectivity-management' ) );
		}
		update_post_meta( $post_id, self::START, $value['start'] );
		update_post_meta( $post_id, self::END, end( $value['days'] )['date'] );
		do_action( 'thecore_collectivity/school_menus/updated', $post_id, $value );
		do_action( 'the_core_cache_system_post_updated', $post_id, 'school_menu' );
		return $value;
	}

	public static function conflict( $post_id, $start ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id=p.ID WHERE p.post_type=%s AND p.post_status IN ('publish','private','future') AND m.meta_key=%s AND m.meta_value=%s AND p.ID<>%d ORDER BY p.ID ASC LIMIT 1",
			self::POST_TYPE, self::START, $start, $post_id
		) );
	}

	public static function guard_publication( $data, $postarr ) {
		if ( self::POST_TYPE !== $data['post_type'] || ! in_array( $data['post_status'], array( 'publish', 'private', 'future' ), true ) ) {
			return $data;
		}
		$post_id = absint( $postarr['ID'] ?? 0 );
		$previous = $post_id ? self::get( $post_id ) : null;
		$candidate = $previous;
		$nonce = $_POST['tccm_school_menu_nonce'] ?? null;
		$payload = $_POST['tccm_school_menu_payload'] ?? null;
		if ( is_string( $nonce ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'tccm_school_menu_save' ) && current_user_can( 'edit_post', $post_id ) ) {
			$candidate = is_string( $payload ) && strlen( $payload ) <= 1048576 ? self::normalize( json_decode( wp_unslash( $payload ), true, 32 ) ) : null;
		}
		$invalid = ! is_array( $candidate );
		$duplicate = ! $invalid && self::conflict( $post_id, $candidate['start'] );
		if ( $invalid || $duplicate ) {
			$old_status = $post_id ? get_post_status( $post_id ) : 'draft';
			$preserve = $previous && in_array( $old_status, array( 'publish', 'private', 'future' ), true ) && ! self::conflict( $post_id, $previous['start'] );
			$data['post_status'] = $preserve ? $old_status : 'draft';
			$message = $duplicate ? __( 'Publication refusée : un menu publié ou programmé existe déjà pour cette semaine.', 'thecore-collectivity-management' ) : __( 'Publication refusée : renseignez un menu valide avant de publier.', 'thecore-collectivity-management' );
			$message .= $preserve ? ' ' . __( 'La semaine déjà publiée est conservée.', 'thecore-collectivity-management' ) : ' ' . __( 'Ce menu reste en brouillon.', 'thecore-collectivity-management' );
			TheCore_Collectivity_School_Menus_Admin::save_error( $post_id, $message, false );
		}
		return $data;
	}
}
