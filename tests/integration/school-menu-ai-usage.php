<?php
// Run from the command line with the theme source. An optional second path exercises a legacy runtime.
if (PHP_SAPI !== 'cli') exit;
if (!isset($argv[1]) || !is_file($argv[1] . '/tests/Support/AIServiceHarness.php')) {
    fwrite(STDERR, "Usage: php tests/integration/school-menu-ai-usage.php PATH_TO_METERED_THEME [PATH_TO_RUNTIME_THEME]\n");
    exit(2);
}
require $argv[1] . '/tests/Support/AIServiceHarness.php';
$runtime = $argv[2] ?? $argv[1];
$metered = is_file($runtime . '/includes/Services/AIUsageAttempt.php');
if ($metered) require $runtime . '/includes/Services/AIUsageAttempt.php';
require $runtime . '/includes/Services/AI.php';
$module = dirname(__DIR__, 2) . '/includes/features/school-menus';
require $module . '/class-thecore-collectivity-school-menu-themes.php';

function get_posts($args) { return $GLOBALS['posts'] ?? []; }
function get_post_meta($id, $key, $single) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function wp_slash($value) { return $value; }
function update_post_meta($id, $key, $value) { $GLOBALS['meta'][$id][$key] = $value; }
function get_post_status($id) { return $GLOBALS['posts'][$id]->post_status; }
function wp_insert_post($attributes, $return_error) {
    if ($GLOBALS['fail_save'] ?? false) return new WP_Error('db_insert_error', 'private-db-error');
    $id = count($GLOBALS['posts'] ?? []) + 1;
    $GLOBALS['posts'][$id] = (object) ($attributes + ['ID' => $id]);
    return $id;
}
function wp_update_post($attributes, $return_error) {
    $GLOBALS['posts'][$attributes['ID']]->post_status = $attributes['post_status'];
    return $attributes['ID'];
}
function verify($value, $description) {
    if (!$value) throw new RuntimeException($description);
    $GLOBALS['checks']++;
}
function generation($provider_result) {
    global $base, $module_class;
    ai_test_configure();
    $GLOBALS['response'] = ['status' => 200, 'headers' => ['x-request-id' => 'req_test'], 'body' => json_encode([
        'status' => 'completed', 'model' => 'test-snapshot', 'usage' => ['input_tokens' => 4000, 'output_tokens' => 500],
        'output' => [['content' => [['type' => 'output_text', 'text' => json_encode($provider_result)]]]],
    ])];
    return $module_class::generate_draft('A private school menu design brief', $base);
}
function check_observations(int $offset, string $business) {
    $events = array_slice(ai_test_events(), $offset);
    verify(array_column($events, 'event_type') === ['started', 'provider', 'business'], 'Start, measurement and module outcome recorded in order');
    verify(count(array_unique(array_column($events, 'attempt_id'))) === 1, 'All phases belong to the same provider attempt');
    verify($events[0]['module'] === 'school-menus' && $events[0]['feature'] === 'school-menu-theme', 'Real module registration identifies the operation');
    verify(array_column($events[1]['units'], 'quantity', 'kind') === ['output_tokens' => 500, 'input_tokens' => 4000], 'Measured units survive the later business result');
    verify($events[2]['business_status'] === $business, 'Expected module outcome ' . $business);
}
$GLOBALS['checks'] = 0;
$module_class = TheCore_Collectivity_School_Menu_Themes::class;
$module_class::setup();
$base = json_decode(file_get_contents($module . '/assets/designer/themes/autumn-hedgehog.json'), true);
$GLOBALS['caps'] = ['publish_tccm_school_menus'];
verify(generation($base)->get_error_code() === 'menu_theme_forbidden' && $dispatches === 0, 'Catering alone cannot use the AI design action');
$GLOBALS['caps'][] = 'edit_others_posts';
verify(is_wp_error($module_class::generate_draft('x', $base)), 'Invalid brief rejected before generation');
verify(is_wp_error($module_class::generate_draft('A valid brief', [])) && $dispatches === 0 && !ai_test_events(), 'Invalid base produces zero attempts');
$result = generation($base);
verify(is_array($result) && $result['published'] === false && get_post_status($result['id']) === 'draft', 'Generated model persisted as a draft');
if ($metered) check_observations(0, 'draft_created');
$result = generation(['invalid' => 'private-model-text']);
verify(is_wp_error($result) && $result->get_error_code() === 'menu_theme_invalid', 'Real menu validator rejects invalid provider output');
if ($metered) check_observations(3, 'result_rejected');
$GLOBALS['fail_save'] = true;
$result = generation($base);
verify(is_wp_error($result) && $result->get_error_code() === 'db_insert_error', 'Storage failure returned to caller');
if ($metered) check_observations(6, 'save_failed');
$GLOBALS['fail_save'] = false;
verify($dispatches === 3 && count($GLOBALS['posts']) === 1, 'One provider call per explicit generation, just one draft');
$prior = ai_test_events();

$archive = $ai_test_dir . '/external.zip';
$zip = new ZipArchive();
if ($zip->open($archive, ZipArchive::CREATE) !== true) throw new RuntimeException('Cannot build sample archive');
$image = 'assets/preview.png';
foreach ($base['elements'] as &$element) $element['source'] = $image;
unset($element);
$base['style']['backgroundImage'] = $image;
foreach ($base['style']['badges']['logos'] as &$logo) $logo = $image;
unset($logo);
$base['style']['animation'] = ['mode' => 'leaves', 'density' => 'normal', 'speed' => 'slow', 'zone' => 'edges', 'image' => $image, 'variant' => $image];
$zip->addFromString('theme.json', json_encode($base));
$zip->addFromString($image, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j6OcAAAAASUVORK5CYII='));
$zip->close();
$import = TheCore_Collectivity_School_Menu_Theme_Archive::read($archive);
verify(!is_wp_error($import), is_wp_error($import) ? $import->get_error_message() : 'External ZIP can be imported');
$imported_draft = $module_class::persist(['intent' => 'draft', 'id' => 0, 'theme' => $import]);
verify(!is_wp_error($imported_draft), is_wp_error($imported_draft) ? $imported_draft->get_error_message() : 'Imported model can be saved');
verify($dispatches === 3 && ai_test_events() === $prior, 'External ZIP and manual save create zero AI events');
foreach (['A private school menu design brief', 'private-db-error', 'private-model-text', $base['name']] as $private) {
    verify(!str_contains(json_encode(ai_test_events()), $private), 'Private menu and error content excluded');
}
echo $checks . ' menu integration checks passed with ' . ($metered ? 'metered' : 'legacy') . " theme.\n";
