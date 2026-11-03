// Imports generated product photos from <repo>/incoming into the theme.
// Usage: node tools/mockups/import.mjs
// File names must match the targets: set-hoodie-navy-1.png, hero.jpg, etc.
import { readdir, mkdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const incoming = path.join(root, 'incoming');
const imgDir = path.join(root, 'theme/knu-merch/assets/img');
const productDir = path.join(imgDir, 'products');
const BG = '#F5F5F3';

const PRODUCT = /^(set-hoodie|set-sweatshirt)-(grey|navy|black)-[12]$|^cap-(navy|black)-1$|^mug-white-1$|^keychain-blue-1$/;

await mkdir(productDir, { recursive: true });
const files = (await readdir(incoming)).filter((f) => /\.(png|jpe?g|webp)$/i.test(f));
const done = [];
const skipped = [];

for (const file of files) {
	const name = path.parse(file).name.toLowerCase();
	const src = path.join(incoming, file);
	let out;
	let size;
	if (name === 'hero') {
		out = path.join(imgDir, 'hero.webp');
		size = { width: 1600, height: 1200 };
	} else if (PRODUCT.test(name)) {
		out = path.join(productDir, `${name}.webp`);
		size = { width: 1200, height: 1500 };
	} else {
		skipped.push(file);
		continue;
	}
	await sharp(src)
		.flatten({ background: BG })
		.resize({ ...size, fit: 'cover', position: 'centre' })
		.webp({ quality: 82 })
		.toFile(out);
	done.push(path.relative(root, out));
}

console.log(`Imported ${done.length}:\n  ${done.join('\n  ')}`);
if (skipped.length) console.log(`Skipped (unknown name): ${skipped.join(', ')}`);
