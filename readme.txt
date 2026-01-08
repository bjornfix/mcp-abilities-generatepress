=== MCP Abilities - GeneratePress ===
Contributors: devenia
Tags: mcp, generatepress, theme, ai, automation
Requires at least: 6.9
Tested up to: 6.9
Stable tag: 1.1.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

GeneratePress theme management for WordPress via MCP.

== Description ==

This add-on plugin exposes GeneratePress theme settings, elements, and GenerateBlocks settings through MCP (Model Context Protocol). Your AI assistant can adjust colors, typography, layouts, global styles, and hook elements - all through conversation.

== Abilities ==

* Theme settings (colors, typography, layout, buttons)
* GeneratePress options (read/write)
* GeneratePress Elements (list/get/create/update/delete)
* Page-specific meta (read/write)
* GeneratePress cache control
* GenerateBlocks global styles/defaults/settings
* GenerateBlocks CSS cache control

Part of the MCP Expose Abilities ecosystem.

== Installation ==

1. Install the required plugins (Abilities API, MCP Adapter)
2. Download the latest release
3. Upload via WordPress Admin → Plugins → Add New → Upload Plugin
4. Activate the plugin

== Changelog ==

= 1.1.0 =
* Add: GeneratePress elements CRUD (hooks/blocks/headers/layouts)
* Add: Option listing/get/update for GeneratePress/GenerateBlocks
* Add: Theme info ability and page meta read ability
* Add: GeneratePress cache clear ability
* Update: GenerateBlocks abilities include settings option

= 1.0.4 =
* Fix: Store global_colors inside generate_settings where GP expects them (was incorrectly using separate option)

= 1.0.3 =
* Fix: Clear CSS cache after settings update to ensure changes take effect immediately

= 1.0.2 =
* Fixed permission callback for update-page-meta ability

= 1.0.1 =
* Added generatepress/update-page-meta ability for page-specific settings (disable title, sidebar, footer, etc.)

= 1.0.0 =
* Initial release
