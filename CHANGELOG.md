# Changelog — FCC Cafe Menu

All notable changes to this plugin are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [1.3.0] — 2026-06-19

### Added
- Bricks Builder dynamic data tags (`includes/class-bricks-dynamic-tags.php`)
- Tags appear under the **FCC Menu** group in the Bricks dynamic data picker:
  - `{fcc_item_row}` — full name + dotted leader + size/price row HTML, ready for use in a Rich Text element inside a query loop
  - `{fcc_item_description}` — item description text
  - `{fcc_item_price_1}` / `{fcc_item_price_2}` — formatted prices (e.g. `$6.25`)
  - `{fcc_item_size_1}` / `{fcc_item_size_2}` — size labels (e.g. `16oz`)
  - `{fcc_item_is_happy_hour}` — returns `1` if happy hour, empty string if not

---

---

## [1.2.0] — 2026-06-19

### Added
- Export / Import admin page under **Cafe Menu → Export / Import**
- Export: downloads all `fcc_menu` posts as a dated JSON file including all meta fields and taxonomy assignments (`fcc_menu_category`, `fcc_menu_name`)
- Import: uploads a JSON file and processes each item — creates new posts, and prompts per duplicate
- Duplicate handling: side-by-side diff view showing existing vs incoming item data, with per-item **Update** / **Skip** buttons and bulk **Update All** / **Skip All** options
- Import summary notice showing created / updated / skipped counts
- Admin CSS for export/import page (`assets/export-import.css`)

---

## [1.1.0] — 2026-03-10

### Added
- `fcc_menu_name` taxonomy for top-level menus (e.g. Drink Menu, Pastries Menu) — managed via **Cafe Menu → Menus** in the admin sidebar
- Menu column added to the admin list view alongside the existing Category column
- `menu=` attribute on `[fcc_menu]` shortcode to scope output to a specific menu
- GitHub auto-updater (`class-github-updater.php`) — updates delivered via GitHub Releases through the WordPress admin

### Changed
- Removed `custom-fields` from post type supports — hides the Custom Fields meta box in the editor
- `[fcc_menu]` shortcode now intelligently limits displayed categories to those containing items in the requested menu

---

## [1.0.0] — 2026-02-16

### Added
- Initial release
- Custom post type `fcc_menu` with dashicons-coffee icon
- `fcc_menu_category` taxonomy (hierarchical) for organizing items into categories
- Menu item fields: description, size/price option 1, size/price option 2, happy hour flag, allergen info
- Admin list columns: Category, Price, Happy Hour
- Quick edit support for size and price fields from the admin list view
- Shortcodes: `[fcc_menu]`, `[fcc_menu_category]`
- Shortcode attributes: `category=`, `show_happy_hour=`
- Frontend CSS for menu item display with size/price layout
- Responsive layout and happy hour badge styling
