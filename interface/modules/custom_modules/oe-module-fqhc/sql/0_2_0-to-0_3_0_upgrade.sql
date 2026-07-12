-- FQHC module — 0.2.0 to 0.3.0 upgrade
--
-- Default the FQHC configuration to the modern theme. INSERT IGNORE only
-- takes effect when no explicit site choice has ever been saved (saving
-- Administration > Globals writes every global, including css_header), so
-- an admin's saved theme — and any per-user theme override — always wins.
-- The upstream default in library/globals.inc.php is untouched.
--
-- @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3

INSERT IGNORE INTO `globals` (`gl_name`, `gl_index`, `gl_value`)
VALUES ('css_header', 0, 'style_fqhc_modern.css');
