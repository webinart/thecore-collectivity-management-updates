<?php
/**
 * wp eval-file tests/integration/publications-wordpress.php MANAGER_ID EDITOR_ID CONTRIBUTOR_ID
 * Uses three existing test users, writes temporary drafts and removes them in finally.
 */
use TheCore\Collectivity\Publications\Editor;
use TheCore\Collectivity\Publications\Library;
use TheCore\Collectivity\Publications\Media;

if (!defined('WP_CLI') || count($args ?? []) !== 3 || !class_exists(Editor::class)) {
    throw new RuntimeException('Run via WP-CLI with Publications enabled and three QA user IDs.');
}
$check = static function ($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
    echo "OK $message\n";
};
$drafts = [];
$original = get_current_user_id();
try {
    foreach (['manager', 'editor', 'contributor'] as $i => $profile) {
        wp_set_current_user((int) $args[$i]);
        $check(current_user_can(Library::MANAGE) === ($profile === 'manager'), "$profile library permission");
        $check(current_user_can(Library::ASSEMBLE) === ($profile !== 'contributor'), "$profile assembly permission");
        $id = Library::create_draft('interview');
        $check(is_int($id) && $id > 0 && Editor::managed($id), "$profile creates managed draft");
        $drafts[] = $id;
        $raw = get_post_field('post_content', $id, 'raw');
        $blocks = parse_blocks($raw);
        $blocks[0]['attrs']['values'] = [0 => ['body' => 'La commune présente son projet.']];
        $body = serialize_blocks($blocks);
        $check(Editor::valid_change($id, $body), "$profile content may change");
        $more = $body . serialize_block(Library::block(Library::designs()['gallery']));
        $check(Editor::valid_change($id, $more) === ($profile !== 'contributor'), "$profile add-section permission");
        $saved = wp_update_post(wp_slash(['ID' => $id, 'post_content' => $body]), true);
        $check(!is_wp_error($saved) && str_contains(get_the_excerpt($id), 'son projet'), "$profile excerpt");
        $check(str_contains(do_blocks(get_post_field('post_content', $id)), 'son projet'), "$profile public text");
        if ($profile === 'contributor') {
            wp_update_post(wp_slash(['ID' => $id, 'post_content' => $more]));
            $check(get_post_field('post_content', $id, 'raw') === $body, 'fixed structure across native saves');
            $check(!current_user_can('publish_posts'), 'contributor submits for review');
        }
    }
    wp_set_current_user((int) $args[0]);
    $video = Media::render(['design' => Library::designs()['video'], 'values' => [['url' => 'https://www.youtube.com/watch?v=C6R-s_eN1H0']]]);
    $check(str_contains($video, 'YouTube') || str_contains($video, 'youtube'), 'YouTube render');
    if (class_exists(\Elementor\Plugin::class) && \Elementor\Plugin::$instance->widgets_manager->get_widget_types('e-youtube')) {
        $check(str_contains($video, 'e-youtube') || str_contains($video, 'data-thecore-cc-embed-template'), 'Elementor V4 YouTube player');
    }
    if (class_exists(\TheCore\ElementorWidgets\Elementor\Atomic\Widgets\Gallery\MarkupBuilder::class)) {
        $photos = get_posts(['post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids']);
        if ($photos) {
            $gallery = Media::render(['design' => Library::designs()['gallery'], 'values' => [['images' => [['id' => $photos[0]]]]]]);
            $check(str_contains($gallery, 'data-thecore-gallery-item'), 'The Core gallery renderer');
        }
    }
} finally {
    foreach ($drafts as $id) wp_delete_post($id, true);
    wp_set_current_user($original);
}
