<?php
// wp eval-file tests/integration/publication-components.php <library-manager> <editor> <contributor>
use TheCore\Collectivity\Publications\Components;
use TheCore\Collectivity\Publications\Library;
use TheCore\Collectivity\Publications\Media;

if (!defined('WP_CLI') || count($args) !== 3) throw new RuntimeException('Three test users required.');
$assert = static function ($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
};
$original = get_current_user_id();
$created = [];
try {
    wp_set_current_user((int) $args[0]);
    $assert(current_user_can(Library::MANAGE) && !current_user_can('manage_options'), 'Invalid manager.');
    $id = Components::create('Recette bibliothèque ' . wp_generate_uuid4());
    $assert(!is_wp_error($id), is_wp_error($id) ? $id->get_error_message() : 'Creation failed.');
    $created[] = $id;
    $assert(Components::owned($id) && current_user_can('edit_post', $id), 'Managed document.');
    $assert(!isset(Library::designs()['elementor-' . $id]), 'Draft unavailable for insertion.');
    $foreign = wp_insert_post(['post_type' => Components::TYPE, 'post_status' => 'draft', 'post_title' => 'Recette hors bibliothèque']);
    $created[] = $foreign;
    $assert(!current_user_can('edit_post', $foreign), 'Unmanaged component must stay protected.');
    $status = static function ($ids) {
        $request = new WP_REST_Request('PUT', Components::ROUTE . '/status');
        $request->set_body_params(['ids' => $ids, 'status' => 'publish']);
        return rest_do_request($request)->get_status();
    };
    $assert($status([$id, $foreign]) === 403, 'Mixed batch must fail.');
    $assert($status([$id]) === 200 && get_post_status($id) === 'publish', 'Publish native component.');
    $fields = Components::fields($id);
    $assert(count($fields) === 2, 'Default exposed fields.');
    $design = Library::designs()['elementor-' . $id];
    $html = Media::render(['design' => $design, 'values' => [array_key_first($fields) => 'Titre de recette unique']]);
    $assert(str_contains($html, 'Titre de recette unique'), 'Public override must render.');
    $assert(!current_user_can('manage_options'), 'Administration stays restricted.');
    foreach (array_slice($args, 1) as $user) {
        wp_set_current_user((int) $user);
        $assert(!current_user_can('edit_post', $id) && $status([$id]) === 403, 'Role cannot change master.');
        $assert(is_wp_error(Components::create('Recette refusée')), 'Role cannot create masters.');
    }
    WP_CLI::success('Creation, publication, exposed value and role boundaries verified.');
} finally {
    wp_set_current_user($original);
    foreach ($created as $id) if ($id) wp_delete_post($id, true);
}
