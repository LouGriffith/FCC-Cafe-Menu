# FCC Cafe Menu

**Author:** Lou Griffith — [lougriffith.com](https://lougriffith.com)
**Version:** 1.1.0
**Requires:** WordPress 6.0+, PHP 8.0+

Manage the Fancy Cat Cafe menu. Organize items across menus and categories, set sizes and pricing, flag happy hour items, and display the full menu or individual sections anywhere on your site using shortcodes.

---

## Features

- Custom post type `fcc_menu` for managing individual menu items
- Two taxonomies: **Menus** (e.g. Drink Menu, Pastries Menu) and **Categories** (e.g. Coffee, Tea, Pastries)
- Fields per item: description, two size/price options, happy hour flag, allergen info
- Admin list view showing Menu, Category, Price, and Happy Hour columns
- Quick edit prices directly from the admin list without opening each item
- Frontend shortcodes with flexible filtering by menu and/or category
- GitHub-powered auto-updates via WordPress admin

---

## Shortcodes

### `[fcc_menu]`
Displays menu items grouped by category.

| Attribute | Options | Default | Description |
|---|---|---|---|
| `menu` | comma-separated slugs | all menus | Filter to one or more menus |
| `category` | comma-separated slugs | all categories | Filter to one or more categories |
| `show_happy_hour` | `only`, `false` | `false` | `only` shows exclusively happy hour items |

**Examples:**
```
[fcc_menu]
[fcc_menu menu="drink-menu"]
[fcc_menu menu="drink-menu" category="coffee,tea"]
[fcc_menu menu="pastries-menu"]
[fcc_menu show_happy_hour="only"]
```

### `[fcc_menu_category]`
Shortcut to display a single category section.

| Attribute | Options | Default | Description |
|---|---|---|---|
| `category` | slug | — | Category to display (required) |
| `menu` | slug | all | Optionally scope to a specific menu |

**Examples:**
```
[fcc_menu_category category="coffee"]
[fcc_menu_category category="pastries" menu="pastries-menu"]
```

---

## Admin Usage

### Adding a Menu Item
1. Go to **Cafe Menu → Add New Item**
2. Enter the item name in the title field
3. Assign it to a **Menu** (e.g. Drink Menu) and a **Category** (e.g. Coffee) using the sidebar panels
4. Fill in the **Menu Item Details** panel: description, size/price options, happy hour flag, allergen info
5. Publish

### Quick Editing Prices
From the **Cafe Menu** list view, hover over any item and click **Quick Edit**. The Size and Price fields are available directly in the inline editor — no need to open the full edit screen.

### Managing Menus & Categories
- **Cafe Menu → Menus** — add top-level menus (e.g. Drink Menu, Pastries Menu)
- **Cafe Menu → Categories** — add categories within menus (e.g. Coffee, Tea, Add-ons, Kids)

---

## GitHub Updates

This plugin updates directly through the WordPress admin via GitHub Releases.

**To release an update:**
1. Bump the version in `fcc-cafe-menu.php`
2. Push changes to GitHub
3. Create a new Release tagged `v1.x.x` with the plugin `.zip` attached
4. WordPress will detect the update within 12 hours

See `GITHUB-SETUP.md` for full instructions.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md)
