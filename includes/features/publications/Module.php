<?php

use TheCore\Collectivity\Publications\Editor;
use TheCore\Collectivity\Publications\Library;
use TheCore\Collectivity\Publications\Screen;

defined('ABSPATH') || exit;

require_once __DIR__ . '/Library.php';
require_once __DIR__ . '/Media.php';
require_once __DIR__ . '/Editor.php';
require_once __DIR__ . '/Screen.php';

final class TheCore_Collectivity_Publications_Module extends TheCore_Collectivity_Abstract_Module {
    public function get_id() { return 'publications'; }

    public function register_hooks() {
        add_action('init', [Library::class, 'register']);
        add_action('init', [Editor::class, 'register']);
        add_action('enqueue_block_editor_assets', [Editor::class, 'assets']);
        add_filter('block_editor_settings_all', [Editor::class, 'settings'], 20, 2);
        add_filter('rest_pre_insert_post', [Editor::class, 'rest_guard'], 20, 2);
        add_filter('wp_insert_post_data', [Editor::class, 'guard'], 20, 2);
        add_action('load-post-new.php', static function () {
            if (($_GET['post_type'] ?? 'post') === 'post' && self::guided(wp_get_current_user())) {
                wp_safe_redirect(Screen::url());
                exit;
            }
        });
        add_filter('map_meta_cap', static function ($caps, $cap, $uid, $args) {
            if (!in_array($cap, ['edit_post', 'delete_post'], true) || empty($args[0]) || get_post_type($args[0]) !== 'post') return $caps;
            $user = get_userdata($uid);
            if ($user && self::guided($user) && !Library::starting() && !Editor::managed((int) $args[0])) return ['do_not_allow'];
            return $caps;
        }, 20, 4);
        add_filter('wp_insert_post_empty_content', [self::class, 'require_composition'], 20, 2);
        add_action('admin_post_tccm_publication_visual', [Screen::class, 'visual']);
        add_filter('get_the_excerpt', [Editor::class, 'excerpt'], 9, 2);
        add_action('admin_post_tccm_publication_start', [Screen::class, 'start']);
        add_action('admin_post_tccm_publication_library', [Screen::class, 'save']);
        foreach (['option_elementor_exclude_user_roles', 'default_option_elementor_exclude_user_roles'] as $filter) {
            add_filter($filter, static function ($roles) {
                return array_values(array_unique(array_merge((array) $roles, ['tccm_publication_editor', 'tccm_publication_contributor'])));
            });
        }
        add_action('admin_enqueue_scripts', static function () {
            if (in_array($_GET['page'] ?? '', ['thecore-workspace', 'tccm-publications'], true)
                && current_user_can(Library::WRITE)) wp_enqueue_style('tccm-publication');
        });
        add_filter('thecore_administration_workspace_asset_handles', static function ($handles) {
            if (!in_array($_GET['screen'] ?? '', ['publications', apply_filters('tccm_publication_screen', 'publications')], true)) return $handles;
            $handles['styles'][] = 'tccm-publication';
            if (current_user_can(Library::MANAGE) && ($_GET['section'] ?? '') === 'bibliotheque') {
                $handles['scripts'][] = 'tccm-publication-library';
            }
            return $handles;
        });
        add_action('admin_menu', [Screen::class, 'menu']);
        add_filter('thecore_administration_screens', [self::class, 'screens'], 30);
        add_filter('thecore_administration_workspace_profiles', [self::class, 'profiles'], 30);
        add_filter('user_has_cap', [self::class, 'capabilities'], 30, 4);
        add_action('wp_enqueue_scripts', [self::class, 'public_assets'], 40);
    }

    public function maybe_upgrade() {
        foreach (['editor' => 'Éditeur de publications', 'contributor' => 'Contributeur de publications'] as $name => $label) {
            $slug = 'tccm_publication_' . $name;
            if (get_role($slug)) continue;
            $caps = ['read' => true, 'edit_posts' => true, 'upload_files' => true, Library::WRITE => true];
            if ($name === 'editor') $caps += [Library::ASSEMBLE => true, 'edit_others_posts' => true, 'edit_published_posts' => true, 'publish_posts' => true];
            add_role($slug, $label, $caps);
        }
    }

    public static function capabilities($all, $caps, $args, $user) {
        if (!array_intersect($caps, [Library::WRITE, Library::ASSEMBLE, Library::MANAGE])) return $all;
        $managers = apply_filters('tccm_publication_manager_roles', ['administrator']);
        if (array_intersect((array) $user->roles, $managers) || !empty($all[Library::MANAGE])) {
            foreach ([Library::WRITE, Library::ASSEMBLE, Library::MANAGE] as $cap) $all[$cap] = true;
        }
        return $all;
    }

    private static function guided($user): bool {
        return (bool) array_intersect(['tccm_publication_editor', 'tccm_publication_contributor'], (array) $user->roles)
            && !user_can($user, Library::MANAGE);
    }

    public static function require_composition($empty, $post) {
        if (($post['post_type'] ?? 'post') !== 'post' || !self::guided(wp_get_current_user()) || Library::starting()) return $empty;
        return empty($post['ID']) || !Editor::managed((int) $post['ID']) || $empty;
    }

    public static function screens($screens) {
        $screens['publications'] = ['label' => 'Publications', 'capability' => Library::WRITE, 'render' => [Screen::class, 'render']];
        return $screens;
    }

    public static function profiles($profiles) {
        foreach (['editor' => 'Éditeur de publications', 'contributor' => 'Contributeur de publications'] as $role => $label) {
            $profiles['tccm-publication-' . $role] = ['label' => $label, 'roles' => ['tccm_publication_' . $role], 'screens' => ['publications'], 'landing' => 'publications'];
        }
        return $profiles;
    }

    public static function public_assets() {
        $id = (int) get_queried_object_id();
        if (is_singular('post') && Editor::managed($id)) {
            wp_enqueue_style('tccm-publication');
            if (wp_style_is('thecore-gallery', 'registered') && str_contains((string) get_post_field('post_content', $id), '"gallery"')) {
                wp_enqueue_style('thecore-gallery');
            }
        }
    }
}
