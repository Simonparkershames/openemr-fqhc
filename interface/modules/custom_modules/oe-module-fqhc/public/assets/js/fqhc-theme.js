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

  /**
   * The control itself is `<fqhc-segmented>` from the component library — this
   * element only owns the *meaning* of the three positions (read the stored
   * preference, write it, apply it to the root). When the segmented control
   * was hand-rolled here it was a second implementation of the same widget;
   * now there is one, and it is documented in the style guide.
   */
  customElements.define('fqhc-theme-toggle', class extends HTMLElement {
    connectedCallback() {
      if (this._wired) {
        return;
      }
      this._wired = true;

      const options = MODES.map((mode) => `${mode}|${LABELS[mode]}`).join(',');
      const control = document.createElement('fqhc-segmented');
      control.setAttribute('label', 'Colour theme');
      control.setAttribute('options', options);
      control.setAttribute('value', readMode());

      control.addEventListener('fqhc-change', (event) => {
        const mode = event.detail.value;
        writeMode(mode);
        applyMode(mode);
      });

      this.replaceChildren(control);
    }
  });
})();
