<?php

namespace TheCore\Collectivity\Publications;

defined('ABSPATH') || exit;

final class Library {
    const COMPONENT = 'tccm_pub_component';
    const LAYOUT = 'tccm_pub_layout';
    const MANAGE = 'tccm_manage_publication_library';
    const ASSEMBLE = 'tccm_assemble_publications';
    const WRITE = 'tccm_write_publications';

    public static function register(): void {
        foreach ([self::COMPONENT, self::LAYOUT] as $type) {
            register_post_type($type, [
                'public' => false, 'show_ui' => false, 'rewrite' => false,
                'supports' => ['title', 'editor', 'revisions'],
                'map_meta_cap' => false,
                'capabilities' => array_fill_keys([
                    'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts',
                    'delete_posts', 'publish_posts', 'read_private_posts', 'create_posts',
                ], self::MANAGE),
            ]);
        }
    }

    public static function parts(): array {
        return ['text' => 'Texte', 'image' => 'Photo', 'video' => 'Vidéo YouTube', 'gallery' => 'Galerie', 'file' => 'Document'];
    }

    public static function designs(): array {
        $base = [
            'text' => ['Texte', ['text']],
            'report' => ['Texte et photo', ['text', 'image']],
            'video' => ['Vidéo', ['video']],
            'gallery' => ['Galerie', ['gallery']],
            'file' => ['Document à télécharger', ['file']],
        ];
        $result = [];
        foreach ($base as $id => [$name, $parts]) {
            $result[$id] = ['name' => $name, 'layout' => $id === 'report' ? 'columns' : 'stack', 'parts' => $parts];
        }
        foreach (self::entries(self::COMPONENT) as $post) {
            $data = json_decode($post->post_content, true);
            if (is_array($data) && !empty($data['parts'])) {
                $result['section-' . $post->ID] = ['name' => $post->post_title, 'layout' => $data['layout'], 'parts' => $data['parts']];
            }
        }
        return $result;
    }

    public static function layouts(): array {
        $result = [
            'brief' => ['name' => 'Actualité simple', 'sections' => ['text']],
            'photos' => ['name' => 'Retour en images', 'sections' => ['text', 'gallery']],
            'interview' => ['name' => 'Article avec vidéo', 'sections' => ['text', 'video']],
        ];
        foreach (self::entries(self::LAYOUT) as $post) {
            $sections = json_decode($post->post_content, true);
            if ($sections) $result['layout-' . $post->ID] = ['name' => $post->post_title, 'sections' => $sections];
        }
        return $result;
    }

    public static function entries(string $type): array {
        return get_posts(['post_type' => $type, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
    }

    public static function block(array $design): array {
        return ['blockName' => 'tccm/section', 'attrs' => ['design' => $design], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []];
    }

    private static bool $starting = false;

    public static function starting(): bool {
        return self::$starting;
    }

    public static function create_draft(string $layout) {
        $layouts = self::layouts();
        if (!current_user_can(self::WRITE) || !current_user_can('edit_posts') || !isset($layouts[$layout])) {
            return new \WP_Error('invalid_layout', 'Composition indisponible.');
        }
        $designs = self::designs();
        $blocks = [];
        foreach ($layouts[$layout]['sections'] as $key) {
            if (!isset($designs[$key])) return new \WP_Error('missing_section', 'Une section de la composition est indisponible.');
            $blocks[] = self::block($designs[$key]);
        }
        self::$starting = true;
        try {
            return wp_insert_post(wp_slash(['post_type' => 'post', 'post_status' => 'draft', 'post_author' => get_current_user_id(),
            'post_content' => serialize_blocks($blocks), 'meta_input' => [Editor::META => 1]]), true);
        } finally {
            self::$starting = false;
        }
    }
}
