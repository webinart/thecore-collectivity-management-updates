<?php

namespace TheCore\Collectivity\Publications;

use TheCore\Modules\Administration\Workspace;

defined('ABSPATH') || exit;

final class Screen {
    public static function menu(): void {
        add_submenu_page('edit.php', 'Publications', 'Publications', Library::WRITE, 'tccm-publications', [self::class, 'render']);
    }

    public static function url(array $params = []): string {
        $url = admin_url('edit.php?page=tccm-publications');
        if (class_exists(Workspace::class)) {
            $key = apply_filters('tccm_publication_screen', 'publications');
            if (isset(Workspace::available_screens()[$key])) $url = Workspace::url($key);
        }
        return add_query_arg($params, $url);
    }

    public static function start(): void {
        self::authorize('tccm_publication_start', Library::WRITE);
        $id = Library::create_draft(sanitize_key(wp_unslash($_POST['layout'] ?? '')));
        if (is_wp_error($id)) wp_die(esc_html($id->get_error_message()), '', ['response' => 400]);
        wp_safe_redirect(admin_url('post.php?post=' . (int) $id . '&action=edit'), 303);
        exit;
    }

    private static function can_use_elementor(): bool {
        return current_user_can(Library::MANAGE) && class_exists(\Elementor\User::class)
            && \Elementor\User::is_current_user_can_edit_post_type('post');
    }

    public static function visual(): void {
        self::authorize('tccm_publication_visual', Library::MANAGE);
        if (!self::can_use_elementor()) wp_die('Éditeur indisponible.', '', ['response' => 403]);
        require_once ABSPATH . 'wp-admin/includes/post.php';
        $post = get_default_post_to_edit('post', true);
        $doc = \Elementor\Plugin::$instance->documents->get($post->ID);
        wp_safe_redirect($doc ? $doc->get_edit_url() : get_edit_post_link($post->ID, 'raw'), 303);
        exit;
    }

    private static function authorize(string $action, string $cap): void {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !current_user_can($cap)) wp_die('Accès refusé.', '', ['response' => 403]);
        check_admin_referer($action);
    }

    private static function form(string $action): void {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr($action) . '">';
        wp_nonce_field($action);
    }

    private static function link(string $text, array $params = []): void {
        echo '<a href="' . esc_url(self::url($params)) . '">' . esc_html($text) . '</a>';
    }

    private static function start_form(): void {
        self::form('tccm_publication_start');
        echo '<label for="tccm-layout">Composition de départ</label><select name="layout" id="tccm-layout">';
        foreach (Library::layouts() as $id => $layout) {
            echo '<option value="' . esc_attr($id) . '">' . esc_html($layout['name']) . '</option>';
        }
        echo '</select> <button class="button button-primary svjd-action" type="submit">Rédiger une actualité</button></form>';
    }

    public static function render(): void {
        if (!current_user_can(Library::WRITE)) wp_die('Accès refusé.', '', ['response' => 403]);
        $library = ($_GET['section'] ?? '') === 'bibliotheque' && current_user_can(Library::MANAGE);
        wp_enqueue_style('tccm-publication');
        echo '<div class="wrap tccm-publications"><h1>' . ($library ? 'Bibliothèque éditoriale' : 'Actualités') . '</h1>';
        if ($library) {
            self::link('Retour aux actualités');
            self::library_screen();
        } else {
            echo '<section class="tccm-pub-panel">';
            self::start_form();
            if (self::can_use_elementor()) {
                echo '<details><summary>Mise en page avancée</summary>';
                self::form('tccm_publication_visual');
                echo '<button class="button">Créer un article avec Elementor</button></form></details>';
            }
            if (current_user_can(Library::MANAGE)) self::link('Gérer les sections et les compositions', ['section' => 'bibliotheque']);
            echo '</section>';
            $args = ['post_type' => 'post', 'posts_per_page' => 20, 'orderby' => 'modified', 'order' => 'DESC',
                'post_status' => ['draft', 'pending', 'publish', 'future']];
            if (!current_user_can('edit_others_posts')) $args['author'] = get_current_user_id();
            $args = apply_filters('tccm_publication_list_args', $args);
            echo '<section class="tccm-pub-panel"><h2>Articles récents</h2><ul>';
            foreach (get_posts($args) as $post) {
                if (!current_user_can('edit_post', $post->ID)) continue;
                $status = get_post_status_object($post->post_status);
                echo '<li><a href="' . esc_url(get_edit_post_link($post->ID, 'raw')) . '">' . esc_html($post->post_title ?: 'Sans titre') . '</a> · ' . esc_html($status->label) . '</li>';
            }
            echo '</ul><a href="' . esc_url(admin_url('edit.php')) . '">Tous les articles et brouillons</a></section>';
        }
        echo '</div>';
    }

    private static function library_screen(): void {
        $type = ($_GET['type'] ?? '') === 'layout' ? Library::LAYOUT : Library::COMPONENT;
        $id = absint($_GET['item'] ?? 0);
        $post = $id ? get_post($id) : null;
        if ($id && (!$post || $post->post_type !== $type)) wp_die('Modèle introuvable.', '', ['response' => 404]);
        $section = ['section' => 'bibliotheque'];
        echo '<nav>';
        self::link('Sections', $section);
        echo ' · ';
        self::link('Compositions', $section + ['type' => 'layout']);
        echo '</nav>';
        $is_layout = $type === Library::LAYOUT;
        $key = $is_layout ? 'layout' : 'component';
        $choices = $is_layout ? array_map(static fn($d) => $d['name'], Library::designs()) : Library::parts();
        $catalog = $is_layout ? Library::layouts() : Library::designs();
        echo '<section class="tccm-pub-panel"><h2>' . ($is_layout ? 'Compositions disponibles' : 'Sections disponibles') . '</h2><ul>';
        foreach ($catalog as $ref => $entry) {
            echo '<li>';
            self::link($entry['name'], $section + ['type' => $key, 'source' => $ref] + (preg_match('/^(section|layout)-(\d+)$/', $ref, $m) ? ['item' => $m[2]] : []));
            echo '</li>';
        }
        echo '</ul>';
        self::link($is_layout ? 'Nouvelle composition' : 'Nouvelle section', $section + ['type' => $key]);
        echo '</section>';
        $source = $catalog[sanitize_key($_GET['source'] ?? '')] ?? [];
        $data = $post ? json_decode($post->post_content, true) : ($is_layout ? ($source['sections'] ?? []) : $source);
        $rows = ($is_layout ? $data : ($data['parts'] ?? [])) ?: [array_key_first($choices)];
        echo '<section class="tccm-pub-panel"><h2>' . ($id ? 'Modifier' : 'Créer') . ($is_layout ? ' une composition' : ' une section') . '</h2>';
        self::form('tccm_publication_library');
        echo '<input type="hidden" name="item" value="' . $id . '"><input type="hidden" name="type" value="' . esc_attr($key) . '">';
        echo '<input type="hidden" name="revision" value="' . ($post ? esc_attr(self::revision($post)) : '') . '">';
        echo '<label>Nom<input name="title" required maxlength="120" value="' . esc_attr($post->post_title ?? ($source['name'] ?? '')) . '"></label>';
        if (!$is_layout) {
            echo '<label>Présentation<select name="layout"><option value="stack">De haut en bas</option><option value="columns" ' . selected($data['layout'] ?? '', 'columns', false) . '>En colonnes sur grand écran</option></select></label>';
        }
        echo '<fieldset><legend>' . ($is_layout ? 'Sections dans l’ordre de lecture' : 'Champs à remplir, dans l’ordre de lecture') . '</legend><div data-pub-rows>';
        foreach ($rows as $value) {
            echo '<div data-pub-row><select name="parts[]" aria-label="Élément">';
            foreach ($choices as $choice => $label) echo '<option value="' . esc_attr($choice) . '" ' . selected($value, $choice, false) . '>' . esc_html($label) . '</option>';
            echo '</select> <button type="button" data-op="up">Monter</button> <button type="button" data-op="down">Descendre</button> <button type="button" data-op="remove">Retirer</button></div>';
        }
        echo '</div><button type="button" data-op="add">Ajouter ' . ($is_layout ? 'une section' : 'un champ') . '</button></fieldset>';
        echo '<button type="submit" class="button button-primary svjd-action">Enregistrer dans la bibliothèque</button></form></section>';
        wp_enqueue_script('tccm-publication-library');
    }

    private static function revision($post): string {
        return hash('sha256', $post->post_title . "\0" . $post->post_content);
    }

    public static function save(): void {
        self::authorize('tccm_publication_library', Library::MANAGE);
        $is_layout = ($_POST['type'] ?? '') === 'layout';
        $type = $is_layout ? Library::LAYOUT : Library::COMPONENT;
        $id = absint($_POST['item'] ?? 0);
        $post = $id ? get_post($id) : null;
        if ($id && (!$post || $post->post_type !== $type || !hash_equals(self::revision($post), (string) ($_POST['revision'] ?? '')))) {
            wp_die('Ce modèle a changé. Rouvrez-le avant de reprendre vos modifications.', '', ['response' => 409]);
        }
        $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        $parts = (array) wp_unslash($_POST['parts'] ?? []);
        $choices = $is_layout ? Library::designs() : Library::parts();
        if (!$title || !$parts || count($parts) > 80 || array_filter($parts, static fn($p) => !is_string($p) || !isset($choices[$p]))) {
            wp_die('Renseignez un nom et des éléments valides.', '', ['response' => 400]);
        }
        $data = $is_layout ? array_values($parts) : ['layout' => ($_POST['layout'] ?? '') === 'columns' ? 'columns' : 'stack', 'parts' => array_values($parts)];
        $id = wp_insert_post(wp_slash(['ID' => $id, 'post_title' => $title, 'post_type' => $type, 'post_status' => 'publish', 'post_content' => wp_json_encode($data)]), true);
        if (is_wp_error($id)) wp_die(esc_html($id->get_error_message()), '', ['response' => 500]);
        wp_safe_redirect(self::url(['section' => 'bibliotheque', 'type' => $is_layout ? 'layout' : 'component', 'item' => $id]), 303);
        exit;
    }
}
