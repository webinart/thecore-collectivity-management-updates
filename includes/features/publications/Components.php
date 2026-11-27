<?php
namespace TheCore\Collectivity\Publications;

use Elementor\Modules\Components\Components_Access_Controller as Access;
use Elementor\Modules\Components\Components_REST_API;
use Elementor\Modules\Components\Documents\Component;
use Elementor\Plugin;

defined('ABSPATH') || exit;

final class Components {
    const TYPE = 'elementor_component';
    const META = '_tccm_publication_component';
    const ROUTE = '/elementor/v1/components';
    private static ?\WP_REST_Request $creation = null;

    public static function hooks(): void {
        add_filter('elementor/document/related_posts', static function ($related, $id) {
            if (get_post_type($id) !== 'post' || !Editor::managed((int) $id)) return $related;
            return array_unique(array_merge($related, self::article_components((int) $id)));
        }, 10, 2);
        add_action('elementor/editor/after_enqueue_scripts', [self::class, 'editor_assets']);
        add_filter('rest_endpoints', [self::class, 'permissions']);
        add_filter('map_meta_cap', [self::class, 'protect_document'], 25, 4);
        add_filter('elementor/document/urls/preview', [self::class, 'preview_url'], 10, 2);
        add_filter('elementor/document/urls/exit_to_dashboard', static function ($url, $document) {
            return self::owned($document->get_main_id()) && current_user_can(Library::MANAGE)
                ? Screen::url(['section' => 'bibliotheque']) : $url;
        }, 10, 2);
        add_filter('request', [self::class, 'preview_query']);
        add_filter('template_include', [self::class, 'preview_template'], 999);
    }

    public static function editor_assets(): void {
        $id = absint($_GET['post'] ?? 0);
        if (!self::owned($id) || !current_user_can(Library::MANAGE) || !current_user_can('edit_post', $id)) return;
        $document = Plugin::$instance->documents->get($id);
        $handle = 'tccm-component-editor';
        wp_enqueue_script($handle, THECORE_COLLECTIVITY_MANAGEMENT_URL . 'includes/features/publications/component-editor.js',
            ['elementor-editor'], THECORE_COLLECTIVITY_MANAGEMENT_VERSION, true);
        wp_add_inline_script($handle, 'window.tccmPublicationMaster=' . wp_json_encode([
            'id' => $id, 'properties' => $document->get_overridable_props()->to_associative_array(),
        ], JSON_HEX_TAG | JSON_HEX_AMP) . ';', 'before');
    }

    public static function available(): bool {
        return class_exists(Access::class) && post_type_exists(self::TYPE);
    }

    public static function owned(int $id): bool {
        return $id && get_post_type($id) === self::TYPE && get_post_meta($id, self::META, true) === '1';
    }

    public static function protect_document($caps, $cap, $user_id, $args) {
        if (!in_array($cap, ['edit_post', 'delete_post'], true) || empty($args[0])) return $caps;
        $id = wp_is_post_revision($args[0]) ?: (int) $args[0];
        if (get_post_type($id) !== self::TYPE || user_can($user_id, 'manage_options')) return $caps;
        if (!self::owned($id)) {
            return user_can($user_id, Library::WRITE) ? ['do_not_allow'] : $caps;
        }
        if (!self::available() || !Access::can_edit()) return ['do_not_allow'];
        return $cap === 'edit_post' ? [Library::MANAGE] : ['do_not_allow'];
    }

    public static function permissions(array $routes): array {
        $operations = ['' => 'POST', '/lock' => 'POST', '/unlock' => 'POST', '/lock-status' => 'GET', '/status' => 'PUT'];
        foreach ($operations as $suffix => $method) {
            $route = self::ROUTE . $suffix;
            foreach (($routes[$route] ?? []) as $index => $handler) {
                $native = $handler['permission_callback'] ?? null;
                $methods = $handler['methods'] ?? '';
                if (($methods !== $method && !(is_array($methods) && !empty($methods[$method]))) || !$native instanceof \Closure ||
                    (new \ReflectionFunction($native))->getClosureScopeClass()?->getName() !== Components_REST_API::class) continue;
                $routes[$route][$index]['permission_callback'] = static function ($request) use ($native, $suffix) {
                    if (!current_user_can(Library::MANAGE) || current_user_can('manage_options')) return $native($request);
                    if ($suffix === '') {
                        if ($request !== self::$creation) return $native($request);
                    } else {
                        $ids = $suffix === '/status' ? $request['ids'] : [$request['componentId']];
                        if (!$ids || !is_array($ids)) return false;
                        foreach ($ids as $id) {
                            if (!ctype_digit((string) $id) || !self::owned((int) $id)) return false;
                        }
                    }
                    $uid = get_current_user_id();
                    $map = static function ($caps, $cap, $user_id) use ($uid) {
                        return $cap === 'manage_options' && $user_id === $uid && $caps === ['manage_options']
                            ? [Library::MANAGE] : $caps;
                    };
                    add_filter('map_meta_cap', $map, 100, 3);
                    try {
                        return $native($request);
                    } finally {
                        remove_filter('map_meta_cap', $map, 100);
                    }
                };
            }
        }
        return $routes;
    }

    public static function create(string $title) {
        if (!current_user_can(Library::MANAGE) || !self::available() || !Access::can_create()) {
            return new \WP_Error('unavailable', 'Création de composants indisponible.');
        }
        $title = sanitize_text_field($title);
        $uid = wp_generate_uuid4();
        $props = [];
        $children = [];
        foreach (['title' => ['e-heading', 'Intertitre', 'Titre de la section'], 'paragraph' => ['e-paragraph', 'Texte', 'Votre texte ici.']] as $field => [$widget, $label, $sample]) {
            $id = \Elementor\Utils::generate_random_string();
            $key = wp_generate_uuid4();
            $value = ['$$type' => 'escaped-html', 'value' => $sample];
            $props[$key] = ['overrideKey' => $key, 'elementId' => $id, 'elType' => 'widget', 'widgetType' => $widget,
                'propKey' => $field, 'label' => $label, 'groupId' => 'content', 'originValue' => $value];
            $children[] = ['id' => $id, 'elType' => 'widget', 'widgetType' => $widget, 'version' => '0.0', 'elements' => [],
                'settings' => [$field => ['$$type' => 'overridable', 'value' => ['override_key' => $key, 'origin_value' => $value]]]];
        }
        $group = ['id' => 'content', 'label' => 'Contenu', 'props' => array_keys($props)];
        $root = ['id' => \Elementor\Utils::generate_random_string(), 'elType' => 'e-flexbox', 'version' => '0.0', 'settings' => [], 'elements' => $children];
        $request = new \WP_REST_Request('POST', self::ROUTE);
        $request->set_body_params(['status' => 'draft', 'items' => [['uid' => $uid, 'title' => $title, 'elements' => [$root],
            'settings' => ['overridable_props' => ['props' => $props, 'groups' => ['items' => ['content' => $group], 'order' => ['content']]]]]]]);
        // Elementor saves the document before returning its ID. Mark only the new document with our server-generated UID.
        $mark = static function ($meta_id, $post_id, $key, $value) use ($uid) {
            if ($key === Component::COMPONENT_UID_META_KEY && $value === $uid && get_post_type($post_id) === self::TYPE) {
                add_post_meta($post_id, self::META, '1', true);
            }
        };
        self::$creation = $request;
        add_action('added_post_meta', $mark, 10, 4);
        try {
            $response = rest_do_request($request);
        } finally {
            remove_action('added_post_meta', $mark, 10);
            self::$creation = null;
        }
        $data = $response->get_data();
        $id = (int) ($data['data'][$uid] ?? 0);
        if ($response->get_status() !== 201 || !self::owned($id)) {
            return new \WP_Error('component_create', $data['message'] ?? 'La création du composant a échoué.');
        }
        return $id;
    }

    public static function preview_url($url, $doc) {
        $id = $doc->get_main_id();
        return self::owned($id) && current_user_can('edit_post', $id)
            ? add_query_arg(['tccm-component-preview' => $id, 'elementor-preview' => $id], home_url('/')) : $url;
    }

    private static function preview_id(): int {
        $id = absint($_GET['tccm-component-preview'] ?? 0);
        return $id && $id === absint($_GET['elementor-preview'] ?? 0) && self::owned($id)
            && current_user_can(Library::MANAGE) && current_user_can('edit_post', $id) ? $id : 0;
    }

    public static function preview_query($vars) {
        $id = self::preview_id();
        return $id ? ['p' => $id, 'post_type' => self::TYPE, 'post_status' => get_post_status($id)] : $vars;
    }

    public static function preview_template($template) {
        if (self::preview_id() && defined('ELEMENTOR_PATH')) {
            nocache_headers();
            return ELEMENTOR_PATH . 'modules/page-templates/templates/canvas.php';
        }
        return $template;
    }

    public static function catalog(bool $drafts = false): array {
        if (!self::available()) return [];
        $posts = get_posts(['post_type' => self::TYPE, 'post_status' => $drafts ? ['publish', 'draft'] : ['publish'],
            'meta_key' => self::META, 'meta_value' => '1', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        $result = [];
        foreach ($posts as $post) {
            $doc = Plugin::$instance->documents->get($post->ID);
            if ($doc instanceof Component && !$doc->get_is_archived()) {
                $result['elementor-' . $post->ID] = ['name' => $post->post_title, 'component' => $post->ID, 'parts' => []];
            }
        }
        return $result;
    }

    public static function fields(int $id): array {
        if (!self::available() || !self::owned($id)) return [];
        $doc = Plugin::$instance->documents->get($id);
        $fields = [];
        foreach ($doc->get_overridable_props()->props as $prop) {
            $type = $prop->origin_value['$$type'] ?? '';
            if (in_array($type, ['string', 'escaped-html', 'html', 'url', 'image'], true)) {
                $fields[$prop->override_key] = ['type' => $type, 'label' => $prop->label,
                    'default' => is_string($prop->origin_value['value'] ?? null) ? wp_strip_all_tags($prop->origin_value['value']) : ''];
            }
        }
        return $fields;
    }

    public static function article_components(int $post_id): array {
        $ids = [];
        foreach (parse_blocks((string) get_post_field('post_content', $post_id, 'raw')) as $block) {
            if ($block['blockName'] !== 'tccm/section') continue;
            $id = (int) ($block['attrs']['design']['component'] ?? 0);
            if (self::owned($id) && get_post_status($id) === 'publish') $ids[$id] = $id;
        }
        return array_values($ids);
    }

    public static function styles(int $id): void {
        if (!self::available()) return;
        do_action('elementor/post/render', $id);
        \Elementor\Core\Files\CSS\Post::create($id)->enqueue();
    }

    public static function render(int $id, array $values, bool $preview = false): string {
        if (!self::available() || !self::owned($id) ||
            (get_post_status($id) !== 'publish' && !($preview && current_user_can(Library::MANAGE)))) return '';
        $overrides = [];
        foreach (self::fields($id) as $key => $field) {
            if (!isset($values[$key])) continue;
            $type = $field['type'];
            $input = $values[$key];
            if ($type === 'image') {
                if (!is_scalar($input) || !wp_attachment_is_image(absint($input))) continue;
                $value = ['size' => ['$$type' => 'string', 'value' => 'large'], 'src' => ['$$type' => 'image-src', 'value' => ['id' => ['$$type' => 'image-attachment-id', 'value' => absint($input)]]]];
            } else {
                if (!is_string($input)) continue;
                $value = $type === 'url' ? esc_url_raw($input) : sanitize_textarea_field($input);
            }
            $overrides[] = ['$$type' => 'override', 'value' => ['override_key' => $key,
                'schema_source' => ['type' => 'component', 'id' => $id], 'override_value' => ['$$type' => $type, 'value' => $value]]];
        }
        $widget = Plugin::$instance->elements_manager->create_element_instance([
            'id' => \Elementor\Utils::generate_random_string(), 'elType' => 'widget', 'widgetType' => 'e-component', 'version' => '0.0',
            'elements' => [], 'settings' => ['component_instance' => ['$$type' => 'component-instance', 'value' => [
                'component_id' => ['$$type' => 'number', 'value' => $id], 'overrides' => ['$$type' => 'overrides', 'value' => $overrides]]]]]);
        if (!$widget) return '';
        if ($preview) self::styles($id);
        ob_start();
        $widget->print_element();
        return '<div class="elementor elementor-' . $id . '">' . ob_get_clean() . '</div>';
    }
}
