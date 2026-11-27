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

    public static function create_component(): void {
        self::authorize('tccm_component_create', Library::MANAGE);
        $id = Components::create(sanitize_text_field(wp_unslash($_POST['title'] ?? '')));
        if (is_wp_error($id)) wp_die(esc_html($id->get_error_message()), '', ['response' => 400, 'back_link' => true]);
        wp_safe_redirect(\Elementor\Plugin::$instance->documents->get($id)->get_edit_url(), 303);
        exit;
    }

    private static function thumb(string $ref, bool $layout = false): string {
        $url = add_query_arg(['action' => 'tccm_component_thumbnail', 'ref' => $ref, 'layout' => (int) $layout], admin_url('admin-post.php'));
        return '<span class="tccm-catalog-thumb" aria-hidden="true" inert><iframe tabindex="-1" loading="lazy" sandbox="allow-same-origin" src="' . esc_url($url) . '" title="Aperçu"></iframe></span>';
    }

    public static function thumbnail(): void {
        if (!current_user_can(Library::WRITE)) wp_die('Accès refusé.', '', ['response' => 403]);
        $designs = Library::designs();
        if (current_user_can(Library::MANAGE)) $designs += Components::catalog(true);
        $ref = sanitize_key($_GET['ref'] ?? '');
        $sections = empty($_GET['layout']) ? [$ref] : (Library::layouts()[$ref]['sections'] ?? []);
        $html = '';
        foreach ($sections as $key) {
            if (!isset($designs[$key])) continue;
            $entry = $designs[$key];
            if (isset($entry['component'])) {
                $html .= Components::render($entry['component'], [], true);
                continue;
            }
            $html .= '<div class="sample ' . esc_attr($entry['layout']) . '">';
            foreach ($entry['parts'] as $part) {
                if ($part === 'text') {
                    $html .= '<div><h2>Au fil de la commune</h2><p>Retrouvez les nouvelles de la vie locale et les rendez-vous de la semaine.</p></div>';
                } elseif ($part === 'file') {
                    $html .= '<div class="file">↓ &nbsp; Télécharger le document</div>';
                } elseif ($part === 'video') {
                    $html .= '<div class="video">▶</div>';
                } else {
                    $count = $part === 'gallery' ? 3 : 1;
                    $html .= '<div class="photos">';
                    $images = get_posts(['post_type' => 'post', 'post_status' => 'publish', 'has_password' => false, 'meta_key' => '_thumbnail_id', 'posts_per_page' => $count]);
                    foreach ($images as $photo) $html .= get_the_post_thumbnail($photo->ID, 'medium', ['alt' => '']);
                    $html .= '</div>';
                }
            }
            $html .= '</div>';
        }
        nocache_headers();
        header('X-Robots-Tag: noindex');
        header("Content-Security-Policy: script-src 'none'; frame-src 'none'; frame-ancestors 'self'; form-action 'none'");
        header('Content-Type: text/html; charset=' . get_option('blog_charset'));
        echo '<!doctype html><html lang="fr"><head><meta charset="utf-8">';
        if (Components::available()) \Elementor\Plugin::$instance->frontend->enqueue_styles();
        wp_print_styles();
        echo '<style>html,body{margin:0!important;background:white;color:#25484a;font:17px/1.45 system-ui}main{padding:20px}.sample{display:grid;gap:14px;margin-bottom:12px}.columns{grid-template-columns:1fr 1fr}.sample h2{font:600 29px/1.1 Georgia;margin:0 0 7px}.sample p{margin:0}.photos{display:flex;gap:8px;overflow:hidden}.photos img{width:0;flex:1;min-width:0;height:116px;object-fit:cover}.video,.file{border-radius:5px;padding:20px;background:#edf4f3}.video{display:grid;place-items:center;background:#24484b;color:white;font-size:30px;height:85px}</style></head><body><main>';
        echo $html . '</main></body></html>';
        exit;
    }

    private static function library_screen(): void {
        $section = ['section' => 'bibliotheque'];
        $is_layout = ($_GET['type'] ?? '') === 'layout';
        $type = $is_layout ? Library::LAYOUT : Library::COMPONENT;
        $id = absint($_GET['item'] ?? 0);
        $post = $id ? get_post($id) : null;
        if ($id && (!$post || $post->post_type !== $type)) wp_die('Modèle introuvable.', '', ['response' => 404]);
        echo '<nav class="tccm-catalog-tabs" aria-label="Bibliothèque">';
        foreach (['component' => 'Sections', 'layout' => 'Compositions'] as $tab => $label) {
            echo '<a ' . (($tab === 'layout') === $is_layout ? 'aria-current="page" ' : '') . 'href="' . esc_url(self::url($section + ['type' => $tab])) . '">' . $label . '</a>';
        }
        echo '</nav>';
        $designs = Library::designs();
        $catalog = $is_layout ? Library::layouts() : Components::catalog(true) + $designs;
        echo '<section class="tccm-pub-panel"><h2>' . ($is_layout ? 'Compositions' : 'Sections') . '</h2>';
        if (!$is_layout && Components::available() && \Elementor\Modules\Components\Components_Access_Controller::can_create()) {
            self::form('tccm_component_create');
            echo '<label>Nom de la nouvelle section <input name="title" required minlength="2" maxlength="200" placeholder="Texte et portrait"></label> <button type="submit" class="button button-primary svjd-action">Créer avec Elementor</button></form>';
        }
        if ($is_layout) self::link('Nouvelle composition', $section + ['type' => 'layout', 'new' => 1]);
        echo '<div class="tccm-catalog-grid">';
        foreach ($catalog as $ref => $entry) {
            $button = '';
            $note = '';
            if (isset($entry['component'])) {
                $doc = \Elementor\Plugin::$instance->documents->get($entry['component']);
                $button = '<a href="' . esc_url($doc->get_edit_url()) . '">Modifier avec Elementor</a>';
                $note = get_post_status($entry['component']) === 'draft' ? 'Brouillon' : 'Publié';
            } elseif ($is_layout || preg_match('/^section-\d+$/', $ref)) {
                $params = $section + ['type' => $is_layout ? 'layout' : 'component', 'source' => $ref];
                $custom = preg_match('/^(section|layout)-(\d+)$/', $ref, $match);
                if ($custom) $params['item'] = $match[2];
                $button = '<a href="' . esc_url(self::url($params)) . '">' . ($custom ? 'Modifier' : 'Créer une variante') . '</a>';
            }
            echo '<article class="tccm-catalog-card">' . self::thumb($ref, $is_layout) . '<div><strong>' . esc_html($entry['name']) . '</strong>';
            if ($note) echo '<small>' . esc_html($note) . '</small>';
            echo $button . '</div></article>';
        }
        echo '</div></section>';
        if (!$post && (!$is_layout || !isset($_GET['new']) && !isset($_GET['source']))) return;
        $source = $catalog[sanitize_key($_GET['source'] ?? '')] ?? [];
        $data = $post ? json_decode($post->post_content, true) : ($source['sections'] ?? []);
        $rows = $is_layout ? $data : ($data['parts'] ?? []);
        $choices = $is_layout ? array_map(static fn($d) => $d['name'], $designs) : Library::parts();
        echo '<section class="tccm-pub-panel" id="tccm-composer"><h2>' . ($is_layout ? 'Composer un article' : 'Modifier la section Gutenberg') . '</h2>';
        self::form('tccm_publication_library');
        echo '<input type="hidden" name="type" value="' . ($is_layout ? 'layout' : 'component') . '"><input type="hidden" name="item" value="' . $id . '"><input type="hidden" name="revision" value="' . esc_attr($post ? self::revision($post) : '') . '">';
        echo '<label>Nom <input required name="title" value="' . esc_attr($post ? $post->post_title : ($source['name'] ?? '')) . '"></label>';
        if (!$is_layout) {
            echo '<label>Disposition <select name="layout"><option value="stack">Une colonne</option><option value="columns" ' . selected($data['layout'] ?? '', 'columns', false) . '>Deux colonnes</option></select></label>';
        }
        echo '<div class="tccm-composer"><div><h3>' . ($is_layout ? 'Ajouter une section' : 'Ajouter un champ') . '</h3><div class="tccm-palette">';
        foreach ($choices as $key => $label) {
            echo '<button type="button" data-add="' . esc_attr($key) . '">' . ($is_layout ? self::thumb($key) : '') . '<span>' . esc_html($label) . '</span></button>';
        }
        echo '</div></div><div><h3>Ordre de lecture</h3><ol data-composition>';
        foreach ($rows as $key) {
            if (!isset($choices[$key])) continue;
            echo '<li data-key="' . esc_attr($key) . '"><input type="hidden" name="parts[]" value="' . esc_attr($key) . '">' . ($is_layout ? self::thumb($key) : '') . '<strong>' . esc_html($choices[$key]) . '</strong></li>';
        }
        echo '</ol><p role="status" class="screen-reader-text"></p></div></div><button type="submit" class="button button-primary svjd-action">Enregistrer dans la bibliothèque</button></form></section>';
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
