<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-thecore-collectivity-school-menus-downloads.php';

final class TheCore_Collectivity_School_Menus_Renderer {
	private static function icon( $icon ) {
		static $cache = array();
		$files = array( 'leaf' => 'leaf-fill.svg', 'house' => 'house-fill.svg', 'chef' => 'chef-hat-fill.svg', 'origin' => 'map-pin-fill.svg', 'grain' => 'grains-fill.svg', 'barn' => 'barn-fill.svg', 'help' => 'question-fill.svg', 'download' => 'download-simple-fill.svg', 'calendar' => 'calendar-blank-fill.svg' );
		$icon = isset( $files[ $icon ] ) ? $icon : 'origin';
		if ( ! isset( $cache[ $icon ] ) ) {
			$cache[ $icon ] = str_replace( '<svg ', '<svg width="18" height="18" aria-hidden="true" focusable="false" ', file_get_contents( __DIR__ . '/assets/markers/' . $files[ $icon ] ) );
		}
		return $cache[ $icon ];
	}

	private static function course_icon( $course ) {
		static $source = null;
		if ( null === $source ) {
			$source = json_decode( file_get_contents( __DIR__ . '/assets/course-icons.json' ), true );
		}
		if ( ! isset( $source['crops'][ $course ] ) ) {
			return '';
		}
		$crop = $source['crops'][ $course ];
		$alpha = 'green' === ( $crop['ink_channel'] ?? '' ) ? '0 1.5 0 0 -0.5' : '0 0 1.5 0 -0.5';
		$id = wp_unique_id( 'tccms-course-' );
		$rectangle = 'x="' . esc_attr( $crop['x'] ) . '" y="' . esc_attr( $crop['y'] ) . '" width="' . esc_attr( $crop['width'] ) . '" height="' . esc_attr( $crop['height'] ) . '"';
		return '<svg class="tccms-course-icon" data-menu-course-icon="' . esc_attr( $course ) . '" width="42" height="42" viewBox="' . esc_attr( $crop['x'] . ' ' . $crop['y'] . ' ' . $crop['width'] . ' ' . $crop['height'] ) . '" aria-hidden="true" focusable="false"><defs><clipPath id="' . esc_attr( $id . '-clip' ) . '" clipPathUnits="userSpaceOnUse"><rect ' . $rectangle . '/></clipPath><filter id="' . esc_attr( $id . '-ink' ) . '" filterUnits="userSpaceOnUse" ' . $rectangle . ' color-interpolation-filters="sRGB"><feColorMatrix type="matrix" values="0 0 0 0 1 0 0 0 0 1 0 0 0 0 1 ' . $alpha . '" result="silhouette"/><feFlood flood-color="currentColor" result="ink"/><feComposite in="ink" in2="silhouette" operator="in"/></filter></defs><image href="' . esc_url( $source['source'] ) . '" width="' . esc_attr( $source['image_width'] ) . '" height="' . esc_attr( $source['image_height'] ) . '" clip-path="url(#' . esc_attr( $id . '-clip' ) . ')" filter="url(#' . esc_attr( $id . '-ink' ) . ')"/></svg>';
	}

	private static function marker( array $marker, $prefix, $interactive = true ) {
		$id = wp_unique_id( $prefix . '-marker-' );
		$icon = in_array( $marker['icon'], array( 'leaf', 'house', 'chef', 'origin', 'grain', 'barn' ), true ) ? $marker['icon'] : 'origin';
		$visual = $marker['logo'] ? '<img src="' . esc_url( $marker['logo'] ) . '" alt="" loading="lazy">' : self::icon( $icon );
		if ( $marker['logo'] && ! empty( $marker['crop'] ) ) {
			$crop = $marker['crop'];
			$clip_id = $id . '-clip';
			$visual = '<svg class="tccms-marker-logo" width="18" height="18" viewBox="' . esc_attr( $crop['x'] . ' ' . $crop['y'] . ' ' . $crop['width'] . ' ' . $crop['height'] ) . '" aria-hidden="true" focusable="false"><defs><clipPath id="' . esc_attr( $clip_id ) . '" clipPathUnits="userSpaceOnUse"><rect x="' . esc_attr( $crop['x'] ) . '" y="' . esc_attr( $crop['y'] ) . '" width="' . esc_attr( $crop['width'] ) . '" height="' . esc_attr( $crop['height'] ) . '"/></clipPath></defs><image href="' . esc_url( $marker['logo'] ) . '" width="' . esc_attr( $crop['image_width'] ) . '" height="' . esc_attr( $crop['image_height'] ) . '" clip-path="url(#' . esc_attr( $clip_id ) . ')"/></svg>';
		}
		$classes = 'tccms-marker tccms-marker--' . esc_attr( $marker['family'] ) . ' tccms-marker--' . esc_attr( $icon ) . ( $marker['logo'] ? ' tccms-marker--logo' : '' );
		$label = $interactive && 'origin' === $marker['family'] ? sprintf( __( 'Provenance : %s', 'thecore-collectivity-management' ), $marker['name'] ) : $marker['name'];
		$start = $interactive ? '<button type="button" class="' . $classes . '" title="' . esc_attr( $label ) . '" aria-label="' . esc_attr( $label ) . '" aria-controls="' . esc_attr( $id ) . '" aria-describedby="' . esc_attr( $id ) . '" data-menu-marker>' : '<span class="' . $classes . '" aria-hidden="true">';
		return '<span class="tccms-marker-group' . ( $interactive ? ' tccms-marker-group--interactive' : '' ) . '">' . $start . $visual . ( $interactive ? '</button>' : '</span>' ) . '<span class="tccms-marker-name" id="' . esc_attr( $id ) . '"' . ( $interactive ? ' role="tooltip"' : '' ) . ' data-menu-marker-label>' . esc_html( $label ) . '</span></span>';
	}

	private static function items( array $items, $prefix ) {
		$html = '<ul class="tccms-dishes">';
		foreach ( $items as $item ) {
			$html .= '<li>';
			foreach ( $item['parts'] as $index => $part ) {
				$html .= ( $index ? ' ' : '' ) . '<span class="tccms-food-part">';
				$markers = '';
				foreach ( $part['markers'] as $marker_id ) {
					$marker = TheCore_Collectivity_School_Menus_Data::marker( $marker_id );
					if ( $marker ) {
						$markers .= self::marker( $marker, $prefix );
					}
				}
				if ( $markers ) {
					$last_word = $part['text'];
					if ( preg_match( '/^(.*?)(\s+)(\S+)$/us', $part['text'], $words ) ) {
						$html .= '<span class="tccms-dish-name">' . esc_html( $words[1] ) . '</span>' . esc_html( $words[2] );
						$last_word = $words[3];
					}
					$html .= '<span class="tccms-food-tail"><span class="tccms-dish-name">' . esc_html( $last_word ) . '</span>' . $markers . '</span>';
				} else {
					$html .= '<span class="tccms-dish-name">' . esc_html( $part['text'] ) . '</span>';
				}
				$html .= '</span>';
			}
			$html .= '</li>';
		}
		return $html . '</ul>';
	}

	private static function day_name( $date, $format = 'l' ) {
		return wp_date( $format, TheCore_Collectivity_School_Menus_Data::date( $date )->getTimestamp(), wp_timezone() );
	}

	private static function day_status( $status ) {
		return 'closed' === $status ? __( 'Pas de service.', 'thecore-collectivity-management' ) : __( 'Menu non renseigné.', 'thecore-collectivity-management' );
	}

	private static function period( array $week, $short = false ) {
		$end = $week['days'][4]['date'];
		$format = substr( $week['start'], 0, 7 ) !== substr( $end, 0, 7 ) ? ( $short ? 'j M' : 'j F' ) : 'j';
		if ( substr( $week['start'], 0, 4 ) !== substr( $end, 0, 4 ) ) {
			$format .= ' Y';
		}
		return array( self::day_name( $week['start'], $format ), self::day_name( $end, $short ? 'j M Y' : 'j F Y' ) );
	}

	private static function legend( array $used_markers, $prefix, $badges = '' ) {
		$html = '<section class="tccms-legend" aria-label="' . esc_attr__( 'Les repères du menu', 'thecore-collectivity-management' ) . '"><div class="tccms-legend-heading"><span class="tccms-legend-badge" aria-hidden="true">' . self::icon( 'help' ) . '</span><h3>' . esc_html__( 'Les repères du menu', 'thecore-collectivity-management' ) . '</h3></div>';
		foreach ( array( 'preparation' => __( 'Qualité et préparation', 'thecore-collectivity-management' ), 'origin' => __( 'Origine et fournisseur', 'thecore-collectivity-management' ) ) as $family => $label ) {
			$entries = array();
			foreach ( array_unique( $used_markers ) as $marker_id ) {
				$marker = TheCore_Collectivity_School_Menus_Data::marker( $marker_id );
				if ( $marker && $marker['family'] === $family ) {
					$entries[] = '<li>' . self::marker( $marker, $prefix, false ) . '</li>';
				}
			}
			if ( $entries ) {
				$html .= '<div class="tccms-legend-family tccms-legend-family--' . esc_attr( $family ) . '"><h4>' . esc_html( $label ) . '</h4><ul>' . implode( '', $entries ) . '</ul></div>';
			}
		}
		return $html . $badges . '</section>';
	}

	private static function source_badges( array $marker_ids, $prefix, $asset_url ) {
		$entries = array();
		foreach ( array_unique( $marker_ids ) as $marker_id ) {
			$marker = TheCore_Collectivity_School_Menus_Data::marker( $marker_id );
			if ( $marker && 'origin' === $marker['family'] ) {
				$key = TheCore_Collectivity_School_Menus_Settings::FRANCE_KEYS[ $marker['slug'] ] ?? '';
				$entries[] = '<li data-france-badge="' . esc_attr( $key ) . '">' . self::marker( $marker, $prefix, false ) . '</li>';
			}
		}
		return $entries ? '<aside class="tccms-origin-rail" aria-label="' . esc_attr__( 'Repères d’origine présents sur l’affiche', 'thecore-collectivity-management' ) . '"><ul>' . implode( '', $entries ) . '</ul><img class="tccms-edge-leaves" src="' . esc_url( $asset_url . 'autumn-leaves.png' ) . '" alt="" width="1024" height="1536" loading="lazy" aria-hidden="true"></aside>' : '';
	}

	public static function render( $post_id = 0, $limit = 8, $illustrated = false ) {
		$args = array( 'post_type' => TheCore_Collectivity_School_Menus_Data::POST_TYPE, 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => max( 1, min( 52, (int) $limit ) ), 'no_found_rows' => true, 'ignore_sticky_posts' => true, 'meta_key' => TheCore_Collectivity_School_Menus_Data::START, 'orderby' => array( 'meta_value' => 'DESC', 'ID' => 'DESC' ) );
		if ( $post_id ) {
			$args['p'] = absint( $post_id );
		}
		$query = new WP_Query( $args );
		$weeks = array();
		foreach ( array_reverse( $query->posts ) as $post ) {
			$value = TheCore_Collectivity_School_Menus_Data::get( $post->ID );
			if ( $value ) {
				$weeks[ $post->ID ] = $value;
			}
		}
		if ( ! $weeks ) {
			return '<p class="tccm-school-menus-empty">' . esc_html__( 'Les prochains menus seront publiés ici.', 'thecore-collectivity-management' ) . '</p>';
		}
		wp_enqueue_style( 'tccm-school-menus' );
		wp_enqueue_script( 'tccm-school-menus' );
		if ( $illustrated ) {
			wp_enqueue_script( 'tccm-school-menus-illustrated' );
		}
		if ( $post_id ) {
			return self::group( $weeks, $illustrated );
		}
		$current = array();
		$previous = array();
		foreach ( $weeks as $menu_id => $week ) {
			if ( TheCore_Collectivity_School_Menus_Data::is_past( $week ) ) {
				$previous[ $menu_id ] = $week;
			} else {
				$current[ $menu_id ] = $week;
			}
		}
		return '<div class="tccms-calendar" data-menu-calendar data-timezone="' . esc_attr( wp_timezone_string() ) . '">' . self::group( $current, $illustrated, 'current' ) . '<details class="tccms-archives" data-menu-archives' . ( $previous ? '' : ' hidden' ) . '><summary>' . esc_html__( 'Menus précédents', 'thecore-collectivity-management' ) . '</summary>' . self::group( array_reverse( $previous, true ), $illustrated, 'archive' ) . '</details></div>';
	}

	private static function group( array $weeks, $illustrated, $kind = '' ) {
		$prefix = wp_unique_id( 'tccm-menus-' );
		$active = array_key_first( $weeks );
		$asset_url = THECORE_COLLECTIVITY_MANAGEMENT_URL . 'includes/features/school-menus/assets/';
		$html = '<div class="tccm-school-menus" data-school-menus data-menu-group="' . esc_attr( $kind ) . '" data-default-menu="' . esc_attr( $active ? $prefix . '-' . $active : '' ) . '"><p class="tccm-school-menus-empty" data-menu-empty' . ( $weeks ? ' hidden' : '' ) . '>' . esc_html__( 'Les prochains menus seront publiés ici.', 'thecore-collectivity-management' ) . '</p><nav class="tccms-periods" aria-label="' . esc_attr__( 'Choisir la semaine des menus', 'thecore-collectivity-management' ) . '">';
		if ( $illustrated && $weeks ) {
			$html .= wp_get_inline_script_tag( '(()=>{const root=document.currentScript.closest("[data-school-menus]");if(!root)return;root.dataset.illustratedLoading="true";root.tccmsLoadingTimer=setTimeout(()=>{root.removeAttribute("data-illustrated-loading");root.querySelectorAll(".tccms-illustrated-stage").forEach(stage=>{stage.style.height="";});},30000);})();' );
		}
		foreach ( $weeks as $menu_id => $week ) {
			$target = $prefix . '-' . $menu_id;
			$period = self::period( $week, true );
			$html .= '<a href="#' . esc_attr( $target ) . '" aria-controls="' . esc_attr( $target ) . '">' . esc_html( $period[0] . '–' . $period[1] ) . '</a>';
		}
		$html .= '</nav>';
		foreach ( $weeks as $menu_id => $week ) {
			$used_markers = $week['source_markers'] ?? array();
			foreach ( $week['days'] as $day ) {
				foreach ( $day['courses'] as $items ) {
					foreach ( $items as $item ) {
						foreach ( $item['parts'] as $part ) {
							$used_markers = array_merge( $used_markers, $part['markers'] );
						}
					}
				}
			}
			$week_id = $prefix . '-' . $menu_id;
			$period = self::period( $week );
			$title = sprintf( __( 'Semaine du %1$s au %2$s', 'thecore-collectivity-management' ), $period[0], $period[1] );
			$rail = self::source_badges( $week['source_badges'] ?? array(), $prefix, $asset_url );
			$legend_badges = ! empty( $week['source_badges_in_legend'] ) ? $rail : '';
			if ( $legend_badges ) {
				$rail = '';
			}
			$html .= '<section class="tccms-week' . ( $rail && ! $illustrated ? ' tccms-week--with-rail' : '' ) . '"' . ( $legend_badges ? ' data-legend-badges' : '' ) . ' data-menu-week data-week-start="' . esc_attr( $week['start'] ) . '"' . ( $illustrated ? ' data-illustrated-url="' . esc_url( TheCore_Collectivity_School_Menus_Downloads::view_url( $menu_id ) ) . '"' : '' ) . ' id="' . esc_attr( $week_id ) . '" aria-labelledby="' . esc_attr( $week_id . '-title' ) . '" tabindex="-1">';
			if ( $illustrated ) {
				$html .= '<div class="tccms-illustrated-stage"><p class="tccms-illustrated-loading" role="status">' . esc_html__( 'Chargement du menu…', 'thecore-collectivity-management' ) . '</p><div class="tccms-week tccms-illustrated-fallback' . ( $rail ? ' tccms-week--with-rail' : '' ) . '">';
			}
			$html .= '<div class="tccms-week-heading"><img class="tccms-season tccms-season--desktop" src="' . esc_url( $asset_url . 'autumn-hedgehog.png' ) . '" alt="" width="1536" height="1024"><img class="tccms-season tccms-season--mobile" src="' . esc_url( $asset_url . 'autumn-hedgehog.png' ) . '" alt="" width="1536" height="1024"><h2 id="' . esc_attr( $week_id . '-title' ) . '"><span class="tccms-week-leaf tccms-week-leaf--left" aria-hidden="true"></span>' . esc_html( $title ) . '<span class="tccms-week-leaf tccms-week-leaf--right" aria-hidden="true"></span></h2></div>';
			$html .= '<div class="tccms-table-view"><table class="tccms-table"><caption class="tccms-sr-only">' . esc_html( $title ) . '</caption><thead><tr><th scope="col" class="tccms-corner"><span class="tccms-sr-only">' . esc_html__( 'Repas', 'thecore-collectivity-management' ) . '</span></th>';
			foreach ( $week['days'] as $index => $day ) {
				$html .= '<th scope="col" id="' . esc_attr( $week_id . '-day-' . $index ) . '" class="tccms-day-head tccms-color-' . $index . '"><span class="tccms-day-name">' . esc_html( self::day_name( $day['date'] ) ) . '</span><time datetime="' . esc_attr( $day['date'] ) . '">' . esc_html( self::day_name( $day['date'], 'j F' ) ) . '</time>' . ( $day['vegetarian'] ? '<span class="tccms-vegetarian">' . self::icon( 'leaf' ) . '<span>' . esc_html__( 'Menu végétarien', 'thecore-collectivity-management' ) . '</span></span>' : '' ) . '</th>';
			}
			$html .= '</tr></thead><tbody>';
			foreach ( TheCore_Collectivity_School_Menus_Data::courses() as $course => $label ) {
				$row_id = $week_id . '-' . $course;
				$course_label = 'main' === $course ? esc_html__( 'Plat', 'thecore-collectivity-management' ) . '<span class="tccms-sr-only">' . esc_html__( ' et accompagnement', 'thecore-collectivity-management' ) . '</span>' : esc_html( $label );
				$html .= '<tr><th scope="row" id="' . esc_attr( $row_id ) . '" class="tccms-course tccms-course--' . esc_attr( $course ) . '"><span class="tccms-course-label">' . self::course_icon( $course ) . '<span>' . $course_label . '</span></span></th>';
				foreach ( $week['days'] as $index => $day ) {
					$html .= '<td headers="' . esc_attr( $week_id . '-day-' . $index . ' ' . $row_id ) . '" class="tccms-cell tccms-color-' . $index . '">' . ( 'menu' === $day['status'] ? self::items( $day['courses'][ $course ], $prefix ) : '<p class="tccms-status">' . esc_html( self::day_status( $day['status'] ) ) . '</p>' ) . '</td>';
				}
				$html .= '</tr>';
			}
			$html .= '</tbody></table></div><div class="tccms-day-view">';
			foreach ( $week['days'] as $index => $day ) {
				$html .= '<article class="tccms-mobile-day tccms-color-' . $index . '" aria-labelledby="' . esc_attr( $week_id . '-mobile-' . $index ) . '"><header class="tccms-mobile-heading"><h3 id="' . esc_attr( $week_id . '-mobile-' . $index ) . '">' . esc_html( self::day_name( $day['date'] ) ) . '<time datetime="' . esc_attr( $day['date'] ) . '">' . esc_html( self::day_name( $day['date'], 'j F' ) ) . '</time></h3>' . ( $day['vegetarian'] ? '<span class="tccms-vegetarian">' . self::icon( 'leaf' ) . '<span>' . esc_html__( 'Menu végétarien', 'thecore-collectivity-management' ) . '</span></span>' : '' ) . '</header>';
				if ( 'menu' === $day['status'] ) {
					$html .= '<dl>';
					foreach ( TheCore_Collectivity_School_Menus_Data::courses() as $course => $label ) {
						$html .= '<div class="tccms-mobile-course"><dt>' . self::course_icon( $course ) . esc_html( $label ) . '</dt><dd>' . self::items( $day['courses'][ $course ], $prefix ) . '</dd></div>';
					}
					$html .= '</dl>';
				} else {
					$html .= '<p class="tccms-status">' . esc_html( self::day_status( $day['status'] ) ) . '</p>';
				}
				$html .= '</article>';
			}
			$html .= '</div><div class="tccms-week-details">' . self::legend( $used_markers, $prefix, $legend_badges ) . '<div class="tccms-week-notes">';
			if ( $week['notice'] ) {
				$html .= '<p class="tccms-notice">' . nl2br( esc_html( $week['notice'] ) ) . '</p>';
			}
			if ( $illustrated ) {
				$html .= '</div></div>' . $rail . '</div></div>';
			}
			$html .= '<div class="tccms-week-footer">';
			if ( TheCore_Collectivity_PDF_Client::enabled() ) {
				$html .= '<a class="tccms-download tccms-download--print" href="' . esc_url( TheCore_Collectivity_School_Menus_Downloads::pdf_url( $menu_id ) ) . '" download>' . self::icon( 'download' ) . '<span>' . esc_html__( 'Télécharger le menu', 'thecore-collectivity-management' ) . ' <small>(PDF)</small></span></a>';
			} else {
				$html .= '<a class="tccms-download tccms-download--print" href="' . esc_url( TheCore_Collectivity_School_Menus_Downloads::print_url( $menu_id ) ) . '" target="_blank" rel="noopener noreferrer">' . self::icon( 'download' ) . '<span>' . esc_html__( 'Imprimer / enregistrer en PDF', 'thecore-collectivity-management' ) . '<span class="tccms-sr-only">' . esc_html__( ' (nouvelle fenêtre)', 'thecore-collectivity-management' ) . '</span></span></a>';
			}
			if ( in_array( 'menu', array_column( $week['days'], 'status' ), true ) ) {
				$html .= '<a class="tccms-download tccms-download--calendar" href="' . esc_url( TheCore_Collectivity_School_Menus_Downloads::url( $menu_id ) ) . '" download>' . self::icon( 'calendar' ) . '<span>' . esc_html__( 'Télécharger au format agenda', 'thecore-collectivity-management' ) . ' <small>(.ics)</small></span></a>';
			}
			$html .= '</div>';
			$html .= ( $illustrated ? '' : '</div></div>' . $rail ) . '</section>';
		}
		return $html . '</div>';
	}
}
