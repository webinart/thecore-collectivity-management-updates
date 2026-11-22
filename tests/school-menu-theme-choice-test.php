<?php
// Run with php tests/school-menu-theme-choice-test.php.
define('ABSPATH', __DIR__ . '/');
require_once dirname(__DIR__) . '/includes/modules/class-thecore-collectivity-school-menus-settings.php';
require_once dirname(__DIR__) . '/includes/features/school-menus/class-thecore-collectivity-school-menus-data.php';
require_once dirname(__DIR__) . '/includes/features/school-menus/class-thecore-collectivity-school-menu-themes.php';

function get_option($name, $default) { return $GLOBALS['options'][$name] ?? $default; }
function current_user_can($cap) { return in_array($cap, $GLOBALS['caps'], true); }
function get_post_type_object($type) { return (object) ['cap' => (object) ['edit_posts' => 'edit_tccm_school_menus']]; }
function update_post_meta($id, $key, $value) { $GLOBALS['meta'][$key] = $value; }
function delete_post_meta($id, $key) { unset($GLOBALS['meta'][$key]); }
function get_post_meta($id, $key, $single) { return $GLOBALS['meta'][$key] ?? ''; }
function wp_slash($value) { return $value; }
function do_action($name, ...$args) {}
function expect_choice($condition, $message) { if (!$condition) throw new RuntimeException($message); }

use TheCore_Collectivity_School_Menu_Themes as Themes;
use TheCore_Collectivity_School_Menus_Settings as Settings;

$GLOBALS['caps'] = ['edit_tccm_school_menus'];
expect_choice(Themes::can_choose(), 'Existing sites must retain catering choice by default.');
$frozen = ['theme' => ['name' => 'Winter'], 'composition' => 'standard'];
$GLOBALS['meta'] = [Themes::CHOICE => 12, Themes::SNAPSHOT => $frozen];
$GLOBALS['options'][Settings::CATERING_THEME_CHOICE] = '0';
$GLOBALS['caps'][] = 'publish_tccm_school_menus';
expect_choice(!Themes::can_choose(), 'Direct menu publication must not grant design control.');
Themes::assign(7, 0, null);
Themes::assign(7, 15, ['theme' => ['name' => 'Summer']]);
expect_choice($GLOBALS['meta'] === [Themes::CHOICE => 12, Themes::SNAPSHOT => $frozen], 'Both the model ID and the frozen design must survive a catering save.');

$GLOBALS['caps'][] = 'edit_others_posts';
expect_choice(Themes::can_choose(), 'Communication must be able to choose when catering choice is disabled.');
$GLOBALS['caps'] = ['manage_options'];
expect_choice(Themes::can_choose(), 'Administrators must retain design control.');
$GLOBALS['caps'] = ['edit_tccm_school_menus'];
$GLOBALS['options'][Settings::CATERING_THEME_CHOICE] = '1';
expect_choice(Themes::can_choose(), 'Re-enabling the option must restore catering choice.');
$GLOBALS['caps'] = [];
expect_choice(!Themes::can_choose(), 'Enabling the option must not grant menu editing rights.');
echo "School menu theme choice checks passed.\n";
