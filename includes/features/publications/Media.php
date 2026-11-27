<?php

namespace TheCore\Collectivity\Publications;

use TheCore\ElementorWidgets\Elementor\Atomic\Widgets\Gallery\MarkupBuilder;

defined('ABSPATH') || exit;

final class Media {
    private static function image(array $value): string {
        $id = absint($value['id'] ?? 0);
        if (!$id || !wp_attachment_is_image($id)) return '';
        $attrs = isset($value['alt']) ? ['alt' => sanitize_text_field($value['alt'])] : [];
        $caption = $value['caption'] ?? wp_get_attachment_caption($id);
        return '<figure>' . wp_get_attachment_image($id, 'large', false, $attrs)
            . ($caption ? '<figcaption>' . esc_html($caption) . '</figcaption>' : '') . '</figure>';
    }

    private static function gallery(array $items): string {
        $images = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $id = absint($item['id'] ?? 0);
            $large = wp_get_attachment_image_src($id, 'large');
            $full = wp_get_attachment_image_src($id, 'full');
            if (!$full || !$large || !wp_attachment_is_image($id)) continue;
            $images[] = ['id' => $id, 'src' => $large[0], 'width' => $large[1], 'height' => $large[2],
                'full_src' => $full[0], 'full_width' => $full[1], 'full_height' => $full[2],
                'srcset' => wp_get_attachment_image_srcset($id, 'large') ?: '', 'sizes' => wp_get_attachment_image_sizes($id, 'large') ?: '',
                'alt' => (string) ($item['alt'] ?? get_post_meta($id, '_wp_attachment_image_alt', true)),
                'caption' => (string) ($item['caption'] ?? wp_get_attachment_caption($id))];
        }
        if (!$images) return '';
        if (class_exists(MarkupBuilder::class)) {
            wp_enqueue_script('thecore-gallery');
            wp_enqueue_style('thecore-gallery');
            return (new MarkupBuilder())->build($images, ['captions' => true, 'lightbox' => true], ['data-id' => wp_unique_id('publications-')]);
        }
        return '<div class="tccm-pub-gallery">' . implode('', array_map([self::class, 'image'], $images)) . '</div>';
    }

    private static function video(string $url): string {
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        if (!in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'www.youtube-nocookie.com'], true)) return '';
        $link = esc_url($url, ['https', 'http']);
        if (!$link) return '';
        $fallback = '<a href="' . $link . '">Voir la vidéo sur YouTube</a>';
        if (!class_exists(\Elementor\Plugin::class) || !did_action('elementor/init')) return $fallback;
        $widget = \Elementor\Plugin::$instance->elements_manager->create_element_instance([
            'id' => substr(str_replace('-', '', wp_generate_uuid4()), 0, 8),
            'elType' => 'widget', 'widgetType' => 'e-youtube', 'version' => '0.0', 'elements' => [],
            'settings' => ['source' => ['$$type' => 'string', 'value' => esc_url_raw($url)],
                'privacy_mode' => ['$$type' => 'boolean', 'value' => true],
                'player_controls' => ['$$type' => 'boolean', 'value' => true]],
        ]);
        if (!$widget) return $fallback;
        foreach ($widget->get_script_depends() as $handle) wp_enqueue_script($handle);
        foreach ($widget->get_style_depends() as $handle) wp_enqueue_style($handle);
        ob_start();
        $widget->print_element();
        return '<div class="tccm-pub-video elementor">' . ob_get_clean() . '</div>';
    }

    public static function render(array $attributes): string {
        $design = (array) ($attributes['design'] ?? []);
        $values = (array) ($attributes['values'] ?? []);
        if (isset($design['component'])) return Components::render((int) $design['component'], $values);
        $parts = $design['parts'] ?? [];
        if (!is_array($parts)) return '';
        $content = [];
        foreach ($parts as $index => $type) {
            $value = $values[$index] ?? [];
            if (!is_array($value)) continue;
            $html = '';
            switch ($type) {
                case 'text':
                    $heading = sanitize_text_field($value['heading'] ?? '');
                    $body = wp_kses((string) ($value['body'] ?? ''), ['a' => ['href' => true], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'br' => [], 'p' => [], 'ul' => [], 'ol' => [], 'li' => []]);
                    $html = ($heading ? '<h2>' . esc_html($heading) . '</h2>' : '') . wpautop($body);
                    $items = preg_split('/\R/u', (string) ($value['list'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
                    if ($items) $html .= '<ul><li>' . implode('</li><li>', array_map('esc_html', $items)) . '</li></ul>';
                    break;
                case 'image':
                    $html = self::image($value);
                    break;
                case 'gallery':
                    $html = self::gallery((array) ($value['images'] ?? []));
                    break;
                case 'video':
                    $html = self::video((string) ($value['url'] ?? ''));
                    $poster = absint($value['poster']['id'] ?? 0);
                    if ($html && $poster && wp_attachment_is_image($poster)) {
                        $html = '<details class="tccm-pub-video-preview"><summary>' . wp_get_attachment_image($poster, 'large', false, ['alt' => ''])
                            . '<span>Afficher la vidéo</span></summary>' . $html . '</details>';
                    }
                    break;
                case 'file':
                    $id = absint($value['id'] ?? 0);
                    $url = $id ? wp_get_attachment_url($id) : false;
                    if ($url) $html = '<a href="' . esc_url($url) . '">' . esc_html(($value['label'] ?? '') ?: get_the_title($id)) . '</a>';
                    break;
            }
            if ($html !== '') $content[] = '<div class="tccm-pub-part">' . $html . '</div>';
        }
        $columns = ($design['layout'] ?? '') === 'columns' && count($content) > 1;
        return $content ? '<div class="tccm-pub-section' . ($columns ? ' tccm-pub-columns' : '') . '">' . implode('', $content) . '</div>' : '';
    }
}
