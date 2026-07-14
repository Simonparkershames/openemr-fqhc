// Certify that a Dockerless preview matches the real running app, by
// screenshotting both at the same viewport and producing a pixel diff.
//
// Use this during a one-time Docker run: point --preview at the preview
// dev-server and --live at the same template in the running app, and check the
// reported mismatch percentage. If it's ~0, you can trust the fast preview loop
// for that template; if it diverges, that template needs the full app.
//
// Usage:
//   node tools/preview/paritydiff.mjs --preview=<url> --live=<url> [--viewport=WxH] [--out=dir] [--full]
//
// Example:
//   node tools/preview/paritydiff.mjs \
//     --preview="http://127.0.0.1:8400/?t=portal/login/autologin.html.twig&p=tools/preview/params/autologin.json" \
//     --live="http://localhost:8300/portal/index.php?site=default&...whatever renders the same view..." \
//     --viewport=1280x800
//
// Outputs preview.png, live.png, diff.png (differing pixels highlighted) and a
// side-by-side composite.png in the output dir, and prints the mismatch %.
// The diff is computed in-browser via canvas, so no extra npm deps are needed.

import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

function parseArgs(argv) {
  const args = { preview: null, live: null, viewport: '1280x800', out: 'tools/preview/out/parity', full: false };
  for (const a of argv.slice(2)) {
    if (a.startsWith('--preview=')) args.preview = a.slice('--preview='.length);
    else if (a.startsWith('--live=')) args.live = a.slice('--live='.length);
    else if (a.startsWith('--viewport=')) args.viewport = a.slice('--viewport='.length);
    else if (a.startsWith('--out=')) args.out = a.slice('--out='.length);
    else if (a === '--full') args.full = true;
  }
  return args;
}

const args = parseArgs(process.argv);
if (!args.preview || !args.live) {
  console.error('Usage: node tools/preview/paritydiff.mjs --preview=<url> --live=<url> [--viewport=WxH] [--out=dir] [--full]');
  process.exit(1);
}

const [w, h] = args.viewport.split('x').map((n) => parseInt(n, 10));
const executablePath = process.env.PLAYWRIGHT_CHROMIUM_PATH || '/opt/pw-browsers/chromium';
mkdirSync(args.out, { recursive: true });

const browser = await chromium.launch({ executablePath, args: ['--no-sandbox'] });

async function shoot(url) {
  const page = await browser.newPage({ viewport: { width: w, height: h } });
  await page.goto(url, { waitUntil: 'networkidle' });
  const buf = await page.screenshot({ fullPage: args.full });
  await page.close();
  return buf;
}

try {
  const [previewBuf, liveBuf] = await Promise.all([shoot(args.preview), shoot(args.live)]);
  writeFileSync(resolve(args.out, 'preview.png'), previewBuf);
  writeFileSync(resolve(args.out, 'live.png'), liveBuf);

  // Compute the diff in a headless page using canvas (no extra deps).
  const page = await browser.newPage();
  const result = await page.evaluate(async ([previewB64, liveB64]) => {
    const load = (b64) => new Promise((ok) => {
      const img = new Image();
      img.onload = () => ok(img);
      img.src = 'data:image/png;base64,' + b64;
    });
    const [a, b] = await Promise.all([load(previewB64), load(liveB64)]);
    const width = Math.max(a.width, b.width);
    const height = Math.max(a.height, b.height);

    const ctx = (im) => {
      const c = document.createElement('canvas');
      c.width = width; c.height = height;
      const x = c.getContext('2d');
      x.fillStyle = '#fff'; x.fillRect(0, 0, width, height);
      x.drawImage(im, 0, 0);
      return { canvas: c, data: x.getImageData(0, 0, width, height) };
    };
    const A = ctx(a), B = ctx(b);

    const diffCanvas = document.createElement('canvas');
    diffCanvas.width = width; diffCanvas.height = height;
    const dctx = diffCanvas.getContext('2d');
    const diff = dctx.createImageData(width, height);

    let changed = 0;
    const threshold = 32; // per-channel tolerance for anti-aliasing/subpixel
    for (let i = 0; i < A.data.data.length; i += 4) {
      const dr = Math.abs(A.data.data[i] - B.data.data[i]);
      const dg = Math.abs(A.data.data[i + 1] - B.data.data[i + 1]);
      const db = Math.abs(A.data.data[i + 2] - B.data.data[i + 2]);
      if (dr > threshold || dg > threshold || db > threshold) {
        changed++;
        diff.data[i] = 255; diff.data[i + 1] = 0; diff.data[i + 2] = 0; diff.data[i + 3] = 255;
      } else {
        // Faded original for context.
        diff.data[i] = A.data.data[i]; diff.data[i + 1] = A.data.data[i + 1];
        diff.data[i + 2] = A.data.data[i + 2]; diff.data[i + 3] = 60;
      }
    }
    dctx.fillStyle = '#fff'; dctx.fillRect(0, 0, width, height);
    dctx.putImageData(diff, 0, 0);

    // Side-by-side composite: preview | live | diff.
    const comp = document.createElement('canvas');
    comp.width = width * 3 + 20; comp.height = height;
    const cc = comp.getContext('2d');
    cc.fillStyle = '#ddd'; cc.fillRect(0, 0, comp.width, comp.height);
    cc.drawImage(A.canvas, 0, 0);
    cc.drawImage(B.canvas, width + 10, 0);
    cc.drawImage(diffCanvas, width * 2 + 20, 0);

    const total = width * height;
    return {
      mismatch: (changed / total) * 100,
      diff: diffCanvas.toDataURL('image/png').split(',')[1],
      composite: comp.toDataURL('image/png').split(',')[1],
    };
  }, [previewBuf.toString('base64'), liveBuf.toString('base64')]);
  await page.close();

  writeFileSync(resolve(args.out, 'diff.png'), Buffer.from(result.diff, 'base64'));
  writeFileSync(resolve(args.out, 'composite.png'), Buffer.from(result.composite, 'base64'));

  const pct = result.mismatch.toFixed(2);
  console.log(`mismatch: ${pct}%`);
  console.log(`  ${resolve(args.out, 'composite.png')}  (preview | live | diff)`);
  console.log(`  ${resolve(args.out, 'diff.png')}`);
  // Non-zero exit when divergence is clearly beyond anti-aliasing noise.
  process.exitCode = result.mismatch > 1 ? 2 : 0;
} finally {
  await browser.close();
}
