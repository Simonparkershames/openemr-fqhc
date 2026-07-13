// Screenshot a preview URL at one or more viewports using the pre-installed
// Chromium (Playwright). Produces PNG files you can review inline.
//
// Usage:
//   node tools/preview/shoot.mjs <url> [--out=dir] [--viewport=WxH ...] [--full]
//
// Examples:
//   node tools/preview/shoot.mjs "http://127.0.0.1:8400/?t=portal/login/autologin.html.twig&p=tools/preview/params/autologin.json"
//   node tools/preview/shoot.mjs "$URL" --viewport=1280x800 --viewport=375x812 --full
//
// Defaults to desktop (1280x800), tablet (768x1024) and mobile (375x812) if no
// --viewport is given. --full captures the entire scrollable page.

import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

function parseArgs(argv) {
  const args = { url: null, out: 'tools/preview/out', viewports: [], full: false };
  for (const a of argv.slice(2)) {
    if (a.startsWith('--out=')) args.out = a.slice('--out='.length);
    else if (a.startsWith('--viewport=')) args.viewports.push(a.slice('--viewport='.length));
    else if (a === '--full') args.full = true;
    else if (!a.startsWith('--') && args.url === null) args.url = a;
  }
  if (args.viewports.length === 0) {
    args.viewports = ['1280x800', '768x1024', '375x812'];
  }
  return args;
}

const args = parseArgs(process.argv);
if (!args.url) {
  console.error('Usage: node tools/preview/shoot.mjs <url> [--out=dir] [--viewport=WxH ...] [--full]');
  process.exit(1);
}

const executablePath = process.env.PLAYWRIGHT_CHROMIUM_PATH
  || '/opt/pw-browsers/chromium';

mkdirSync(args.out, { recursive: true });

const browser = await chromium.launch({
  executablePath,
  args: ['--no-sandbox'],
});

try {
  for (const vp of args.viewports) {
    const [w, h] = vp.split('x').map((n) => parseInt(n, 10));
    if (!Number.isFinite(w) || !Number.isFinite(h)) {
      console.error(`Skipping invalid viewport: ${vp}`);
      continue;
    }
    const page = await browser.newPage({ viewport: { width: w, height: h } });
    await page.goto(args.url, { waitUntil: 'networkidle' });
    const file = resolve(args.out, `preview-${w}x${h}.png`);
    await page.screenshot({ path: file, fullPage: args.full });
    await page.close();
    console.log(file);
  }
} finally {
  await browser.close();
}
