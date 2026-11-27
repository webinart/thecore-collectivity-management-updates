<?php

namespace TheCore\Collectivity\Publications;

defined('ABSPATH') || exit;

final class Editor {
    const META = '_tccm_editorial_article';

    public static function register(): void {
        $url = THECORE_COLLECTIVITY_MANAGEMENT_URL . 'includes/features/publications/';
        wp_register_script('tccm-publication-editor', $url . 'editor.js', ['wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n'], THECORE_COLLECTIVITY_MANAGEMENT_VERSION, true);
        wp_register_script('tccm-publication-library', $url . 'library.js', [], THECORE_COLLECTIVITY_MANAGEMENT_VERSION, true);
        wp_register_style('tccm-publication', $url . 'publication.css', [], THECORE_COLLECTIVITY_MANAGEMENT_VERSION);
        register_block_type('tccm/section', [
            'api_version' => 3, 'attributes' => ['design' => ['type' => 'object'], 'values' => ['type' => 'object', 'default' => (object) []]],
            'render_callback' => [Media::class, 'render'], 'style_handles' => ['tccm-publication'],
        ]);
    }

    public static function settings(array $settings, $context): array {
        if (empty($context->post) || !self::managed($context->post->ID)) return $settings;
        $assemble = current_user_can(Library::ASSEMBLE);
        $settings['allowedBlockTypes'] = ['tccm/section'];
        $settings['canLockBlocks'] = false;
        $settings['codeEditingEnabled'] = false;
        $settings['templateLock'] = $assemble ? false : 'all';
        $settings['enableOpenverseMediaCategory'] = false;
        return $settings;
    }

    public static function assets(): void {
        global $post;
        if (!$post || !self::managed($post->ID)) return;
        wp_enqueue_media();
        wp_enqueue_script('tccm-publication-editor');
        $fields = [];
        foreach (Components::catalog() as $entry) $fields[$entry['component']] = Components::fields($entry['component']);
        wp_add_inline_script('tccm-publication-editor', 'window.tccmPublications=' . wp_json_encode([
            'fields' => $fields, 'designs' => Library::designs(), 'labels' => Library::parts(), 'assemble' => current_user_can(Library::ASSEMBLE),
        ], JSON_HEX_TAG | JSON_HEX_AMP) . ';', 'before');
    }

    public static function managed(int $id): bool {
        return (bool) get_post_meta(wp_is_post_revision($id) ?: $id, self::META, true);
    }

    public static function excerpt($excerpt, $post): string {
        if ($excerpt !== '' || !$post || !self::managed($post->ID)) return (string) $excerpt;
        $text = [];
        foreach (parse_blocks($post->post_content) as $block) {
            foreach (($block['attrs']['design']['parts'] ?? []) as $index => $kind) {
                if ($kind !== 'text') continue;
                $value = $block['attrs']['values'][$index] ?? [];
                foreach (['heading', 'body'] as $key) {
                    $plain = wp_strip_all_tags((string) ($value[$key] ?? ''));
                    if ($plain !== '') $text[] = $plain;
                }
            }
        }
        return wp_trim_words(implode(' ', $text), (int) apply_filters('excerpt_length', 55));
    }

    private static function designs_in(string $content): ?array {
        $result = [];
        foreach (parse_blocks($content) as $block) {
            if (empty($block['blockName']) && trim($block['innerHTML']) === '') continue;
            if ($block['blockName'] !== 'tccm/section' || !is_array($block['attrs']['design'] ?? null)
                || $block['innerBlocks'] || trim($block['innerHTML']) !== '') return null;
            $result[] = $block['attrs']['design'];
        }
        return $result;
    }

    public static function valid_change(int $id, string $content): bool {
        $before = self::designs_in((string) get_post_field('post_content', $id, 'raw'));
        $after = self::designs_in($content);
        if ($before === null || $after === null) return false;
        if (!current_user_can(Library::ASSEMBLE)) return $before == $after;
        $available = array_merge($before, array_values(Library::designs()));
        foreach ($after as $design) {
            if (!in_array($design, $available, false)) return false;
        }
        return true;
    }

    public static function rest_guard($prepared, $request) {
        $id = absint($request['id'] ?? 0);
        if (is_wp_error($prepared) || !$id || !self::managed($id) || !isset($prepared->post_content)) return $prepared;
        return self::valid_change($id, $prepared->post_content) ? $prepared :
            new \WP_Error('tccm_publication_layout', 'Cette modification dépasse les droits sur la composition.', ['status' => 403]);
    }

    public static function guard(array $data, array $args): array {
        $id = (int) ($args['ID'] ?? 0);
        $target = ($data['post_type'] ?? '') === 'revision' ? (int) ($data['post_parent'] ?? 0) : $id;
        if (!$target || !self::managed($target) || !isset($data['post_content'])) return $data;
        if (!self::valid_change($target, wp_unslash($data['post_content']))) {
            $data['post_content'] = wp_slash((string) get_post_field('post_content', $target, 'raw'));
        }
        return $data;
    }
}
