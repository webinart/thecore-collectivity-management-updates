<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-thecore-collectivity-school-menus-pdf.php';
require_once __DIR__ . '/class-thecore-collectivity-school-menus-illustrated-print.php';

final class TheCore_Collectivity_School_Menus_Downloads {
	public static function register_hooks() {
		add_action( 'admin_post_tccm_school_menu_calendar', array( __CLASS__, 'download' ) );
		add_action( 'admin_post_nopriv_tccm_school_menu_calendar', array( __CLASS__, 'download' ) );
		add_action( 'admin_post_tccm_school_menu_print', array( __CLASS__, 'print_menu' ) );
		add_action( 'admin_post_nopriv_tccm_school_menu_print', array( __CLASS__, 'print_menu' ) );
		add_action( 'admin_post_tccm_school_menu_view', array( __CLASS__, 'view_menu' ) );
		add_action( 'admin_post_nopriv_tccm_school_menu_view', array( __CLASS__, 'view_menu' ) );
		add_action( 'admin_post_tccm_school_menu_pdf', array( __CLASS__, 'download_pdf' ) );
		add_action( 'admin_post_nopriv_tccm_school_menu_pdf', array( __CLASS__, 'download_pdf' ) );
		TheCore_Collectivity_School_Menus_PDF::register_hooks();
	}

	public static function url( $post_id ) {
		return add_query_arg( array( 'action' => 'tccm_school_menu_calendar', 'menu' => (int) $post_id ), admin_url( 'admin-post.php' ) );
	}

	public static function print_url( $post_id ) {
		return add_query_arg( array( 'action' => 'tccm_school_menu_print', 'menu' => (int) $post_id ), admin_url( 'admin-post.php' ) );
	}

	public static function pdf_url( $post_id ) {
		return add_query_arg( array( 'action' => 'tccm_school_menu_pdf', 'menu' => (int) $post_id ), admin_url( 'admin-post.php' ) );
	}

	public static function view_url( $post_id ) {
		return add_query_arg( array( 'action' => 'tccm_school_menu_view', 'menu' => (int) $post_id ), admin_url( 'admin-post.php' ) );
	}

	public static function view_document( $post_id ) {
		$published = self::published_week( $post_id );
		return is_wp_error( $published ) ? $published : TheCore_Collectivity_School_Menus_Illustrated_Print::document( $published, true );
	}

	public static function view_menu() {
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'X-Content-Type-Options: nosniff', true );
		header( 'X-Frame-Options: SAMEORIGIN', true );
		if ( ! in_array( $_SERVER['REQUEST_METHOD'] ?? '', array( 'GET', 'HEAD' ), true ) ) {
			header( 'Allow: GET, HEAD' );
			wp_die( esc_html__( 'Utilisez le lien du menu.', 'thecore-collectivity-management' ), '', array( 'response' => 405 ) );
		}
		$document = self::view_document( $_GET['menu'] ?? null );
		if ( is_wp_error( $document ) ) {
			wp_die( esc_html( $document->get_error_message() ), '', array( 'response' => 404 ) );
		}
		header( 'Content-Type: text/html; charset=utf-8' );
		if ( 'HEAD' !== $_SERVER['REQUEST_METHOD'] ) {
			echo $document;
		}
		exit;
	}

	public static function pdf_file( $post_id, $generate = true ) {
		$published = self::published_week( $post_id );
		return is_wp_error( $published ) ? $published : TheCore_Collectivity_School_Menus_PDF::file( $published, $generate );
	}

	public static function download_pdf() {
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'X-Content-Type-Options: nosniff', true );
		$method = $_SERVER['REQUEST_METHOD'] ?? '';
		if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
			header( 'Allow: GET, HEAD' );
			wp_die( esc_html__( 'Utilisez le lien de téléchargement du menu.', 'thecore-collectivity-management' ), '', array( 'response' => 405 ) );
		}
		$file = self::pdf_file( $_GET['menu'] ?? null, 'HEAD' !== $method );
		if ( is_wp_error( $file ) ) {
			$data = $file->get_error_data();
			if ( ! empty( $data['retry_after'] ) ) {
				header( 'Retry-After: ' . (int) $data['retry_after'] );
			}
			wp_die( esc_html( $file->get_error_message() ), '', array( 'response' => $data['status'] ?? 503 ) );
		}
		$stream = fopen( $file['path'], 'rb' );
		if ( ! $stream ) {
			wp_die( esc_html__( 'Le PDF est temporairement indisponible.', 'thecore-collectivity-management' ), '', array( 'response' => 503 ) );
		}
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $file['filename'] . '"' );
		header( 'Content-Length: ' . fstat( $stream )['size'] );
		if ( 'HEAD' !== $method ) {
			fpassthru( $stream );
		}
		fclose( $stream );
		exit;
	}

	private static function text( $value ) {
		$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value );
		return str_replace( array( '\\', ';', ',', "\n" ), array( '\\\\', '\\;', '\\,', '\\n' ), $value );
	}

	private static function fold( $line ) {
		$result = '';
		$length = 0;
		foreach ( preg_split( '//u', $line, -1, PREG_SPLIT_NO_EMPTY ) as $character ) {
			$bytes = strlen( $character );
			if ( $length + $bytes > 75 ) {
				$result .= "\r\n ";
				$length = 1;
			}
			$result .= $character;
			$length += $bytes;
		}
		return $result;
	}

	private static function description( array $day, $notice ) {
		$sections = $day['vegetarian'] ? array( __( 'Menu végétarien', 'thecore-collectivity-management' ) ) : array();
		foreach ( TheCore_Collectivity_School_Menus_Data::courses() as $course => $label ) {
			$items = array();
			foreach ( $day['courses'][ $course ] as $item ) {
				$parts = array();
				foreach ( $item['parts'] as $part ) {
					$markers = array();
					foreach ( $part['markers'] as $marker_id ) {
						$marker = TheCore_Collectivity_School_Menus_Data::marker( $marker_id );
						if ( $marker ) {
							$markers[] = $marker['name'];
						}
					}
					$parts[] = $part['text'] . ( $markers ? ' (' . implode( ', ', $markers ) . ')' : '' );
				}
				$items[] = implode( ' ', $parts );
			}
			if ( $items ) {
				$sections[] = $label . "\n" . implode( "\n", $items );
			}
		}
		if ( $notice ) {
			$sections[] = $notice;
		}
		return implode( "\n\n", $sections );
	}

	private static function published_week( $post_id ) {
		$post_id = filter_var( $post_id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
		$post = $post_id ? get_post( $post_id ) : null;
		$unavailable = new WP_Error( 'tccm_school_menu_unavailable', __( 'Ce menu est indisponible.', 'thecore-collectivity-management' ), array( 'status' => 404 ) );
		if ( ! $post || TheCore_Collectivity_School_Menus_Data::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status || '' !== $post->post_password ) {
			return $unavailable;
		}
		$week = TheCore_Collectivity_School_Menus_Data::get( $post_id );
		if ( ! $week ) {
			return $unavailable;
		}
		return array( 'post' => $post, 'week' => $week, 'id' => $post_id );
	}

	public static function file( $post_id ) {
		$published = self::published_week( $post_id );
		if ( is_wp_error( $published ) ) {
			return $published;
		}
		$post_id = $published['id'];
		$post = $published['post'];
		$week = $published['week'];
		if ( ! in_array( 'menu', array_column( $week['days'], 'status' ), true ) ) {
			return new WP_Error( 'tccm_school_menu_unavailable', __( 'Ce menu est indisponible.', 'thecore-collectivity-management' ), array( 'status' => 404 ) );
		}
		$stamp = get_post_modified_time( 'Ymd\THis\Z', true, $post );
		$stamp = $stamp ?: gmdate( 'Ymd\THis\Z' );
		$site = substr( hash( 'sha256', home_url( '/' ) ), 0, 32 );
		$lines = array( 'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//The Core//Menus scolaires//FR', 'CALSCALE:GREGORIAN' );
		foreach ( $week['days'] as $day ) {
			if ( 'menu' !== $day['status'] ) {
				continue;
			}
			$date = TheCore_Collectivity_School_Menus_Data::date( $day['date'] );
			$lines = array_merge( $lines, array(
				'BEGIN:VEVENT',
				'UID:tccm-' . $site . '-' . $post_id . '-' . $date->format( 'Ymd' ) . '@thecore',
				'DTSTAMP:' . $stamp,
				'LAST-MODIFIED:' . $stamp,
				'DTSTART;VALUE=DATE:' . $date->format( 'Ymd' ),
				'DTEND;VALUE=DATE:' . $date->modify( '+1 day' )->format( 'Ymd' ),
				'SUMMARY:' . self::text( __( 'Menu scolaire', 'thecore-collectivity-management' ) ),
				'DESCRIPTION:' . self::text( self::description( $day, $week['notice'] ) ),
				'TRANSP:TRANSPARENT',
				'END:VEVENT',
			) );
		}
		$lines[] = 'END:VCALENDAR';
		return array( 'filename' => 'menu-scolaire-' . $week['start'] . '.ics', 'content' => implode( "\r\n", array_map( array( __CLASS__, 'fold' ), $lines ) ) . "\r\n" );
	}

	private static function print_heading() {
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		$logo = $logo_id ? wp_get_attachment_image_src( $logo_id, 'full' ) : false;
		if ( ! $logo ) {
			$logo = wp_get_attachment_image_src( (int) get_option( 'site_icon' ), 'full' );
		}
		$identity = $logo
			? '<img class="tccms-print-logo" src="' . esc_url( $logo[0] ) . '" width="' . (int) $logo[1] . '" height="' . (int) $logo[2] . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '">'
			: '<span class="tccms-print-identity">' . esc_html( get_bloginfo( 'name' ) ) . '</span>';
		return '<header class="tccms-print-heading">' . $identity . '<h1>' . esc_html__( 'Menu scolaire', 'thecore-collectivity-management' ) . '</h1></header>';
	}

	public static function print_document( $post_id ) {
		$published = self::published_week( $post_id );
		if ( is_wp_error( $published ) ) {
			return $published;
		}
		return TheCore_Collectivity_School_Menus_Illustrated_Print::document( $published );
	}

	public static function print_menu() {
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'X-Content-Type-Options: nosniff', true );
		if ( ! in_array( $_SERVER['REQUEST_METHOD'] ?? '', array( 'GET', 'HEAD' ), true ) ) {
			header( 'Allow: GET, HEAD' );
			wp_die( esc_html__( 'Utilisez le lien d’impression du menu.', 'thecore-collectivity-management' ), '', array( 'response' => 405 ) );
		}
		$document = self::print_document( $_GET['menu'] ?? null );
		if ( is_wp_error( $document ) ) {
			wp_die( esc_html( $document->get_error_message() ), '', array( 'response' => 404 ) );
		}
		header( 'Content-Type: text/html; charset=utf-8' );
		if ( 'HEAD' !== $_SERVER['REQUEST_METHOD'] ) {
			echo $document;
		}
		exit;
	}

	public static function download() {
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'X-Content-Type-Options: nosniff', true );
		if ( ! in_array( $_SERVER['REQUEST_METHOD'] ?? '', array( 'GET', 'HEAD' ), true ) ) {
			header( 'Allow: GET, HEAD' );
			wp_die( esc_html__( 'Utilisez le lien de téléchargement du menu.', 'thecore-collectivity-management' ), '', array( 'response' => 405 ) );
		}
		$file = self::file( $_GET['menu'] ?? null );
		if ( is_wp_error( $file ) ) {
			wp_die( esc_html( $file->get_error_message() ), '', array( 'response' => 404 ) );
		}
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $file['filename'] . '"' );
		if ( 'HEAD' !== $_SERVER['REQUEST_METHOD'] ) {
			echo $file['content'];
		}
		exit;
	}
}
