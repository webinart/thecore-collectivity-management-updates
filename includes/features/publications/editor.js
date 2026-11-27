(function (wp, config) {
    'use strict';
    if (!config) return;
    const h = wp.element.createElement;
    const { Button, TextControl, TextareaControl, SelectControl, Notice } = wp.components;
    const { RichText, MediaUpload, MediaUploadCheck, useBlockProps } = wp.blockEditor;
    const designs = Object.entries(config.designs);
    const imageValue = media => ({
        id: media.id, alt: media.alt || '',
        caption: typeof media.caption === 'string' ? media.caption : media.caption?.raw || '',
        thumbnail: media.sizes?.thumbnail?.url || media.url || ''
    });

    function picker(label, props) {
        return h(MediaUploadCheck, null, h(MediaUpload, {
            ...props, render: ({ open }) => h(Button, { variant: 'secondary', onClick: open }, label)
        }));
    }

    function Field({ type, value, change }) {
        const update = field => val => change({ ...value, [field]: val });
        if (type === 'text') return h('div', null,
            h(TextControl, { label: 'Intertitre (facultatif)', value: value.heading || '', onChange: update('heading') }),
            h(RichText, { tagName: 'div', className: 'tccm-pub-prose', 'aria-label': 'Texte de la section',
                placeholder: 'Rédigez votre texte…', value: value.body || '', onChange: update('body'),
                allowedFormats: ['core/bold', 'core/italic', 'core/link'] }),
            h('details', { open: value.list ? true : undefined }, h('summary', null, 'Liste à puces'),
                h(TextareaControl, { label: 'Un élément par ligne', value: value.list || '', onChange: update('list') })));
        if (type === 'video') return h('div', null, h(TextControl, { label: 'Lien YouTube', type: 'url',
            placeholder: 'https://www.youtube.com/watch?v=…', value: value.url || '', onChange: update('url') }),
            value.poster?.thumbnail ? h('img', { className: 'tccm-pub-thumb', src: value.poster.thumbnail, alt: '' }) : null,
            picker(value.poster?.id ? 'Changer l’image d’aperçu' : 'Ajouter une image d’aperçu', { allowedTypes: ['image'],
                value: value.poster?.id, onSelect: media => update('poster')(imageValue(media)) }),
            value.poster?.id ? h(Button, { onClick: () => update('poster')(null) }, 'Retirer l’image') : null);
        if (type === 'file') return h('div', null,
            picker(value.id ? 'Remplacer le document' : 'Choisir un document', { value: value.id,
                onSelect: media => change({ id: media.id, label: value.label || media.title || media.filename }) }),
            value.id ? h(TextControl, { label: 'Texte du lien', value: value.label || '', onChange: update('label') }) : null);
        if (type === 'image') return h('div', null,
            value.thumbnail ? h('img', { className: 'tccm-pub-thumb', src: value.thumbnail, alt: '' }) : null,
            picker(value.id ? 'Remplacer la photo' : 'Choisir une photo', { allowedTypes: ['image'], value: value.id,
                onSelect: media => change(imageValue(media)) }),
            value.id ? h('div', null,
                h(TextControl, { label: 'Texte alternatif', value: value.alt || '', onChange: update('alt') }),
                h(TextControl, { label: 'Légende', value: value.caption || '', onChange: update('caption') })) : null);
        if (type === 'gallery') {
            const images = value.images || [];
            const changeImage = (index, field, text) => update('images')(images.map((img, i) => i === index ? { ...img, [field]: text } : img));
            return h('div', null, picker(images.length ? 'Organiser les photos' : 'Choisir les photos', {
                allowedTypes: ['image'], multiple: true, gallery: true, value: images.map(img => img.id),
                onSelect: selection => update('images')(selection.map(media => images.find(img => img.id === media.id) || imageValue(media)))
            }), images.map((img, i) => h('div', { key: img.id, className: 'tccm-pub-gallery-item' },
                h('img', { src: img.thumbnail, alt: '', className: 'tccm-pub-thumb' }),
                h(TextControl, { label: 'Texte alternatif', value: img.alt || '', onChange: text => changeImage(i, 'alt', text) }),
                h(TextControl, { label: 'Légende', value: img.caption || '', onChange: text => changeImage(i, 'caption', text) }))));
        }
        return null;
    }

    wp.blocks.registerBlockType('tccm/section', {
        apiVersion: 3, title: 'Section de publication', icon: 'layout', category: 'text',
        attributes: { design: { type: 'object' }, values: { type: 'object', default: {} } },
        supports: { html: false, reusable: false, lock: false, customClassName: false },
        variations: designs.map(([key, design]) => ({
            name: key, title: design.name, attributes: { design }, scope: ['inserter'],
            isActive: attrs => JSON.stringify(attrs.design) === JSON.stringify(design)
        })),
        edit: ({ attributes, setAttributes }) => {
            const props = useBlockProps({ className: 'tccm-pub-edit' });
            const design = attributes.design;
            if (!design) return h('div', props, config.assemble ? h(SelectControl, {
                label: 'Choisir une section', value: '', options: [{ label: 'Sélectionner…', value: '' },
                    ...designs.map(([value, d]) => ({ value, label: d.name }))],
                onChange: key => key && setAttributes({ design: config.designs[key] })
            }) : h(Notice, { status: 'warning', isDismissible: false }, 'Section indisponible.'));
            if (design.component) {
                const fields = Object.entries(config.fields?.[design.component] || {});
                const change = (key, value) => setAttributes({ values: { ...attributes.values, [key]: value } });
                return h('section', props, h('h3', null, design.name), fields.map(([key, field]) => {
                    if (field.type === 'image') return h('div', { key }, picker(field.label, {
                        allowedTypes: ['image'], value: attributes.values?.[key], onSelect: image => change(key, image.id)
                    }));
                    return h(TextareaControl, { key, label: field.label, value: attributes.values?.[key] ?? field.default,
                        onChange: value => change(key, value) });
                }), !fields.length && h('p', null, 'Cette section reprend le contenu du composant.'));
            }
            return h('section', props, h('h3', null, design.name),
                design.parts.map((type, index) => h(Field, { key: index, type, value: attributes.values?.[index] || {},
                    change: value => setAttributes({ values: { ...attributes.values, [index]: value } }) })));
        },
        save: () => null
    });
})(window.wp, window.tccmPublications);
