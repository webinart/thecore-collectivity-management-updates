const assert = require("node:assert/strict");
const test = require("node:test");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");
const geometry = require("../includes/assets/transport-route-geometry.js");

const feature = (coordinates, directionId = "0", headsign = "Far", routeId = "R") => ({
	type: "Feature", properties: { variants: [{ directionId, headsign, routeId }] }, geometry: { type: "LineString", coordinates }
});
const collection = (...features) => ({ type: "FeatureCollection", features });
const short = feature([[0, 0], [1, 0], [2, 0]], "0", "Near");
const long = feature([[0, 0], [1, 0], [2, 0], [3, 0]]);
const branch = feature([[0, 0], [1, 0], [2, 1]], "0", "Branch");
const back = feature([[3, 0], [2, 0], [1, 0], [0, 0]], "1", "Origin");
const network = collection(short, long, branch, back);

function paths(data) {
	return (data.features || []).flatMap((f) => f.geometry.type === "LineString" ? [f.geometry.coordinates] : f.geometry.coordinates);
}
function edges(data) {
	return paths(data).flatMap((line) => line.slice(1).map((b, i) => [JSON.stringify(line[i]), JSON.stringify(b)].sort().join("|")));
}

test("all branches remain covered, while shared segments are drawn once", () => {
	const snapshot = JSON.stringify(network);
	const displayed = geometry.deduplicate(network);
	assert.deepEqual(new Set(edges(displayed)), new Set(edges(network)));
	assert.equal(edges(displayed).length, new Set(edges(network)).size);
	assert.equal(JSON.stringify(network), snapshot, "Rendering must not mutate GTFS provenance");
	assert.deepEqual(new Set(edges(geometry.deduplicate(collection(back, branch, long, short)))), new Set(edges(displayed)));
});

test("headsign and direction must both match; direction 0 is valid", () => {
	assert.deepEqual(geometry.filterByDirection(network, { directionId: 0, headsign: "Far" }).features, [long]);
	assert.deepEqual(geometry.filterByDirection(network, { directionId: "0", headsign: "Near" }).features, [short]);
	assert.deepEqual(geometry.filterByDirection(network, { directionId: "1", headsign: "Far" }).features, []);
	assert.deepEqual(geometry.filterByDirection(network, { directionId: "0", headsign: "Missing" }).features, []);
});

test("shared geometry retains all its route associations and exact labels", () => {
	const shared = feature([[0, 0], [1, 0]], "", "Cote", "A");
	shared.properties.variants.push({ directionId: "1", headsign: "Côte", routeId: "B" });
	const data = collection(shared, feature([[2, 0], [3, 0]], "", "Cote", "C"));
	assert.deepEqual(geometry.filterByDirection(data, { headsign: "Côte", routeId: "B" }).features, [shared]);
	assert.deepEqual(geometry.filterByDirection(data, { directionId: "0", headsign: "Côte" }).features, []);
	assert.deepEqual(geometry.filterByDirection(data, { routeId: "A", headsign: "Côte" }).features, []);
});

test("missing direction metadata and pre-update scalar metadata remain safe", () => {
	const unknown = feature([[0, 0], [1, 0]], "", "");
	const legacy = feature([[1, 0], [2, 0]]);
	legacy.properties = { directionId: "0", headsign: "Far" };
	const data = collection(unknown, legacy);
	assert.equal(geometry.filterByDirection(data, {}), data);
	assert.deepEqual(geometry.filterByDirection(data, { directionId: "0", headsign: "Far" }).features, [legacy]);
	assert.equal(geometry.filterByDirection(null, {}), null);
});

test("loops, opposite streets, disconnected paths and invalid geometry never create bridges", () => {
	const loop = feature([[0, 0], [1, 0], [1, 1], [0, 0]]);
	const parallel = feature([[0, 0.00001], [1, 0.00001]]);
	const disjoint = feature([[20, 20], [21, 20]]);
	const data = collection(loop, parallel, disjoint, null, feature([[0, 0], [NaN, 0], [2, 0]]));
	assert.deepEqual(new Set(edges(geometry.deduplicate(data))), new Set(edges(collection(loop, parallel, disjoint))));
	const marked = geometry.referenceGeoJson(collection(short, disjoint));
	assert.equal(paths(marked).length, 1);
	assert.ok(paths(marked)[0].every((p) => p[0] <= 2), "Do not place an icon between distant branches");
});

test("return journeys are deduplicated after, never before, direction filtering", () => {
	const data = collection(long, back);
	assert.equal(edges(geometry.deduplicate(data)).length, 3);
	const returning = geometry.filterByDirection(data, { directionId: "1", headsign: "Origin" });
	assert.equal(edges(geometry.deduplicate(returning)).length, 3);
});

test("manual cropping uses one distance window, including for short/return journeys", () => {
	const data = collection(short, long, back);
	assert.equal(geometry.trim(data, 0, 0, [1, 0]), data);
	const cropped = geometry.trim(data, 0.25, 0.25, [1, 0]);
	const lines = paths(cropped);
	assert.equal(lines.length, 3);
	for (const line of lines) {
		assert.ok(Math.abs(line[0][0] - 0.75) < 1e-9);
		assert.ok(line[line.length - 1][0] <= 2.25 + 1e-9);
	}
	assert.equal(lines[0][lines[0].length - 1][0], 2, "Short service ends at its real terminus");
	assert.deepEqual(new Set(edges(geometry.deduplicate(cropped))), new Set(edges(cropped)));
	assert.deepEqual(geometry.trim(collection(short), 0.5, 0.5, [1, 0]).features, []);
});

// Load the real widget methods without starting Elementor, timers or Leaflet.
function widgetPrototype(file, className) {
	const document = { readyState: "loading", addEventListener() {}, querySelectorAll: () => [] };
	const window = { TheCoreTransportGeometry: geometry, setTimeout() {}, location: { search: "" } };
	window.top = window;
	const source = fs.readFileSync(path.join(__dirname, "..", file), "utf8").replace(/\}\)\(\);\s*$/, `window.TestWidget = ${className};\n})();`);
	vm.runInNewContext(source, { window, document, console, URLSearchParams, setTimeout() {} });
	return window.TestWidget.prototype;
}

test("Transport Explorer uses full geometry by default and only the selected destination in branch mode", () => {
	const proto = widgetPrototype("includes/features/transports/assets/transport-explorer.js", "BellevueTransportExplorer");
	const instance = Object.create(proto);
	instance.state = { activeBusLineId: "42" };
	const line = { id: 42, geojson: network };
	instance.mapDirectionBranch = false;
	assert.equal(instance.getDisplayedLineGeoJson(line), network);
	instance.mapDirectionBranch = true;
	instance.getCurrentLineScheduleSelection = () => ({ selectedDirection: { directionId: "0", headsign: "Far" } });
	assert.deepEqual(instance.getDisplayedLineGeoJson(line).features, [long]);
	instance.getCurrentLineScheduleSelection = () => ({ selectedDirection: { directionId: "0", headsign: "Missing" } });
	assert.deepEqual(instance.getDisplayedLineGeoJson(line).features, []);
});

test("MAP no longer chooses a single representative shape; filters and trimming still work", () => {
	const proto = widgetPrototype("includes/features/maps/assets/map-widget.js", "TheCoreCollectivityMap");
	const instance = Object.create(proto);
	const item = { id: "transport-line-42", transportLineId: 42, source: "transport", geometryType: "route", markerLongitude: 1, markerLatitude: 0, geojson: network };
	instance.categoryStyles = {};
	instance.routeStyles = {};
	assert.equal(instance.getDisplayedRouteGeoJson(item), network);
	instance.selectedTransportDetail = { lineId: 42, mapDirectionBranch: true, directionId: "0", headsign: "Far" };
	assert.deepEqual(instance.getDisplayedRouteGeoJson(item).features, [long]);
	instance.routeStyles = { "transport-line-42": { routeTrimStart: 50, routeTrimEnd: 50 } };
	const cropped = paths(instance.getDisplayedRouteGeoJson(item));
	assert.equal(cropped.length, 1);
	assert.ok(Math.abs(cropped[0][0][0] - 0.75) < 1e-9);
	const circuit = { id: "7", source: "map", geometryType: "route", geojson: network };
	assert.equal(instance.getDisplayedRouteGeoJson(circuit), network, "Editorial circuits are unchanged");
});
