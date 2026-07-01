=== MCP Abilities - GeneratePress ===
Contributors: devenia
Tags: mcp, generatepress, theme, ai, automation
Requires at least: 6.9
Tested up to: 7.0
Stable tag: 1.1.31
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

GeneratePress theme management for WordPress via MCP.

== Description ==

This add-on plugin exposes GeneratePress theme settings, elements, and GenerateBlocks settings through MCP (Model Context Protocol). Your AI assistant can adjust colors, typography, layouts, global styles, and hook elements - all through conversation.

== Abilities ==

* Theme settings (colors, typography, layout, buttons)
* Full GeneratePress, GeneratePress Premium, GenerateBlocks, and Pro control-surface discovery
* Live GeneratePress setting-key discovery and global design settings updates for typography, colors, layout, spacing, buttons, and site identity
* Typography rules and font manager (Local Font Library)
* Module status management and module settings access
* Full bounded GP/GP Premium module settings discovery and updates for any stored `generate_*_settings` option
* Native blog archive settings and featured-image size diagnostics
* Archive/page-for-posts GeneratePress Element upsert for GenerateBlocks hero/CTA bands
* GeneratePress options (read/write)
* GeneratePress theme mods (read/write for GP-relevant mods)
* WordPress Custom CSS for the active GeneratePress stylesheet (read/update/patch/clear)
* GeneratePress Elements (list/get/create/update/delete)
* Page-specific meta (read/write)
* GeneratePress cache control
* Starter Site cache inspection/clear
* GenerateBlocks global styles/defaults/settings and control-surface diagnostics
* GenerateBlocks Pattern Library discovery and pattern search using the same source as the editor, including custom/local libraries
* GenerateBlocks and GenerateBlocks Pro options (list/get/update for GB-owned option prefixes)
* GenerateBlocks CSS cache control

Part of the MCP Expose Abilities ecosystem.

== Installation ==

1. Install the required plugins (Abilities API, MCP Adapter)
2. Download the latest release
3. Upload via WordPress Admin → Plugins → Add New → Upload Plugin
4. Activate the plugin

== Changelog ==

= 1.1.31 =
* Fixed: Pattern Library abilities now read the GenerateBlocks library registry directly so enabled, disabled, custom, and local libraries are discoverable by library ID without exposing public keys.

= 1.1.30 =
* Added: `generateblocks/list-pattern-libraries`, `generateblocks/list-pattern-categories`, and `generateblocks/search-pattern-library` for editor-native GenerateBlocks Pattern Library discovery, including custom/local design libraries by library ID.

= 1.1.29 =
* Fixed: optional-input ability callbacks now normalize null MCP input to an empty array.

= 1.1.28 =
* Fixed: optional-input ability schemas now match the PHP array transport shape used by the MCP adapter.

= 1.1.27 =
* Fixed: optional-input schema compatibility no longer passes null into array-typed ability callbacks.

= 1.1.26 =
* Fixed: optional-input GeneratePress and GenerateBlocks abilities now accept empty MCP input objects reliably.

= 1.1.25 =
* Added: `generatepress/audit-page-layout-meta` to audit page families for expected GeneratePress layout meta and optional content markers, with idempotent meta repair.

= 1.1.24 =
* Added: `generatepress/update-page-meta` now reads back requested GeneratePress page meta after writing and verifies frontend layout body classes by default for layout-sensitive updates.
* Added: optional expected/forbidden body-class checks for special GeneratePress setups without hardcoding site-specific layouts.

= 1.1.23 =
* Fixed: automatic page-layout sync now detects GenerateBlocks headline H1 blocks, not only core heading blocks.
* Fixed: `generatepress/get-settings` layout output now includes regular page, blog/archive, and single post sidebar layout defaults.

= 1.1.22 =
* Fixed: `generatepress/get-custom-css` compatibility schema is now applied to the correct ability.

= 1.1.21 =
* Fixed: `generatepress/get-custom-css` now exposes an explicit compatibility input property so MCP clients that reject empty object schemas can call it reliably.

= 1.1.20 =
* Added: `generatepress/get-custom-css`, `generatepress/update-custom-css`, `generatepress/patch-custom-css`, and `generatepress/clear-custom-css` for managing WordPress Custom CSS through the GeneratePress MCP surface.
* Added: `custom_css_post_id` to the supported theme-mod surface so Custom CSS state is visible alongside other GeneratePress customizer data.
* Added: `generatepress/list-control-surface` to discover GeneratePress, GeneratePress Premium, GenerateBlocks, and Pro control surfaces on each site.
* Added: `generatepress/list-module-settings` and expanded module settings abilities so agents can discover and update any stored `generate_*_settings` option, not only a fixed module list.
* Added: `generateblocks/list-options`, `generateblocks/get-options`, and `generateblocks/update-options` for bounded GenerateBlocks and GenerateBlocks Pro option management.

= 1.1.19 =
* Added: automatic GeneratePress headline disabling for pages whose Gutenberg content already contains an H1, preventing duplicate visible titles.
* Added: `generatepress/audit-duplicate-headlines` to find and optionally fix existing duplicate-title pages.

= 1.1.18 =
* Fixed: Updated `Tested up to` to WordPress 7.0 for Plugin Check compliance.

= 1.1.17 =
* Added: `generatepress/update-global-design-settings` typography groups for common GeneratePress surfaces beyond body/navigation/buttons/headings: `html`, `site_title`, `mobile_navigation_site_title`, `site_tagline`, `subnavigation`, `entry_meta`, `sidebar_widget_title`, `sidebar_widget_text`, `footer_widget_title`, `footer_widget_text`, and `footer_bar_text`.
* Fixed: selector-only global typography rules can now be updated through the supported typography workflow instead of raw `generate_settings.typography` option writes.

= 1.1.16 =
* Fixed: GenerateBlocks CSS generation now includes matching GeneratePress Block Element content for archive contexts, so posts-page hero/CTA Elements are styled by the page CSS file.

= 1.1.15 =
* Fixed: `generatepress/upsert-archive-hook-element` now creates GeneratePress Block Elements of type hook so GenerateBlocks markup renders with its containers and generated CSS.

= 1.1.14 =
* Added: `generatepress/upsert-archive-hook-element` for posts-page/archive hero and CTA Elements using GenerateBlocks while preserving native GeneratePress loops.
* Added: built-in guidance so agents do not put design into ignored posts-page content or solve archive design with CSS hacks.

= 1.1.13 =
* Fixed: featured-image audit/regeneration abilities now use the supported `site` ability category so they register reliably.

= 1.1.12 =
* Added: `generatepress/list-setting-keys` to discover live GeneratePress settings, classify keys, and expose module/theme-mod/image-size control surfaces.
* Added: `generatepress/get-theme-mods` and `generatepress/update-theme-mods` for GeneratePress-relevant theme mods.
* Added: `generatepress/get-blog-archive-settings` and `generatepress/update-blog-archive-settings` for native blog archive control.
* Added: `generatepress/audit-featured-image-sizes` and `generatepress/regenerate-featured-image-sizes` for reliable native archive images.
* Added: `generateblocks/list-control-surface` for GenerateBlocks options, global styles, dynamic CSS posts, and generated CSS file status.
* Expanded: global design updates now include the spacing group.

= 1.1.11 =
* Added: `letterSpacing` support to `generatepress/update-global-design-settings` typography groups.
* Added: flat `settings` object to `generatepress/update-global-design-settings` so the global settings workflow can update any GeneratePress global setting.

= 1.1.10 =
* Added: `generatepress/update-global-design-settings` for site-wide typography, colors, layout, buttons, and site identity changes.
* Fixed: `generatepress/update-settings` now rejects global design keys and nested design-section objects instead of bypassing the global design-settings workflow.

= 1.1.9 =
* Fixed: targeted destructive GenerateBlocks cache clears now delete only the expected CSS files for the provided `post_ids`.

= 1.1.8 =
* Fixed: `generateblocks/clear-cache` now preserves per-page CSS files by default so frontend pages do not temporarily load without styling after cache maintenance.
* Fixed: GenerateBlocks CSS warming now verifies that the expected generated CSS file exists before reporting a post as warmed.

= 1.1.7 =
* Fixed: `generateblocks/clear-cache` now warms regenerated CSS files for known GenerateBlocks posts after deleting cache files, preventing frontend pages from loading without their per-page CSS.

= 1.1.6 =
* Fixed: page meta updates for GeneratePress full-width content now write the actual full-width content flag used by the theme.

= 1.1.5 =
* Fixed: zero-parameter ability schemas now avoid empty `properties` objects so MCP Adapter 0.4.x clients do not receive invalid `properties: []` JSON

= 1.1.4 =
* Fixed: `generateblocks/clear-cache` input schema now actually exposes `force` so proxy validation matches the callback

= 1.1.3 =
* Fixed: `generatepress/clear-cache` and `generateblocks/clear-cache` now accept `force` as an alias for `confirm` for client compatibility
* Fixed: cache clear abilities now reject `confirm=false` / `force=false` explicitly instead of silently proceeding

= 1.1.2 =
* Fixed: Removed hard plugin header dependency on abilities-api to avoid slug-mismatch activation blocking
* Fixed: list-elements status schema now supports any


= 1.1.1 =
* Fix: Store content in _generate_element_content meta for hook elements (was only saving to post_content)
* Change: delete-element now only moves to trash (no permanent deletion)
* Add: list-elements can filter by status (publish, draft, trash, etc)
* Add: restore-element ability to restore trashed elements

= 1.1.0 =
* Add: Module status and module settings abilities
* Add: Typography and font manager abilities
* Add: Starter Site cache inspection/clear abilities
* Update: get-settings includes typography rules and site identity
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
