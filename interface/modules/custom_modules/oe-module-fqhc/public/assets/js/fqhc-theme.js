/**
 * FQHC Design System — theme control
 *
 * Defines `<fqhc-theme-toggle>`, a three-way control over the theme: follow the
 * operating system (the default), or pin light or dark.
 *
 * ## The preference, and where it lives
 *
 * An explicit choice is stored in `localStorage` under `fqhc-theme` and applied
 * by writing `data-fqhc-theme` on the root element; tokens.css redefines the
 * palette behind both that attribute and `prefers-color-scheme`, so removing
 * the attribute hands control back to the OS. That makes the preference
 * per-browser rather than per-account, which is the right granularity for a
 * display setting — the same person on a workstation and a tablet in an exam
 * room may well want different answers — and it costs no round trip.
 *
 * ## Avoiding the flash
 *
 * The attribute must be on the root element before the first paint, or a
 * dark-preferring user sees a white page for a frame. This file loads as a
 * deferred module and is far too late for that, so the *applying* is done by a
 * tiny synchronous snippet in the document head — see
 * `DesignSystemAssets::themeBootstrapScript()`, which is the authority. This
 * file only provides the control that changes the value afterwards, and reuses
 * the same storage key.
 *
 * ## Scope
 *
 * Dark is scoped to FQHC module surfaces. A deep link out to a legacy screen
 * lands on that screen's own theme; extending the shell is tracked under the
 * app-shell epic (#68).
 *
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

(() => {
  const STORAGE_KEY = 'fqhc-theme';
  const ROOT_ATTRIBUTE = 'data-fqhc-theme';

  /** The three positions of the control. `system` means "no explicit choice". */
  const MODES = ['system', 'light', 'dark'];

  const LABELS = { system: 'System', light: 'Light', dark: 'Dark' };
  const ICONS = { system: 'design-system', light: 'success', dark: 'pending' };

  /**
   * Storage can throw outright (Safari private browsing, blocked site data),
   * so every access is guarded and a failure degrades to "follow the system"
   * rather than breaking the page.
   */
  function readMode() {
    try {
      const stored = window.localStorage.getItem(STORAGE_KEY);
      return MODES.includes(stored) ? stored : 'system';
    } catch {
      return 'system';
    }
  }

  function writeMode(mode) {
    try {
      if (mode === 'system') {
        window.localStorage.removeItem(STORAGE_KEY);
      } else {
        window.localStorage.setItem(STORAGE_KEY, mode);
      }
    } catch {
      // A preference that cannot be persisted still applies for this page.
    }
  }

  function applyMode(mode) {
    const root = document.documentElement;
    if (mode === 'system') {
      root.removeAttribute(ROOT_ATTRIBUTE);
    } else {
      root.setAttribute(ROOT_ATTRIBUTE, mode);
    }

    document.dispatchEvent(new CustomEvent('fqhc-theme-change', { detail: { mode } }));
  }

  customElements.define('fqhc-theme-toggle', class extends HTMLElement {
    constructor() {
      super();
      this.attachShadow({ mode: 'open' });
    }

    connectedCallback() {
      this.shadowRoot.innerHTML = this.template();
      this.shadowRoot.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-mode]');
        if (button === null) {
          return;
        }

        const mode = button.dataset.mode;
        writeMode(mode);
        applyMode(mode);
        this.reflect(mode);
      });

      this.reflect(readMode());
    }

    /** Mark the active segment, for both sighted users and assistive tech. */
    reflect(mode) {
      this.shadowRoot.querySelectorAll('button[data-mode]').forEach((button) => {
        const active = button.dataset.mode === mode;
        button.classList.toggle('active', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
    }

    template() {
      const buttons = MODES.map((mode) => `
        <button type="button" data-mode="${mode}" aria-pressed="false">
          <fqhc-icon name="${ICONS[mode]}"></fqhc-icon>
          <span>${LABELS[mode]}</span>
        </button>
      `).join('');

      return `
        <style>
          :host { display: inline-block; }
          .group {
            display: inline-flex;
            padding: 2px;
            gap: 2px;
            background: var(--fqhc-surface-sunken);
            border: 1px solid var(--fqhc-border);
            border-radius: var(--fqhc-radius-pill);
          }
          button {
            display: inline-flex; align-items: center; gap: var(--fqhc-space-1);
            padding: var(--fqhc-space-1) var(--fqhc-space-3);
            border: none; border-radius: var(--fqhc-radius-pill);
            background: transparent;
            color: var(--fqhc-text-muted);
            font-family: var(--fqhc-font-sans);
            font-size: var(--fqhc-font-size-xs);
            font-weight: var(--fqhc-font-weight-medium);
            cursor: pointer;
            transition: background var(--fqhc-transition), color var(--fqhc-transition);
          }
          button:hover { color: var(--fqhc-text); }
          button.active {
            background: var(--fqhc-surface-card);
            color: var(--fqhc-color-primary-strong);
            box-shadow: var(--fqhc-shadow-sm);
          }
          button:focus-visible {
            outline: none;
            box-shadow: var(--fqhc-focus-ring);
          }
        </style>
        <div class="group" role="group" aria-label="Colour theme">${buttons}</div>
      `;
    }
  });
})();
