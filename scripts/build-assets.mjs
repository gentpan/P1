import { readFile, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import { transform } from 'lightningcss';
import { minify } from 'terser';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const asset = (name) => resolve(root, 'assets', name);

for (const name of ['main', 'admin']) {
  const source = await readFile(asset(`${name}.js`), 'utf8');
  const result = await minify(source, {
    compress: true,
    mangle: true,
    format: { comments: false },
  });
  if (!result.code) throw new Error(`Could not minify ${name}.js`);
  await writeFile(asset(`${name}.min.js`), `${result.code}\n`);
}

const adminCss = transform({
  filename: asset('admin.css'),
  code: await readFile(asset('admin.css')),
  minify: true,
}).code;
await writeFile(asset('admin.min.css'), adminCss);
