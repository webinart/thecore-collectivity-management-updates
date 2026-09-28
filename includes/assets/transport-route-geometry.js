/* Shared display geometry. Original GTFS variants remain untouched. */
((root) => {
	const text = (value) => String(value === null || value === undefined ? "" : value).trim();
	const validPoint = (p) => Array.isArray(p) && p.length >= 2 && Number.isFinite(p[0]) && Number.isFinite(p[1]) && Math.abs(p[0]) <= 180 && Math.abs(p[1]) <= 90;
	const pointKey = (p) => JSON.stringify(p.slice(0, 2));
	const featuresOf = (geojson) => {
		if (!geojson) return [];
		if (geojson.type === "FeatureCollection") {
			return (Array.isArray(geojson.features) ? geojson.features : []).filter((feature) => feature && feature.geometry);
		}
		const feature = geojson.type === "Feature" ? geojson : { type: "Feature", properties: {}, geometry: geojson };
		return feature.geometry ? [feature] : [];
	};
	const linesOf = (geometry) => {
		const lines = !geometry ? [] : geometry.type === "LineString" ? [geometry.coordinates] : geometry.type === "MultiLineString" ? geometry.coordinates : [];
		return (Array.isArray(lines) ? lines : []).filter((line) => Array.isArray(line) && line.length >= 2 && line.every(validPoint));
	};
	const collection = (features) => ({ type: "FeatureCollection", features });

	const filterByDirection = (geojson, selection) => {
		if (!geojson || !selection) return geojson;
		const direction = text(selection.directionId);
		const headsign = text(selection.headsign);
		const route = text(selection.routeId);
		if (!direction && !headsign && !route) return geojson;
		return collection(featuresOf(geojson).filter((feature) => {
			const properties = feature.properties || {};
			const variants = Array.isArray(properties.variants) ? properties.variants : [properties];
			return variants.some((variant) => {
				if (route && text(variant.routeId) !== route) return false;
				if (direction && text(variant.directionId) !== direction) return false;
				// A direction id alone cannot distinguish short workings from extensions.
				if (headsign && text(variant.headsign) !== headsign) return false;
				return true;
			});
		}));
	};

	const deduplicate = (geojson) => {
		if (!geojson) return geojson;
		const seen = new Set();
		const features = [];
		for (const feature of featuresOf(geojson)) {
			const remaining = [];
			for (const line of linesOf(feature.geometry)) {
				let run = [];
				const flush = () => { if (run.length > 1) remaining.push(run); run = []; };
				for (let i = 1; i < line.length; i += 1) {
					const a = pointKey(line[i - 1]);
					const b = pointKey(line[i]);
					if (a === b) continue;
					const key = a < b ? a + "|" + b : b + "|" + a;
					if (seen.has(key)) { flush(); continue; }
					seen.add(key);
					if (!run.length) run.push(line[i - 1]);
					run.push(line[i]);
				}
				flush();
			}
			if (remaining.length) {
				features.push({ ...feature, geometry: remaining.length === 1 ? { type: "LineString", coordinates: remaining[0] } : { type: "MultiLineString", coordinates: remaining } });
			}
		}
		return collection(features);
	};

	const distance = (a, b) => {
		const radians = Math.PI / 180;
		const h = Math.sin((b[1] - a[1]) * radians / 2) ** 2 + Math.cos(a[1] * radians) * Math.cos(b[1] * radians) * Math.sin((b[0] - a[0]) * radians / 2) ** 2;
		return 6371000 * 2 * Math.asin(Math.min(1, Math.sqrt(h)));
	};
	const length = (line) => line.reduce((sum, p, i) => i ? sum + distance(line[i - 1], p) : 0, 0);
	const comparePoint = (a, b) => a[0] - b[0] || a[1] - b[1];
	const canonical = (line) => {
		for (let i = 0; i < line.length; i += 1) {
			const comparison = comparePoint(line[i], line[line.length - 1 - i]);
			if (comparison) return comparison < 0 ? line : line.slice().reverse();
		}
		return line;
	};
	const referenceLine = (geojson) => {
		let best = [];
		let bestLength = -1;
		let bestKey = "";
		for (const feature of featuresOf(geojson)) {
			for (const line of linesOf(feature.geometry)) {
				const candidate = canonical(line);
				const candidateLength = length(candidate);
				const key = JSON.stringify(candidate);
				if (candidateLength > bestLength || (candidateLength === bestLength && key < bestKey)) {
					best = candidate; bestLength = candidateLength; bestKey = key;
				}
			}
		}
		return best;
	};
	const referenceGeoJson = (geojson) => {
		const line = referenceLine(geojson);
		return collection(line.length ? [{ type: "Feature", properties: {}, geometry: { type: "LineString", coordinates: line } }] : []);
	};
	const interpolate = (a, b, ratio) => [a[0] + (b[0] - a[0]) * ratio, a[1] + (b[1] - a[1]) * ratio];
	const anchorDistance = (line, anchor) => {
		if (!validPoint(anchor)) return 0;
		let covered = 0;
		let nearest = Infinity;
		let position = 0;
		const scale = Math.cos(anchor[1] * Math.PI / 180);
		for (let i = 1; i < line.length; i += 1) {
			const a = line[i - 1], b = line[i];
			const dx = (b[0] - a[0]) * scale, dy = b[1] - a[1];
			const squared = dx * dx + dy * dy;
			const ratio = squared ? Math.max(0, Math.min(1, (((anchor[0] - a[0]) * scale * dx) + ((anchor[1] - a[1]) * dy)) / squared)) : 0;
			const away = distance(anchor, interpolate(a, b, ratio));
			const segmentLength = distance(a, b);
			if (away < nearest) { nearest = away; position = covered + ratio * segmentLength; }
			covered += segmentLength;
		}
		return position;
	};
	const sliceLine = (line, start, end) => {
		const result = [];
		let covered = 0;
		const push = (p) => { if (!result.length || pointKey(result[result.length - 1]) !== pointKey(p)) result.push(p); };
		for (let i = 1; i < line.length; i += 1) {
			const a = line[i - 1], b = line[i], segmentLength = distance(a, b);
			const from = Math.max(start, covered), to = Math.min(end, covered + segmentLength);
			if (segmentLength > 0 && to > from) {
				push(interpolate(a, b, (from - covered) / segmentLength));
				push(interpolate(a, b, (to - covered) / segmentLength));
			}
			covered += segmentLength;
			if (covered >= end) break;
		}
		return result;
	};

	const trim = (geojson, startRatio, endRatio, anchor) => {
		if (!geojson || (startRatio <= 0 && endRatio <= 0)) return geojson;
		const reference = referenceLine(geojson);
		if (!reference.length) return collection([]);
		const referenceLength = length(reference);
		const referenceAnchor = anchorDistance(reference, anchor);
		const from = referenceLength * startRatio - referenceAnchor;
		const to = referenceLength * (1 - endRatio) - referenceAnchor;
		const features = [];
		for (const feature of featuresOf(geojson)) {
			const clipped = [];
			for (const original of linesOf(feature.geometry)) {
				const first = original[0], last = original[original.length - 1];
				const forward = distance(first, reference[0]) + distance(last, reference[reference.length - 1]);
				const backward = distance(last, reference[0]) + distance(first, reference[reference.length - 1]);
				const line = forward === backward ? canonical(original) : backward < forward ? original.slice().reverse() : original;
				const origin = anchorDistance(line, anchor);
				// One distance window around the same stop keeps opposite directions aligned.
				const cut = sliceLine(line, from + origin, to + origin);
				if (cut.length > 1) clipped.push(cut);
			}
			if (clipped.length) features.push({ ...feature, geometry: clipped.length === 1 ? { type: "LineString", coordinates: clipped[0] } : { type: "MultiLineString", coordinates: clipped } });
		}
		return collection(features);
	};

	root.TheCoreTransportGeometry = { filterByDirection, deduplicate, referenceGeoJson, trim };
	if (typeof module !== "undefined" && module.exports) module.exports = root.TheCoreTransportGeometry;
})(typeof window !== "undefined" ? window : globalThis);
