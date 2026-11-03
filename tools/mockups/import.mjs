// Imports generated product photos from <repo>/incoming into the theme.
// Usage: node tools/mockups/import.mjs
//
// Product images are 1000x1500 (2:3). Sources with another aspect are placed
// whole ("contain") and the canvas is padded by repeating the edge pixels, so
// the plain studio background continues without a visible seam.
import { mkdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const incoming = path.join(root, 'incoming');
const imgDir = path.join(root, 'theme/knu-merch/assets/img');
const productDir = path.join(imgDir, 'products');

const PRODUCT = { width: 1000, height: 1500 };
const HERO = { width: 1600, height: 1200 };

// target file (relative to imgDir) -> { src, crop?, fit }
const JOBS = {
	'hero.webp': { src: 'collection-navy.webp', fit: 'cover', size: HERO },
	'products/set-hoodie-navy-1.webp': { src: 'set-hoodie-navy-1.webp', fit: 'cover' },
	'products/set-hoodie-navy-2.webp': { src: 'set-hoodie-navy-2.webp', fit: 'cover' },
	'products/set-hoodie-grey-1.webp': { src: 'set-hoodie-grey-1.webp', fit: 'cover' },
	'products/set-hoodie-grey-2.webp': { src: 'set-hoodie-grey-2.webp', fit: 'cover' },
	// The sweatshirt exists only in the collection shots: close crop + whole collection.
	'products/set-sweatshirt-navy-1.webp': { src: 'collection-navy.webp', crop: { left: 40, top: 40, width: 540, height: 810 }, fit: 'cover' },
	'products/set-sweatshirt-navy-2.webp': { src: 'collection-navy.webp', fit: 'contain' },
	'products/set-sweatshirt-grey-1.webp': { src: 'collection-grey.webp', crop: { left: 40, top: 40, width: 540, height: 810 }, fit: 'cover' },
	'products/set-sweatshirt-grey-2.webp': { src: 'collection-grey.webp', fit: 'contain' },
	'products/cap-navy-1.webp': { src: 'cap-navy-1.webp', fit: 'contain' },
	'products/cap-grey-1.webp': { src: 'cap-grey-1.webp', fit: 'contain' },
	'products/mug-white-1.webp': { src: 'mug-white-1.webp', fit: 'contain' },
	'products/mug-navy-1.webp': { src: 'mug-navy-1.webp', fit: 'contain' },
	'products/keychain-blue-1.webp': { src: 'keychain-blue-1.webp', fit: 'cover' },
};

async function render(job, size) {
	let input = sharp(path.join(incoming, job.src));
	if (job.crop) input = input.extract(job.crop);
	const buf = await input.toBuffer();
	if (job.fit === 'cover') {
		return sharp(buf).resize({ ...size, fit: 'cover', position: 'centre' });
	}
	const { data, info } = await sharp(buf).resize({ ...size, fit: 'inside' }).toBuffer({ resolveWithObject: true });
	const padX = size.width - info.width;
	const padY = size.height - info.height;
	return sharp(data).extend({
		left: Math.floor(padX / 2),
		right: Math.ceil(padX / 2),
		top: Math.floor(padY / 2),
		bottom: Math.ceil(padY / 2),
		extendWith: 'copy',
	});
}

await mkdir(productDir, { recursive: true });
for (const [target, job] of Object.entries(JOBS)) {
	const out = path.join(imgDir, target);
	const img = await render(job, job.size ?? PRODUCT);
	await img.webp({ quality: 84 }).toFile(out);
	console.log('wrote', path.relative(root, out));
}
