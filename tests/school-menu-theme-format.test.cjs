const assert = require('node:assert/strict');
const fs = require('node:fs');
const format = require('../includes/features/school-menus/assets/illustrated/modeles-menus/theme-format.js');
const clone = value => JSON.parse(JSON.stringify(value));
let checks = 0;
const test = (name, run) => { run(); checks += 1; };
const winter = format.winter();
const rejects = change => { const theme = clone(winter); change(theme); assert.throws(() => format.parse(theme)); };
test('complete_theme_roundtrip', () => assert.deepEqual(format.parse(JSON.stringify(winter)), winter));
test('generated_schema_matches_registry', () => assert.deepEqual(JSON.parse(fs.readFileSync(`${__dirname}/../docs/school-menus/theme-format.schema.json`)), format.schema));
test('generated_theme_is_actual_valid_example', () => assert.deepEqual(format.parse(fs.readFileSync(`${__dirname}/../docs/school-menus/themes/winter-mist.json`, 'utf8')), winter));
test('winter_contrast_passes', () => assert.deepEqual(format.warnings(winter), []));
test('default_style_has_every_field', () => assert.equal(format.fields.filter(setting => format.get({ style: format.defaults() }, setting.path) !== undefined).length, 112));
for (const setting of format.fields) {
    test(`valid_${setting.path}`, () => {
        assert.notEqual(format.get(winter, setting.path), undefined);
        assert.equal(format.validate(winter).length, 0);
    });
    test(`invalid_${setting.path}`, () => rejects(theme => format.set(theme, setting.path, setting.kind === 'number' ? setting.maximum + 1 : setting.kind === 'boolean' ? 'true' : setting.kind === 'color' ? 'red' : setting.kind === 'asset' ? 'javascript:alert(1)' : 'unknown-value')));
    test(`missing_${setting.path}`, () => rejects(theme => { const keys = setting.path.split('.'); delete format.get(theme, keys.slice(0, -1).join('.'))[keys.at(-1)]; }));
}
for (const source of ['https://example.org/picture.png', 'assets/../../bad.png', 'assets/code.svg', 'data:image/svg+xml;base64,PHN2Zz4=', 'assets/subfolder/picture.png', 'javascript:alert(1)']) {
    test(`unsafe_source_${source}`, () => rejects(theme => { theme.elements = [{ id: 'sample', name: 'Décor', source, anchor: 'index', scale: 100, x: 0, y: 0, layer: 'front', visible: true, paired: false }]; }));
}
test('unknown_root_refused', () => rejects(theme => { theme.content = 'New meal'; }));
test('unknown_nested_refused', () => rejects(theme => { theme.style.days.monday.css = 'background:red'; }));
test('unknown_version_refused', () => rejects(theme => { theme.version = 3; }));
test('unknown_format_refused', () => rejects(theme => { theme.format = 'other'; }));
test('missing_style_not_silently_filled', () => rejects(theme => { delete theme.style; }));
test('wrong_numeric_type_refused', () => rejects(theme => { theme.style.fontSize = '20'; }));
test('fractional_integer_refused', () => rejects(theme => { theme.style.fontSize = 20.5; }));
test('nan_refused', () => assert.ok(format.validate({ ...winter, style: { ...winter.style, fontSize: NaN } }).length));
test('prototype_key_refused', () => assert.throws(() => format.parse(JSON.stringify(winter).replace('"style":{', '"style":{"__proto__":{},'))));
test('malformed_json_refused', () => assert.throws(() => format.parse('{ broken')));
test('oversize_json_refused', () => assert.throws(() => format.parse(' '.repeat(format.maximumBytes + 1))));
test('names_no_markup', () => rejects(theme => { theme.name = '<script>'; }));
test('duplicate_piece_ids_refused', () => rejects(theme => {
    const piece = { id: 'same', name: 'Décor', source: 'assets/green-sprig.png', anchor: 'index', scale: 100, x: 0, y: 0, layer: 'front', visible: true, paired: false };
    theme.elements = [piece, clone(piece)];
}));
test('piece_count_limited', () => rejects(theme => { theme.elements = new Array(17).fill({}); }));
test('workflow_never_exported', () => { const model = format.toEditor(winter); model.status = 'ready'; model.assignment = 'approved'; assert.deepEqual(format.fromEditor(model), winter); });
test('import_is_draft_communication', () => { const model = format.toEditor(winter); assert.equal(model.status, 'draft'); assert.equal(model.assignment, 'communication'); });
test('workflow_in_theme_refused', () => rejects(theme => { theme.status = 'ready'; }));
test('old_local_model_migrates', () => {
    const legacy = { id: 'legacy', name: 'Ancien modèle', theme: 'Automne', elements: [], style: { ribbonColor: '#075b61', font: 'sans', fontSize: 19, texture: false }, status: 'ready', assignment: 'approved' };
    const model = format.migrate(legacy);
    assert.equal(model.style.ribbonColor, legacy.style.ribbonColor);
    assert.equal(model.style.days.monday.background, format.defaults().days.monday.background);
    assert.equal(model.status, 'ready'); assert.equal(model.assignment, 'approved');
    assert.deepEqual(format.validate(format.fromEditor(model)), []);
});
test('low_contrast_reported', () => { const theme = clone(winter); theme.style.ink = '#ffffff'; assert.ok(format.warnings(theme).length >= 5); });
test('contrast_black_white_21', () => assert.equal(format.contrast('#ffffff', '#000000'), 21));
test('setter_rejects_unknown_path', () => { assert.throws(() => format.set(winter, 'style.__proto__.polluted', true)); assert.equal({}.polluted, undefined); });
test('original_theme_has_original_artwork_and_palette', () => {
    const original = format.autumn();
    assert.equal(original.name, 'Automne — Hérisson'); assert.equal(original.style.font, 'hand');
    assert.deepEqual(original.elements.map(piece => piece.source), ['assets/autumn-hedgehog.png', 'assets/green-sprig.png', 'assets/autumn-leaves.png']);
    assert.deepEqual(original.style, format.defaults());
    assert.deepEqual(format.parse(fs.readFileSync(`${__dirname}/../docs/school-menus/themes/autumn-hedgehog.json`, 'utf8')), original);
});
test('new_winter_theme_uses_existing_contract_and_assets', () => {
    const theme = format.winterGarlands();
    assert.deepEqual(format.validate(theme), []); assert.equal(theme.version, 2);
    assert.equal(theme.elements.length, 3); assert.equal(theme.style.badges.placement, 'right');
    for (const piece of theme.elements) assert.ok(fs.existsSync(`${__dirname}/../docs/school-menus/${piece.source}`));
    assert.deepEqual(format.parse(fs.readFileSync(`${__dirname}/../docs/school-menus/themes/winter-garlands.json`, 'utf8')), theme);
});
test('winter_garlands_contrast_passes', () => assert.deepEqual(format.warnings(format.winterGarlands()), []));
test('winter_factory_does_not_mutate_other_presets', () => { const before = JSON.stringify(format.winter()); format.winterGarlands(); assert.equal(JSON.stringify(format.winter()), before); assert.deepEqual(format.autumn().style, format.defaults()); });
const legacyTheme = JSON.parse(fs.readFileSync(`${__dirname}/fixtures/school-menu-theme-v1.json`, 'utf8'));
test('complete_v1_theme_upgraded_with_no_new_artwork', () => {
    const upgraded = format.parse(legacyTheme);
    assert.equal(upgraded.version, 2); assert.equal(upgraded.style.backgroundImage, '');
    assert.equal(upgraded.style.ribbonGradient, false); assert.equal(upgraded.style.courses.main.gradient, false);
    for (const setting of format.fields.filter(setting => !setting.since)) assert.deepEqual(format.get(upgraded, setting.path), format.get(legacyTheme, setting.path));
    assert.equal(legacyTheme.version, 1);
});
test('v1_missing_field_not_silently_filled', () => { const invalid = clone(legacyTheme); delete invalid.style.ink; assert.throws(() => format.parse(invalid)); });
test('v1_unknown_field_refused', () => { const invalid = clone(legacyTheme); invalid.style.backgroundImage = 'assets/fake.png'; assert.throws(() => format.parse(invalid)); });
test('v1_cannot_use_v2_drape_anchor', () => { const invalid = clone(legacyTheme); invalid.elements = [format.winterEnchanted().elements[1]]; assert.throws(() => format.parse(invalid)); });
test('v1_cannot_use_legend_badge_placement', () => { const invalid = clone(legacyTheme); invalid.style.badges.placement = 'legend'; assert.throws(() => format.parse(invalid)); });
test('new_visual_templates_valid_and_contrasted', () => {
    for (const theme of [format.autumnSquirrel(), format.winterEnchanted()]) {
        assert.deepEqual(format.validate(theme), []); assert.deepEqual(format.warnings(theme), []);
        assert.equal(theme.style.backgroundImage, '');
        for (const piece of theme.elements) assert.ok(fs.existsSync(`${__dirname}/../docs/school-menus/${piece.source}`));
    }
});
test('new_winter_is_not_a_rectangular_frame', () => {
    const theme = format.winterEnchanted();
    assert.ok(theme.elements.some(piece => piece.anchor === 'drape-right' && piece.layer === 'front' && piece.source.includes('cutout')));
    assert.ok(theme.elements.some(piece => piece.anchor === 'drape-corner' && piece.layer === 'front'));
    assert.ok(theme.elements.every(piece => !piece.anchor.includes('frame') && !piece.source.includes('frame')));
});
test('background_source_restrictions_apply', () => rejects(theme => { theme.style.backgroundImage = 'https://example.org/picture.png'; }));
test('gradient_contrast_checks_second_color', () => { const theme = format.winterEnchanted(); theme.style.courses.main.secondary = '#ffffff'; assert.ok(format.warnings(theme).some(message => message.startsWith('Plat'))); });
const path = require('node:path');
const docs = path.resolve(__dirname, '../docs/school-menus');
for (const filename of fs.readdirSync(path.join(docs, 'themes'))) {
    test(`portable_example_${filename}`, () => {
        const theme = format.parse(fs.readFileSync(path.join(docs, 'themes', filename), 'utf8'));
        assert.deepEqual(format.warnings(theme), []);
        for (const element of theme.elements) {
            assert.ok(fs.statSync(path.join(docs, element.source)).isFile(), element.source);
        }
    });
}
for (const filename of ['THEME-GUIDE.md', 'PDF-SERVICE.md', 'ASSETS.md']) {
    test(`documentation_links_${filename}`, () => {
        const content = fs.readFileSync(path.join(docs, filename), 'utf8');
        for (const match of content.matchAll(/\]\(([^)#]+)(?:#[^)]*)?\)/g)) {
            if (!/^https?:/.test(match[1])) assert.ok(fs.existsSync(path.resolve(docs, match[1])), match[1]);
        }
        assert.ok(!content.includes('/home/mabe6153/'));
        assert.ok(!content.includes('temp/saint-vincent-sur-jard/'));
    });
}
console.log(`${checks} theme format checks passed.`);
