# MCP Abilities - GeneratePress

Change the site's shared design through the same GeneratePress settings, Elements and GenerateBlocks Global Styles that WordPress editors use. Ask an authenticated assistant to inspect the current design, make a specific change and read it back.

[![Release 1.1.66](https://img.shields.io/badge/release-1.1.66-blue.svg)](https://downloads.devenia.com/mcp-abilities-generatepress.zip)
[![License: GPL v2 or later](https://img.shields.io/badge/license-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress 6.9+](https://img.shields.io/badge/WordPress-6.9%2B-blue.svg)](https://wordpress.org/)
[![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://www.php.net/)

**Stable tag:** 1.1.66

**Tested up to:** WordPress 7.1

**Requires:** WordPress 6.9 and PHP 8.0

**License:** GPLv2 or later

**Tags:** mcp, generatepress, theme, ai, automation

## What It Does

The add-on exposes theme settings, typography, colours, page layouts, Elements, pattern libraries and Global Styles as WordPress abilities. It also provides targeted CSS cache refreshes and featured-image size diagnostics.

GeneratePress keeps ownership of theme settings and Elements. GenerateBlocks keeps ownership of blocks, patterns and Global Styles. The assistant can work with these native objects, and a person can continue editing them in WordPress.

## The Real Workflow

Suppose the heading size needs to change across a site. The assistant first reads the active theme and available setting keys, inspects the typography rules, then updates the relevant global setting. It reads the result back and checks representative pages at desktop and mobile widths.

For a new section, it can discover the site's native pattern libraries, select a pattern and import its required Global Styles. The content guard rejects supported page and post saves when referenced Global Styles are missing or do not have usable native output.

## Why This Feels Different

A shared design change belongs in a shared setting or style. This add-on gives the assistant direct access to those objects. Native Block Element and Overlay Panel upserts reuse the same object on repeated calls, while Global Style updates report which selectors were created, changed, left unchanged or deleted.

## Before vs After

| Task | Manual workflow | With this add-on |
| --- | --- | --- |
| Adjust site typography | Find each relevant Customizer setting | Discover current keys, inspect and update the native global settings |
| Reuse a section design | Search pattern libraries and track required styles | Discover native libraries and import the selected pattern's required Global Styles |
| Maintain a shared hook section | Find the Element and its display rules | Read or upsert the native Block Element with explicit conditions |
| Diagnose missing image sizes | Inspect attachments individually | Audit featured images and request confirmed metadata regeneration |

## Who It Is For

Site owners, agencies and developers who already use GeneratePress and want an authenticated assistant to perform specific design and maintenance tasks. It is useful when the same typography, layout or reusable section serves several pages.

## Requirements

- WordPress 6.9 or later and PHP 8.0 or later.
- Native WordPress Abilities API and an authenticated MCP connection, normally through the WordPress MCP Adapter and MCP Expose Abilities.
- GeneratePress for theme operations. GeneratePress Premium features require the relevant installed and active modules.
- GenerateBlocks for block operations. Current Global Styles, Overlay Panels and other Pro features require the corresponding GenerateBlocks Pro functionality.
- Polylang is optional. When it copies a translation, this add-on includes the native GeneratePress page-layout metadata; it does not force later layout synchronisation.

Premium products and licences are separate. Discovery reports the control surfaces available on the actual installation.

## Documentation

- [Plugin page](https://devenia.com/plugins/mcp-abilities-generatepress/)
- [MCP Expose Abilities](https://devenia.com/plugins/mcp-expose-abilities/)
- [Runtime dependencies](DEPENDENCIES.md)

## Start Here

1. Activate GeneratePress and the GenerateBlocks features needed for the task.
2. Set up the WordPress Abilities API, MCP Adapter and MCP Expose Abilities connection.
3. Install this add-on and verify its abilities appear in authenticated discovery.
4. Ask the assistant to inspect the theme and available controls before changing them.
5. Give a specific task and check the saved result in WordPress and on the rendered page.

## Abilities (55)

| Ability | Purpose |
| --- | --- |
| `generatepress/get-info` | Get active theme information and GeneratePress Premium status. |
| `generatepress/clear-cache` | Clears GeneratePress dynamic CSS cache to force regeneration. |
| `generatepress/list-options` | List GeneratePress/GenerateBlocks options available in wp_options. |
| `generatepress/get-options` | Get specific GeneratePress/GenerateBlocks options by name. |
| `generatepress/update-options` | Update or delete GeneratePress/GenerateBlocks options by name. |
| `generatepress/get-settings` | Retrieves GeneratePress theme settings including colors, typography, layout, and global styles. |
| `generatepress/list-control-surface` | Discovers the active GeneratePress, GeneratePress Premium, GenerateBlocks, and Pro control surfaces available on this site. |
| `generatepress/list-setting-keys` | Discovers the live GeneratePress setting keys, classifies them, and returns module/theme-mod control surfaces so MCP clients can avoid guessing. |
| `generatepress/get-theme-mods` | Gets GeneratePress-relevant theme mods such as custom_logo and GP-prefixed mods. |
| `generatepress/update-theme-mods` | Updates GeneratePress-relevant theme mods. Use null to remove a mod. |
| `generatepress/get-custom-css` | Gets the WordPress Custom CSS for the active GeneratePress stylesheet. |
| `generatepress/update-custom-css` | Updates the WordPress Custom CSS for the active GeneratePress stylesheet. Pass an empty string to clear it. |
| `generatepress/patch-custom-css` | Patches the WordPress Custom CSS for the active GeneratePress stylesheet using exact or regex replacement. |
| `generatepress/clear-custom-css` | Clears the WordPress Custom CSS for the active GeneratePress stylesheet. |
| `generatepress/update-settings` | Updates GeneratePress theme settings. Merges with existing settings - only provided keys are updated. |
| `generatepress/update-global-design-settings` | Updates global GeneratePress design settings. Use this instead of page/block-level styling for site-wide design decisions. |
| `generatepress/list-modules` | Lists GeneratePress Premium module statuses (generate_package_* options). |
| `generatepress/list-module-settings` | Discovers all GeneratePress and GP Premium module settings options currently stored. |
| `generatepress/update-modules` | Activates or deactivates GeneratePress Premium modules (generate_package_* options). |
| `generatepress/get-module-settings` | Gets settings for any discovered GeneratePress or GP Premium module settings option. |
| `generatepress/update-module-settings` | Updates settings for any discovered GeneratePress or GP Premium module settings option. |
| `generatepress/get-blog-archive-settings` | Gets native WordPress reading settings and GeneratePress blog/layout settings that control the posts archive. |
| `generatepress/update-blog-archive-settings` | Updates native blog archive controls: reading options, GP archive layout keys, and GP Premium blog module settings. |
| `generatepress/get-typography` | Retrieves GeneratePress typography rules and font manager entries (Local Font Library). |
| `generatepress/update-typography` | Updates GeneratePress typography rules and/or font manager entries (Local Font Library). |
| `generatepress/get-site-library-cache` | Returns cached Starter Site library metadata without dumping the full dataset. |
| `generatepress/list-design-catalog` | Returns the complete active GeneratePress Site Library and GenerateBlocks Pattern Library catalog for contextual source-page design selection. The agent must choose from this live catalog; no site or pattern is preselected. |
| `generatepress/clear-site-library-cache` | Clears the cached Starter Site library to force a refresh. |
| `generateblocks/get-global-styles` | Retrieves current GenerateBlocks Pro Global Styles backed by native global classes. |
| `generateblocks/update-global-styles` | Upserts current GenerateBlocks Pro Global Styles, compiles their native style data, and explicitly deletes named class selectors. |
| `generateblocks/list-options` | Lists GenerateBlocks and GenerateBlocks Pro options in wp_options. |
| `generateblocks/get-options` | Gets specific GenerateBlocks or GenerateBlocks Pro options by name. |
| `generateblocks/update-options` | Updates or deletes GenerateBlocks and GenerateBlocks Pro options by name. |
| `generateblocks/list-control-surface` | Discovers GenerateBlocks global styles, defaults, plugin settings, dynamic CSS posts, and generated CSS file status. |
| `generateblocks/list-pattern-libraries` | Lists the GenerateBlocks Pattern Libraries available to the WordPress editor using the same local REST source as the editor. |
| `generateblocks/list-pattern-categories` | Lists categories for a GenerateBlocks Pattern Library using the same local REST source as the editor. |
| `generateblocks/search-pattern-library` | Searches a GenerateBlocks Pattern Library using the same local REST source as the editor. Pattern block markup is returned only when explicitly requested. |
| `generateblocks/import-pattern-global-styles` | Imports the native GenerateBlocks Pro Global Styles required by selected Pattern Library patterns before their block markup is saved. |
| `generatepress/get-page-meta` | Retrieves GeneratePress page-specific meta values for a post or page. |
| `generatepress/update-page-meta` | Updates GeneratePress page-specific settings like disabling title, sidebar layout, content width, navigation, and footer. |
| `generatepress/audit-duplicate-headlines` | Finds pages where GeneratePress would render the theme headline while Gutenberg content already contains an H1, and optionally disables the GeneratePress headline. |
| `generatepress/audit-page-layout-meta` | Audits pages or page families for expected GeneratePress layout meta and optional required content markers. Can repair GeneratePress meta mismatches. |
| `generatepress/list-elements` | Lists GeneratePress Elements (gp_elements) with optional filters. |
| `generatepress/get-element` | Retrieves a GeneratePress Element (gp_elements) by ID. |
| `generatepress/create-element` | Creates a new GeneratePress Element (gp_elements) with meta and content. |
| `generatepress/update-element` | Updates an existing GeneratePress Element (gp_elements). |
| `generatepress/upsert-block-element` | Idempotently creates or updates one native GeneratePress Block Element at a stable slug with exact display conditions. |
| `generatepress/list-overlay-panels` | Lists native GenerateBlocks Pro Overlay Panels and their types. |
| `generatepress/upsert-overlay-panel` | Idempotently creates or updates one native GenerateBlocks Pro Overlay Panel at a stable slug. |
| `generatepress/attach-menu-item-mega-menu` | Idempotently attaches a published native mega-menu Overlay Panel to one WordPress navigation menu item. |
| `generatepress/delete-element` | Moves a GeneratePress Element (gp_elements) to trash. Restore from WordPress admin. |
| `generatepress/restore-element` | Restores a trashed GeneratePress Element (gp_elements) by ID. |
| `generatepress/audit-featured-image-sizes` | Audits posts for missing featured images and missing generated image sizes so native GeneratePress archive images can be trusted. |
| `generatepress/regenerate-featured-image-sizes` | Regenerates attachment metadata for featured images on selected posts or recent posts, then reports remaining missing sizes. |
| `generateblocks/clear-cache` | Clears GenerateBlocks CSS cache metadata while preserving generated files by default. |

## Usage Examples

Ask: “Inspect the current heading typography. Show me which global setting controls it, then apply the size we have agreed.” The assistant can start with:

```json
{}
```

Use that input with `generatepress/get-info`, followed by `generatepress/list-setting-keys` and `generatepress/get-typography`.

To inspect Element 123 without returning its content, call `generatepress/get-element` with:

```json
{"id":123,"include_meta":true,"include_content":false}
```

To create or update a native Global Style, call `generateblocks/update-global-styles` with the approved selector and native style data, for example:

```json
{"global_styles":[{"selector":".example-section","status":"publish","styles":{"paddingTop":"2rem","paddingBottom":"2rem"}}]}
```

Read it back through `generateblocks/get-global-styles` and check a block using the class. The add-on compiles the native style data; callers do not need to supply a second CSS copy.

## Safety and Ownership

Every ability has a WordPress capability check. Theme operations normally require `edit_theme_options`; broader option and module operations require `manage_options`. Pattern discovery requires `edit_posts`, while featured-image operations require `upload_files`. Regeneration also checks `edit_post` for each attachment before accessing its file or changing metadata. These operations act with the connected WordPress user's privileges.

Option and metadata operations are bounded to the supported GeneratePress and GenerateBlocks names. Destructive operations have their own schemas and confirmation requirements. Read the discovered schema before a write, and inspect the result afterwards. An authenticated write can affect many pages when it changes a global setting.

RTL sidebar mirroring is opt-in through `mcp_generatepress_mirror_rtl_sidebars`. It uses native layout and widget filters and preserves stored assignments. The add-on does not supply an AI model, premium theme licence or MCP client.

## Installation

Download the [plugin ZIP](https://downloads.devenia.com/mcp-abilities-generatepress.zip). In WordPress, open **Plugins → Add New → Upload Plugin**, select the ZIP, install it and activate it. Configure the required theme, optional premium features and authenticated MCP connection, then verify discovery.

## Recent Changes

### 1.1.66

- Require permission to edit each attachment before regenerating its image files or metadata.

### 1.1.65

- Add opt-in native RTL sidebar mirroring and copy GeneratePress layout metadata when Polylang creates translations.
- Extend the Global Style content guard to posts and use a compact style validation index.
- Recognise intentional empty semantic styles while rejecting missing or malformed native style data.
- Distinguish CSS selectors from class-like text in URLs, comments, attribute values and generated content.
- Accept native inline GenerateBlocks CSS when checking generated output.
- Honour `include_content: false` throughout the Element response.

See [readme.txt](readme.txt) for earlier changes.

## Contributing

Report the affected ability, installed WordPress and theme/plugin versions, a minimal input and the expected versus actual result. Remove credentials and private site content from reports. Keep proposed changes within the native WordPress, GeneratePress and GenerateBlocks interfaces.

## License

GPLv2 or later. See [the licence text](https://www.gnu.org/licenses/gpl-2.0.html).

## Author

[basicus](https://profiles.wordpress.org/basicus/).

## Links

- [Plugin page](https://devenia.com/plugins/mcp-abilities-generatepress/)
- [Download](https://downloads.devenia.com/mcp-abilities-generatepress.zip)
- [Plugin directory](https://devenia.com/plugins/)
