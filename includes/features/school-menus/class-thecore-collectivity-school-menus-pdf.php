<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__, 2 ) . '/services/class-thecore-collectivity-pdf-client.php';

final class TheCore_Collectivity_School_Menus_PDF {
	public static function register_hooks() {
		add_action( 'thecore_collectivity/school_menus/updated', array( __CLASS__, 'schedule' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'transition' ), 10, 3 );
		add_action( 'thecore_collectivity_school_menu_pdf_prepare', array( __CLASS__, 'prepare' ) );
		add_action( 'thecore_collectivity_school_menu_pdf_cleanup', array( __CLASS__, 'cleanup' ) );
		if ( TheCore_Collectivity_PDF_Client::enabled() && ! wp_next_scheduled( 'thecore_collectivity_school_menu_pdf_cleanup' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'thecore_collectivity_school_menu_pdf_cleanup' );
		}
	}

	public static function schedule( $post_id ) {
		$post = get_post( $post_id );
		$arguments = array( (int) $post_id );
		if ( TheCore_Collectivity_PDF_Client::enabled() && $post && TheCore_Collectivity_School_Menus_Data::POST_TYPE === $post->post_type && 'publish' === $post->post_status && ! wp_next_scheduled( 'thecore_collectivity_school_menu_pdf_prepare', $arguments ) ) {
			wp_schedule_single_event( time() + 10, 'thecore_collectivity_school_menu_pdf_prepare', $arguments );
		}
	}

	public static function transition( $new_status, $old_status, $post ) {
		if ( 'publish' === $new_status && 'publish' !== $old_status ) {
			self::schedule( $post->ID );
		}
	}

	public static function prepare( $post_id ) {
		$result = TheCore_Collectivity_School_Menus_Downloads::pdf_file( $post_id );
		if ( is_wp_error( $result ) && 503 === ( $result->get_error_data()['status'] ?? 0 ) ) {
			$attempt = (int) get_transient( 'tccm_pdf_retry_' . $post_id );
			if ( $attempt < 3 ) {
				set_transient( 'tccm_pdf_retry_' . $post_id, $attempt + 1, HOUR_IN_SECONDS );
				wp_schedule_single_event( time() + 120, 'thecore_collectivity_school_menu_pdf_prepare', array( (int) $post_id ) );
			}
		} else {
			delete_transient( 'tccm_pdf_retry_' . $post_id );
		}
	}

	private static function revision( array $published ) {
		$markers = array();
		$terms = get_terms( array( 'taxonomy' => TheCore_Collectivity_School_Menus_Data::TAXONOMY, 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) ) {
			return TheCore_Collectivity_PDF_Client::error();
		}
		foreach ( $terms as $term ) {
			$markers[ $term->term_id ] = TheCore_Collectivity_School_Menus_Data::marker( $term->term_id );
		}
		ksort( $markers );
		$assets = array();
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( __DIR__ . '/assets', FilesystemIterator::SKIP_DOTS ) ) as $file ) {
			if ( $file->isFile() ) {
				$assets[ $file->getPathname() ] = hash_file( 'sha256', $file->getPathname() );
			}
		}
		foreach ( glob( __DIR__ . '/*.php' ) as $file ) {
			$assets[ $file ] = hash_file( 'sha256', $file );
		}
		ksort( $assets );
		$identity = array( home_url( '/' ), get_bloginfo( 'name' ), get_bloginfo( 'language' ), get_theme_mod( 'custom_logo' ), get_option( 'site_icon' ) );
		return hash( 'sha256', wp_json_encode( array( $published['week'], $markers, $assets, $identity ) ) );
	}

	public static function file( array $published, $generate = true ) {
		$config = TheCore_Collectivity_PDF_Client::configuration();
		if ( is_wp_error( $config ) ) {
			return $config;
		}
		$revision = self::revision( $published );
		if ( is_wp_error( $revision ) ) {
			return $revision;
		}
		$path = $config['storage'] . '/menu-' . $published['id'] . '-' . $revision . '.pdf';
		$filename = 'menu-scolaire-' . $published['week']['start'] . '.pdf';
		if ( is_file( $path ) && filesize( $path ) > 4 && '%PDF-' === file_get_contents( $path, false, null, 0, 5 ) ) {
			return array( 'path' => $path, 'filename' => $filename, 'revision' => $revision );
		}
		if ( ! $generate ) {
			return TheCore_Collectivity_PDF_Client::error( 503, 10 );
		}
		$lock = fopen( $config['storage'] . '/render.lock', 'c' );
		if ( ! $lock ) {
			return TheCore_Collectivity_PDF_Client::error();
		}
		chmod( $config['storage'] . '/render.lock', 0600 );
		if ( ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
			fclose( $lock );
			return TheCore_Collectivity_PDF_Client::error( 503, 10 );
		}
		try {
			if ( is_file( $path ) && filesize( $path ) > 4 && '%PDF-' === file_get_contents( $path, false, null, 0, 5 ) ) {
				return array( 'path' => $path, 'filename' => $filename, 'revision' => $revision );
			}
			$failure_key = 'tccm_pdf_failure_' . $revision;
			if ( get_transient( $failure_key ) ) {
				return TheCore_Collectivity_PDF_Client::error();
			}
			self::prune( $config['storage'] );
			$source = add_query_arg( 'pdf_revision', $revision, TheCore_Collectivity_School_Menus_Downloads::print_url( $published['id'] ) );
			$result = TheCore_Collectivity_PDF_Client::render_to_file( $source, $revision, $path, array( 'format' => 'A4', 'landscape' => true, 'readySelector' => 'body[data-print-ready="true"]' ) );
			if ( is_wp_error( $result ) ) {
				set_transient( $failure_key, true, 60 );
				return $result;
			}
			return array( 'path' => $path, 'filename' => $filename, 'revision' => $revision );
		} finally {
			flock( $lock, LOCK_UN );
			fclose( $lock );
		}
	}

	public static function cleanup() {
		$config = TheCore_Collectivity_PDF_Client::configuration();
		if ( is_wp_error( $config ) ) {
			return;
		}
		$lock = fopen( $config['storage'] . '/render.lock', 'c' );
		if ( ! $lock ) {
			return;
		}
		chmod( $config['storage'] . '/render.lock', 0600 );
		if ( flock( $lock, LOCK_EX | LOCK_NB ) ) {
			try {
				self::prune( $config['storage'] );
			} finally {
				flock( $lock, LOCK_UN );
			}
		}
		fclose( $lock );
	}

	private static function prune( $storage ) {
		$files = glob( $storage . '/menu-*.pdf' );
		usort( $files, static function ( $first, $second ) { return filemtime( $first ) <=> filemtime( $second ); } );
		$bytes = array_sum( array_map( 'filesize', $files ) );
		$count = count( $files );
		foreach ( $files as $path ) {
			if ( filemtime( $path ) < time() - 90 * DAY_IN_SECONDS || $bytes > 524288000 - TheCore_Collectivity_PDF_Client::MAX_BYTES || $count >= 500 ) {
				$bytes -= filesize( $path );
				$count--;
				unlink( $path );
			}
		}
	}
}
