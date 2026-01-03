# MCP Abilities - GeneratePress

GeneratePress theme management for WordPress via MCP.

[![GitHub release](https://img.shields.io/github/v/release/bjornfix/mcp-abilities-generatepress)](https://github.com/bjornfix/mcp-abilities-generatepress/releases)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

**Tested up to:** 6.9
**Stable tag:** 1.0.2
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

## What It Does

This add-on plugin exposes GeneratePress theme and GenerateBlocks settings through MCP (Model Context Protocol). Your AI assistant can adjust colors, typography, layouts, and global styles - all through conversation.

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

## Abilities (6)

| Ability | Description |
|---------|-------------|
| `generatepress/get-settings` | Get theme settings (colors, typography, layout) |
| `generatepress/update-settings` | Update theme settings |
| `generatepress/update-page-meta` | Update page-specific settings (disable title, sidebar, footer, etc.) |
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
    "settings": {
      "global_colors": {
        "contrast": "#222222",
        "contrast-2": "#575760",
        "accent": "#1e73be"
      }
    }
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

## License

GPL-2.0+

## Author

[Devenia](https://devenia.com) - We've been doing SEO and web development since 1993.

## Links

- [Plugin Page](https://devenia.com/plugins/mcp-expose-abilities/)
- [Core Plugin (MCP Expose Abilities)](https://github.com/bjornfix/mcp-expose-abilities)
- [All Add-on Plugins](https://devenia.com/plugins/mcp-expose-abilities/#add-ons)
