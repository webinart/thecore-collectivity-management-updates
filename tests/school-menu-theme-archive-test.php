<?php
define('ABSPATH', '/tmp/');
class WP_Error { public function __construct(public $code, public $message) {} }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_json_encode($value) { return json_encode($value); }
require dirname(__DIR__) . '/includes/features/school-menus/class-thecore-collectivity-school-menu-theme-archive.php';

if (!class_exists('ZipArchive')) {
    echo "Theme archive tests skipped: PHP ZIP unavailable.\n";
    exit;
}

$checks = 0;
$archives = [];
function verify($condition, $message) {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function make_archive(array $entries): string {
    global $archives;
    $filename = tempnam(sys_get_temp_dir(), 'tccm-theme-');
    $archives[] = $filename;
    $zip = new ZipArchive();
    if ($zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Test ZIP unavailable.');
    $zip->addEmptyDir('assets');
    foreach ($entries as $name => $bytes) $zip->addFromString($name, $bytes);
    if (!$zip->close()) throw new RuntimeException('Test ZIP could not be written.');
    return $filename;
}

$root = dirname(__DIR__) . '/includes/features/school-menus/assets/designer';
$theme = json_decode(file_get_contents($root . '/themes/autumn-hedgehog.json'), true);
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j6OcAAAAASUVORK5CYII=');
$asset = 'assets/apercu.png';
$embedded = 'data:image/png;base64,' . base64_encode($png);
foreach ($theme['elements'] as $index => $element) $theme['elements'][$index]['source'] = $asset;
$theme['style']['backgroundImage'] = $asset;
$theme['style']['badges']['logos']['eggs_fr'] = $asset;
$theme['style']['animation'] = ['mode' => 'leaves', 'density' => 'normal', 'speed' => 'slow', 'zone' => 'edges', 'image' => $asset, 'variant' => $asset];
$entries = ['theme.json' => json_encode($theme), $asset => $png];
$class = TheCore_Collectivity_School_Menu_Theme_Archive::class;

try {
    $imported = $class::read(make_archive($entries));
    verify(!is_wp_error($imported), 'ZIP with theme and PNG accepted.');
    verify($imported['id'] === $theme['id'] && $imported['name'] === $theme['name'], 'The original design identity is preserved for the editor.');
    verify(array_unique(array_column($imported['elements'], 'source')) === [$embedded], 'All references to the same decoration are embedded.');
    verify($imported['style']['backgroundImage'] === $embedded && $imported['style']['badges']['logos']['eggs_fr'] === $embedded, 'Background and badge images are embedded.');
    verify($imported['style']['animation']['image'] === $embedded && $imported['style']['animation']['variant'] === $embedded, 'Both animation motifs travel with the imported theme.');
    verify($imported['style']['badges']['logos']['beef_fr'] === '', 'An unchanged official badge stays configured.');
    verify(!is_wp_error($class::read(make_archive(['theme.json' => json_encode($imported)]))), 'Theme with embedded images accepted inside ZIP.');
    verify(is_wp_error($class::read(make_archive([$asset => $png]))), 'Missing theme.json rejected.');
    verify(is_wp_error($class::read(make_archive(['theme.json' => '{', $asset => $png]))), 'Unreadable JSON rejected.');
    verify(is_wp_error($class::read(make_archive(['theme.json' => json_encode($theme)]))), 'Missing referenced asset rejected.');
    verify(is_wp_error($class::read(make_archive($entries + ['notes.txt' => 'Planche de travail']))), 'Unexpected file rejected.');
    verify(is_wp_error($class::read(make_archive(array_replace($entries, [$asset => 'This is a note.'])))), 'Non-image content rejected.');
    $wrongFormat = $theme;
    $wrongFormat['elements'][0]['source'] = 'assets/apercu.webp';
    verify(is_wp_error($class::read(make_archive(array_replace($entries, ['theme.json' => json_encode($wrongFormat), 'assets/apercu.webp' => $png])))), 'File type must match the image content.');
    verify(is_wp_error($class::read(make_archive(array_replace($entries, [$asset => str_repeat(' ', TheCore_Collectivity_School_Menu_Theme_Validator::MAXIMUM_IMAGE_BYTES + 1)])))), 'Per-image size limit checked.');
    $tooMany = $entries;
    for ($index = 0; $index < 24; $index++) $tooMany['assets/visuel-' . $index . '.png'] = $png;
    verify(is_wp_error($class::read(make_archive($tooMany))), 'Maximum entry count checked.');
} finally {
    foreach ($archives as $filename) unlink($filename);
}
echo $checks . " theme archive checks passed.\n";
