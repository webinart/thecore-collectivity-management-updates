<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Event_Programme_Renderer {
	public static function render( $post_id, $title = 'Programme', $heading = 'h2', $classes = array(), $css_id = '' ) {
		$post = get_post( $post_id );
		if ( ! $post || 'post' !== $post->post_type || post_password_required( $post )
			|| ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $post_id ) ) ) {
			return '';
		}
		$value = TheCore_Collectivity_Event_Programme::get( $post_id );
		if ( ! $value['days'] && '' === trim( $value['information'] ) ) {
			return '';
		}
		TheCore_Collectivity_Event_Programme::register_styles();
		wp_enqueue_style( TheCore_Collectivity_Event_Programme::STYLE );
		$heading = in_array( $heading, array( 'h2', 'h3', 'h4' ), true ) ? $heading : 'h2';
		$day_heading = 'h' . ( (int) substr( $heading, 1 ) + 1 );
		$classes = array_merge( array( 'tccm-event-programme' ), is_array( $classes ) ? array_map( 'sanitize_html_class', $classes ) : array() );
		$html = '<div class="' . esc_attr( implode( ' ', array_unique( array_filter( $classes ) ) ) ) . '"';
		if ( '' !== $css_id ) {
			$html .= ' id="' . esc_attr( $css_id ) . '"';
		}
		$html .= '>';
		if ( '' !== trim( $title ) ) {
			$html .= '<' . $heading . ' class="tccm-event-programme__heading">' . esc_html( $title ) . '</' . $heading . '>';
		}
		foreach ( $value['days'] as $day ) {
			$date = new DateTimeImmutable( $day['date'], wp_timezone() );
			$label = ucfirst( wp_date( 'l j F', $date->getTimestamp() ) );
			$html .= '<section class="tccm-event-programme__day"><' . $day_heading . ' class="tccm-event-programme__date"><time datetime="' . esc_attr( $day['date'] ) . '">' . esc_html( $label ) . '</time></' . $day_heading . '><ol class="tccm-event-programme__items">';
			foreach ( $day['items'] as $item ) {
				$untimed = '' === $item['start'] && '' === $item['end'] && 0 === $item['duration'];
				$html .= '<li class="tccm-event-programme__item' . ( $untimed ? ' tccm-event-programme__item--untimed' : '' ) . '"><div class="tccm-event-programme__time">';
				if ( '' !== $item['start'] ) {
					$html .= self::time( $day['date'], $item['start'] );
				}
				if ( '' !== $item['end'] ) {
					$end_date = $item['end'] < $item['start'] ? $date->modify( '+1 day' )->format( 'Y-m-d' ) : $day['date'];
					$html .= '<span class="tccm-event-programme__until">' . esc_html__( 'à', 'thecore-collectivity-management' ) . '</span>' . self::time( $end_date, $item['end'] );
					if ( $end_date !== $day['date'] ) {
						$html .= '<span class="tccm-event-programme__duration">' . esc_html__( 'le lendemain', 'thecore-collectivity-management' ) . '</span>';
					}
				}
				if ( $item['duration'] > 0 ) {
					$html .= '<span class="tccm-event-programme__duration">' . esc_html( sprintf( __( '(%d min)', 'thecore-collectivity-management' ), $item['duration'] ) ) . '</span>';
				}
				$html .= '</div><div class="tccm-event-programme__details"><p class="tccm-event-programme__title">' . esc_html( $item['title'] ) . '</p>';
				if ( '' !== $item['description'] ) {
					$html .= '<p class="tccm-event-programme__description">' . nl2br( esc_html( $item['description'] ) ) . '</p>';
				}
				if ( '' !== $item['location'] ) {
					$html .= '<p class="tccm-event-programme__location">' . esc_html( $item['location'] ) . '</p>';
				}
				$html .= '</div></li>';
			}
			$html .= '</ol></section>';
		}
		if ( '' !== trim( $value['information'] ) ) {
			$html .= '<div class="tccm-event-programme__information">' . wpautop( wp_kses_post( $value['information'] ) ) . '</div>';
		}
		return $html . '</div>';
	}

	private static function time( $date, $value ) {
		$parts = explode( ':', $value );
		$label = (int) $parts[0] . ' h' . ( '00' === $parts[1] ? '' : ' ' . $parts[1] );
		return '<time datetime="' . esc_attr( $date . 'T' . $value ) . '">' . esc_html( $label ) . '</time>';
	}
}
