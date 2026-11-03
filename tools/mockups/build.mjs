// KNU Merch "1925" — product image generator.
// Renders studio-style garment / accessory mockups as layered SVG (soft light,
// fabric noise, folds, rib knit, embroidered logo) and rasterises them with sharp.
//
// Usage:  cd tools/mockups && npm install && node build.mjs [--only=name]
// Input:  _downloads/logo_knu.png  (official logo from knu.kg, downloaded separately)
// Output: theme/knu-merch/assets/img/{logo*.png, hero.webp, products/*.webp}

import sharp from 'sharp';
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(HERE, '../..');
const OUT = path.join(ROOT, 'theme/knu-merch/assets/img');
const OUT_P = path.join(OUT, 'products');
const LOGO_SRC = path.join(HERE, '_downloads/logo_knu.png');
const BG = '#F5F5F3';
const BLUE = '#0067AC';
const WEBP = { quality: 82, effort: 6, smartSubsample: true };
const ONLY = (process.argv.find((a) => a.startsWith('--only=')) || '').slice(7);
const PREVIEW = process.argv.includes('--preview');

// ---------------------------------------------------------------- utils
const clamp = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
const hex2rgb = (h) => [1, 3, 5].map((i) => parseInt(h.slice(i, i + 2), 16));
const rgb2hex = (c) => '#' + c.map((v) => Math.round(clamp(v, 0, 255)).toString(16).padStart(2, '0')).join('');
const mix = (a, b, t) => rgb2hex(hex2rgb(a).map((v, i) => v + (hex2rgb(b)[i] - v) * t));
let UID = 0;
let NS = 1; // noise scale: keeps fabric grain constant in output pixels regardless of garment scale
const nf = (f) => +(f * NS).toFixed(3);
const uid = (s) => `${s}${++UID}`;

// Garment colours. `base` is the rendered cloth tone (tuned so the average
// reads as the swatch colour), shade/hi are strengths of shadow/highlight overlays.
const COLORS = {
  grey: { base: '#BCBCB9', shade: 0.34, hi: 0.17, melange: true, ink: BLUE, inkName: 'blue' },
  navy: { base: '#1C2A44', shade: 0.55, hi: 0.11, ink: '#F3F3EF', inkName: 'white' },
  black: { base: '#1B1B1C', shade: 0.6, hi: 0.095, ink: '#F3F3EF', inkName: 'white' },
};

// ---------------------------------------------------------------- logos
let LOGO_MASK; // Uint8 alpha mask of the one-colour logo, 809x809
let LOGO_INFO;

async function prepareLogo() {
  const { data, info } = await sharp(LOGO_SRC).ensureAlpha().raw().toBuffer({ resolveWithObject: true });
  const n = info.width * info.height;
  const mask = Buffer.alloc(n);
  for (let i = 0; i < n; i++) {
    const r = data[i * 4], g = data[i * 4 + 1], b = data[i * 4 + 2], a = data[i * 4 + 3] / 255;
    // "ink" = distance from paper-white. Blue and yellow both have min(r,g,b)≈0.
    let ink = 1 - Math.min(r, g, b) / 255;
    // Yellow parts (sun arc, "У" of the monogram) get a lighter thread tone,
    // so they stay distinguishable from the blue "К" they touch.
    const yellow = clamp((r - b - 60) / 120);
    ink *= 1 - 0.45 * yellow;
    ink = clamp((ink - 0.06) / 0.88);
    mask[i] = Math.round(ink * a * 255);
  }
  LOGO_MASK = mask;
  LOGO_INFO = info;
}

function tintedRaw(hex) {
  const [r, g, b] = hex2rgb(hex);
  const n = LOGO_INFO.width * LOGO_INFO.height;
  const out = Buffer.alloc(n * 4);
  for (let i = 0; i < n; i++) {
    out[i * 4] = r; out[i * 4 + 1] = g; out[i * 4 + 2] = b; out[i * 4 + 3] = LOGO_MASK[i];
  }
  return sharp(out, { raw: { width: LOGO_INFO.width, height: LOGO_INFO.height, channels: 4 } });
}

async function buildLogoFiles() {
  await sharp(LOGO_SRC).resize(512, 512, { kernel: 'lanczos3' }).png({ compressionLevel: 9 }).toFile(path.join(OUT, 'logo.png'));
  await tintedRaw('#FFFFFF').resize(512, 512).png({ compressionLevel: 9 }).toFile(path.join(OUT, 'logo-white.png'));
  await tintedRaw(BLUE).resize(512, 512).png({ compressionLevel: 9 }).toFile(path.join(OUT, 'logo-blue.png'));
}

const uriCache = new Map();
async function logoURI(kind, size) {
  const key = kind + size;
  if (uriCache.has(key)) return uriCache.get(key);
  let img;
  if (kind === 'color') img = sharp(LOGO_SRC);
  else img = tintedRaw(kind === 'blue' ? BLUE : kind === 'white' ? '#F3F3EF' : '#FFFFFF');
  const buf = await sharp(await img.png().toBuffer()).resize(size, size, { kernel: 'lanczos3' }).png().toBuffer();
  const uri = 'data:image/png;base64,' + buf.toString('base64');
  uriCache.set(key, uri);
  return uri;
}

// Cylindrical warp of the colour logo (for mug / cap): compresses edges as on a curved surface.
async function warpedLogoURI(kind, size, halfAngleDeg) {
  const key = `warp${kind}${size}${halfAngleDeg}`;
  if (uriCache.has(key)) return uriCache.get(key);
  let img = kind === 'color' ? sharp(LOGO_SRC) : tintedRaw(kind === 'blue' ? BLUE : '#F3F3EF');
  const { data, info } = await sharp(await img.png().toBuffer()).resize(size, size).ensureAlpha().raw().toBuffer({ resolveWithObject: true });
  const A = (halfAngleDeg * Math.PI) / 180;
  const W = info.width, H = info.height;
  const outW = Math.round((W * Math.sin(A)) / A);
  const out = Buffer.alloc(outW * H * 4);
  for (let x = 0; x < outW; x++) {
    const xn = ((x + 0.5) / outW) * 2 - 1; // -1..1 on screen
    const phi = Math.asin(clamp(xn * Math.sin(A), -1, 1));
    const u = ((phi / A + 1) / 2) * W - 0.5;
    const x0 = Math.floor(u), f = u - x0;
    for (let y = 0; y < H; y++) {
      for (let c = 0; c < 4; c++) {
        const a = x0 >= 0 && x0 < W ? data[(y * W + x0) * 4 + c] : 0;
        const b = x0 + 1 >= 0 && x0 + 1 < W ? data[(y * W + x0 + 1) * 4 + c] : 0;
        out[(y * outW + x) * 4 + c] = Math.round(a * (1 - f) + b * f);
      }
    }
  }
  const buf = await sharp(out, { raw: { width: outW, height: H, channels: 4 } }).png().toBuffer();
  const res = { uri: 'data:image/png;base64,' + buf.toString('base64'), w: outW, h: H };
  uriCache.set(key, res);
  return res;
}

// ---------------------------------------------------------------- shared SVG defs
function commonDefs(p, c, W, H) {
  const box = `filterUnits="userSpaceOnUse" x="-400" y="-400" width="${W + 800}" height="${H + 800}"`;
  const blur = (n) => `<filter id="${p}-b${n}" ${box}><feGaussianBlur stdDeviation="${n}"/></filter>`;
  return `
  ${[2, 4, 7, 12, 20, 34].map(blur).join('')}
  <filter id="${p}-noise" filterUnits="userSpaceOnUse" x="0" y="0" width="${W}" height="${H}">
    <feTurbulence type="fractalNoise" baseFrequency="${nf(0.9)}" numOctaves="2" seed="${UID % 50}"/>
    <feColorMatrix type="matrix" values="0 0 0 0 0  0 0 0 0 0  0 0 0 0 0  -2.2 0 0 0 1.25"/>
  </filter>
  <filter id="${p}-noiseW" filterUnits="userSpaceOnUse" x="0" y="0" width="${W}" height="${H}">
    <feTurbulence type="fractalNoise" baseFrequency="${nf(0.9)}" numOctaves="2" seed="${(UID % 50) + 11}"/>
    <feColorMatrix type="matrix" values="0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  2.2 0 0 0 -0.95"/>
  </filter>
  <filter id="${p}-mel" filterUnits="userSpaceOnUse" x="0" y="0" width="${W}" height="${H}">
    <feTurbulence type="fractalNoise" baseFrequency="${nf(0.22)} ${nf(1.1)}" numOctaves="3" seed="${(UID % 50) + 5}"/>
    <feColorMatrix type="matrix" values="0 0 0 0 0  0 0 0 0 0  0 0 0 0 0  -3.2 0 0 0 1.75"/>
  </filter>
  <filter id="${p}-melW" filterUnits="userSpaceOnUse" x="0" y="0" width="${W}" height="${H}">
    <feTurbulence type="fractalNoise" baseFrequency="${nf(0.22)} ${nf(1.1)}" numOctaves="3" seed="${(UID % 50) + 23}"/>
    <feColorMatrix type="matrix" values="0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  3.2 0 0 0 -1.6"/>
  </filter>
  <filter id="${p}-emb" ${box}>
    <feGaussianBlur in="SourceAlpha" stdDeviation="0.9" result="b"/>
    <feOffset in="b" dx="0.4" dy="1.3" result="o"/>
    <feFlood flood-color="#000" flood-opacity="${c && c.inkName === 'blue' ? 0.35 : 0.55}"/>
    <feComposite in2="o" operator="in" result="sh"/>
    <feOffset in="SourceAlpha" dx="-0.4" dy="-0.5" result="hl0"/>
    <feFlood flood-color="#fff" flood-opacity="${c && c.inkName === 'blue' ? 0.25 : 0.1}"/>
    <feComposite in2="hl0" operator="in" result="hl"/>
    <feMerge><feMergeNode in="sh"/><feMergeNode in="hl"/><feMergeNode in="SourceGraphic"/></feMerge>
  </filter>
  <pattern id="${p}-rib" width="6" height="10" patternUnits="userSpaceOnUse">
    <rect width="2.6" height="10" fill="#000" opacity="0.16"/><rect x="3" width="1.2" height="10" fill="#fff" opacity="0.07"/>
  </pattern>
  <pattern id="${p}-thread" width="3" height="3" patternUnits="userSpaceOnUse" patternTransform="rotate(35)">
    <rect width="3" height="1.1" fill="#000" opacity="0.22"/>
  </pattern>`;
}

// Fabric surface overlays (noise / melange), clipped to `clip`.
function fabric(p, c, W, H, clip) {
  let s = `<g clip-path="url(#${clip})">`;
  if (c.melange) {
    s += `<rect width="${W}" height="${H}" filter="url(#${p}-mel)" opacity="0.13"/>`;
    s += `<rect width="${W}" height="${H}" filter="url(#${p}-melW)" opacity="0.2"/>`;
  }
  s += `<rect width="${W}" height="${H}" filter="url(#${p}-noise)" opacity="${c.melange ? 0.12 : 0.2}"/>`;
  s += `<rect width="${W}" height="${H}" filter="url(#${p}-noiseW)" opacity="${c.melange ? 0.1 : 0.06}"/>`;
  return s + '</g>';
}

// Soft fold: dark (d) or light (l) blurred stroke
const fold = (p, c, d, w, k, blur = 7, o = 1) =>
  `<path d="${d}" fill="none" stroke="${k === 'd' ? '#000' : '#fff'}" stroke-width="${w}" stroke-linecap="round" opacity="${(k === 'd' ? c.shade : c.hi) * o}" filter="url(#${p}-b${blur})"/>`;
const seam = (d, o = 0.28, w = 2) =>
  `<path d="${d}" fill="none" stroke="#000" stroke-width="${w}" opacity="${o}" stroke-linecap="round"/>`;
const stitch = (d, c, o = 1) =>
  `<path d="${d}" fill="none" stroke="${c.inkName === 'blue' ? '#000' : '#fff'}" stroke-width="1.6" stroke-dasharray="5 4" opacity="${(c.inkName === 'blue' ? 0.16 : 0.13) * o}" stroke-linecap="round"/>`;

// Ground shadow from silhouette markup
function groundShadow(p, sil, { dy = 16, blur = 20, o = 0.2 } = {}) {
  return `<g transform="translate(0 ${dy})" filter="url(#${p}-b${blur})" opacity="${o}">${sil}</g>
  <g transform="translate(0 3)" filter="url(#${p}-b4)" opacity="${o * 0.7}">${sil}</g>`;
}

// Embroidered logo on cloth
async function embLogo(p, c, cx, cy, size, rot = 0) {
  const uri = await logoURI(c.inkName, 320);
  const mask = await logoURI('white-full', 320);
  const x = cx - size / 2, y = cy - size / 2;
  return `<g transform="rotate(${rot} ${cx} ${cy})">
    <mask id="${p}-lm" maskUnits="userSpaceOnUse" x="${x - 5}" y="${y - 5}" width="${size + 10}" height="${size + 10}"><image href="${mask}" x="${x}" y="${y}" width="${size}" height="${size}"/></mask>
    <g filter="url(#${p}-emb)"><image href="${uri}" x="${x}" y="${y}" width="${size}" height="${size}" opacity="${c.inkName === 'blue' ? 0.96 : 0.94}"/></g>
    <rect x="${x}" y="${y}" width="${size}" height="${size}" fill="url(#${p}-thread)" mask="url(#${p}-lm)"/>
  </g>`;
}

// ---------------------------------------------------------------- top garment (hoodie / sweatshirt)
// local box 1000 x 1000, centred on x = 500. Hood top y≈10, hem y≈912.
const TOP = {
  W: 1000, H: 1000,
  body: 'M408 200 C370 212 300 232 238 250 C246 300 252 370 262 420 C258 560 262 720 266 842 L734 842 C738 720 742 560 738 420 C748 370 754 300 762 250 C700 232 630 212 592 200 C556 226 444 226 408 200 Z',
  sleeveL: 'M238 250 C190 262 160 310 148 390 C132 520 118 680 108 800 L200 808 C210 690 230 540 262 420 C252 370 246 300 238 250 Z',
  cuffL: 'M108 794 L201 801 L196 880 C170 886 140 885 114 879 Z',
  hem: 'M265 836 L735 836 L729 905 C600 914 400 914 271 905 Z',
  armL: 'M238 250 C246 300 252 370 262 420',
};
const mirror = (s) => `<g transform="translate(1000 0) scale(-1 1)">${s}</g>`;

async function topGarment(colorKey, kind /* hoodie|sweat */) {
  const c = COLORS[colorKey];
  const p = uid('g');
  const { W, H } = TOP;
  const base = c.base;
  const darkIn = mix(base, '#000000', colorKey === 'grey' ? 0.4 : 0.45);
  const lighter = mix(base, '#ffffff', colorKey === 'grey' ? 0.08 : 0.05);
  const hood = kind === 'hoodie';

  const HOOD_OUT = 'M360 242 C336 150 356 54 430 24 C472 8 528 8 570 24 C644 54 664 150 640 242 Z';
  const HOOD_IN = 'M500 62 C440 64 406 112 408 180 C410 230 448 258 500 274 C552 258 590 230 592 180 C594 112 560 64 500 62 Z';
  const LAP_R = 'M500 60 C562 62 594 112 592 180 C590 232 556 268 466 292';
  const LAP_L = 'M500 60 C438 62 406 112 408 180 C410 232 444 268 534 292';

  const silParts = [
    hood ? `<path d="${HOOD_OUT}"/>` : '',
    `<path d="${TOP.sleeveL}"/><path d="${TOP.cuffL}"/>`,
    `<path d="${TOP.sleeveL}" transform="translate(1000 0) scale(-1 1)"/><path d="${TOP.cuffL}" transform="translate(1000 0) scale(-1 1)"/>`,
    `<path d="${TOP.body}"/><path d="${TOP.hem}"/>`,
  ].join('');

  const defs = `<defs>${commonDefs(p, c, W, H)}
    <clipPath id="${p}-all">${silParts}</clipPath>
    <clipPath id="${p}-body"><path d="${TOP.body}"/></clipPath>
    <clipPath id="${p}-sl"><path d="${TOP.sleeveL}"/></clipPath>
    <clipPath id="${p}-cuff"><path d="${TOP.cuffL}"/></clipPath>
    <clipPath id="${p}-hem"><path d="${TOP.hem}"/></clipPath>
    <clipPath id="${p}-hood"><path d="${HOOD_OUT}"/></clipPath>
    <clipPath id="${p}-hin"><path d="${HOOD_IN}"/></clipPath>
    <linearGradient id="${p}-cyl" x1="0" x2="1" y1="0" y2="0">
      <stop offset="0" stop-color="#000" stop-opacity="${c.shade * 0.7}"/>
      <stop offset="0.13" stop-color="#000" stop-opacity="0"/>
      <stop offset="0.4" stop-color="#fff" stop-opacity="${c.hi * 0.4}"/>
      <stop offset="0.6" stop-color="#fff" stop-opacity="${c.hi * 0.25}"/>
      <stop offset="0.87" stop-color="#000" stop-opacity="0"/>
      <stop offset="1" stop-color="#000" stop-opacity="${c.shade * 0.7}"/>
    </linearGradient>
    <linearGradient id="${p}-slv" x1="0" x2="1" y1="0" y2="0">
      <stop offset="0" stop-color="#000" stop-opacity="${c.shade * 0.8}"/>
      <stop offset="0.25" stop-color="#000" stop-opacity="0.0"/>
      <stop offset="0.48" stop-color="#fff" stop-opacity="${c.hi * 0.5}"/>
      <stop offset="0.75" stop-color="#000" stop-opacity="0"/>
      <stop offset="1" stop-color="#000" stop-opacity="${c.shade * 0.85}"/>
    </linearGradient>
    <linearGradient id="${p}-vert" x1="0" x2="0" y1="0" y2="1">
      <stop offset="0" stop-color="#fff" stop-opacity="${c.hi * 0.5}"/>
      <stop offset="0.35" stop-color="#fff" stop-opacity="0"/>
      <stop offset="0.7" stop-color="#000" stop-opacity="0"/>
      <stop offset="1" stop-color="#000" stop-opacity="${c.shade * 0.3}"/>
    </linearGradient>
    <linearGradient id="${p}-hinG" x1="0" x2="0" y1="0" y2="1">
      <stop offset="0" stop-color="#000" stop-opacity="0.5"/>
      <stop offset="0.6" stop-color="#000" stop-opacity="0.1"/>
      <stop offset="1" stop-color="#000" stop-opacity="0.3"/>
    </linearGradient>
  </defs>`;

  // --- sleeve (left, mirrored for right). Folds kept irregular to avoid a striped look.
  const sleeve = `
    <path d="${TOP.sleeveL}" fill="${base}"/>
    <g clip-path="url(#${p}-sl)">
      <path d="${TOP.sleeveL}" fill="url(#${p}-slv)"/>
      ${fold(p, c, 'M258 440 C240 520 222 590 210 640', 24, 'd', 12, 0.6)}
      ${fold(p, c, 'M232 540 C210 556 186 566 160 566', 12, 'd', 7, 0.55)}
      ${fold(p, c, 'M236 524 C214 538 190 546 164 546', 7, 'l', 4, 0.8)}
      ${fold(p, c, 'M216 628 C196 646 168 652 140 650', 10, 'd', 7, 0.45)}
      ${fold(p, c, 'M212 742 C190 750 160 754 124 748', 10, 'd', 4, 0.75)}
      ${fold(p, c, 'M214 728 C190 736 160 740 128 734', 5, 'l', 4, 0.85)}
      ${fold(p, c, 'M206 778 C184 786 150 790 116 782', 9, 'd', 4, 0.85)}
      ${fold(p, c, 'M180 290 C164 350 156 420 152 470', 30, 'l', 20, 0.8)}
      ${fold(p, c, 'M238 250 C190 262 160 310 148 390 C132 520 118 680 108 800', 24, 'd', 12, 0.55)}
    </g>
    <path d="${TOP.cuffL}" fill="${base}"/>
    <g clip-path="url(#${p}-cuff)">
      <rect x="90" y="784" width="130" height="110" fill="url(#${p}-rib)" transform="rotate(4 150 840)"/>
      <path d="${TOP.cuffL}" fill="url(#${p}-slv)"/>
      ${fold(p, c, 'M100 796 L210 803', 12, 'd', 4, 0.9)}
      ${fold(p, c, 'M110 882 C140 888 172 889 200 882', 10, 'd', 4, 0.6)}
    </g>
    ${seam('M109 796 L201 803', 0.3, 1.6)}`;

  // --- body
  const pocket = hood
    ? (() => {
        const P = 'M398 598 L602 598 L664 716 L670 838 L330 838 L336 716 Z';
        return `
      <g clip-path="url(#${p}-body)">
        <path d="M400 610 L338 728" stroke="#000" stroke-width="16" opacity="${c.shade * 0.6}" filter="url(#${p}-b7)"/>
        <path d="M600 610 L662 728" stroke="#000" stroke-width="16" opacity="${c.shade * 0.6}" filter="url(#${p}-b7)"/>
      </g>
      <path d="${P}" fill="${base}"/>
      <path d="${P}" fill="url(#${p}-cyl)" opacity="0.4"/>
      ${fold(p, c, 'M400 602 L600 602', 8, 'l', 4, 0.9)}
      ${seam('M398 598 L602 598', 0.25, 1.6)}
      ${seam('M398 598 L336 716 L330 838', 0.32, 2)}
      ${seam('M602 598 L664 716 L670 838', 0.32, 2)}
      ${fold(p, c, 'M400 600 L337 718', 5, 'l', 2, 1)}
      ${fold(p, c, 'M600 600 L663 718', 5, 'l', 2, 1)}
      ${stitch('M406 608 L594 608', c)}
      ${stitch('M408 606 L347 720 L342 834', c)}
      ${stitch('M592 606 L653 720 L658 834', c)}
      ${fold(p, c, 'M430 760 C452 790 466 812 474 836', 12, 'd', 7, 0.4)}
      ${fold(p, c, 'M590 744 C572 780 560 810 552 836', 12, 'd', 7, 0.4)}`;
      })()
    : '';

  const bodyDetails = `
    <path d="${TOP.body}" fill="${base}"/>
    <g clip-path="url(#${p}-body)">
      <path d="${TOP.body}" fill="url(#${p}-cyl)"/>
      ${fold(p, c, 'M274 440 C300 480 330 512 366 540', 24, 'd', 12, 0.6)}
      ${fold(p, c, 'M726 440 C700 480 670 512 634 540', 24, 'd', 12, 0.6)}
      ${fold(p, c, 'M340 290 C400 330 460 340 500 340', 44, 'l', 20, 0.65)}
      ${fold(p, c, 'M660 290 C600 330 540 340 500 340', 44, 'l', 20, 0.45)}
      ${fold(p, c, 'M480 300 L470 640', 80, 'l', 34, 0.5)}
      ${fold(p, c, 'M300 792 L306 842', 9, 'd', 4, 0.8)}
      ${fold(p, c, 'M392 786 L396 842', 9, 'd', 4, 0.6)}
      ${fold(p, c, 'M612 780 L608 842', 9, 'd', 4, 0.6)}
      ${fold(p, c, 'M700 796 L694 842', 9, 'd', 4, 0.8)}
      ${fold(p, c, 'M262 420 C258 560 262 720 266 842', 30, 'd', 12, 0.5)}
      ${fold(p, c, 'M738 420 C742 560 738 720 734 842', 30, 'd', 12, 0.5)}
      ${fold(p, c, TOP.armL, 18, 'd', 7, 0.65)}
      ${mirror(fold(p, c, TOP.armL, 18, 'd', 7, 0.65))}
    </g>
    ${seam(TOP.armL, 0.32, 1.8)}${mirror(seam(TOP.armL, 0.32, 1.8))}
    ${stitch('M246 252 C254 302 260 372 270 420', c, 0.8)}${mirror(stitch('M246 252 C254 302 260 372 270 420', c, 0.8))}
    ${pocket}
    <path d="${TOP.hem}" fill="${base}"/>
    <g clip-path="url(#${p}-hem)">
      <rect x="250" y="830" width="500" height="90" fill="url(#${p}-rib)"/>
      <path d="${TOP.hem}" fill="url(#${p}-cyl)"/>
      ${fold(p, c, 'M262 838 L738 838', 12, 'd', 4, 0.9)}
      ${fold(p, c, 'M272 906 C400 916 600 916 728 906', 10, 'd', 4, 0.6)}
    </g>
    ${seam('M266 837 L734 837', 0.3, 1.6)}`;

  // --- neck: hood or rib collar
  const neckBack = hood
    ? `<path d="${HOOD_OUT}" fill="${base}"/>
       <g clip-path="url(#${p}-hood)">
         <path d="${HOOD_OUT}" fill="url(#${p}-cyl)"/>
         ${fold(p, c, 'M462 30 C420 52 392 100 384 160', 22, 'l', 12, 0.8)}
         ${fold(p, c, 'M360 242 C336 150 356 54 430 24', 18, 'd', 7, 0.7)}
         ${fold(p, c, 'M640 242 C664 150 644 54 570 24', 18, 'd', 7, 0.7)}
       </g>
       <path d="${HOOD_IN}" fill="${darkIn}"/>
       <g clip-path="url(#${p}-hin)"><path d="${HOOD_IN}" fill="url(#${p}-hinG)"/>
         ${fold(p, c, 'M500 110 C470 140 460 190 470 250', 40, 'l', 20, 0.3)}
       </g>`
    : `<path d="M414 204 C444 192 556 192 586 204 C566 252 434 252 414 204 Z" fill="${darkIn}"/>
       <path d="M414 204 C444 192 556 192 586 204" fill="none" stroke="#000" stroke-width="14" opacity="0.35" filter="url(#${p}-b7)" transform="translate(0 8)"/>
       <path d="M484 196 L516 196 L515 212 L485 212 Z" fill="${mix(base, '#ffffff', 0.2)}" opacity="0.45"/>`;



  const neckFront = hood
    ? `
      <path d="${LAP_R}" fill="none" stroke="#000" stroke-width="30" opacity="0.35" filter="url(#${p}-b7)" transform="translate(0 4)"/>
      <path d="${LAP_R}" fill="none" stroke="${lighter}" stroke-width="24" stroke-linecap="round"/>
      <path d="${LAP_R}" fill="none" stroke="#000" stroke-width="24" opacity="${c.shade * 0.22}"/>
      ${stitch(LAP_R, c, 0.6)}
      <path d="${LAP_L}" fill="none" stroke="#000" stroke-width="30" opacity="0.4" filter="url(#${p}-b7)" transform="translate(0 4)"/>
      <path d="${LAP_L}" fill="none" stroke="${lighter}" stroke-width="24" stroke-linecap="round"/>
      ${stitch(LAP_L, c, 0.9)}
      <circle cx="466" cy="266" r="6.5" fill="${darkIn}"/><circle cx="466" cy="266" r="6.5" fill="none" stroke="${lighter}" stroke-width="2.5"/>
      <circle cx="534" cy="266" r="6.5" fill="${darkIn}"/><circle cx="534" cy="266" r="6.5" fill="none" stroke="${lighter}" stroke-width="2.5"/>
      ${['M466 267 C458 320 472 380 460 440', 'M534 267 C544 312 530 372 543 426']
        .map(
          (d) => `<path d="${d}" fill="none" stroke="#000" stroke-width="12" opacity="0.35" filter="url(#${p}-b4)" transform="translate(3 6)"/>
          <path d="${d}" fill="none" stroke="${mix(base, '#000', 0.1)}" stroke-width="10" stroke-linecap="round"/>
          <path d="${d}" fill="none" stroke="#fff" stroke-width="3" opacity="${c.hi * 1.3}" stroke-linecap="round" transform="translate(-1.5 0)"/>`
        )
        .join('')}
      <rect x="453" y="434" width="13" height="26" rx="5" fill="${mix(base, '#000', 0.32)}" transform="rotate(8 459 447)"/>
      <rect x="537" y="420" width="13" height="26" rx="5" fill="${mix(base, '#000', 0.32)}" transform="rotate(-8 543 433)"/>`
    : `
      <path d="M390 200 C410 284 590 284 610 200" fill="none" stroke="#000" stroke-width="14" opacity="0.35" filter="url(#${p}-b7)" transform="translate(0 6)"/>
      <path d="M390 200 C424 178 576 178 610 200 C590 284 410 284 390 200 Z M414 204 C444 192 556 192 586 204 C566 252 434 252 414 204 Z" fill-rule="evenodd" fill="${base}"/>
      <path d="M390 200 C424 178 576 178 610 200 C590 284 410 284 390 200 Z M414 204 C444 192 556 192 586 204 C566 252 434 252 414 204 Z" fill-rule="evenodd" fill="url(#${p}-rib)"/>
      <path d="M416 210 C436 250 564 250 584 210" fill="none" stroke="#000" stroke-width="6" opacity="0.35" filter="url(#${p}-b2)"/><path d="M398 214 C424 270 576 270 602 214" fill="none" stroke="#fff" stroke-width="8" opacity="${c.hi * 0.9}" filter="url(#${p}-b4)"/><path d="M392 198 C426 180 574 180 608 198" fill="none" stroke="#fff" stroke-width="3" opacity="${c.hi * 0.8}" filter="url(#${p}-b2)"/>
      <path d="M390 200 C410 284 590 284 610 200" fill="none" stroke="#000" stroke-width="1.6" opacity="0.3"/>
      ${stitch('M394 210 C418 290 582 290 606 210', c, 0.9)}`;

  // logo: wearer's left chest = viewer's right
  const logo = await embLogo(p, c, 612, 372, 66);

  const svg = `${defs}
  ${groundShadow(p, `<g fill="#000">${silParts}</g>`)}
  ${neckBack}
  ${sleeve}
  ${mirror(sleeve)}
  ${bodyDetails}
  ${fabric(p, c, W, H, `${p}-all`)}
  ${logo}
  <g clip-path="url(#${p}-all)">
    <rect width="${W}" height="${H}" fill="url(#${p}-vert)"/>
  </g>
  ${neckFront}`;
  return { svg, W, H };
}


// ---------------------------------------------------------------- joggers (local 600 x 1000)
const JOG = {
  W: 600, H: 1000,
  legs: 'M114 66 L486 66 C494 180 498 300 492 420 C484 600 462 780 438 902 L338 902 C330 760 320 580 310 420 C308 392 292 392 290 420 C280 580 270 760 262 902 L162 902 C138 780 116 600 108 420 C102 300 106 180 114 66 Z',
  waist: 'M110 6 C240 14 360 14 490 6 L488 74 C360 82 240 82 112 74 Z',
  cuffL: 'M162 896 L262 896 L257 976 C232 982 194 982 168 976 Z',
};

async function joggers(colorKey) {
  const c = COLORS[colorKey];
  const p = uid('j');
  const { W, H } = JOG;
  const base = c.base;
  const sil = `<path d="${JOG.legs}"/><path d="${JOG.waist}"/><path d="${JOG.cuffL}"/><path d="${JOG.cuffL}" transform="translate(600 0) scale(-1 1)"/>`;
  const defs = `<defs>${commonDefs(p, c, W, H)}
    <clipPath id="${p}-all">${sil}</clipPath>
    <clipPath id="${p}-legs"><path d="${JOG.legs}"/></clipPath>
    <clipPath id="${p}-waist"><path d="${JOG.waist}"/></clipPath>
    <clipPath id="${p}-cuffs"><path d="${JOG.cuffL}"/><path d="${JOG.cuffL}" transform="translate(600 0) scale(-1 1)"/></clipPath>
    <linearGradient id="${p}-leg" x1="0" x2="1" y1="0" y2="0">
      <stop offset="0" stop-color="#000" stop-opacity="${c.shade * 0.8}"/>
      <stop offset="0.1" stop-color="#000" stop-opacity="0"/>
      <stop offset="0.24" stop-color="#fff" stop-opacity="${c.hi * 0.5}"/>
      <stop offset="0.4" stop-color="#000" stop-opacity="0"/>
      <stop offset="0.5" stop-color="#000" stop-opacity="${c.shade * 0.5}"/>
      <stop offset="0.6" stop-color="#000" stop-opacity="0"/>
      <stop offset="0.76" stop-color="#fff" stop-opacity="${c.hi * 0.4}"/>
      <stop offset="0.9" stop-color="#000" stop-opacity="0"/>
      <stop offset="1" stop-color="#000" stop-opacity="${c.shade * 0.8}"/>
    </linearGradient>
    <linearGradient id="${p}-vert" x1="0" x2="0" y1="0" y2="1">
      <stop offset="0" stop-color="#fff" stop-opacity="${c.hi * 0.4}"/>
      <stop offset="0.4" stop-color="#fff" stop-opacity="0"/>
      <stop offset="1" stop-color="#000" stop-opacity="${c.shade * 0.3}"/>
    </linearGradient>
  </defs>`;
  const legFolds = [
      fold(p, c, 'M140 300 C170 330 205 350 250 362', 14, 'd', 12, 0.45),
      fold(p, c, 'M126 600 C160 612 210 616 272 604', 12, 'd', 7, 0.45),
      fold(p, c, 'M128 586 C162 596 210 600 272 590', 7, 'l', 4, 0.55),
      fold(p, c, 'M150 790 C178 800 220 800 264 790', 11, 'd', 7, 0.55),
      fold(p, c, 'M152 776 C180 786 220 786 264 776', 6, 'l', 4, 0.6),
      fold(p, c, 'M160 858 C186 868 226 870 262 860', 10, 'd', 4, 0.75),
      fold(p, c, 'M160 845 C186 854 226 856 262 846', 6, 'l', 4, 0.7),
      fold(p, c, 'M205 120 C212 300 214 520 212 880', 34, 'l', 20, 0.5),
      fold(p, c, 'M460 300 C430 330 395 350 350 362', 14, 'd', 12, 0.45),
      fold(p, c, 'M474 650 C440 662 390 666 328 654', 12, 'd', 7, 0.4),
      fold(p, c, 'M474 636 C440 646 390 650 328 640', 7, 'l', 4, 0.5),
      fold(p, c, 'M452 742 C422 754 382 756 336 746', 10, 'd', 7, 0.45),
      fold(p, c, 'M444 826 C418 836 378 838 338 828', 11, 'd', 4, 0.7),
      fold(p, c, 'M444 812 C418 822 378 824 338 814', 6, 'l', 4, 0.65),
      fold(p, c, 'M440 878 C418 886 378 888 340 880', 9, 'd', 4, 0.7),
      fold(p, c, 'M395 120 C388 300 386 520 390 880', 34, 'l', 20, 0.45),
    ].join('');
  const pocketL = 'M120 96 C140 150 152 210 156 262';
  const ink = c.ink;
  const svg = `${defs}
  ${groundShadow(p, `<g fill="#000">${sil}</g>`, { dy: 14, blur: 20, o: 0.2 })}
  <path d="${JOG.legs}" fill="${base}"/>
  <g clip-path="url(#${p}-legs)">
    <path d="${JOG.legs}" fill="url(#${p}-leg)"/>
    ${legFolds}
    ${fold(p, c, 'M300 80 L300 392', 10, 'd', 4, 0.7)}
    ${fold(p, c, 'M296 380 C290 360 270 340 240 330', 14, 'd', 7, 0.6)}
    ${fold(p, c, 'M304 380 C310 360 330 340 360 330', 14, 'd', 7, 0.6)}
    ${fold(p, c, pocketL, 12, 'd', 4, 0.8)}
    ${fold(p, c, 'M480 96 C460 150 448 210 444 262', 12, 'd', 4, 0.8)}
    ${fold(p, c, 'M114 66 C106 180 102 300 108 420 C116 600 138 780 162 902', 24, 'd', 12, 0.6)}
    ${fold(p, c, 'M486 66 C494 180 498 300 492 420 C484 600 462 780 438 902', 24, 'd', 12, 0.6)}
  </g>
  ${seam('M300 78 L300 390', 0.3, 1.8)}
  ${stitch('M308 80 C312 180 312 260 300 330', c)}
  ${seam(pocketL, 0.35, 1.8)}${seam('M480 96 C460 150 448 210 444 262', 0.35, 1.8)}
  <path d="${JOG.waist}" fill="${base}"/>
  <g clip-path="url(#${p}-waist)">
    <rect x="100" y="0" width="400" height="90" fill="url(#${p}-rib)"/>
    <path d="${JOG.waist}" fill="url(#${p}-leg)" opacity="0.6"/>
    ${fold(p, c, 'M110 74 C240 82 360 82 490 74', 12, 'd', 4, 0.8)}
    ${fold(p, c, 'M110 8 C240 16 360 16 490 8', 8, 'l', 4, 0.8)}
  </g>
  ${seam('M112 74 C240 82 360 82 488 74', 0.3, 1.6)}
  <circle cx="284" cy="52" r="5" fill="${mix(base, '#000', 0.4)}"/><circle cx="316" cy="52" r="5" fill="${mix(base, '#000', 0.4)}"/>
  ${['M284 52 C276 100 290 140 278 200', 'M316 52 C326 96 312 150 326 190']
    .map(
      (d) => `<path d="${d}" fill="none" stroke="#000" stroke-width="10" opacity="0.3" filter="url(#${p}-b4)" transform="translate(3 5)"/>
      <path d="${d}" fill="none" stroke="${mix(base, '#000', 0.12)}" stroke-width="8" stroke-linecap="round"/>
      <path d="${d}" fill="none" stroke="#fff" stroke-width="2.5" opacity="${c.hi * 1.2}" stroke-linecap="round" transform="translate(-1.2 0)"/>`
    )
    .join('')}
  <path d="${JOG.cuffL}" fill="${base}"/>
  <path d="${JOG.cuffL}" fill="${base}" transform="translate(600 0) scale(-1 1)"/>
  <g clip-path="url(#${p}-cuffs)">
    <rect x="130" y="890" width="350" height="100" fill="url(#${p}-rib)"/>
    <path d="${JOG.legs}" fill="url(#${p}-leg)" opacity="0.8" transform="translate(0 40)"/>
    ${fold(p, c, 'M160 898 L264 898', 10, 'd', 4, 0.9)}${fold(p, c, 'M336 898 L440 898', 10, 'd', 4, 0.9)}
  </g>
  <g clip-path="url(#${p}-all)"><rect width="${W}" height="${H}" fill="url(#${p}-vert)"/></g>
  ${fabric(p, c, W, H, `${p}-all`)}
  <g filter="url(#${p}-emb)">
    <text x="404" y="168" text-anchor="middle" font-family="Montserrat, Segoe UI, Arial" font-weight="700" font-size="16" letter-spacing="2" fill="${ink}" opacity="0.95" transform="rotate(-2 404 168)">КНУ · 1925</text>
  </g>
`;
  return { svg, W, H };
}

// ---------------------------------------------------------------- cap (local 760 x 560), 3/4 front-left view
async function cap(colorKey) {
  const c = COLORS[colorKey];
  const p = uid('c');
  const W = 760, H = 560;
  const base = c.base;
  const CROWN = 'M246 362 C206 250 250 118 392 90 C506 68 616 108 656 212 C678 272 672 332 654 374 C560 404 380 410 246 366 Z';
  const BILL = 'M250 356 C160 352 70 380 44 424 C26 458 104 484 236 474 C344 466 440 432 482 390 C400 392 310 380 250 356 Z';
  const BILL_EDGE = 'M44 424 C26 458 104 484 236 474 C344 466 440 432 482 390';
  const FRONT_SEAM = 'M452 84 C392 150 356 250 352 386';
  const SIDE_SEAM = 'M452 84 C506 150 548 260 556 390';
  const SIDE_SEAM2 = 'M452 84 C552 120 620 200 650 300';
  const sil = `<path d="${CROWN}"/><path d="${BILL}"/>`;
  const logo = await warpedLogoURI(c.inkName, 360, 55);
  const mask = await warpedLogoURI('white', 360, 55);
  const lh = 148, lw = lh * (logo.w / logo.h) * 0.86;
  const lcx = 318, lcy = 232;
  const lx = lcx - lw / 2, ly = lcy - lh / 2;
  const defs = `<defs>${commonDefs(p, c, W, H)}
    <clipPath id="${p}-crown"><path d="${CROWN}"/></clipPath>
    <clipPath id="${p}-bill"><path d="${BILL}"/></clipPath>
    <clipPath id="${p}-all">${sil}</clipPath>
    <radialGradient id="${p}-dome" cx="0.3" cy="0.3" r="0.85">
      <stop offset="0" stop-color="#fff" stop-opacity="${c.hi * 1.2}"/>
      <stop offset="0.4" stop-color="#fff" stop-opacity="0"/>
      <stop offset="0.75" stop-color="#000" stop-opacity="${c.shade * 0.35}"/>
      <stop offset="1" stop-color="#000" stop-opacity="${c.shade * 0.85}"/>
    </radialGradient>
    <linearGradient id="${p}-billG" x1="0" x2="1" y1="0" y2="1">
      <stop offset="0" stop-color="#fff" stop-opacity="${c.hi * 0.9}"/>
      <stop offset="0.5" stop-color="#fff" stop-opacity="0"/>
      <stop offset="1" stop-color="#000" stop-opacity="${c.shade * 0.5}"/>
    </linearGradient>
    <mask id="${p}-lm" maskUnits="userSpaceOnUse" x="0" y="0" width="${W}" height="${H}"><image href="${mask.uri}" x="${lx}" y="${ly}" width="${lw}" height="${lh}" preserveAspectRatio="none" transform="rotate(-6 ${lcx} ${lcy})"/></mask>
  </defs>`;
  const svg = `${defs}
  ${groundShadow(p, `<g fill="#000">${sil}</g>`, { dy: 18, blur: 20, o: 0.22 })}
  <path d="${CROWN}" fill="${base}"/>
  <g clip-path="url(#${p}-crown)">
    ${fold(p, c, FRONT_SEAM, 26, 'd', 12, 0.35)}
    ${seam(FRONT_SEAM, 0.35, 2)}${seam(SIDE_SEAM, 0.35, 2)}${seam(SIDE_SEAM2, 0.3, 2)}
    ${stitch('M444 86 C384 152 348 252 344 386', c)}${stitch('M460 86 C400 152 364 252 360 386', c)}
    ${stitch('M460 86 C514 152 556 262 564 390', c, 0.8)}${stitch('M444 86 C498 152 540 262 548 390', c, 0.8)}
    ${fold(p, c, SIDE_SEAM, 20, 'd', 12, 0.4)}
    ${fold(p, c, 'M250 360 C360 392 560 396 656 372', 26, 'd', 12, 0.5)}
  </g>
  <g filter="url(#${p}-emb)"><image href="${logo.uri}" x="${lx}" y="${ly}" width="${lw}" height="${lh}" preserveAspectRatio="none" opacity="0.95" transform="rotate(-6 ${lcx} ${lcy})"/></g>
  <rect x="${lx - 20}" y="${ly - 20}" width="${lw + 40}" height="${lh + 40}" fill="url(#${p}-thread)" mask="url(#${p}-lm)"/>
  <g clip-path="url(#${p}-crown)">
    <path d="${CROWN}" fill="url(#${p}-dome)"/>
    ${fold(p, c, 'M246 362 C206 250 250 118 392 90', 24, 'l', 12, 0.5)}
    ${fold(p, c, 'M656 212 C678 272 672 332 654 374', 30, 'd', 12, 0.8)}
    <ellipse cx="560" cy="176" rx="6" ry="8" fill="${mix(base, '#000', 0.5)}" transform="rotate(-20 560 176)"/>
    <ellipse cx="560" cy="176" rx="6" ry="8" fill="none" stroke="${mix(base, '#fff', 0.12)}" stroke-width="2.4" transform="rotate(-20 560 176)"/>
  </g>
  ${fabric(p, c, W, H, `${p}-crown`)}
  <ellipse cx="452" cy="86" rx="20" ry="9" fill="${mix(base, '#000', 0.15)}" transform="rotate(-8 452 86)"/>
  <ellipse cx="450" cy="83" rx="16" ry="6" fill="${mix(base, '#fff', 0.1)}" transform="rotate(-8 450 83)"/>
  <!-- bill -->
  <path d="${BILL}" fill="#000" opacity="0.4" filter="url(#${p}-b7)" transform="translate(4 8)"/>
  <path d="${BILL_EDGE}" fill="none" stroke="${mix(base, '#000', 0.35)}" stroke-width="10" stroke-linecap="round" transform="translate(0 4)"/>
  <path d="${BILL}" fill="${base}"/>
  <g clip-path="url(#${p}-bill)">
    <path d="${BILL}" fill="url(#${p}-billG)"/>
    ${[0, 1, 2, 3, 4].map((i) => stitch(BILL_EDGE, c, 1.3).replace('<path ', `<path transform="translate(${6 + i * 6} ${-12 - i * 13}) scale(${1 - i * 0.02} 1)" `)).join('')}
    ${fold(p, c, 'M250 360 C310 384 400 394 482 392', 18, 'd', 7, 0.8)}
    ${fold(p, c, 'M70 430 C120 410 180 404 240 404', 30, 'l', 20, 0.6)}
  </g>
  ${fabric(p, c, W, H, `${p}-bill`)}
  ${seam('M250 358 C310 382 400 392 482 390', 0.4, 2)}`;
  return { svg, W, H };
}

// ---------------------------------------------------------------- mug (local 680 x 640)
async function mug() {
  const p = uid('m');
  const W = 680, H = 640;
  const BODY = 'M130 120 L130 528 C130 566 450 566 450 528 L450 120 Z';
  const HANDLE = 'M446 196 C566 182 590 300 570 384 C556 444 506 478 446 472 L446 430 C494 430 524 404 532 362 C544 300 528 236 446 238 Z';
  const logo = await warpedLogoURI('color', 420, 42);
  const lh = 210, lw = lh * (logo.w / logo.h);
  const lx = 290 - lw / 2, ly = 318 - lh / 2;
  const defs = `<defs>${commonDefs(p, null, W, H)}
    <clipPath id="${p}-body"><path d="${BODY}"/></clipPath>
    <linearGradient id="${p}-cyl" x1="130" x2="450" y1="0" y2="0" gradientUnits="userSpaceOnUse">
      <stop offset="0" stop-color="#000" stop-opacity="0.2"/>
      <stop offset="0.1" stop-color="#000" stop-opacity="0.1"/>
      <stop offset="0.3" stop-color="#000" stop-opacity="0"/>
      <stop offset="0.55" stop-color="#000" stop-opacity="0.02"/>
      <stop offset="0.8" stop-color="#000" stop-opacity="0.11"/>
      <stop offset="0.94" stop-color="#000" stop-opacity="0.2"/>
      <stop offset="1" stop-color="#000" stop-opacity="0.13"/>
    </linearGradient>
    <linearGradient id="${p}-spec" x1="0" x2="1" y1="0" y2="0">
      <stop offset="0" stop-color="#fff" stop-opacity="0"/><stop offset="0.5" stop-color="#fff" stop-opacity="0.85"/><stop offset="1" stop-color="#fff" stop-opacity="0"/>
    </linearGradient>
    <linearGradient id="${p}-hdl" x1="0" x2="1" y1="0" y2="0">
      <stop offset="0" stop-color="#000" stop-opacity="0.22"/><stop offset="0.5" stop-color="#000" stop-opacity="0.02"/><stop offset="1" stop-color="#000" stop-opacity="0.2"/>
    </linearGradient>
    <linearGradient id="${p}-in" x1="0" x2="0" y1="0" y2="1">
      <stop offset="0" stop-color="#E9E9E5"/><stop offset="1" stop-color="#C9C9C4"/>
    </linearGradient>
  </defs>`;
  const sil = `<path d="${BODY}"/><path d="${HANDLE}"/>`;
  const svg = `${defs}
  ${groundShadow(p, `<g fill="#000">${sil}</g>`, { dy: 14, blur: 20, o: 0.2 })}
  <ellipse cx="300" cy="552" rx="200" ry="22" fill="#000" opacity="0.25" filter="url(#${p}-b12)"/>
  <path d="${HANDLE}" fill="#FBFBF9"/>
  <path d="${HANDLE}" fill="url(#${p}-hdl)"/>
  <path d="M470 214 C548 214 566 300 548 372" fill="none" stroke="#fff" stroke-width="6" opacity="0.9" filter="url(#${p}-b2)"/>
  <path d="${BODY}" fill="#FBFBF9"/>
  <image href="${logo.uri}" x="${lx}" y="${ly}" width="${lw}" height="${lh}"/>
  <g clip-path="url(#${p}-body)">
    <rect x="120" y="90" width="340" height="500" fill="url(#${p}-cyl)"/>
    <rect x="196" y="110" width="26" height="440" fill="url(#${p}-spec)" opacity="0.8" filter="url(#${p}-b4)"/>
    <rect x="420" y="110" width="12" height="440" fill="url(#${p}-spec)" opacity="0.35" filter="url(#${p}-b4)"/>
    <path d="M130 528 C130 566 450 566 450 528" fill="none" stroke="#000" stroke-width="14" opacity="0.12" filter="url(#${p}-b4)"/>
    <path d="M436 300 L452 300" stroke="#000" stroke-width="0"/>
  </g>
  <ellipse cx="290" cy="120" rx="160" ry="34" fill="#FBFBF9"/>
  <ellipse cx="290" cy="122" rx="148" ry="28" fill="url(#${p}-in)"/>
  <path d="M142 122 C150 104 430 104 438 122" fill="none" stroke="#000" stroke-width="10" opacity="0.12" filter="url(#${p}-b4)" transform="translate(0 6)"/>
  <ellipse cx="290" cy="120" rx="160" ry="34" fill="none" stroke="#fff" stroke-width="3"/>
  <ellipse cx="290" cy="120" rx="160" ry="34" fill="none" stroke="#000" stroke-width="1" opacity="0.08"/>
  `;
  return { svg, W, H };
}

// ---------------------------------------------------------------- keychain (local 600 x 900)
async function keychain() {
  const p = uid('k');
  const W = 600, H = 900;
  const logo = await logoURI('color', 600);
  const defs = `<defs>${commonDefs(p, null, W, H)}
    <linearGradient id="${p}-metal" x1="0" x2="1" y1="0" y2="1">
      <stop offset="0" stop-color="#EDEDEB"/><stop offset="0.3" stop-color="#8C8C89"/><stop offset="0.5" stop-color="#DCDCD9"/>
      <stop offset="0.72" stop-color="#6F6F6C"/><stop offset="1" stop-color="#C4C4C1"/>
    </linearGradient>
    <linearGradient id="${p}-metal2" x1="1" x2="0" y1="0" y2="1">
      <stop offset="0" stop-color="#F7F7F5"/><stop offset="0.35" stop-color="#9C9C99"/><stop offset="0.6" stop-color="#E4E4E1"/><stop offset="1" stop-color="#7E7E7B"/>
    </linearGradient>
    <radialGradient id="${p}-gloss" cx="0.3" cy="0.22" r="0.75">
      <stop offset="0" stop-color="#fff" stop-opacity="0.55"/><stop offset="0.45" stop-color="#fff" stop-opacity="0.05"/><stop offset="1" stop-color="#000" stop-opacity="0.12"/>
    </radialGradient>
    <clipPath id="${p}-face"><circle cx="300" cy="560" r="214"/></clipPath>
  </defs>`;
  const sil = `<circle cx="300" cy="560" r="232"/><circle cx="300" cy="150" r="86"/><circle cx="300" cy="306" r="30"/>`;
  const svg = `${defs}
  <g transform="translate(0 12)" filter="url(#${p}-b20)" opacity="0.22"><circle cx="300" cy="560" r="232"/></g>
  <g transform="translate(0 3)" filter="url(#${p}-b4)" opacity="0.18"><circle cx="300" cy="560" r="232"/></g>
  <g filter="url(#${p}-b7)" opacity="0.2" transform="translate(0 10)"><circle cx="300" cy="150" r="80" fill="none" stroke="#000" stroke-width="14"/></g>
  <!-- split ring -->
  <circle cx="300" cy="150" r="80" fill="none" stroke="url(#${p}-metal)" stroke-width="13"/>
  <circle cx="302" cy="152" r="73" fill="none" stroke="url(#${p}-metal2)" stroke-width="5" stroke-dasharray="380 80" stroke-dashoffset="-40"/>
  <circle cx="300" cy="150" r="86.5" fill="none" stroke="#000" stroke-width="1" opacity="0.18"/>
  <circle cx="300" cy="150" r="73.5" fill="none" stroke="#000" stroke-width="1" opacity="0.18"/>
  <!-- jump ring -->
  <ellipse cx="300" cy="262" rx="20" ry="36" fill="none" stroke="url(#${p}-metal2)" stroke-width="8"/>
  <ellipse cx="300" cy="262" rx="20" ry="36" fill="none" stroke="#000" stroke-width="1" opacity="0.2"/>
  <!-- tab -->
  <path d="M262 340 C262 300 338 300 338 340 L338 352 L262 352 Z" fill="url(#${p}-metal)"/>
  <circle cx="300" cy="320" r="10" fill="${BG}"/>
  <circle cx="300" cy="320" r="10" fill="none" stroke="#000" stroke-width="2" opacity="0.25"/>
  <!-- badge -->
  <circle cx="300" cy="560" r="232" fill="#9A9A97"/>
  <circle cx="300" cy="560" r="223" fill="none" stroke="url(#${p}-metal)" stroke-width="18"/>
  <circle cx="300" cy="560" r="231.5" fill="none" stroke="#000" stroke-width="1" opacity="0.25"/>
  <circle cx="300" cy="560" r="227" fill="none" stroke="#fff" stroke-width="1.5" opacity="0.35"/>
  <circle cx="300" cy="560" r="214.5" fill="#000" opacity="0.45" filter="url(#${p}-b2)"/>
  <circle cx="300" cy="560" r="214" fill="#FDFDFB"/>
  <image href="${logo}" x="84" y="344" width="432" height="432" clip-path="url(#${p}-face)"/>
  <g clip-path="url(#${p}-face)">
    <circle cx="300" cy="560" r="214" fill="url(#${p}-gloss)"/>
    <circle cx="300" cy="560" r="214" fill="none" stroke="#000" stroke-width="8" opacity="0.18" filter="url(#${p}-b4)"/>
    <path d="M140 470 C180 390 250 360 320 356" fill="none" stroke="#fff" stroke-width="14" opacity="0.5" stroke-linecap="round" filter="url(#${p}-b4)"/>
  </g>`;
  void sil;
  return { svg, W, H };
}

// ---------------------------------------------------------------- composition
function place(g, x, y, s, rot = 0) {
  // x,y = position of the local-box centre on canvas
  return `<g transform="translate(${x} ${y}) rotate(${rot}) scale(${s}) translate(${-g.W / 2} ${-g.H / 2})">${g.svg}</g>`;
}

// Hoodie/sweatshirt laid over joggers, the pair centred at (cx, cy).
function outfit(top, jog, topY, s, cx, cy) {
  const overlap = 110; // top hem covers the joggers' waistband
  const total = (912 - topY + 978 - overlap) * s;
  const y0 = cy - total / 2;
  const topCy = y0 + (500 - topY) * s;
  const jogTop = y0 + (912 - topY - overlap) * s;
  return [place(jog, cx, jogTop + (500 - 6) * s, s), place(top, cx, topCy, s)];
}

async function render(file, w, h, items, fmt = 'webp') {
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="${w}" height="${h}" viewBox="0 0 ${w} ${h}">
  <rect width="${w}" height="${h}" fill="${BG}"/>${items.join('\n')}</svg>`;
  if (PREVIEW) await fs.writeFile(file.replace(/\.\w+$/, '.svg'), svg);
  // render at 2x for smooth edges, then downsample
  const img = sharp(Buffer.from(svg), { density: 144, limitInputPixels: false }).resize(w, h, { kernel: 'lanczos3' }).flatten({ background: BG });
  if (fmt === 'webp') await img.webp(WEBP).toFile(file);
  else await img.png().toFile(file);
  console.log('  ✓', path.relative(ROOT, file));
}

const want = (name) => !ONLY || name.includes(ONLY);

async function main() {
  await fs.mkdir(OUT_P, { recursive: true });
  await prepareLogo();
  if (want('logo')) await buildLogoFiles();

  const PW = 1200, PH = 1500;
  for (const col of ['grey', 'navy', 'black']) {
    for (const kind of ['hoodie', 'sweatshirt']) {
      const slug = `set-${kind}`;
      const hood = kind === 'hoodie';
      const topY = hood ? 10 : 182; // local top of the garment (hood / collar)
      if (want(`${slug}-${col}-1`)) {
        NS = 1.24; const top = await topGarment(col, hood ? 'hoodie' : 'sweat');
        const s = 1.24, mid = (topY + 912) / 2;
        await render(path.join(OUT_P, `${slug}-${col}-1.webp`), PW, PH, [place(top, 600, 750 + (500 - mid) * s, s)]);
      }
      if (want(`${slug}-${col}-2`)) {
        NS = 0.8; const top = await topGarment(col, hood ? 'hoodie' : 'sweat'); const jog = await joggers(col);
        const ty = hood ? 0 : 50; // sweatshirt has no hood: shift it down a bit
        await render(path.join(OUT_P, `${slug}-${col}-2.webp`), PW, PH, [
          place(jog, 895, 1030, 0.8, 5),
          place(top, 410, 505 + ty, 0.8, -4),
        ]);
      }
    }
  }
  for (const col of ['navy', 'black']) {
    if (want(`cap-${col}-1`)) await render(path.join(OUT_P, `cap-${col}-1.webp`), PW, PH, (NS = 1.4, [place(await cap(col), 566, 770, 1.55)]));
  }
  if (want('mug-white-1')) await render(path.join(OUT_P, 'mug-white-1.webp'), PW, PH, [place(await mug(), 600, 742, 1.7)]);
  if (want('keychain-blue-1')) await render(path.join(OUT_P, 'keychain-blue-1.webp'), PW, PH, [place(await keychain(), 600, 790, 1.42, -6)]);

  if (want('hero')) {
    // Flat-lay of the navy hoodie set plus a cap; one colourway keeps the hero calm.
    NS = 0.9;
    const n = await topGarment('navy', 'hoodie'), nj = await joggers('navy');
    NS = 0.6;
    const k = await cap('black');
    await render(path.join(OUT, 'hero.webp'), 1600, 1200, [
      place(nj, 1150, 650, 0.9, 6),
      place(n, 640, 565, 0.9, -4),
      place(k, 380, 1040, 0.6, -8),
    ]);
  }
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
