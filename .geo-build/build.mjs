// Sphotography v1.2.6 — boundary data build pipeline.
//
// Produces two normalized GeoJSON files consumed by both the server-side
// point-in-polygon indexer (PHP) and the frontend renderer (MapLibre):
//   assets/geo/boundaries-provinces.json  — DataV China provinces + Natural Earth 10m admin-1 (ex-China)
//   assets/geo/boundaries-cities.json     — DataV China city/district level
//
// Normalized feature.properties schema:
//   id    : string  — stable region id (China: 6-digit adcode; world: NE adm1_code)
//   name  : string  — display name
//   level : 'province' | 'city'
//   cc    : string  — ISO country code ('CN' for China, iso_a2 otherwise)
//   pid   : string  — (cities only) parent province adcode
//
// Prereqs in this dir: ne10m.zip (Natural Earth 10m admin-1 shapefile zip).
// Run: node build.mjs
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';

// Theme sources now live at the repository root.
const OUT = path.resolve('../assets/geo');
fs.mkdirSync(OUT, { recursive: true });
const DATAV = (adcode) => `https://geo.datav.aliyun.com/areas_v3/bound/${adcode}_full.json`;

async function getJSON(url, tries = 3) {
  for (let i = 0; i < tries; i++) {
    try {
      const r = await fetch(url, { signal: AbortSignal.timeout(45000) });
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      return await r.json();
    } catch (e) {
      if (i === tries - 1) throw e;
      await new Promise((res) => setTimeout(res, 800 * (i + 1)));
    }
  }
}
const round = (n) => Math.round(n * 1e5) / 1e5;
function roundGeom(g) {
  const walk = (c) => { if (typeof c[0] === 'number') { c[0] = round(c[0]); c[1] = round(c[1]); return; } c.forEach(walk); };
  if (g && g.coordinates) g.coordinates.forEach(walk);
  return g;
}
function mapshaper(args) {
  execFileSync('npx', ['mapshaper', ...args], { stdio: 'inherit', shell: true });
}

// ---- 1. China provinces (DataV national bound) ----
console.log('Fetching China provinces…');
const cnNational = await getJSON(DATAV(100000));
const cnProvinces = [];
const provinceAdcodes = [];
for (const f of cnNational.features) {
  if (!f.geometry) continue;
  const adcode = String(f.properties.adcode);
  provinceAdcodes.push(adcode);
  cnProvinces.push({ type: 'Feature', properties: { id: adcode, name: f.properties.name, level: 'province', cc: 'CN' }, geometry: roundGeom(f.geometry) });
}
console.log(`  ${cnProvinces.length} provinces.`);

// ---- 2. China cities (per-province DataV bound) ----
console.log('Fetching China cities (per province)…');
const cnCities = [];
for (const adcode of provinceAdcodes) {
  try {
    const prov = await getJSON(DATAV(adcode));
    let n = 0;
    for (const f of prov.features) {
      if (!f.geometry) continue;
      const cadcode = String(f.properties.adcode);
      if (cadcode === adcode) continue;
      cnCities.push({ type: 'Feature', properties: { id: cadcode, name: f.properties.name, level: 'city', cc: 'CN', pid: adcode }, geometry: roundGeom(f.geometry) });
      n++;
    }
    console.log(`  ${adcode}: ${n} sub-areas`);
  } catch (e) { console.warn(`  ${adcode}: FAILED (${e.message}) — skipped`); }
}
console.log(`  ${cnCities.length} China city/district polygons.`);

fs.writeFileSync('cn_provinces.raw.json', JSON.stringify({ type: 'FeatureCollection', features: cnProvinces }));
fs.writeFileSync('cities.raw.json', JSON.stringify({ type: 'FeatureCollection', features: cnCities }));

// ---- 3. Simplify each piece ----
console.log('Simplifying China provinces…');
mapshaper(['cn_provinces.raw.json', '-simplify', '8%', 'keep-shapes', '-clean', '-o', 'cn_prov.simp.json', 'format=geojson', 'precision=0.00001']);
console.log('Simplifying China cities…');
mapshaper(['cities.raw.json', '-simplify', '10%', 'keep-shapes', '-clean', '-o', path.join(OUT, 'boundaries-cities.json'), 'format=geojson', 'precision=0.00001']);

// ---- 4. Natural Earth 10m admin-1 → world provinces (ex-China), normalized + simplified ----
console.log('Processing Natural Earth 10m admin-1…');
mapshaper([
  'ne10m.zip',
  '-filter', '"iso_a2 !== \'CN\'"',
  '-each', '"id=String(adm1_code), name=(name||name_en||adm1_code), level=\'province\', cc=(iso_a2 && iso_a2!==\'-1\' ? iso_a2 : adm0_a3)"',
  '-filter-fields', 'id,name,level,cc',
  '-simplify', '5%', 'keep-shapes',
  '-clean',
  '-o', 'ne_world.simp.json', 'format=geojson', 'precision=0.00001',
]);

// ---- 5. Concat China provinces + world provinces ----
console.log('Merging provinces…');
const cnProvSimp = JSON.parse(fs.readFileSync('cn_prov.simp.json', 'utf8'));
const neWorld = JSON.parse(fs.readFileSync('ne_world.simp.json', 'utf8'));
const provinces = { type: 'FeatureCollection', features: [...cnProvSimp.features, ...neWorld.features] };
fs.writeFileSync(path.join(OUT, 'boundaries-provinces.json'), JSON.stringify(provinces));

const sz = (f) => (fs.statSync(f).size / 1024 / 1024).toFixed(2) + ' MB';
console.log('\nDone.');
console.log('  provinces:', provinces.features.length, 'features,', sz(path.join(OUT, 'boundaries-provinces.json')));
const cities = JSON.parse(fs.readFileSync(path.join(OUT, 'boundaries-cities.json'), 'utf8'));
console.log('  cities:   ', cities.features.length, 'features,', sz(path.join(OUT, 'boundaries-cities.json')));
