<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Event_Programme_Schema {
	const MAX_DAYS = 31;
	const MAX_ITEMS = 500;

	public static function empty_programme() {
		return array( 'version' => 1, 'days' => array(), 'information' => '' );
	}

	public static function normalize( $value ) {
		if ( ! is_array( $value ) || ( isset( $value['version'] ) && 1 !== $value['version'] )
			|| ! isset( $value['days'] ) || ! is_array( $value['days'] ) || count( $value['days'] ) > self::MAX_DAYS ) {
			return self::error( __( 'Le format du programme est invalide.', 'thecore-collectivity-management' ) );
		}
		$information = $value['information'] ?? '';
		if ( ! is_string( $information ) || strlen( $information ) > 40000 ) {
			return self::error( __( 'Les informations complémentaires sont trop longues.', 'thecore-collectivity-management' ) );
		}
		$result = self::empty_programme();
		$result['information'] = wp_kses_post( $information );
		$dates = array();
		$total = 0;
		foreach ( $value['days'] as $day ) {
			if ( ! is_array( $day ) || ! is_string( $day['date'] ?? null ) || ! is_array( $day['items'] ?? null ) ) {
				return self::error( __( 'Chaque journée doit avoir une date et une liste d’animations.', 'thecore-collectivity-management' ) );
			}
			$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $day['date'], wp_timezone() );
			if ( ! $date || $date->format( 'Y-m-d' ) !== $day['date'] || isset( $dates[ $day['date'] ] ) ) {
				return self::error( __( 'Les dates doivent être valides et chaque journée doit être unique.', 'thecore-collectivity-management' ) );
			}
			$dates[ $day['date'] ] = true;
			$items = array();
			foreach ( $day['items'] as $item ) {
				$total++;
				if ( $total > self::MAX_ITEMS || ! is_array( $item ) ) {
					return self::error( __( 'Le programme contient trop d’animations.', 'thecore-collectivity-management' ) );
				}
				$clean = array();
				foreach ( array( 'title' => 200, 'description' => 4000, 'location' => 200 ) as $field => $limit ) {
					$text = $item[ $field ] ?? '';
					if ( ! is_string( $text ) || ( function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text ) ) > $limit ) {
						return self::error( __( 'Un intitulé ou un complément dépasse la longueur autorisée.', 'thecore-collectivity-management' ) );
					}
					$clean[ $field ] = 'description' === $field ? sanitize_textarea_field( $text ) : sanitize_text_field( $text );
				}
				if ( '' === trim( $clean['title'] ) ) {
					return self::error( __( 'Chaque animation doit avoir un intitulé.', 'thecore-collectivity-management' ) );
				}
				foreach ( array( 'start', 'end' ) as $field ) {
					$time = $item[ $field ] ?? '';
					if ( ! is_string( $time ) || ( '' !== $time && ! preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $time ) ) ) {
						return self::error( __( 'Les horaires doivent être au format heures et minutes.', 'thecore-collectivity-management' ) );
					}
					$clean[ $field ] = $time;
				}
				$duration = $item['duration'] ?? 0;
				if ( ! is_int( $duration ) || $duration < 0 || $duration > 1440
					|| ( '' !== $clean['end'] && ( '' === $clean['start'] || $duration > 0 ) ) ) {
					return self::error( __( 'Renseignez une heure de fin ou une durée en minutes, pas les deux.', 'thecore-collectivity-management' ) );
				}
				$clean['duration'] = $duration;
				$items[] = $clean;
			}
			usort( $items, static function ( $first, $second ) {
				return strcmp( $first['start'] ?: '99:99', $second['start'] ?: '99:99' );
			} );
			if ( $items ) {
				$result['days'][] = array( 'date' => $day['date'], 'items' => $items );
			}
		}
		usort( $result['days'], static function ( $first, $second ) { return strcmp( $first['date'], $second['date'] ); } );
		return $result;
	}

	private static function error( $message ) {
		return new WP_Error( 'tccm_event_programme_invalid', $message );
	}
}
