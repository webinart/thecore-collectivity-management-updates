<?php
define('ABSPATH', '/tmp/');
class WP_Error { public function __construct(public $code, public $message) {} }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_json_encode($value) { return json_encode($value); }
require dirname(__DIR__) . '/includes/features/school-menus/class-thecore-collectivity-school-menu-theme-validator.php';
$checks = 0;
function verify($condition, $message) { global $checks; if (!$condition) throw new RuntimeException($message); $checks++; }
$root = dirname(__DIR__) . '/includes/features/school-menus/assets/designer';
foreach (glob($root . '/themes/*.json') as $file) {
    $theme = json_decode(file_get_contents($file), true);
    verify(!is_wp_error(TheCore_Collectivity_School_Menu_Theme_Validator::validate($theme, true)), basename($file));
}
$base = json_decode(file_get_contents($root . '/themes/autumn-hedgehog.json'), true);
foreach (['https://evil.test/image.png', 'assets/../secret.png', 'assets/missing.png', 'data:image/svg+xml;base64,PHN2Zz4=', 'data:image/png;base64,bm90LWFuLWltYWdl'] as $source) {
    $theme = $base;
    $theme['elements'][0]['source'] = $source;
    verify(is_wp_error(TheCore_Collectivity_School_Menu_Theme_Validator::validate($theme)), 'Unsafe asset rejected');
}
$theme = $base;
$theme['execute'] = 'anything';
verify(is_wp_error(TheCore_Collectivity_School_Menu_Theme_Validator::validate($theme)), 'Unknown properties rejected');
$theme = $base;
$theme['elements'][1]['id'] = $theme['elements'][0]['id'];
verify(is_wp_error(TheCore_Collectivity_School_Menu_Theme_Validator::validate($theme)), 'Duplicate elements rejected');
$theme = $base;
$theme['version'] = '2';
verify(is_wp_error(TheCore_Collectivity_School_Menu_Theme_Validator::validate($theme)), 'Strict JSON types enforced');
$theme = $base;
$theme['style']['fontSize'] = 10000;
verify(is_wp_error(TheCore_Collectivity_School_Menu_Theme_Validator::validate($theme)), 'Numeric bounds enforced');
$theme = $base;
$theme['name'] = str_repeat('a', 2000000);
verify(is_wp_error(TheCore_Collectivity_School_Menu_Theme_Validator::validate($theme)), 'String bounds enforced');
echo $checks . " server theme validator checks passed.\n";
