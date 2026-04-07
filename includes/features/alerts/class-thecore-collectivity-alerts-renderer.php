<?php
/**
 * Alerts HTML renderer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Alerts_Renderer {
	/**
	 * Render alert banner list.
	 *
	 * @param array $alerts Alert payloads.
	 * @param array $args   Rendering options.
	 * @return string
	 */
	public function render_banner_list( $alerts, $args = array() ) {
		$alerts = is_array( $alerts ) ? $alerts : array();
		if ( empty( $alerts ) ) {
			return '';
		}

		$args = wp_parse_args(
			$args,
			array(
				'wrapper_class' => '',
				'show_topics'   => false,
			)
		);

		$wrapper_classes = trim( 'bellevue-alerts ' . $args['wrapper_class'] );
		ob_start();
		?>
		<div class="<?php echo esc_attr( $wrapper_classes ); ?>">
			<div class="bellevue-alerts__list">
				<?php foreach ( $alerts as $alert ) : ?>
					<?php
					$severity   = isset( $alert['severity'] ) ? sanitize_html_class( $alert['severity'] ) : 'warning';
					$title      = isset( $alert['title'] ) ? (string) $alert['title'] : '';
					$message    = isset( $alert['message'] ) ? (string) $alert['message'] : '';
					$link_url   = isset( $alert['linkUrl'] ) ? (string) $alert['linkUrl'] : '';
					$link_label = isset( $alert['linkLabel'] ) ? (string) $alert['linkLabel'] : '';
					$topics     = isset( $alert['topics'] ) && is_array( $alert['topics'] ) ? $alert['topics'] : array();
					?>
					<article class="bellevue-alerts__item bellevue-alerts__item--<?php echo esc_attr( $severity ); ?>">
						<span class="bellevue-alerts__icon" aria-hidden="true"><?php echo $this->get_icon_markup( $severity ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<div class="bellevue-alerts__content">
							<?php if ( '' !== $title ) : ?>
								<p class="bellevue-alerts__title"><?php echo esc_html( $title ); ?></p>
							<?php endif; ?>
							<?php if ( '' !== $message ) : ?>
								<p class="bellevue-alerts__message"><?php echo esc_html( $message ); ?></p>
							<?php endif; ?>
							<?php if ( $args['show_topics'] && ! empty( $topics ) ) : ?>
								<p class="bellevue-alerts__topics"><?php echo esc_html( implode( ' / ', $topics ) ); ?></p>
							<?php endif; ?>
						</div>
						<?php if ( '' !== $link_url ) : ?>
							<a class="bellevue-alerts__link" href="<?php echo esc_url( $link_url ); ?>">
								<?php echo esc_html( '' !== $link_label ? $link_label : __( 'En savoir plus', 'bellevue' ) ); ?>
							</a>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Get SVG icon markup.
	 *
	 * @param string $severity Alert severity.
	 * @return string
	 */
	private function get_icon_markup( $severity ) {
		switch ( $severity ) {
			case 'critical':
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>';
			case 'info':
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';
			case 'warning':
			default:
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>';
		}
	}
}
