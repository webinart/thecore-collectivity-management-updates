<?php
if (!defined('ABSPATH')) exit;

final class TheCore_Collectivity_School_Menu_Theme_Validator {
    const MAXIMUM_BYTES = 8388608;
    const MAXIMUM_IMAGE_BYTES = 2097152;

    public static function schema() {
        return json_decode(file_get_contents(__DIR__ . '/assets/designer/theme-format.schema.json'), true);
    }

    public static function validate_schema($theme) {
        $json = wp_json_encode($theme);
        if (!$json || strlen($json) > self::MAXIMUM_BYTES) return new WP_Error('menu_theme_invalid', 'Le thème dépasse la taille autorisée.');
        $error = self::node($theme, self::schema(), 'theme');
        if ($error) return new WP_Error('menu_theme_invalid', $error);
        $identifiers = array_column($theme['elements'], 'id');
        if (count($identifiers) !== count(array_unique($identifiers))) return new WP_Error('menu_theme_invalid', 'Les identifiants des illustrations doivent être uniques.');
        return $theme;
    }

    public static function validate($theme, $publishing = false) {
        $theme = self::validate_schema($theme);
        if (is_wp_error($theme)) return $theme;
        $sources = array_merge(array_column($theme['elements'], 'source'), array_values($theme['style']['badges']['logos']), [$theme['style']['backgroundImage'], $theme['style']['animation']['image'] ?? '', $theme['style']['animation']['variant'] ?? '']);
        foreach ($sources as $source) {
            if ($source === '') continue;
            if (str_starts_with($source, 'assets/')) {
                if (!is_file(__DIR__ . '/assets/designer/' . $source)) return new WP_Error('menu_theme_invalid', 'Une illustration est absente de la bibliothèque.');
            } else {
                $binary = base64_decode(substr($source, strpos($source, ',') + 1), true);
                $image = $binary === false ? false : @getimagesizefromstring($binary);
                if (!$image || strlen($binary) > self::MAXIMUM_IMAGE_BYTES || $image[0] * $image[1] > 16000000 || !in_array($image['mime'], ['image/png', 'image/jpeg', 'image/webp'], true)) return new WP_Error('menu_theme_invalid', 'Une image importée est invalide ou trop volumineuse.');
            }
        }
        if ($publishing && self::warnings($theme)) return new WP_Error('menu_theme_contrast', 'Corrigez les contrastes avant de publier le modèle.');
        return $theme;
    }

    private static function node($value, array $schema, $path) {
        if (isset($schema['const']) && $value !== $schema['const']) return $path . ' : valeur imposée non respectée.';
        if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) return $path . ' : choix invalide.';
        $type = $schema['type'] ?? '';
        if ($type === 'object') {
            if (!is_array($value) || ($value !== [] && array_is_list($value))) return $path . ' : objet attendu.';
            foreach ($schema['required'] ?? [] as $key) if (!array_key_exists($key, $value)) return $path . '.' . $key . ' : paramètre manquant.';
            foreach ($value as $key => $entry) {
                if (!array_key_exists($key, $schema['properties'] ?? [])) return $path . '.' . $key . ' : paramètre inconnu.';
                $error = self::node($entry, $schema['properties'][$key], $path . '.' . $key);
                if ($error) return $error;
            }
        } elseif ($type === 'array') {
            if (!is_array($value) || !array_is_list($value) || count($value) > ($schema['maxItems'] ?? PHP_INT_MAX)) return $path . ' : liste invalide.';
            foreach ($value as $index => $entry) {
                $error = self::node($entry, $schema['items'], $path . '[' . $index . ']');
                if ($error) return $error;
            }
        } elseif ($type === 'string') {
            if (!is_string($value) || mb_strlen($value) < ($schema['minLength'] ?? 0) || mb_strlen($value) > ($schema['maxLength'] ?? PHP_INT_MAX)) return $path . ' : texte invalide.';
            if (isset($schema['pattern'])) {
                $pattern = str_replace(['\\u0000', '\\u001f'], ['\\x{0000}', '\\x{001f}'], $schema['pattern']);
                if (preg_match('~' . $pattern . '~uD', $value) !== 1) return $path . ' : format invalide.';
            }
        } elseif ($type === 'boolean' && !is_bool($value)) return $path . ' : booléen attendu.';
        elseif ($type === 'integer' && (!is_int($value) || $value < ($schema['minimum'] ?? -INF) || $value > ($schema['maximum'] ?? INF))) return $path . ' : entier hors limites.';
        elseif ($type === 'number' && ((!is_int($value) && !is_float($value)) || !is_finite((float) $value) || $value < ($schema['minimum'] ?? -INF) || $value > ($schema['maximum'] ?? INF))) return $path . ' : nombre hors limites.';
        return null;
    }

    private static function contrast($foreground, $background) {
        $luminance = static function ($color) {
            $values = array_map(static function ($component) { $value = hexdec($component) / 255; return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4; }, str_split(substr($color, 1), 2));
            return $values[0] * 0.2126 + $values[1] * 0.7152 + $values[2] * 0.0722;
        };
        $values = [$luminance($foreground), $luminance($background)];
        return (max($values) + 0.05) / (min($values) + 0.05);
    }

    public static function warnings(array $theme) {
        $style = $theme['style'];
        $pairs = [[$style['ribbonText'], $style['ribbonColor'], 3]];
        if ($style['ribbonGradient']) $pairs[] = [$style['ribbonText'], $style['ribbonSecondary'], 3];
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday'] as $day) {
            $colors = $style['days'][$day];
            $pairs[] = [$colors['text'], $colors['background'], 3];
            if ($colors['gradient']) $pairs[] = [$colors['text'], $colors['secondary'], 3];
            $pairs[] = [$style['ink'], $colors['cellBackground'], 4.5];
        }
        foreach (['starter', 'main', 'dessert'] as $course) {
            $colors = $style['courses'][$course];
            $pairs[] = [$colors['text'], $colors['background'], 3];
            if ($colors['gradient']) $pairs[] = [$colors['text'], $colors['secondary'], 3];
        }
        foreach (['preparationBackground', 'originBackground'] as $background) $pairs[] = [$style['legend']['text'], $style['legend'][$background], 4.5];
        return (bool) array_filter($pairs, static fn($pair) => self::contrast($pair[0], $pair[1]) < $pair[2]);
    }
}
