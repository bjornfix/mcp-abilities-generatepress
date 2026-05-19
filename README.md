# MCP Abilities - GeneratePress

GeneratePress theme management for WordPress via MCP.

[![GitHub release](https://img.shields.io/github/v/release/bjornfix/mcp-abilities-generatepress)](https://github.com/bjornfix/mcp-abilities-generatepress/releases)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

**Tested up to:** 6.9
**Stable tag:** 1.1.8
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

## What It Does

This add-on plugin exposes GeneratePress theme settings, elements, and GenerateBlocks settings through MCP (Model Context Protocol). Your AI assistant can adjust colors, typography, layouts, global styles, and hook elements - all through conversation.

**Part of the [MCP Expose Abilities](https://github.com/bjornfix/mcp-expose-abilities) ecosystem.**

This is one piece of a bigger open WordPress automation stack that lets AI agents do real theme and GenerateBlocks work instead of handing humans a checklist.

## Why This Is Cool

Theme settings, Elements, page meta, and GenerateBlocks styles are exactly the kind of tasks that make WordPress maintenance drag.

With this add-on, you can ask Codex or Claude to inspect the current setup, change one setting, patch one Element, clear the right cache, and move on. That is a radically better workflow than clicking through multiple admin screens for every tiny change.

## Documentation

- [Core Plugin: MCP Expose Abilities](https://github.com/bjornfix/mcp-expose-abilities)
- [MCP Wiki Home](https://github.com/bjornfix/mcp-expose-abilities/wiki)
- [Why Teams Use It](https://github.com/bjornfix/mcp-expose-abilities/wiki/Why-Teams-Use-It)
- [Use Cases](https://github.com/bjornfix/mcp-expose-abilities/wiki/Use-Cases)
- [GeneratePress Add-On Guide](https://github.com/bjornfix/mcp-expose-abilities/wiki/Addon-GeneratePress)
- [Getting Started](https://github.com/bjornfix/mcp-expose-abilities/wiki/Getting-Started)

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

## Abilities (26)

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
| `generatepress/restore-element` | Restore a trashed GeneratePress Element |
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

`generatepress/clear-cache` and `generateblocks/clear-cache` also accept `{"force": true}` as a compatibility alias for `{"confirm": true}`.

`generateblocks/clear-cache` preserves existing generated CSS files by default and only clears cache metadata. Use `{"delete_files": true}` only for destructive file clearing; warming then verifies that the expected per-page CSS file was actually regenerated. You can also pass `post_ids` and `limit` to restrict warming.

## Changelog

### 1.1.8
- Fixed `generateblocks/clear-cache` to preserve per-page CSS files by default so frontend pages do not temporarily load without styling after cache maintenance.
- Improved GenerateBlocks CSS warming so a post is only reported as warmed when its expected CSS file exists after the request.

### 1.1.7
- Fixed `generateblocks/clear-cache` so it warms regenerated CSS files for known GenerateBlocks posts after deleting cache files, preventing frontend pages from loading without their per-page CSS.

### 1.1.6
- Fixed page meta updates for GeneratePress full-width content so `content_area=full-width-content` writes the actual full-width content flag used by the theme.

### 1.1.5
- Fixed zero-parameter ability schemas so MCP Adapter 0.4.x clients do not receive invalid `properties: []` JSON

### 1.1.4
- Fixed: `generateblocks/clear-cache` input schema now actually exposes `force` so proxy validation matches the callback

### 1.1.3
- Fixed: `generatepress/clear-cache` and `generateblocks/clear-cache` now accept `force` as an alias for `confirm` for client compatibility
- Fixed: cache clear abilities now reject `confirm=false` / `force=false` explicitly instead of silently proceeding

### 1.1.2
- Fixed: removed hard plugin header dependency on `abilities-api` to avoid slug-mismatch activation blocking
- Fixed: list-elements status schema now supports any

### 1.1.1
- Fixed: hook element content now stored in `_generate_element_content`
- Changed: delete-element now moves elements to trash
- Added: list-elements status filter and `generatepress/restore-element`

### 1.1.0

- Add GeneratePress elements CRUD and option access
- Add page meta read ability and GP cache control
- Expose GenerateBlocks settings option

## License

GPL-2.0+

## Author

[Devenia](https://devenia.com) - We've been doing SEO and web development since 1993.

## Free and Open

Like the rest of the ecosystem, this add-on is free, fully open source, and built from real use rather than demo-only marketing.

## Star and Share

If this add-on helps, please star the repo, share the ecosystem, and point people to the main wiki:

- https://github.com/bjornfix/mcp-expose-abilities
- https://github.com/bjornfix/mcp-expose-abilities/wiki

## Links

- [Core Plugin (MCP Expose Abilities)](https://github.com/bjornfix/mcp-expose-abilities)
- [Main Wiki](https://github.com/bjornfix/mcp-expose-abilities/wiki)
- [GeneratePress Add-On Guide](https://github.com/bjornfix/mcp-expose-abilities/wiki/Addon-GeneratePress)
