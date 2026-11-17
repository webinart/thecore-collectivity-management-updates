(() => {
    const images = new Map();
    const results = new Map();
    const pending = new Map();
    let revision = 0;
    const imageFor = source => {
        if (!images.has(source)) images.set(source, (async () => {
            const image = new Image(); image.src = source; await image.decode(); return image;
        })());
        return images.get(source);
    };
    const length = (value, size) => (parseFloat(value) || 0) * (value?.endsWith('%') ? size / 100 : 1);
    const expand = values => [values[0], values[1] ?? values[0], values[2] ?? values[0], values[3] ?? values[1] ?? values[0]];
    const boundsOf = node => {
        const bounds = node.getBoundingClientRect();
        return { left: bounds.left, top: bounds.top, width: bounds.width, height: bounds.height };
    };
    const intersects = (first, second) => first.left < second.left + second.width && first.left + first.width > second.left && first.top < second.top + second.height && first.top + first.height > second.top;
    const imageUrl = value => value.match(/url\(["']?([^"')]+)["']?\)/)?.[1];
    const readCard = node => {
        const bounds = boundsOf(node);
        const before = getComputedStyle(node, '::before');
        const borderSource = imageUrl(before.getPropertyValue('-webkit-mask-box-image-source'));
        const maskSource = borderSource || imageUrl(before.maskImage);
        const painted = before.content !== 'none' && Boolean(maskSource);
        const style = painted ? before : getComputedStyle(node);
        return {
            background: painted ? {
                left: bounds.left + length(before.left, bounds.width),
                top: bounds.top + length(before.top, bounds.height),
                width: bounds.width - length(before.left, bounds.width) - length(before.right, bounds.width),
                height: bounds.height - length(before.top, bounds.height) - length(before.bottom, bounds.height)
            } : bounds,
            radii: ['TopLeft', 'TopRight', 'BottomRight', 'BottomLeft'].map(corner => style[`border${corner}Radius`]),
            maskSource: painted ? maskSource : null,
            sliced: painted && Boolean(borderSource),
            slices: before.getPropertyValue('-webkit-mask-box-image-slice').replace(/\s*fill\s*/, '').trim().split(/\s+/),
            borders: before.getPropertyValue('-webkit-mask-box-image-width').trim().split(/\s+/)
        };
    };
    const canvasFor = bounds => {
        const canvas = document.createElement('canvas');
        canvas.width = Math.ceil(bounds.width * 2); canvas.height = Math.ceil(bounds.height * 2);
        const context = canvas.getContext('2d');
        context.scale(canvas.width / bounds.width, canvas.height / bounds.height);
        return { canvas, context };
    };
    const drawSlices = (context, image, card, origin) => {
        const slices = expand(card.slices);
        const borders = expand(card.borders);
        const sourceColumns = [0, length(slices[3], image.naturalWidth), image.naturalWidth - length(slices[1], image.naturalWidth), image.naturalWidth];
        const sourceRows = [0, length(slices[0], image.naturalHeight), image.naturalHeight - length(slices[2], image.naturalHeight), image.naturalHeight];
        const targetColumns = [0, length(borders[3], card.background.width), card.background.width - length(borders[1], card.background.width), card.background.width];
        const targetRows = [0, length(borders[0], card.background.height), card.background.height - length(borders[2], card.background.height), card.background.height];
        for (let row = 0; row < 3; row += 1) for (let column = 0; column < 3; column += 1) context.drawImage(image,
            sourceColumns[column], sourceRows[row], sourceColumns[column + 1] - sourceColumns[column], sourceRows[row + 1] - sourceRows[row],
            card.background.left - origin.left + targetColumns[column], card.background.top - origin.top + targetRows[row], targetColumns[column + 1] - targetColumns[column], targetRows[row + 1] - targetRows[row]);
    };
    const protectedRegions = root => {
        const regions = [...root.querySelectorAll('.tccms-marker,.tccms-course-icon')].map(boundsOf);
        for (const node of root.querySelectorAll('.tccms-dish-name,.tccms-day-name,.tccms-day-head time,.tccms-course-label,.tccms-mobile-heading h3,.tccms-mobile-course dt,.tccms-week-heading h2,.tccms-legend-heading h3')) {
            const walker = document.createTreeWalker(node, NodeFilter.SHOW_TEXT);
            let text;
            while ((text = walker.nextNode())) {
                if (!text.textContent.trim()) continue;
                const range = document.createRange(); range.selectNodeContents(text);
                for (const bounds of range.getClientRects()) regions.push({ left: bounds.left, top: bounds.top, width: bounds.width, height: bounds.height });
            }
        }
        return regions.filter(bounds => bounds.width && bounds.height);
    };
    const classifyLeaves = (pixels, width, height, protectedPixels, foregroundBands) => {
        const labels = new Uint32Array(width * height);
        const queue = new Uint32Array(width * height);
        const foreground = [false];
        for (let origin = 0; origin < labels.length; origin += 1) {
            if (labels[origin] || pixels[origin * 4 + 3] < 24) continue;
            const component = foreground.length;
            let blocked = false;
            let count = 1;
            let cursor = 0;
            let totalRow = 0;
            queue[0] = origin; labels[origin] = component;
            while (cursor < count) {
                const index = queue[cursor++];
                if (protectedPixels[index] && pixels[index * 4 + 3] > 96) blocked = true;
                const column = index % width;
                const row = Math.floor(index / width);
                totalRow += row;
                for (let neighborRow = Math.max(0, row - 1); neighborRow <= Math.min(height - 1, row + 1); neighborRow += 1) for (let neighborColumn = Math.max(0, column - 1); neighborColumn <= Math.min(width - 1, column + 1); neighborColumn += 1) {
                    const neighbor = neighborRow * width + neighborColumn;
                    if (labels[neighbor] || pixels[neighbor * 4 + 3] < 24) continue;
                    labels[neighbor] = component; queue[count++] = neighbor;
                }
            }
            const position = totalRow / count / height;
            const inFront = !foregroundBands || foregroundBands.some(([start, end]) => position >= start && position <= end);
            foreground.push(!blocked && count > 12 && inFront);
        }
        return { labels, foreground };
    };
    const render = async scene => {
        const { canvas, context } = canvasFor(scene.bounds);
        const source = await imageFor(scene.source);
        const crop = scene.crop || { left: 0, width: 1 };
        context.drawImage(source, source.naturalWidth * crop.left, 0, source.naturalWidth * crop.width, source.naturalHeight, 0, 0, scene.bounds.width, scene.bounds.height);
        const mask = canvasFor(scene.bounds);
        for (const card of scene.cards) {
            const corners = card.radii.map(value => {
                const parts = value.split(/\s+/);
                return { x: length(parts[0], card.background.width), y: length(parts[1] ?? parts[0], card.background.height) };
            });
            mask.context.save(); mask.context.beginPath();
            mask.context.roundRect(card.background.left - scene.bounds.left, card.background.top - scene.bounds.top, card.background.width, card.background.height, corners);
            mask.context.clip();
            if (card.maskSource) {
                const image = await imageFor(card.maskSource);
                if (card.sliced) drawSlices(mask.context, image, card, scene.bounds);
                else mask.context.drawImage(image, card.background.left - scene.bounds.left, card.background.top - scene.bounds.top, card.background.width, card.background.height);
            } else { mask.context.fillStyle = '#fff'; mask.context.fill(); }
            mask.context.restore();
        }
        const pixels = context.getImageData(0, 0, canvas.width, canvas.height);
        const coverage = mask.context.getImageData(0, 0, canvas.width, canvas.height).data;
        const protectedPixels = new Uint8Array(canvas.width * canvas.height);
        const ratioX = canvas.width / scene.bounds.width;
        const ratioY = canvas.height / scene.bounds.height;
        const padding = scene.protectionPadding ?? 4;
        for (const bounds of scene.protected) {
            const left = Math.max(0, Math.floor((bounds.left - scene.bounds.left - padding) * ratioX));
            const right = Math.min(canvas.width, Math.ceil((bounds.left + bounds.width - scene.bounds.left + padding) * ratioX));
            const top = Math.max(0, Math.floor((bounds.top - scene.bounds.top - padding) * ratioY));
            const bottom = Math.min(canvas.height, Math.ceil((bounds.top + bounds.height - scene.bounds.top + padding) * ratioY));
            for (let row = top; row < bottom; row += 1) protectedPixels.fill(1, row * canvas.width + left, row * canvas.width + right);
        }
        const leaves = classifyLeaves(pixels.data, canvas.width, canvas.height, protectedPixels, scene.foregroundBands);
        let frontPixels = 0;
        let hiddenPixels = 0;
        for (let index = 0; index < leaves.labels.length; index += 1) {
            const offset = index * 4 + 3;
            if (coverage[offset] === 0 || pixels.data[offset] === 0) continue;
            if (leaves.foreground[leaves.labels[index]]) {
                if (pixels.data[offset] > 180 && coverage[offset] > 180) frontPixels += 1;
            } else {
                if (pixels.data[offset] > 180 && coverage[offset] > 180) hiddenPixels += 1;
                pixels.data[offset] *= coverage[offset] >= 250 ? 0 : 1 - coverage[offset] / 255;
            }
        }
        context.putImageData(pixels, 0, 0);
        const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
        if (!blob) throw new Error('Le décor de feuilles ne peut pas être composé.');
        return { url: URL.createObjectURL(blob), frontPixels, hiddenPixels };
    };
    const update = async (root, layers) => {
        const version = ++revision;
        const regions = protectedRegions(root);
        const scenes = layers.map(layer => {
            const bounds = boundsOf(layer.node);
            const scene = { ...layer, bounds, cards: [...root.querySelectorAll(layer.cards)].map(readCard).filter(card => intersects(bounds, card.background)), protected: regions.filter(region => intersects(bounds, region)) };
            scene.signature = JSON.stringify({ source: scene.source, crop: scene.crop, foregroundBands: scene.foregroundBands, protectionPadding: scene.protectionPadding, bounds, cards: scene.cards, protected: scene.protected });
            return scene;
        });
        const rendered = await Promise.all(scenes.map(scene => {
            if (results.has(scene.signature)) return results.get(scene.signature);
            if (!pending.has(scene.signature)) pending.set(scene.signature, render(scene).then(result => { results.set(scene.signature, result); pending.delete(scene.signature); return result; }, error => { pending.delete(scene.signature); throw error; }));
            return pending.get(scene.signature);
        }));
        if (version !== revision) return false;
        await Promise.all(scenes.map(async (scene, index) => {
            const result = rendered[index];
            if (scene.node.src !== result.url) { scene.node.src = result.url; await scene.node.decode(); }
            if (version === revision) {
                scene.node.dataset.composed = 'true';
                scene.node.dataset.frontPixels = result.frontPixels;
                scene.node.dataset.hiddenPixels = result.hiddenPixels;
            }
        }));
        const active = new Set(layers.map(layer => layer.node.src));
        for (const [key, result] of results) if (results.size > 6 && !active.has(result.url)) { URL.revokeObjectURL(result.url); results.delete(key); }
        return version === revision;
    };
    window.addEventListener('pagehide', event => { if (!event.persisted) for (const result of results.values()) URL.revokeObjectURL(result.url); });
    window.SVJAutumnLayers = Object.freeze({ update });
})();
