// Produce a single self-contained HTML file from a preview URL by inlining all
// same-origin stylesheets and images as data URIs. The result has no external
// dependencies, so it can be published as an Artifact (strict CSP) or opened
// offline while still looking like the running app.
//
// Usage:
//   node tools/preview/bundle.mjs <url> [--out=file.html]
//
// Example:
//   node tools/preview/bundle.mjs "http://127.0.0.1:8400/?t=portal/login/autologin.html.twig&p=tools/preview/params/autologin.json" --out=tools/preview/out/autologin.html

import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';

function parseArgs(argv) {
  const args = { url: null, out: 'tools/preview/out/bundle.html' };
  for (const a of argv.slice(2)) {
    if (a.startsWith('--out=')) args.out = a.slice('--out='.length);
    else if (!a.startsWith('--') && args.url === null) args.url = a;
  }
  return args;
}

const args = parseArgs(process.argv);
if (!args.url) {
  console.error('Usage: node tools/preview/bundle.mjs <url> [--out=file.html]');
  process.exit(1);
}

const executablePath = process.env.PLAYWRIGHT_CHROMIUM_PATH
  || '/opt/pw-browsers/chromium';

const browser = await chromium.launch({ executablePath, args: ['--no-sandbox'] });

try {
  const page = await browser.newPage();
  await page.goto(args.url, { waitUntil: 'networkidle' });

  // Inline resources from inside the page: same-origin fetch() is allowed, and
  // the browser has already resolved every relative URL for us.
  const html = await page.evaluate(async () => {
    const toDataUri = async (url) => {
      try {
        const res = await fetch(url);
        const blob = await res.blob();
        return await new Promise((ok) => {
          const fr = new FileReader();
          fr.onload = () => ok(fr.result);
          fr.readAsDataURL(blob);
        });
      } catch {
        return null;
      }
    };

    // Replace <link rel="stylesheet"> with inline <style>.
    for (const link of Array.from(document.querySelectorAll('link[rel="stylesheet"]'))) {
      try {
        const css = await (await fetch(link.href)).text();
        const style = document.createElement('style');
        style.textContent = css;
        link.replaceWith(style);
      } catch {
        // Leave unresolved links out entirely so the Artifact CSP has nothing
        // external to block.
        link.remove();
      }
    }

    // Inline <img src> and <script src> as data URIs.
    for (const img of Array.from(document.querySelectorAll('img[src]'))) {
      const data = await toDataUri(img.src);
      if (data) img.setAttribute('src', data);
    }
    for (const s of Array.from(document.querySelectorAll('script[src]'))) {
      const data = await toDataUri(s.src);
      if (data) s.setAttribute('src', data);
      else s.remove();
    }

    return '<!DOCTYPE html>\n' + document.documentElement.outerHTML;
  });

  const outPath = resolve(args.out);
  mkdirSync(dirname(outPath), { recursive: true });
  writeFileSync(outPath, html);
  console.log(outPath);
} finally {
  await browser.close();
}
