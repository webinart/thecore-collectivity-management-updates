(function (factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    if (typeof window !== 'undefined') window.SVJMenuTheme = api;
})(() => {
    const format = 'thecore.school-menu-theme';
    const version = 2;
    const maximumBytes = 8 * 1024 * 1024;
    const clone = value => JSON.parse(JSON.stringify(value));
    const groups = { base: 'Bandeau et textes', days: 'Jours de la semaine', courses: 'Entrée, plat et dessert', cells: 'Cellules des plats', badges: 'Badges latéraux', markers: 'Pictogrammes des aliments', legend: 'Légendes', animation: 'Animation saisonnière', print: 'Impression' };
    const days = { monday: 'Lundi', tuesday: 'Mardi', wednesday: 'Mercredi', thursday: 'Jeudi', friday: 'Vendredi' };
    const courses = { starter: 'Entrée', main: 'Plat', dessert: 'Dessert' };
    const badgeNames = { produce_fr: 'Fruits et légumes de France', eggs_fr: 'Œufs de France', beef_fr: 'Viande bovine française', pork_fr: 'Le Porc Français' };
    const fields = [];
    const field = (path, label, group, kind, value, extra = {}) => fields.push({ path: `style.${path}`, label, group, kind, default: value, ...extra });
    const color = (path, label, group, value, extra) => field(path, label, group, 'color', value, extra);
    const number = (path, label, group, value, minimum, maximum, unit = 'px', step = 1) => field(path, label, group, 'number', value, { minimum, maximum, unit, step });
    const select = (path, label, group, value, options, extra) => field(path, label, group, 'select', value, { options, ...extra });
    const shapes = { painted: 'Contour peint', rounded: 'Arrondi', square: 'Rectangle' };
    color('ribbonColor', 'Fond du bandeau', 'base', '#267a15');
    color('ribbonText', 'Texte du bandeau', 'base', '#ffffff');
    select('ribbonShape', 'Forme du bandeau', 'base', 'painted', shapes);
    number('ribbonRadius', 'Arrondi du bandeau', 'base', 10, 0, 28);
    number('ribbonPadding', 'Espacement vertical du bandeau', 'base', 16, 8, 28);
    select('font', 'Écriture des plats', 'base', 'hand', { hand: 'Manuscrite — Patrick Hand', sans: 'Sans empattement — DM Sans' });
    select('headingFont', 'Écriture des titres', 'base', 'brush', { brush: 'Caveat Brush', sans: 'DM Sans' });
    number('fontSize', 'Taille du texte des plats', 'base', 22, 17, 24);
    color('ink', 'Couleur du texte des plats', 'base', '#152e65');
    number('lineHeight', 'Interligne des plats', 'base', 1.2, 1, 1.6, '', .05);
    number('dishSpacing', 'Séparation entre les plats', 'base', 6, 2, 14);
    color('pageBackground', 'Fond de la feuille', 'base', '#ffffff');
    number('pagePadding', 'Marge intérieure à l’écran', 'base', 36, 20, 48);
    number('indexWidth', 'Largeur de la colonne des catégories', 'base', 24, 20, 28, '%');
    field('texture', 'Texture papier', 'base', 'boolean', true);
    select('days.shape', 'Forme des en-têtes', 'days', 'painted', shapes);
    number('days.radius', 'Arrondi des en-têtes', 'days', 8, 0, 28);
    number('days.height', 'Hauteur des en-têtes sur ordinateur', 'days', 107, 90, 135);
    number('days.padding', 'Espacement vertical', 'days', 16, 10, 24);
    number('days.fontSize', 'Taille du nom du jour', 'days', 26, 22, 32);
    number('days.dateSize', 'Taille de la date', 'days', 17, 14, 20);
    number('days.dateGap', 'Espace entre le jour et la date', 'days', 2, 0, 8);
    const dayDefaults = [
        ['#ce0047', '#ff3b7b', '#ffffff', '#fff0f5', '#ff6295'],
        ['#ff8a0d', '#ffc82f', '#492500', '#fff5e5', '#ffac30'],
        ['#ffda12', '#fff559', '#493600', '#fffce5', '#dfbd16'],
        ['#8bd71b', '#b8ec3c', '#193e03', '#f4fbe5', '#87ca30'],
        ['#1bd6e4', '#70edf1', '#073b50', '#eafcfc', '#2bc9d5']
    ];
    Object.keys(days).forEach((day, index) => {
        const colors = dayDefaults[index];
        color(`days.${day}.background`, 'Fond de l’en-tête', 'days', colors[0], { scope: day });
        color(`days.${day}.secondary`, 'Seconde couleur du dégradé', 'days', colors[1], { scope: day });
        field(`days.${day}.gradient`, 'Dégradé de l’en-tête', 'days', 'boolean', true, { scope: day });
        color(`days.${day}.text`, 'Texte de l’en-tête', 'days', colors[2], { scope: day });
        color(`days.${day}.cellBackground`, 'Fond des cellules', 'days', colors[3], { scope: day });
        color(`days.${day}.border`, 'Bordure des cellules', 'days', colors[4], { scope: day });
    });
    select('courses.shape', 'Forme des catégories', 'courses', 'painted', shapes);
    number('courses.radius', 'Arrondi des catégories', 'courses', 8, 0, 28);
    number('courses.fontSize', 'Taille des libellés', 'courses', 30, 24, 36);
    number('courses.iconSize', 'Taille des pictogrammes', 'courses', 42, 28, 48);
    number('courses.padding', 'Espacement vertical', 'courses', 13, 8, 24);
    const courseColors = { starter: '#267a15', main: '#ae4800', dessert: '#9024b0' };
    Object.keys(courses).forEach(course => {
        color(`courses.${course}.background`, 'Fond de la catégorie', 'courses', courseColors[course], { scope: course });
        color(`courses.${course}.text`, 'Texte et pictogramme', 'courses', '#ffffff', { scope: course });
        select(`courses.${course}.icon`, 'Pictogramme', 'courses', 'original', { original: 'Pictogramme de l’affiche', salad: 'Saladier', utensils: 'Assiette et couverts', cake: 'Dessert' }, { scope: course });
    });
    select('cells.shape', 'Forme des cellules', 'cells', 'rounded', { square: 'Rectangle', rounded: 'Arrondi régulier', organic: 'Arrondi asymétrique' });
    number('cells.radius', 'Arrondi', 'cells', 8, 0, 24);
    number('cells.borderWidth', 'Épaisseur de bordure', 'cells', 1, 0, 3);
    number('cells.paddingX', 'Espacement horizontal', 'cells', 12, 8, 22);
    number('cells.paddingY', 'Espacement vertical', 'cells', 13, 8, 22);
    number('cells.gap', 'Espace entre les cellules', 'cells', 0, 0, 6);
    select('badges.placement', 'Emplacement des badges', 'badges', 'right', { right: 'À droite du tableau', footer: 'Sous le tableau', legend: 'Dans les repères du menu' });
    number('badges.size', 'Taille des badges', 'badges', 76, 44, 90);
    number('badges.gap', 'Espace entre les badges', 'badges', 10, 4, 18);
    select('badges.frame', 'Encadrement', 'badges', 'none', { none: 'Sans cadre', circle: 'Cercle', rounded: 'Rectangle arrondi' });
    color('badges.background', 'Fond des cadres', 'badges', '#ffffff');
    color('badges.border', 'Couleur des cadres', 'badges', '#c9d8d3');
    number('badges.padding', 'Marge à l’intérieur du cadre', 'badges', 0, 0, 10);
    Object.entries(badgeNames).forEach(([key, label]) => field(`badges.logos.${key}`, label, 'badges', 'asset', ''));
    color('markers.homemade', 'Pictogramme Fait maison', 'markers', '#db1070');
    color('markers.onsite', 'Pictogramme Préparé sur place', 'markers', '#0074d9');
    number('markers.iconSize', 'Taille des pictogrammes', 'markers', 18, 14, 22);
    number('markers.organicSize', 'Hauteur du logo AB', 'markers', 20, 16, 24);
    color('legend.preparationBackground', 'Fond Qualité et préparation', 'legend', '#f0f6e8');
    color('legend.originBackground', 'Fond Origine et fournisseur', 'legend', '#fbf6e9');
    color('legend.text', 'Texte des légendes', 'legend', '#223e43');
    color('legend.border', 'Bordure des légendes', 'legend', '#d5dfce');
    number('legend.radius', 'Arrondi des blocs', 'legend', 12, 0, 20);
    number('legend.fontSize', 'Taille du texte des repères', 'legend', 13, 11, 15);
    number('legend.padding', 'Marge intérieure des blocs', 'legend', 12, 8, 18);
    number('legend.gap', 'Espace entre les deux blocs', 'legend', 8, 4, 16);
    number('print.padding', 'Marge intérieure du papier', 'print', 10, 8, 14, 'mm');
    number('print.logoSize', 'Taille du logo municipal', 'print', 60, 44, 72);
    number('print.titleSize', 'Taille du titre Menu scolaire', 'print', 36, 28, 42);
    number('print.fontAdjustment', 'Ajustement du texte des plats', 'print', -3, -5, 0);
    field('backgroundImage', 'Image de fond de la feuille', 'base', 'asset', '', { since: 2 });
    field('backgroundOpacity', 'Intensité du fond illustré', 'base', 'number', 100, { minimum: 0, maximum: 100, unit: '%', step: 1, since: 2 });
    select('backgroundFit', 'Ajustement du fond', 'base', 'cover', { cover: 'Remplir la feuille', contain: 'Afficher toute l’image', stretch: 'Adapter à la feuille' }, { since: 2 });
    color('ribbonSecondary', 'Seconde couleur du bandeau', 'base', '#408d1d', { since: 2 });
    field('ribbonGradient', 'Dégradé du bandeau', 'base', 'boolean', true, { since: 2 });
    field('courses.shadow', 'Relief sous les catégories', 'courses', 'boolean', true, { since: 2 });
    const courseHighlights = { starter: '#408d1d', main: '#de7210', dessert: '#b337c6' };
    for (const course of Object.keys(courses)) {
        color(`courses.${course}.secondary`, 'Seconde couleur du dégradé', 'courses', courseHighlights[course], { scope: course, since: 2 });
        field(`courses.${course}.gradient`, 'Dégradé de la catégorie', 'courses', 'boolean', true, { scope: course, since: 2 });
    }
    field('cells.gradient', 'Dégradé doux dans les cellules', 'cells', 'boolean', false, { since: 2 });
    const motionField = (name, label, kind, value, extra = {}) =>
        field('animation.' + name, label, 'animation', kind, value, { since: 'animation', ...extra });
    motionField('mode', 'Mouvement', 'select', 'none', { options: {
        none: 'Aucun', snow: 'Neige douce', leaves: 'Feuilles qui tournoient', bubbles: 'Bulles ascendantes'
    } });
    motionField('density', 'Densité', 'select', 'normal', { options: { low: 'Légère', normal: 'Moyenne', high: 'Dense' } });
    motionField('speed', 'Vitesse', 'select', 'normal', { options: { slow: 'Lente', normal: 'Normale', fast: 'Rapide' } });
    motionField('zone', 'Zone animée', 'select', 'all', { options: { all: 'Tout le tableau', edges: 'Bords du tableau' } });
    motionField('image', 'Motif principal', 'asset', '');
    motionField('variant', 'Second motif (facultatif)', 'asset', '');

    const get = (object, path) => path.split('.').reduce((value, key) => value && Object.hasOwn(value, key) ? value[key] : undefined, object);
    const set = (object, path, value) => {
        if (!fields.some(setting => setting.path === path)) throw new Error('Paramètre de thème inconnu.');
        const keys = path.split('.');
        let target = object;
        for (const key of keys.slice(0, -1)) { if (!Object.hasOwn(target, key)) target[key] = {}; target = target[key]; }
        target[keys.at(-1)] = value;
    };
    const objectSchema = properties => ({ type: 'object', additionalProperties: false, properties, required: Object.keys(properties) });
    const safeSourcePattern = '^(assets/[a-z0-9][a-z0-9._-]*\\.(png|jpg|jpeg|webp)|data:image/(png|jpeg|webp);base64,[A-Za-z0-9+/]+={0,2})$';
    const sourceSchema = { type: 'string', maxLength: 2800000, pattern: safeSourcePattern };
    const nameSchema = { type: 'string', minLength: 1, maxLength: 80, pattern: '^[^<>\\u0000-\\u001f]+$' };
    const idSchema = { type: 'string', minLength: 1, maxLength: 80, pattern: '^[a-z0-9][a-z0-9_-]*$' };
    const styleSchema = objectSchema({});
    for (const setting of fields) {
        let rule;
        if (setting.kind === 'color') rule = { type: 'string', pattern: '^#[a-fA-F0-9]{6}$' };
        else if (setting.kind === 'select') rule = { type: 'string', enum: Object.keys(setting.options) };
        else if (setting.kind === 'boolean') rule = { type: 'boolean' };
        else if (setting.kind === 'asset') rule = { ...sourceSchema, pattern: `^$|${safeSourcePattern}` };
        else rule = { type: setting.step === 1 ? 'integer' : 'number', minimum: setting.minimum, maximum: setting.maximum };
        rule.title = setting.label;
        rule.default = setting.default;
        const keys = setting.path.split('.').slice(1);
        let target = styleSchema;
        for (const key of keys.slice(0, -1)) {
            if (!target.properties[key]) { target.properties[key] = objectSchema({}); target.required.push(key); }
            target = target.properties[key];
        }
        target.properties[keys.at(-1)] = rule;
        target.required.push(keys.at(-1));
    }
    // Themes saved before seasonal motion omit the entire group.
    styleSchema.required = styleSchema.required.filter(key => key !== 'animation');
    const pieceSchema = objectSchema({ id: idSchema, name: { ...nameSchema, maxLength: 60 }, source: sourceSchema, anchor: { type: 'string', enum: ['index', 'below-badges', 'ribbon', 'top-right', 'bottom', 'drape-right', 'drape-corner'] }, scale: { type: 'number', minimum: 40, maximum: 200 }, x: { type: 'number', minimum: -60, maximum: 60 }, y: { type: 'number', minimum: -60, maximum: 60 }, layer: { type: 'string', enum: ['front', 'back'] }, visible: { type: 'boolean' }, paired: { type: 'boolean' } });
    const schema = { $schema: 'https://json-schema.org/draft/2020-12/schema', title: 'The Core — thème de menu scolaire, version 2', ...objectSchema({ format: { const: format }, version: { const: version }, id: idSchema, name: nameSchema, theme: { type: 'string', enum: ['Automne', 'Hiver', 'Printemps', 'Été', 'Événements'] }, elements: { type: 'array', maxItems: 16, items: pieceSchema }, style: styleSchema }) };
    const legacySchema = clone(schema);
    delete legacySchema.properties.style.properties.animation;
    legacySchema.properties.version.const = 1;
    legacySchema.properties.style.properties.badges.properties.placement.enum = ['right', 'footer'];
    legacySchema.properties.elements.items.properties.anchor.enum = pieceSchema.properties.anchor.enum.filter(anchor => !anchor.startsWith('drape-'));
    for (const setting of fields.filter(setting => setting.since === 2)) {
        const keys = setting.path.split('.').slice(1);
        let target = legacySchema.properties.style;
        for (const key of keys.slice(0, -1)) target = target.properties[key];
        delete target.properties[keys.at(-1)];
        target.required = target.required.filter(key => key !== keys.at(-1));
    }
    const defaults = () => { const result = {}; fields.forEach(setting => set(result, setting.path, setting.default)); return result.style; };
    const inspectRule = (value, rule, path, errors) => {
        if (errors.length >= 20) return;
        if (Object.hasOwn(rule, 'const') && value !== rule.const) { errors.push(`${path} : valeur attendue ${JSON.stringify(rule.const)}.`); return; }
        if (!rule.type) return;
        const matches = rule.type === 'object' ? value !== null && typeof value === 'object' && !Array.isArray(value) : rule.type === 'array' ? Array.isArray(value) : rule.type === 'integer' ? Number.isInteger(value) : typeof value === rule.type;
        if (!matches) { errors.push(`${path} : type ${rule.type} attendu.`); return; }
        if (rule.type === 'object') {
            for (const key of Object.keys(value)) if (!Object.hasOwn(rule.properties, key)) errors.push(`${path}.${key} : paramètre inconnu.`);
            for (const key of rule.required) if (!Object.hasOwn(value, key)) errors.push(`${path}.${key} : paramètre manquant.`);
            for (const [key, child] of Object.entries(rule.properties)) if (Object.hasOwn(value, key)) inspectRule(value[key], child, `${path}.${key}`, errors);
        } else if (rule.type === 'array') {
            if (value.length > rule.maxItems) errors.push(`${path} : ${rule.maxItems} éléments maximum.`);
            else value.forEach((item, index) => inspectRule(item, rule.items, `${path}[${index}]`, errors));
        } else if (rule.type === 'string') {
            if (rule.minLength && value.length < rule.minLength || rule.maxLength && value.length > rule.maxLength) errors.push(`${path} : longueur invalide.`);
            if (rule.pattern && !new RegExp(rule.pattern).test(value)) errors.push(`${path} : format de valeur invalide.`);
            if (rule.enum && !rule.enum.includes(value)) errors.push(`${path} : choisir ${rule.enum.join(', ')}.`);
        } else if (rule.type === 'number' || rule.type === 'integer') {
            if (!Number.isFinite(value) || value < rule.minimum || value > rule.maximum) errors.push(`${path} : valeur entre ${rule.minimum} et ${rule.maximum}.`);
        }
    };
    const validate = theme => {
        const errors = [];
        inspectRule(theme, schema, 'thème', errors);
        if (!errors.length && new Set(theme.elements.map(piece => piece.id)).size !== theme.elements.length) errors.push('thème.elements : les identifiants doivent être uniques.');
        return errors;
    };
    const parse = input => {
        const serialized = typeof input === 'string' ? input : JSON.stringify(input);
        if (!serialized || new TextEncoder().encode(serialized).length > maximumBytes) throw new Error('Le thème doit être un fichier JSON de moins de 8 Mo.');
        let result;
        try { result = JSON.parse(serialized); } catch (error) { throw new Error('Le fichier ne contient pas un JSON valide.'); }
        if (result?.format === format && result.version === 1) {
            const legacyErrors = [];
            inspectRule(result, legacySchema, 'thème v1', legacyErrors);
            if (legacyErrors.length) throw new Error(legacyErrors.slice(0, 5).join('\n'));
            const upgraded = { ...result, version, style: defaults() };
            for (const setting of fields.filter(setting => !setting.since)) set(upgraded, setting.path, get(result, setting.path));
            upgraded.style.ribbonGradient = false;
            upgraded.style.courses.shadow = false;
            for (const course of Object.keys(courses)) upgraded.style.courses[course].gradient = false;
            result = upgraded;
        }
        const errors = validate(result);
        if (errors.length) throw new Error(errors.slice(0, 5).join('\n'));
        if (!Object.hasOwn(result.style, 'animation')) result.style.animation = defaults().animation;
        return result;
    };
    const fromEditor = model => parse({ format, version, id: model.id, name: model.name, theme: model.theme, elements: model.elements, style: model.style });
    const toEditor = theme => ({ ...parse(theme), status: 'draft', assignment: 'communication' });
    const migrate = model => {
        const migrated = { format, version, id: model.id, name: model.name, theme: model.theme, elements: clone(model.elements), style: defaults() };
        for (const setting of fields) { const value = get(model, setting.path); if (value !== undefined) set(migrated, setting.path, value); }
        return { ...toEditor(migrated), status: model.status === 'ready' ? 'ready' : 'draft', assignment: model.assignment === 'approved' ? 'approved' : 'communication' };
    };
    const luminance = colorValue => {
        const components = colorValue.slice(1).match(/../g).map(component => parseInt(component, 16) / 255).map(component => component <= .04045 ? component / 12.92 : ((component + .055) / 1.055) ** 2.4);
        return components[0] * .2126 + components[1] * .7152 + components[2] * .0722;
    };
    const contrast = (foreground, background) => { const values = [luminance(foreground), luminance(background)].sort((first, second) => second - first); return (values[0] + .05) / (values[1] + .05); };
    const warnings = theme => {
        const messages = [];
        const style = theme.style;
        if (contrast(style.ribbonText, style.ribbonColor) < 3 || style.ribbonGradient && contrast(style.ribbonText, style.ribbonSecondary) < 3) messages.push('Le contraste du bandeau de semaine est insuffisant.');
        for (const day of Object.keys(days)) {
            const colors = style.days[day];
            if (contrast(colors.text, colors.background) < 3 || colors.gradient && contrast(colors.text, colors.secondary) < 3) messages.push(`${days[day]} : vérifiez le contraste de l’en-tête.`);
            if (contrast(style.ink, colors.cellBackground) < 4.5) messages.push(`${days[day]} : le texte des plats manque de contraste.`);
        }
        for (const course of Object.keys(courses)) if (contrast(style.courses[course].text, style.courses[course].background) < 3 || style.courses[course].gradient && contrast(style.courses[course].text, style.courses[course].secondary) < 3) messages.push(`${courses[course]} : le libellé manque de contraste.`);
        for (const background of [style.legend.preparationBackground, style.legend.originBackground]) if (contrast(style.legend.text, background) < 4.5) messages.push('Vérifiez le contraste des textes des légendes.');
        return [...new Set(messages)];
    };
    const autumn = () => parse({ format, version, id: 'autumn-hedgehog', name: 'Automne — Hérisson', theme: 'Automne', style: defaults(), elements: [
        { id: 'character', name: 'Personnage', source: 'assets/autumn-hedgehog.png', anchor: 'index', scale: 100, x: 0, y: 0, layer: 'front', visible: true, paired: false },
        { id: 'sprigs', name: 'Feuilles du bandeau', source: 'assets/green-sprig.png', anchor: 'ribbon', scale: 100, x: 0, y: 0, layer: 'front', visible: true, paired: true },
        { id: 'leaves', name: 'Feuilles à droite', source: 'assets/autumn-leaves.png', anchor: 'below-badges', scale: 140, x: 0, y: 0, layer: 'front', visible: true, paired: false }
    ] });
    const winter = () => {
        const theme = { format, version, id: 'winter-mist', name: 'Hiver — Brume', theme: 'Hiver', elements: [], style: defaults() };
        Object.assign(theme.style, { ribbonColor: '#174e64', ribbonShape: 'rounded', ribbonRadius: 14, ribbonText: '#ffffff', ribbonGradient: false, font: 'sans', headingFont: 'sans', fontSize: 19, ink: '#193c50', texture: false, pageBackground: '#ffffff', lineHeight: 1.3, dishSpacing: 8 });
        Object.assign(theme.style.days, { shape: 'rounded', radius: 12, fontSize: 23 });
        const palette = [['#deedf6', '#f7fbfe', '#b8d2e2'], ['#dcefe9', '#f4faf7', '#b5d6c9'], ['#e8e3f4', '#faf8fe', '#cfc3e4'], ['#f4ead8', '#fdfaf3', '#decda9'], ['#e2e9f2', '#f7f9fc', '#bdcde0']];
        Object.keys(days).forEach((day, index) => Object.assign(theme.style.days[day], { background: palette[index][0], secondary: palette[index][0], gradient: false, text: '#193c50', cellBackground: palette[index][1], border: palette[index][2] }));
        Object.assign(theme.style.courses, { shape: 'rounded', radius: 12, fontSize: 27, iconSize: 34 });
        ['#22546c', '#356d88', '#5c527e'].forEach((background, index) => { Object.assign(theme.style.courses[Object.keys(courses)[index]], { background, gradient: false }); });
        Object.assign(theme.style.cells, { radius: 12, gap: 3, paddingX: 12, paddingY: 14 });
        Object.assign(theme.style.badges, { placement: 'footer', size: 56, frame: 'rounded', gap: 14, padding: 5, background: '#ffffff', border: '#d7e2e7' });
        Object.assign(theme.style.legend, { preparationBackground: '#f2f7f8', originBackground: '#f7f6fa', text: '#294451', border: '#d3e0e6', radius: 10 });
        return parse(theme);
    };
    const winterGarlands = () => {
        const theme = winter();
        Object.assign(theme, { id: 'winter-garlands', name: 'Hiver — Guirlandes', elements: [
            { id: 'garland', name: 'Guirlande enneigée', source: 'assets/winter-garland.png', anchor: 'index', scale: 115, x: 0, y: 0, layer: 'front', visible: true, paired: false },
            { id: 'snowflakes', name: 'Flocons du bandeau', source: 'assets/winter-snowflakes.png', anchor: 'ribbon', scale: 110, x: 0, y: 0, layer: 'front', visible: true, paired: true },
            { id: 'snow', name: 'Neige à droite', source: 'assets/winter-snowflakes.png', anchor: 'below-badges', scale: 180, x: 0, y: 0, layer: 'front', visible: true, paired: false }
        ] });
        Object.assign(theme.style, { ribbonColor: '#155d58', ribbonText: '#ffffff', ribbonShape: 'painted', ribbonRadius: 12, ribbonPadding: 16, font: 'hand', headingFont: 'brush', fontSize: 22, ink: '#163b55', texture: true, pageBackground: '#fcfefd', lineHeight: 1.15, dishSpacing: 6 });
        Object.assign(theme.style.days, { shape: 'painted', radius: 10, fontSize: 27 });
        const palette = [
            ['#bce7f4', '#e1f5fc', '#f0faff', '#91c8df'],
            ['#f8d7db', '#fdecee', '#fff5f5', '#eab7c2'],
            ['#f8dfa0', '#fff0c8', '#fffbee', '#e4c47b'],
            ['#c9e7db', '#e1f3e9', '#f1faf4', '#a4d2b7'],
            ['#d8ddf6', '#eef0ff', '#f7f7ff', '#bdc5eb']
        ];
        Object.keys(days).forEach((day, index) => Object.assign(theme.style.days[day], { background: palette[index][0], secondary: palette[index][1], gradient: true, text: '#153e53', cellBackground: palette[index][2], border: palette[index][3] }));
        Object.assign(theme.style.courses, { shape: 'painted', radius: 10, fontSize: 30, iconSize: 42 });
        ['#17685d', '#a5264b', '#255578'].forEach((background, index) => { theme.style.courses[Object.keys(courses)[index]].background = background; });
        Object.assign(theme.style.cells, { radius: 10, gap: 0, paddingX: 12, paddingY: 13 });
        Object.assign(theme.style.badges, { placement: 'right', size: 72, frame: 'none', gap: 10, padding: 0 });
        Object.assign(theme.style.legend, { preparationBackground: '#edf7f2', originBackground: '#fdf6e7', text: '#25454b', border: '#c9ddd6', radius: 12 });
        Object.assign(theme.style.print, { padding: 8, fontAdjustment: -4, logoSize: 52 });
        return parse(theme);
    };
    const autumnSquirrel = () => {
        const theme = autumn();
        Object.assign(theme, { id: 'autumn-squirrel', name: 'Automne — Écureuil' });
        theme.elements[0].source = 'assets/autumn-squirrel.png';
        theme.elements[0].name = 'Écureuil de l’affiche';
        Object.assign(theme.style, { backgroundImage: '', pagePadding: 48 });
        theme.style.cells.gradient = true;
        return parse(theme);
    };
    const winterEnchanted = () => {
        const theme = winterGarlands();
        Object.assign(theme, { id: 'winter-enchanted', name: 'Hiver — Guirlandes libres', elements: [
            { id: 'fox', name: 'Renardeau emmitouflé', source: 'assets/winter-fox.png', anchor: 'index', scale: 125, x: 0, y: 0, layer: 'front', visible: true, paired: false },
            { id: 'hanging-lights', name: 'Guirlande découpée du rebord droit', source: 'assets/winter-table-garland-cutout-v3.png', anchor: 'drape-right', scale: 100, x: 0, y: 0, layer: 'front', visible: true, paired: false },
            { id: 'corner-lights', name: 'Guirlande découpée de l’angle gauche', source: 'assets/winter-table-corner-cutout-v3.png', anchor: 'drape-corner', scale: 120, x: 0, y: 0, layer: 'front', visible: true, paired: false },
            { id: 'snowflakes', name: 'Flocons du bandeau', source: 'assets/winter-snowflakes.png', anchor: 'ribbon', scale: 100, x: 0, y: 0, layer: 'front', visible: true, paired: true }
        ] });
        Object.assign(theme.style, { backgroundImage: '', pagePadding: 48, ribbonColor: '#084d57', ribbonSecondary: '#237b8c', ribbonGradient: true, ink: '#123753', lineHeight: 1.15 });
        Object.assign(theme.style.courses, { shadow: true });
        const categoryColors = [['#075649', '#178c69'], ['#990f40', '#d52d60'], ['#143f85', '#2c7dba']];
        Object.keys(courses).forEach((course, index) => Object.assign(theme.style.courses[course], { background: categoryColors[index][0], secondary: categoryColors[index][1], gradient: true }));
        const palette = [
            ['#75c7f2', '#d6f3ff', '#073457', '#e9f7ff', '#5bafd8'],
            ['#f59bb3', '#ffe2e8', '#5b1638', '#fff0f4', '#dc7e9d'],
            ['#f3c246', '#fff0af', '#58300c', '#fff8dc', '#d6a735'],
            ['#7ccea7', '#d4f6e2', '#104a3a', '#e8f8ef', '#5db88f'],
            ['#b5a3ec', '#ede5ff', '#35205b', '#f1eaff', '#9b83d6']
        ];
        Object.keys(days).forEach((day, index) => Object.assign(theme.style.days[day], { background: palette[index][0], secondary: palette[index][1], text: palette[index][2], cellBackground: palette[index][3], border: palette[index][4], gradient: true }));
        theme.style.cells.gradient = true;
        Object.assign(theme.style.badges, { placement: 'legend', size: 52, frame: 'none', gap: 10 });
        Object.assign(theme.style.print, { padding: 8, fontAdjustment: -5, logoSize: 44, titleSize: 32 });
        return parse(theme);
    };
    return Object.freeze({ format, version, maximumBytes, groups, days, courses, badgeNames, fields, schema, defaults, get, set, validate, parse, fromEditor, toEditor, migrate, warnings, contrast, autumn, autumnSquirrel, winter, winterGarlands, winterEnchanted });
});
