/**
 * FQHC Design System — Web Component library
 *
 * Dependency-free custom elements (no build step, no framework runtime) for the
 * FQHC UI. Each component uses Shadow DOM for style encapsulation and consumes
 * the design tokens from tokens.css (CSS custom properties cross the shadow
 * boundary). These islands mount inside the server-rendered OpenEMR shell
 * without touching any certified page.
 *
 * Components:
 *   <fqhc-page-header heading="..." subheading="..." icon="...">  [slot: actions]
 *   <fqhc-card heading="..." icon="..." span-wide>                [default slot]
 *   <fqhc-field-row label="..." value="...">                      (value="" => em-dash)
 *   <fqhc-status-badge variant="success|warning|danger|info|neutral" icon="..." no-icon>
 *   <fqhc-empty-state message="..." icon="...">                   [default slot]
 *
 * Icons: the `icon` attributes take a *semantic* name from fqhc-icons.js
 * (`patient`, `care-gap`, `report`, …), never a Font Awesome class. These
 * components emit `<fqhc-icon name="…">` into their shadow DOM and let it
 * upgrade itself, so there is no import between the two files and either can
 * be cache-busted alone. If fqhc-icons.js is absent the icon element stays
 * inert and empty — the text still reads correctly.
 *
 * Every icon rendered here is decorative and is never the only carrier of
 * meaning: a status badge keeps its text, a card keeps its heading.
 *
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

(() => {
  const html = (strings, ...values) => strings.reduce((acc, s, i) => acc + s + (values[i] ?? ''), '');

  // Helpers are declared before customElements.define(...) because define()
  // upgrades already-parsed elements synchronously and immediately calls
  // render(); anything render() touches must already be initialized (a const
  // declared lower in this IIFE would still be in its temporal dead zone).
  const ALLOWED_VARIANTS = ['success', 'warning', 'danger', 'info', 'neutral'];

  function cssClass(variant) {
    return ALLOWED_VARIANTS.includes(variant) ? variant : 'neutral';
  }

  function escapeHtml(value) {
    return String(value)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  /**
   * Markup for a decorative icon, or '' when no icon was asked for.
   *
   * Icon names are restricted to the shape fqhc-icons.js uses so a caller
   * cannot smuggle markup through an `icon` attribute; the icon element itself
   * ignores names it does not know.
   */
  function iconMarkup(name, className = 'icon') {
    if (typeof name !== 'string' || !/^[a-z][a-z0-9-]*$/.test(name)) {
      return '';
    }

    return `<fqhc-icon class="${className}" name="${name}"></fqhc-icon>`;
  }

  /**
   * The icon a status badge leads with: an explicit `icon` attribute wins,
   * otherwise the one its variant always uses, unless `no-icon` is set.
   */
  function badgeIcon(element, variant) {
    if (element.hasAttribute('no-icon')) {
      return '';
    }

    const explicit = element.getAttribute('icon');
    if (explicit !== null) {
      return iconMarkup(explicit);
    }

    return iconMarkup(window.fqhcIcons?.variantIcon(variant) ?? variant);
  }

  /** Shared base: renders a <style> + template into an open shadow root once. */
  class FqhcElement extends HTMLElement {
    constructor() {
      super();
      this.attachShadow({ mode: 'open' });
    }

    connectedCallback() {
      if (!this._rendered) {
        this.shadowRoot.innerHTML = this.render();
        this._rendered = true;
      }
    }

    render() {
      return '';
    }
  }

  customElements.define('fqhc-page-header', class extends FqhcElement {
    render() {
      const heading = this.getAttribute('heading') ?? '';
      const subheading = this.getAttribute('subheading') ?? '';
      const icon = iconMarkup(this.getAttribute('icon'));
      return html`
        <style>
          :host { display: block; margin-bottom: var(--fqhc-space-5); }
          .wrap {
            display: flex; flex-wrap: wrap; gap: var(--fqhc-space-3);
            align-items: flex-end; justify-content: space-between;
            padding-bottom: var(--fqhc-space-4);
            border-bottom: 1px solid var(--fqhc-border);
          }
          h1 {
            margin: 0; font-family: var(--fqhc-font-sans);
            font-size: var(--fqhc-font-size-2xl);
            font-weight: var(--fqhc-font-weight-semibold);
            color: var(--fqhc-text); letter-spacing: -0.01em;
            display: flex; align-items: center; gap: var(--fqhc-space-3);
          }
          h1 .icon { color: var(--fqhc-color-primary); font-size: 0.8em; }
          p {
            margin: var(--fqhc-space-1) 0 0; color: var(--fqhc-text-muted);
            font-family: var(--fqhc-font-sans); font-size: var(--fqhc-font-size-sm);
          }
        </style>
        <div class="wrap">
          <div>
            <h1>${icon}<span>${escapeHtml(heading)}</span></h1>
            ${subheading ? `<p>${escapeHtml(subheading)}</p>` : ''}
          </div>
          <slot name="actions"></slot>
        </div>
      `;
    }
  });

  customElements.define('fqhc-card', class extends FqhcElement {
    render() {
      const heading = this.getAttribute('heading') ?? '';
      const icon = iconMarkup(this.getAttribute('icon'));
      return html`
        <style>
          :host { display: block; }
          .card {
            background: var(--fqhc-surface-card);
            border: 1px solid var(--fqhc-border);
            border-radius: var(--fqhc-radius-lg);
            box-shadow: var(--fqhc-shadow-sm);
            overflow: hidden; height: 100%;
            transition: box-shadow var(--fqhc-transition), transform var(--fqhc-transition);
          }
          .card:hover { box-shadow: var(--fqhc-shadow-md); }
          .head {
            margin: 0;
            padding: var(--fqhc-space-4) var(--fqhc-space-5);
            border-bottom: 1px solid var(--fqhc-border);
            font-family: var(--fqhc-font-sans);
            font-size: var(--fqhc-font-size-xs);
            font-weight: var(--fqhc-font-weight-semibold);
            text-transform: uppercase; letter-spacing: 0.06em;
            color: var(--fqhc-color-primary-strong);
            display: flex; align-items: center; gap: var(--fqhc-space-2);
          }
          .head .icon { font-size: 1.25em; opacity: 0.85; }
          .body { padding: var(--fqhc-space-2) var(--fqhc-space-5) var(--fqhc-space-4); }
        </style>
        <div class="card">
          ${heading ? `<h2 class="head">${icon}<span>${escapeHtml(heading)}</span></h2>` : ''}
          <div class="body"><slot></slot></div>
        </div>
      `;
    }
  });

  customElements.define('fqhc-field-row', class extends FqhcElement {
    render() {
      const label = this.getAttribute('label') ?? '';
      const value = this.getAttribute('value');
      const hasValue = value !== null && value.trim() !== '';
      return html`
        <style>
          :host { display: block; }
          .row {
            display: flex; gap: var(--fqhc-space-4); justify-content: space-between;
            align-items: baseline;
            padding: var(--fqhc-space-3) 0;
            border-bottom: 1px solid var(--fqhc-surface-sunken);
            font-family: var(--fqhc-font-sans);
          }
          :host(:last-of-type) .row { border-bottom: none; }
          .label { color: var(--fqhc-text-muted); font-size: var(--fqhc-font-size-sm); }
          .value {
            color: var(--fqhc-text); font-weight: var(--fqhc-font-weight-medium);
            font-size: var(--fqhc-font-size-base); text-align: right;
          }
          .value.empty { color: var(--fqhc-border-strong); font-weight: var(--fqhc-font-weight-regular); }
        </style>
        <div class="row">
          <span class="label">${escapeHtml(label)}</span>
          <span class="value ${hasValue ? '' : 'empty'}">${hasValue ? escapeHtml(value) : '—'}</span>
        </div>
      `;
    }
  });

  customElements.define('fqhc-status-badge', class extends FqhcElement {
    render() {
      const variant = cssClass(this.getAttribute('variant') ?? 'neutral');
      const icon = badgeIcon(this, variant);
      return html`
        <style>
          :host { display: inline-block; }
          .badge {
            display: inline-flex; align-items: center; gap: var(--fqhc-space-1);
            padding: 2px var(--fqhc-space-3);
            border-radius: var(--fqhc-radius-pill);
            font-family: var(--fqhc-font-sans);
            font-size: var(--fqhc-font-size-xs);
            font-weight: var(--fqhc-font-weight-semibold);
            line-height: 1.6;
          }
          .success { background: var(--fqhc-color-success-soft); color: var(--fqhc-color-success); }
          .warning { background: var(--fqhc-color-warning-soft); color: var(--fqhc-color-warning); }
          .danger  { background: var(--fqhc-color-danger-soft);  color: var(--fqhc-color-danger); }
          .info    { background: var(--fqhc-color-info-soft);    color: var(--fqhc-color-info); }
          .neutral { background: var(--fqhc-color-neutral-soft); color: var(--fqhc-color-neutral); }
          .icon { font-size: 1.05em; }
        </style>
        <span class="badge ${variant}">${icon}<slot></slot></span>
      `;
    }
  });

  customElements.define('fqhc-empty-state', class extends FqhcElement {
    render() {
      const message = this.getAttribute('message') ?? '';
      // Defaults to the generic empty-tray icon so every empty state reads as
      // the same kind of moment; pass `icon` to name what is missing instead.
      const icon = iconMarkup(this.getAttribute('icon') ?? 'empty');
      return html`
        <style>
          :host { display: block; }
          .empty {
            display: flex; flex-direction: column; align-items: center; gap: var(--fqhc-space-2);
            text-align: center;
            padding: var(--fqhc-space-5) var(--fqhc-space-4);
            border: 1px dashed var(--fqhc-border-strong);
            border-radius: var(--fqhc-radius-md);
            background: var(--fqhc-surface-sunken);
            color: var(--fqhc-text-muted);
            font-family: var(--fqhc-font-sans); font-size: var(--fqhc-font-size-sm);
          }
          .icon {
            font-size: var(--fqhc-font-size-2xl);
            color: var(--fqhc-border-strong);
          }
        </style>
        <div class="empty">
          ${icon}
          <span>${escapeHtml(message)}</span>
          <slot></slot>
        </div>
      `;
    }
  });

  /* ==================================================================
   * v2 components (issue #62)
   *
   * The first five elements were enough to build a page of cards holding
   * text — which is exactly why every workspace home *was* a page of cards
   * holding text. These are the pieces a dashboard needs instead.
   *
   * Same rules as the originals: Shadow DOM, tokens only (no hard-coded
   * colour), no framework, server-render-friendly, keyboard accessible, and
   * `prefers-reduced-motion` respected — the motion token collapses to 0ms,
   * so anything animated must read correctly with no animation at all.
   * ================================================================== */

  /** Shared reset for the v2 elements, kept in one place. */
  const BASE_STYLE = `
    :host { display: block; font-family: var(--fqhc-font-sans); }
    * { box-sizing: border-box; }
  `;

  /**
   * <fqhc-stat value="128" label="Patients seen" delta="+12%" direction="up"
   *            icon="patient" href="..." sparkline="3,7,4,9,6,11">
   *
   * A metric tile. The provider and manager homes each hand-rolled a worse
   * version of this; `direction` colours the delta rather than the caller
   * choosing, so "up" never accidentally means green on a measure where up is
   * bad — pass `direction="up-bad"` for those.
   */
  customElements.define('fqhc-stat', class extends FqhcElement {
    render() {
      const value = this.getAttribute('value') ?? '—';
      const label = this.getAttribute('label') ?? '';
      const delta = this.getAttribute('delta');
      const direction = this.getAttribute('direction') ?? 'flat';
      const href = this.getAttribute('href');
      const icon = iconMarkup(this.getAttribute('icon'), 'stat-icon');
      const caption = this.getAttribute('caption');

      // Direction decides both the arrow and whether the change reads as good
      // or bad, so a caller cannot colour a regression green by accident.
      const arrows = { up: '▲', down: '▼', 'up-bad': '▲', 'down-good': '▼', flat: '' };
      const tone = { up: 'good', 'down-good': 'good', down: 'bad', 'up-bad': 'bad', flat: 'flat' };
      const arrow = arrows[direction] ?? '';
      const deltaTone = tone[direction] ?? 'flat';

      const body = `
        <div class="head">
          ${icon}
          <span class="label">${escapeHtml(label)}</span>
        </div>
        <div class="value">${escapeHtml(value)}</div>
        ${delta !== null ? `
          <div class="delta ${deltaTone}">
            <span aria-hidden="true">${arrow}</span>
            <span>${escapeHtml(delta)}</span>
          </div>` : ''}
        ${caption !== null ? `<div class="caption">${escapeHtml(caption)}</div>` : ''}
        ${this.sparkline()}
      `;

      return html`
        <style>
          ${BASE_STYLE}
          :host { height: 100%; }
          .tile {
            display: flex; flex-direction: column; gap: var(--fqhc-space-1);
            height: 100%;
            padding: var(--fqhc-space-4) var(--fqhc-space-5);
            background: var(--fqhc-surface-card);
            border: 1px solid var(--fqhc-border);
            border-radius: var(--fqhc-radius-lg);
            box-shadow: var(--fqhc-shadow-sm);
            color: var(--fqhc-text);
            text-decoration: none;
            transition: box-shadow var(--fqhc-transition), transform var(--fqhc-transition),
              border-color var(--fqhc-transition);
          }
          a.tile:hover {
            box-shadow: var(--fqhc-shadow-md);
            border-color: var(--fqhc-border-strong);
            transform: translateY(-1px);
          }
          a.tile:focus-visible { outline: none; box-shadow: var(--fqhc-focus-ring); }
          .head { display: flex; align-items: center; gap: var(--fqhc-space-2); }
          .stat-icon { color: var(--fqhc-color-primary); font-size: 1.1em; }
          .label {
            font-size: var(--fqhc-font-size-xs);
            font-weight: var(--fqhc-font-weight-semibold);
            text-transform: uppercase; letter-spacing: 0.06em;
            color: var(--fqhc-text-muted);
          }
          .value {
            font-size: var(--fqhc-font-size-2xl);
            font-weight: var(--fqhc-font-weight-semibold);
            line-height: 1.1;
            font-variant-numeric: tabular-nums;
            color: var(--fqhc-text);
          }
          .delta {
            display: inline-flex; align-items: center; gap: var(--fqhc-space-1);
            font-size: var(--fqhc-font-size-sm);
            font-weight: var(--fqhc-font-weight-medium);
            font-variant-numeric: tabular-nums;
          }
          .delta.good { color: var(--fqhc-color-success); }
          .delta.bad { color: var(--fqhc-color-danger); }
          .delta.flat { color: var(--fqhc-text-muted); }
          .caption { font-size: var(--fqhc-font-size-xs); color: var(--fqhc-text-muted); }
          svg.spark { margin-top: var(--fqhc-space-2); width: 100%; height: 32px; display: block; }
          svg.spark polyline { fill: none; stroke: var(--fqhc-color-primary); stroke-width: 2;
            stroke-linecap: round; stroke-linejoin: round; }
        </style>
        ${href !== null
          ? `<a class="tile" href="${escapeHtml(href)}">${body}</a>`
          : `<div class="tile">${body}</div>`}
      `;
    }

    /**
     * An optional trend line from a comma-separated series. Decorative: the
     * number above it is the fact, the shape is only context, so it is hidden
     * from assistive technology rather than described badly.
     */
    sparkline() {
      const raw = this.getAttribute('sparkline');
      if (raw === null) {
        return '';
      }

      const points = raw.split(',')
        .map((n) => Number.parseFloat(n.trim()))
        .filter((n) => Number.isFinite(n));

      if (points.length < 2) {
        return '';
      }

      const min = Math.min(...points);
      const max = Math.max(...points);
      const span = max - min || 1;
      const step = 100 / (points.length - 1);
      const path = points
        .map((n, i) => `${(i * step).toFixed(2)},${(28 - ((n - min) / span) * 24).toFixed(2)}`)
        .join(' ');

      return `<svg class="spark" viewBox="0 0 100 32" preserveAspectRatio="none" aria-hidden="true">
        <polyline points="${path}"></polyline>
      </svg>`;
    }
  });

  /**
   * <fqhc-avatar name="Maria Alvarez" id="1043" status="success">
   *
   * Initials chip. The colour is derived from `id` (falling back to the name)
   * so the same patient is always the same colour on every surface — the point
   * is recognition across a list, which a random or sequential colour would
   * defeat. Hue only: lightness and saturation are fixed so every chip keeps
   * the same contrast against its text.
   */
  customElements.define('fqhc-avatar', class extends FqhcElement {
    render() {
      const name = this.getAttribute('name') ?? '';
      const seed = this.getAttribute('id') ?? name;
      const status = this.getAttribute('status');
      const hue = this.hue(seed);

      return html`
        <style>
          ${BASE_STYLE}
          :host { display: inline-block; }
          .wrap { position: relative; display: inline-flex; }
          .chip {
            display: inline-flex; align-items: center; justify-content: center;
            width: 2.25em; height: 2.25em;
            border-radius: 50%;
            font-size: var(--fqhc-font-size-sm);
            font-weight: var(--fqhc-font-weight-semibold);
            letter-spacing: 0.02em;
            color: hsl(${hue} 70% 25%);
            background: hsl(${hue} 70% 88%);
            border: 1px solid hsl(${hue} 45% 72%);
            user-select: none;
          }
          .dot {
            position: absolute; right: -1px; bottom: -1px;
            width: 0.7em; height: 0.7em;
            border-radius: 50%;
            border: 2px solid var(--fqhc-surface-card);
          }
          .dot.success { background: var(--fqhc-color-success); }
          .dot.warning { background: var(--fqhc-color-warning); }
          .dot.danger  { background: var(--fqhc-color-danger); }
          .dot.info    { background: var(--fqhc-color-info); }
          .dot.neutral { background: var(--fqhc-color-neutral); }
        </style>
        <span class="wrap">
          <span class="chip" aria-hidden="true">${escapeHtml(this.initials(name))}</span>
          ${status !== null ? `<span class="dot ${cssClass(status)}"></span>` : ''}
        </span>
      `;
    }

    /** First and last initial; the chip is decorative beside the real name. */
    initials(name) {
      const parts = name.trim().split(/\s+/).filter((part) => part !== '');
      if (parts.length === 0) {
        return '?';
      }

      const first = parts[0][0] ?? '';
      const last = parts.length > 1 ? parts[parts.length - 1][0] ?? '' : '';
      return (first + last).toUpperCase();
    }

    /** Stable hue in [0, 360) from a seed string (FNV-1a, folded). */
    hue(seed) {
      let hash = 2166136261;
      for (let i = 0; i < seed.length; i++) {
        hash ^= seed.charCodeAt(i);
        hash = Math.imul(hash, 16777619);
      }

      return Math.abs(hash) % 360;
    }
  });

  /**
   * <fqhc-segmented options="today|Today,week|Week,all|All" value="today"
   *                 label="Range">
   *
   * A segmented control, replacing the stacked links the workspaces use for
   * today/week/all. Emits `fqhc-change` with the selected value; if the host
   * page wants navigation instead, an option may carry a third field which
   * makes that segment a link: `today|Today|/frontdesk.php?range=today`.
   *
   * Fields are separated by `|` rather than `:` so a URL can appear in one
   * without the parser eating it at the scheme.
   *
   * Keyboard: arrow keys move between segments, matching the radio-group
   * pattern that assistive tech announces for this shape.
   */
  customElements.define('fqhc-segmented', class extends FqhcElement {
    connectedCallback() {
      super.connectedCallback();

      // connectedCallback fires again whenever the element is moved in the
      // DOM; the render is guarded by the base class, and the listeners are
      // guarded here, so neither is duplicated.
      if (this._wired) {
        return;
      }
      this._wired = true;

      this.shadowRoot.addEventListener('click', (event) => {
        const button = event.target.closest('[data-value]');
        if (button !== null) {
          this.select(button.dataset.value);
        }
      });
      this.shadowRoot.addEventListener('keydown', (event) => this.onKeydown(event));
    }

    get options() {
      return (this.getAttribute('options') ?? '')
        .split(',')
        .map((entry) => entry.split('|'))
        .filter((parts) => parts.length >= 2)
        .map(([value, label, href]) => ({ value: value.trim(), label: label.trim(), href }));
    }

    onKeydown(event) {
      const keys = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 };
      const step = keys[event.key];
      if (step === undefined) {
        return;
      }

      event.preventDefault();
      const values = this.options.map((option) => option.value);
      const current = values.indexOf(this.getAttribute('value') ?? values[0]);
      const next = values[(current + step + values.length) % values.length];
      this.select(next);
      this.shadowRoot.querySelector(`[data-value="${next}"]`)?.focus();
    }

    select(value) {
      if (value === this.getAttribute('value')) {
        return;
      }

      this.setAttribute('value', value);
      this.shadowRoot.querySelectorAll('[data-value]').forEach((element) => {
        const active = element.dataset.value === value;
        element.classList.toggle('active', active);
        element.setAttribute('aria-checked', active ? 'true' : 'false');
        element.tabIndex = active ? 0 : -1;
      });

      this.dispatchEvent(new CustomEvent('fqhc-change', {
        detail: { value },
        bubbles: true,
        composed: true,
      }));
    }

    render() {
      const options = this.options;
      const selected = this.getAttribute('value') ?? options[0]?.value ?? '';
      const label = this.getAttribute('label') ?? '';

      const segments = options.map((option) => {
        const active = option.value === selected;
        const attributes = `data-value="${escapeHtml(option.value)}" role="radio" `
          + `aria-checked="${active ? 'true' : 'false'}" tabindex="${active ? '0' : '-1'}"`;

        return option.href !== undefined
          ? `<a class="segment ${active ? 'active' : ''}" href="${escapeHtml(option.href)}" ${attributes}>${escapeHtml(option.label)}</a>`
          : `<button type="button" class="segment ${active ? 'active' : ''}" ${attributes}>${escapeHtml(option.label)}</button>`;
      }).join('');

      return html`
        <style>
          ${BASE_STYLE}
          :host { display: inline-block; }
          .group {
            display: inline-flex; gap: 2px; padding: 2px;
            background: var(--fqhc-surface-sunken);
            border: 1px solid var(--fqhc-border);
            border-radius: var(--fqhc-radius-pill);
          }
          .segment {
            padding: var(--fqhc-space-1) var(--fqhc-space-4);
            border: none; border-radius: var(--fqhc-radius-pill);
            background: transparent;
            color: var(--fqhc-text-muted);
            font: inherit;
            font-size: var(--fqhc-font-size-sm);
            font-weight: var(--fqhc-font-weight-medium);
            text-decoration: none;
            cursor: pointer;
            transition: background var(--fqhc-transition), color var(--fqhc-transition);
          }
          .segment:hover { color: var(--fqhc-text); }
          .segment.active {
            background: var(--fqhc-surface-card);
            color: var(--fqhc-color-primary-strong);
            box-shadow: var(--fqhc-shadow-sm);
          }
          .segment:focus-visible { outline: none; box-shadow: var(--fqhc-focus-ring); }
        </style>
        <div class="group" role="radiogroup" aria-label="${escapeHtml(label)}">${segments}</div>
      `;
    }
  });

  /**
   * <fqhc-timeline>
   *   <fqhc-timeline-event time="9:04" variant="success">Arrived</fqhc-timeline-event>
   *   <fqhc-timeline-event time="9:18" variant="info">Roomed in 3</fqhc-timeline-event>
   * </fqhc-timeline>
   *
   * Vertical event list with a time gutter — the natural shape for a visit's
   * arrival → roomed → seen → checked-out progression.
   *
   * Each event is its own element rather than slotted markup: the row needs a
   * gutter, a marker, and a connector drawn *around* the caller's content, and
   * a component cannot wrap what is slotted into it. This way the host page
   * still owns the content, and the shape is the design system's.
   */
  customElements.define('fqhc-timeline', class extends FqhcElement {
    render() {
      return html`
        <style>
          ${BASE_STYLE}
          .track { display: flex; flex-direction: column; }
        </style>
        <div class="track"><slot></slot></div>
      `;
    }
  });

  customElements.define('fqhc-timeline-event', class extends FqhcElement {
    render() {
      const time = this.getAttribute('time') ?? '';
      const variant = cssClass(this.getAttribute('variant') ?? 'neutral');

      return html`
        <style>
          ${BASE_STYLE}
          .row {
            display: grid;
            grid-template-columns: 4.5rem 1rem 1fr;
            align-items: start;
            gap: var(--fqhc-space-2);
            font-size: var(--fqhc-font-size-sm);
            color: var(--fqhc-text);
          }
          .time {
            padding-top: 1px;
            text-align: right;
            font-variant-numeric: tabular-nums;
            color: var(--fqhc-text-muted);
            font-size: var(--fqhc-font-size-xs);
          }
          .gutter {
            position: relative;
            display: flex; justify-content: center;
            align-self: stretch;
            min-height: 1.75rem;
          }
          /* The connector is drawn by every event and clipped by the last one,
             so a timeline never trails a line into empty space. */
          .gutter::before {
            content: ""; position: absolute; top: 0.85rem; bottom: -0.35rem;
            width: 2px; background: var(--fqhc-border);
          }
          :host(:last-of-type) .gutter::before { display: none; }
          .marker {
            position: relative; z-index: 1;
            width: 0.6rem; height: 0.6rem; margin-top: 0.35rem;
            border-radius: 50%;
            border: 2px solid var(--fqhc-surface-card);
          }
          .marker.success { background: var(--fqhc-color-success); }
          .marker.warning { background: var(--fqhc-color-warning); }
          .marker.danger  { background: var(--fqhc-color-danger); }
          .marker.info    { background: var(--fqhc-color-info); }
          .marker.neutral { background: var(--fqhc-border-strong); }
          .body { padding-bottom: var(--fqhc-space-3); min-width: 0; }
        </style>
        <div class="row">
          <span class="time">${escapeHtml(time)}</span>
          <span class="gutter" aria-hidden="true"><span class="marker ${variant}"></span></span>
          <span class="body"><slot></slot></span>
        </div>
      `;
    }
  });

  /**
   * <fqhc-skeleton lines="3" width="60%"> — a loading placeholder shaped like
   * what is arriving, so the layout does not jump when it lands.
   *
   * The shimmer is a decorative animation; under `prefers-reduced-motion` the
   * transition token is 0ms and the animation is disabled outright, leaving a
   * static block that still reads as "not here yet".
   */
  customElements.define('fqhc-skeleton', class extends FqhcElement {
    render() {
      const lines = Math.max(1, Number.parseInt(this.getAttribute('lines') ?? '1', 10) || 1);
      const width = this.getAttribute('width');
      const circle = this.hasAttribute('circle');

      const bars = Array.from({ length: lines }, (unused, index) => {
        // A ragged last line reads as text far better than a full-width block.
        const last = index === lines - 1 && lines > 1;
        const style = last ? 'width: 70%' : (width !== null ? `width: ${escapeHtml(width)}` : '');
        return `<span class="bar${circle ? ' circle' : ''}" style="${style}"></span>`;
      }).join('');

      return html`
        <style>
          ${BASE_STYLE}
          .stack { display: flex; flex-direction: column; gap: var(--fqhc-space-2); }
          .bar {
            display: block; height: 0.9em; border-radius: var(--fqhc-radius-sm);
            background: linear-gradient(
              90deg,
              var(--fqhc-surface-sunken) 25%,
              var(--fqhc-border) 37%,
              var(--fqhc-surface-sunken) 63%
            );
            background-size: 400% 100%;
            animation: shimmer 1.4s ease infinite;
          }
          .bar.circle { width: 2.25em; height: 2.25em; border-radius: 50%; }
          @keyframes shimmer {
            0% { background-position: 100% 50%; }
            100% { background-position: 0 50%; }
          }
          @media (prefers-reduced-motion: reduce) {
            .bar { animation: none; background: var(--fqhc-surface-sunken); }
          }
        </style>
        <div class="stack" role="status" aria-label="Loading">${bars}</div>
      `;
    }
  });

  /**
   * <fqhc-progress value="72" max="100" label="Screening rate" ring>
   *
   * Linear by default, ring with the `ring` attribute — for measure rates and
   * data-health completeness. Always carries its number as text: a bar is not
   * a value, and a screen reader should not have to infer one.
   */
  customElements.define('fqhc-progress', class extends FqhcElement {
    render() {
      const max = Number.parseFloat(this.getAttribute('max') ?? '100') || 100;
      const raw = Number.parseFloat(this.getAttribute('value') ?? '0') || 0;
      const value = Math.min(Math.max(raw, 0), max);
      const percent = Math.round((value / max) * 100);
      const label = this.getAttribute('label') ?? '';
      const variant = cssClass(this.getAttribute('variant') ?? 'primary');
      const ring = this.hasAttribute('ring');

      const meter = ring
        ? `<svg class="ring" viewBox="0 0 42 42" aria-hidden="true">
             <circle class="ring-track" cx="21" cy="21" r="18"></circle>
             <circle class="ring-fill ${variant}" cx="21" cy="21" r="18"
                     stroke-dasharray="${(percent * 1.131).toFixed(2)} 113.1"></circle>
           </svg>
           <span class="ring-value">${percent}%</span>`
        : `<span class="track"><span class="fill ${variant}" style="width: ${percent}%"></span></span>`;

      return html`
        <style>
          ${BASE_STYLE}
          .wrap { display: flex; flex-direction: column; gap: var(--fqhc-space-1); }
          .wrap.ring { align-items: center; position: relative; }
          .row { display: flex; justify-content: space-between; align-items: baseline; gap: var(--fqhc-space-3); }
          .label { font-size: var(--fqhc-font-size-sm); color: var(--fqhc-text-muted); }
          .percent {
            font-size: var(--fqhc-font-size-sm);
            font-weight: var(--fqhc-font-weight-semibold);
            font-variant-numeric: tabular-nums;
            color: var(--fqhc-text);
          }
          .track {
            display: block; height: 8px; width: 100%;
            background: var(--fqhc-surface-sunken);
            border-radius: var(--fqhc-radius-pill);
            overflow: hidden;
          }
          .fill {
            display: block; height: 100%;
            border-radius: var(--fqhc-radius-pill);
            transition: width var(--fqhc-transition);
          }
          .fill.primary, .ring-fill.primary { background: var(--fqhc-color-primary); stroke: var(--fqhc-color-primary); }
          .fill.success, .ring-fill.success { background: var(--fqhc-color-success); stroke: var(--fqhc-color-success); }
          .fill.warning, .ring-fill.warning { background: var(--fqhc-color-warning); stroke: var(--fqhc-color-warning); }
          .fill.danger,  .ring-fill.danger  { background: var(--fqhc-color-danger);  stroke: var(--fqhc-color-danger); }
          .fill.info,    .ring-fill.info    { background: var(--fqhc-color-info);    stroke: var(--fqhc-color-info); }
          .fill.neutral, .ring-fill.neutral { background: var(--fqhc-color-neutral); stroke: var(--fqhc-color-neutral); }
          .ring { width: 84px; height: 84px; transform: rotate(-90deg); }
          .ring circle { fill: none; stroke-width: 4; }
          .ring-track { stroke: var(--fqhc-surface-sunken); }
          .ring-fill { stroke-linecap: round; transition: stroke-dasharray var(--fqhc-transition); }
          .ring-value {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            font-size: var(--fqhc-font-size-lg);
            font-weight: var(--fqhc-font-weight-semibold);
            font-variant-numeric: tabular-nums;
            color: var(--fqhc-text);
          }
        </style>
        <div class="wrap ${ring ? 'ring' : ''}"
             role="progressbar"
             aria-valuenow="${percent}" aria-valuemin="0" aria-valuemax="100"
             aria-label="${escapeHtml(label)}">
          ${ring ? '' : `<div class="row"><span class="label">${escapeHtml(label)}</span><span class="percent">${percent}%</span></div>`}
          ${meter}
          ${ring && label !== '' ? `<span class="label">${escapeHtml(label)}</span>` : ''}
        </div>
      `;
    }
  });

  /**
   * <fqhc-toast> — transient confirmation after an action, so a state change is
   * acknowledged instead of the page silently reloading.
   *
   * Used imperatively: `document.querySelector('fqhc-toast').show('Patient roomed')`,
   * or declaratively with a `message` attribute for a server-rendered
   * confirmation after a redirect.
   *
   * Announced politely rather than assertively: these confirm something the
   * user just did, so they should not interrupt what is being read.
   */
  customElements.define('fqhc-toast', class extends FqhcElement {
    connectedCallback() {
      super.connectedCallback();
      const message = this.getAttribute('message');
      if (message !== null && message !== '') {
        this.show(message, this.getAttribute('variant') ?? 'success');
      }
    }

    /** @param {string} message @param {string} variant @param {number} ms */
    show(message, variant = 'success', ms = 4000) {
      const region = this.shadowRoot.querySelector('.toast');
      region.className = `toast ${cssClass(variant)} visible`;
      region.querySelector('.text').textContent = message;
      region.querySelector('.icon-slot').innerHTML = iconMarkup(
        window.fqhcIcons?.variantIcon(cssClass(variant)) ?? 'info',
      );

      window.clearTimeout(this._timer);
      this._timer = window.setTimeout(() => this.hide(), ms);
    }

    hide() {
      this.shadowRoot.querySelector('.toast')?.classList.remove('visible');
    }

    render() {
      return html`
        <style>
          ${BASE_STYLE}
          :host { position: fixed; inset-block-end: var(--fqhc-space-5);
                  inset-inline-end: var(--fqhc-space-5); z-index: 60; }
          .toast {
            display: none;
            align-items: center; gap: var(--fqhc-space-2);
            max-width: min(28rem, calc(100vw - var(--fqhc-space-6)));
            padding: var(--fqhc-space-3) var(--fqhc-space-4);
            border-radius: var(--fqhc-radius-md);
            border: 1px solid var(--fqhc-border);
            background: var(--fqhc-surface-card);
            box-shadow: var(--fqhc-shadow-lg);
            font-size: var(--fqhc-font-size-sm);
            color: var(--fqhc-text);
          }
          .toast.visible { display: flex; animation: rise var(--fqhc-transition) ease both; }
          .toast.success .icon-slot { color: var(--fqhc-color-success); }
          .toast.warning .icon-slot { color: var(--fqhc-color-warning); }
          .toast.danger  .icon-slot { color: var(--fqhc-color-danger); }
          .toast.info    .icon-slot { color: var(--fqhc-color-info); }
          .toast.neutral .icon-slot { color: var(--fqhc-color-neutral); }
          @keyframes rise { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
          @media (prefers-reduced-motion: reduce) {
            .toast.visible { animation: none; }
          }
        </style>
        <div class="toast" role="status" aria-live="polite">
          <span class="icon-slot"></span>
          <span class="text"></span>
        </div>
      `;
    }
  });
})();
