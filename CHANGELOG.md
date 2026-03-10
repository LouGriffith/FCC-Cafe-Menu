# Changelog — FCC Cafe Menu

All notable changes to this plugin are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

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
