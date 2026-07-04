import { copyFileSync, mkdirSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');

const files = [
    ['resources/js/calls.js', 'public/js/calls.js'],
    ['public/css/call-ui.css', 'public/css/call-ui.css'], // already in public
];

for (const [fromRel, toRel] of files) {
    const from = join(root, fromRel);
    const to = join(root, toRel);
    if (!existsSync(from)) {
        console.warn('skip missing', fromRel);
        continue;
    }
    mkdirSync(dirname(to), { recursive: true });
    if (from !== to) {
        copyFileSync(from, to);
        console.log('published', toRel);
    }
}

console.log('Call assets ready in public/js and public/css');
