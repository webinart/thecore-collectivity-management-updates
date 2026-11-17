<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_School_Menus_Illustrated_Print {
	public static function document( array $published, $screen = false ) {
		$base = 'includes/features/school-menus/assets/illustrated/proposition-menu-automne/';
		$template = file_get_contents( __DIR__ . '/assets/illustrated/proposition-menu-automne/canvas.html' );
		$theme = json_decode( file_get_contents( __DIR__ . '/assets/illustrated/proposition-menu-automne/theme.json' ), true );
		if ( ! $template || ! is_array( $theme ) ) {
			return TheCore_Collectivity_PDF_Client::error();
		}
		$context = array(
			'theme' => $theme,
			'autoPrint' => ! $screen && ! isset( $_GET['pdf_revision'] ),
			'week' => array(
				'id' => $published['id'],
				'start' => $published['week']['start'],
				'title' => $published['post']->post_title,
				'menuHtml' => TheCore_Collectivity_School_Menus_Renderer::render( $published['id'], 1 ),
			),
		);
		if ( $screen ) {
			$context['screen'] = true;
			$context['mobile'] = isset( $_GET['layout'] ) && 'mobile' === $_GET['layout'];
			$template = str_replace( '<div id="menu-root"><p class="loading-message" role="status">Chargement du menu…</p></div>', '<div id="menu-root">' . $context['week']['menuHtml'] . '</div>', $template );
		}
		$payload = wp_json_encode( $context, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		if ( false === $payload ) {
			return TheCore_Collectivity_PDF_Client::error();
		}
		$head = '<head><base href="' . esc_url( THECORE_COLLECTIVITY_MANAGEMENT_URL . $base ) . '"><script type="application/json" id="svj-school-menu-document">' . $payload . '</script>';
		return str_replace( '<head>', $head, $template );
	}
}
