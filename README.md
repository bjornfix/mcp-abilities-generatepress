# MCP Abilities - GeneratePress

GeneratePress theme management for WordPress via MCP.

[![GitHub release](https://img.shields.io/github/v/release/bjornfix/mcp-abilities-generatepress)](https://github.com/bjornfix/mcp-abilities-generatepress/releases)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

**Tested up to:** 6.9
**Stable tag:** 1.2.0
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

## What It Does

This add-on plugin exposes GeneratePress theme settings, elements, and GenerateBlocks settings through MCP (Model Context Protocol). Your AI assistant can adjust colors, typography, layouts, global styles, and hook elements - all through conversation.

**Part of the [MCP Expose Abilities](https://devenia.com/plugins/mcp-expose-abilities/) ecosystem.**

## Requirements

- WordPress 6.9+
- PHP 8.0+
- [Abilities API](https://github.com/WordPress/abilities-api) plugin
- [MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin
- [GeneratePress](https://generatepress.com/) theme (Free or Premium)
- [GenerateBlocks](https://generateblocks.com/) (optional, for block abilities)

## Installation

1. Install the required plugins (Abilities API, MCP Adapter)
2. Download the latest release from [Releases](https://github.com/bjornfix/mcp-abilities-generatepress/releases)
3. Upload via WordPress Admin → Plugins → Add New → Upload Plugin
4. Activate the plugin

## Abilities (25)

New in 1.2.0: module status controls, module settings access, typography/font manager access, and Starter Site cache tools.

| Ability | Description |
|---------|-------------|
| `generatepress/get-info` | Get active theme and GeneratePress Premium status |
| `generatepress/list-options` | List GeneratePress/GenerateBlocks options in wp_options |
| `generatepress/get-options` | Get specific GeneratePress/GenerateBlocks options |
| `generatepress/update-options` | Update or delete GeneratePress/GenerateBlocks options |
| `generatepress/get-settings` | Get theme settings (colors, typography, layout) |
| `generatepress/update-settings` | Update theme settings |
| `generatepress/get-typography` | Get typography rules and font manager entries |
| `generatepress/update-typography` | Update typography rules and font manager entries |
| `generatepress/list-modules` | List GeneratePress Premium module statuses |
| `generatepress/update-modules` | Activate or deactivate GeneratePress modules |
| `generatepress/get-module-settings` | Get settings for a GeneratePress module |
| `generatepress/update-module-settings` | Update settings for a GeneratePress module |
| `generatepress/get-site-library-cache` | Inspect Starter Site cache metadata |
| `generatepress/clear-site-library-cache` | Clear Starter Site cache |
| `generatepress/clear-cache` | Clear GeneratePress dynamic CSS cache |
| `generatepress/get-page-meta` | Get page-specific settings (disable title, sidebar, footer, etc.) |
| `generatepress/update-page-meta` | Update page-specific settings (disable title, sidebar, footer, etc.) |
| `generatepress/list-elements` | List GeneratePress Elements (hooks, blocks, headers, layouts) |
| `generatepress/get-element` | Get a GeneratePress Element by ID |
| `generatepress/create-element` | Create a GeneratePress Element |
| `generatepress/update-element` | Update a GeneratePress Element |
| `generatepress/delete-element` | Delete a GeneratePress Element |
| `generateblocks/get-global-styles` | Get GenerateBlocks global styles |
| `generateblocks/update-global-styles` | Update GenerateBlocks global styles |
| `generateblocks/clear-cache` | Clear GenerateBlocks CSS cache |

## Usage Examples

### Get theme settings

```json
{
  "ability_name": "generatepress/get-settings",
  "parameters": {
    "section": "colors"
  }
}
```

Sections: `all`, `colors`, `typography`, `layout`, `buttons`, `site_identity`

### Update theme colors

```json
{
  "ability_name": "generatepress/update-settings",
  "parameters": {
    "global_colors": [
      { "name": "contrast", "slug": "contrast", "color": "#222222" },
      { "name": "contrast-2", "slug": "contrast-2", "color": "#575760" },
      { "name": "accent", "slug": "accent", "color": "#1e73be" }
    ]
  }
}
```

### Get module settings

```json
{
  "ability_name": "generatepress/get-module-settings",
  "parameters": {
    "module": "menu_plus"
  }
}
```

### Update module settings

```json
{
  "ability_name": "generatepress/update-module-settings",
  "parameters": {
    "module": "menu_plus",
    "settings": {
      "mobile_header": "enable",
      "sticky_menu": "true"
    }
  }
}
```

Modules: `blog`, `spacing`, `menu_plus`, `secondary_nav`, `woocommerce`. Use `generatepress/list-modules` to inspect status.

### Update typography (Local Font Library)

```json
{
  "ability_name": "generatepress/update-typography",
  "parameters": {
    "font_manager": [
      {
        "fontFamily": "Abhaya Libre",
        "googleFont": true,
        "googleFontCategory": "serif",
        "googleFontVariants": "regular, 500, 600, 700, 800"
      }
    ]
  }
}
```

### Get GenerateBlocks global styles

```json
{
  "ability_name": "generateblocks/get-global-styles",
  "parameters": {}
}
```

### Clear CSS cache

```json
{
  "ability_name": "generateblocks/clear-cache",
  "parameters": {}
}
```

## Changelog

### 1.2.0

- Add module status and module settings abilities
- Add typography and font manager abilities
- Add Starter Site cache inspection/clear abilities
- Expand get-settings with typography rules and site identity

### 1.1.0

- Add GeneratePress elements CRUD and option access
- Add page meta read ability and GP cache control
- Expose GenerateBlocks settings option

## License

GPL-2.0+

## Author

[Devenia](https://devenia.com) - We've been doing SEO and web development since 1993.

## Devenia MCP Guides

- [WordPress MCP Compatibility Matrix (2026)](https://devenia.com/learn/wordpress-mcp-compatibility-matrix/)
- [MCP + Elementor: Setup and Pitfalls](https://devenia.com/learn/mcp-elementor-setup-pitfalls/)
- [MCP Security Checklist for Production WordPress](https://devenia.com/learn/mcp-security-checklist-wordpress/)
- [Top MCP Errors and Fixes (WordPress)](https://devenia.com/learn/mcp-top-errors-and-fixes/)

## Links

- [Plugin Page](https://devenia.com/plugins/mcp-expose-abilities/)
- [Core Plugin (MCP Expose Abilities)](https://github.com/bjornfix/mcp-expose-abilities)
- [All Add-on Plugins](https://devenia.com/plugins/mcp-expose-abilities/#add-ons)
