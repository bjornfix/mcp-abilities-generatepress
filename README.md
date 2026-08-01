# MCP Abilities - GeneratePress

GeneratePress and GenerateBlocks abilities for MCP. Manage theme settings, elements, global styles, page meta, and caches.

[![Release 1.1.50](https://img.shields.io/badge/release-1.1.50-blue.svg)](https://downloads.devenia.com/mcp-abilities-generatepress.zip)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![WordPress](https://img.shields.io/badge/WordPress-6.9%2B-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://php.net)

**Tested up to:** 7.0
**Stable tag:** 1.1.50
**Tags:** mcp, generatepress, theme, ai, automation
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

## What It Does

GeneratePress and GenerateBlocks abilities for MCP. Manage theme settings, elements, global styles, page meta, and caches.

This plugin is part of the Devenia MCP abilities ecosystem. It gives an MCP-capable agent a focused, authenticated way to work with GeneratePress work inside WordPress through MCP.

**Example:** "Handle this WordPress maintenance task directly." - The agent can inspect the site, call the relevant ability, and return the result without making the human click through wp-admin for every step.

## The Real Workflow

In practice, the human should not have to memorize every ability name.

The normal pattern is:

1. install the base MCP stack
2. install only the add-ons the site actually needs
3. let the agent discover the available abilities
4. give the agent a clear task with boundaries
5. verify the result in WordPress

The human's job is mostly to describe the goal.
The agent's job is to figure out the mechanics.

## Why This Feels Different

Most WordPress automation still leaves the repetitive part to the human.

This plugin is different because the agent can act inside the site through a narrow, authenticated ability surface:

- inspect current site state before changing anything
- run the specific action needed for the task
- return structured results that are easy to verify
- keep the workflow inside WordPress instead of a separate checklist

That changes the experience from:

- `Here is what you should do in wp-admin`

to:

- `Tell the agent what needs doing, and let it carry out the work`

## Before vs After

### Before

- ask the AI what to do
- copy the answer into WordPress by hand
- click through wp-admin for the repetitive bits
- postpone maintenance because the task is tedious

### After

- tell the agent what needs doing
- let it inspect the relevant WordPress state
- let it run the targeted ability
- verify the result and move on

## Who It Is For

This is a good fit for:

- agencies managing WordPress sites with AI-assisted maintenance
- operators who want agents to do real WordPress work instead of producing instructions
- teams already using MCP Expose Abilities
- sites where this WordPress area is updated often enough to deserve automation

It is especially useful when the manual version is repetitive enough that important maintenance gets delayed.

## Documentation

Start with the main plugin page and base stack documentation:

- [MCP Expose Abilities](https://devenia.com/plugins/mcp-expose-abilities/)
- [Plugin page](https://devenia.com/plugins/mcp-abilities-generatepress/)
- [Getting Started](https://github.com/bjornfix/mcp-expose-abilities/wiki/Getting-Started)
- [Install Order and Dependencies](https://github.com/bjornfix/mcp-expose-abilities/wiki/Install-Order-and-Dependencies)

If you are using an AI agent, the simplest instruction is often just:

- `Read https://github.com/bjornfix/mcp-expose-abilities and figure out the stack before making changes.`

## Start Here

If you are new to the stack, use this order:

1. Install **Abilities API**.
2. Install **MCP Adapter**.
3. Install **MCP Expose Abilities**.
4. Install **MCP Abilities - GeneratePress**.
5. Confirm the new abilities appear in discovery.
6. Give the agent a clear task that uses this add-on.

If you skip base-stack verification and start with add-ons immediately, troubleshooting gets harder than it needs to be.

## Abilities (48)

| Ability | Description |
|---------|-------------|
| `generatepress/get-info` | Get active theme and GeneratePress Premium status |
| `generatepress/list-options` | List GeneratePress/GenerateBlocks options in wp_options |
| `generatepress/get-options` | Get specific GeneratePress/GenerateBlocks options |
| `generatepress/update-options` | Update or delete GeneratePress/GenerateBlocks options |
| `generatepress/get-settings` | Get theme settings (colors, typography, layout) |
| `generatepress/list-control-surface` | Discover GeneratePress, GeneratePress Premium, GenerateBlocks, and Pro control surfaces |
| `generatepress/list-setting-keys` | Discover live GeneratePress setting keys and classify the full control surface |
| `generatepress/update-settings` | Update theme settings |
| `generatepress/update-global-design-settings` | Update global GeneratePress design settings for typography, colors, layout, spacing, buttons, and site identity |
| `generatepress/get-theme-mods` | Get GeneratePress-relevant theme mods |
| `generatepress/update-theme-mods` | Update GeneratePress-relevant theme mods |
| `generatepress/get-custom-css` | Get WordPress Custom CSS for the active GeneratePress stylesheet |
| `generatepress/update-custom-css` | Update or clear WordPress Custom CSS for the active GeneratePress stylesheet |
| `generatepress/patch-custom-css` | Patch WordPress Custom CSS using exact or regex replacement |
| `generatepress/clear-custom-css` | Clear WordPress Custom CSS for the active GeneratePress stylesheet |
| `generatepress/get-typography` | Get typography rules and font manager entries |
| `generatepress/update-typography` | Update typography rules and font manager entries |
| `generatepress/list-modules` | List GeneratePress Premium module statuses |
| `generatepress/update-modules` | Activate or deactivate GeneratePress modules |
| `generatepress/list-module-settings` | Discover all stored GeneratePress and GP Premium module settings options |
| `generatepress/get-module-settings` | Get settings for a GeneratePress module |
| `generatepress/update-module-settings` | Update settings for a GeneratePress module |
| `generatepress/get-blog-archive-settings` | Get native WordPress and GeneratePress blog archive controls |
| `generatepress/update-blog-archive-settings` | Update native blog archive and GP blog module controls |
| `generatepress/get-site-library-cache` | Inspect Starter Site cache metadata |
| `generatepress/clear-site-library-cache` | Clear Starter Site cache |
| `generatepress/clear-cache` | Clear GeneratePress dynamic CSS cache |
| `generatepress/get-page-meta` | Get page-specific settings (disable title, sidebar, footer, etc.) |
| `generatepress/update-page-meta` | Update page-specific settings (disable title, sidebar, footer, etc.) |
| `generatepress/list-elements` | List GeneratePress Elements (hooks, blocks, headers, layouts) |
| `generatepress/get-element` | Get a GeneratePress Element by ID |
| `generatepress/create-element` | Create a GeneratePress Element |
| `generatepress/update-element` | Update a GeneratePress Element |
| `generatepress/upsert-block-element` | Idempotently create or update a native GeneratePress Block Element with exact display conditions |
| `generatepress/delete-element` | Delete a GeneratePress Element |
| `generatepress/restore-element` | Restore a trashed GeneratePress Element |
| `generatepress/audit-featured-image-sizes` | Audit posts for missing featured-image sizes |
| `generatepress/regenerate-featured-image-sizes` | Regenerate featured-image attachment metadata |
| `generateblocks/get-global-styles` | Get GenerateBlocks global styles |
| `generateblocks/update-global-styles` | Update GenerateBlocks global styles |
| `generateblocks/list-options` | List GenerateBlocks and GenerateBlocks Pro options |
| `generateblocks/get-options` | Get GenerateBlocks and GenerateBlocks Pro options |
| `generateblocks/update-options` | Update or delete GenerateBlocks and GenerateBlocks Pro options |
| `generateblocks/list-control-surface` | Discover GenerateBlocks options, CSS posts, and generated CSS status |
| `generateblocks/list-pattern-libraries` | List GenerateBlocks Pattern Libraries available to the editor, including custom/local libraries |
| `generateblocks/list-pattern-categories` | List categories for a GenerateBlocks Pattern Library by library ID |
| `generateblocks/search-pattern-library` | Search GenerateBlocks Pattern Library patterns by library ID and optionally return block markup |
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

### Update global design settings

Use this ability for site-wide design decisions instead of page/block-level styling. Named sections cover common changes; use `settings` for any other flat GeneratePress setting key. Use `generatepress/list-setting-keys` first when you need exact live keys instead of guessing.

```json
{
  "ability_name": "generatepress/update-global-design-settings",
  "parameters": {
    "typography": {
      "h1": {
        "fontFamily": "Fraunces",
        "fontWeight": "300",
        "fontSize": "64",
        "fontSizeMobile": "42",
        "lineHeight": "0.98",
        "letterSpacing": "-0.025em",
        "textTransform": "none"
      },
      "body": {
        "fontFamily": "Manrope",
        "fontSize": "20",
        "lineHeight": "1.5"
      },
      "footer_widget_title": {
        "fontFamily": "Fraunces",
        "fontWeight": "500",
        "fontSize": "28px",
        "lineHeight": "1.18"
      }
    },
    "layout": {
      "container_width": 1140
    },
    "spacing": {
      "content_top": "80"
    },
    "settings": {
      "paragraph_margin": "1.5",
      "underline_links": "never"
    }
  }
}
```

### Update typography rules (Local Font Library)

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

### Discover the full control surface

```json
{
  "ability_name": "generatepress/list-setting-keys",
  "parameters": {
    "include_values": false,
    "include_known_absent": true
  }
}
```

```json
{
  "ability_name": "generateblocks/list-control-surface",
  "parameters": {
    "include_values": false,
    "limit": 100
  }
}
```

### Control native blog archives

```json
{
  "ability_name": "generatepress/update-blog-archive-settings",
  "parameters": {
    "reading_settings": {
      "page_for_posts": 6744,
      "posts_per_page": 12
    },
    "generate_settings": {
      "blog_layout_setting": "no-sidebar"
    },
    "blog_settings": {
      "post_image": true,
      "post_image_size": "medium_large",
      "date": true,
      "author": false
    }
  }
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

`generateblocks/clear-cache` preserves existing generated CSS files by default and only clears cache metadata. Use `{"delete_files": true}` only with explicit `post_ids` for an atomic destructive refresh; global destructive deletion is rejected before mutation. Warming verifies that each expected per-page CSS file was regenerated. If any target fails or is skipped, the ability returns `success: false` and restores prior files, registry state, and regeneration metadata. You can also pass `limit` to bound warming. Targeted invalidation preserves unrelated registry entries; a non-destructive full clear derives its warm set from published WordPress content as well as regenerable cache metadata.

## Changelog

### 1.1.50

- Replace the archive-specific Element writer with one idempotent native Block Element Interface for any exact GeneratePress display conditions.

### 1.1.49

- Keep linked Query-card media compact beside directory copy instead of stretching it across the card.

### 1.1.48

- Standardize Query-card inventory and detail roles so reusable child collections receive their linked featured images.

### 1.1.47

- Query-card images now retain their native square dimensions without covering the card text.

### 1.1.46

- Query-card images now stay inside their square and open the correct detail page.

### 1.1.45

- Project reusable card media through the native GenerateBlocks Query tree before its direct nested rendering.

### 1.1.44

- Added a reusable native featured-image slot to complete GenerateBlocks Query cards, with square containment and no per-card setup.

### 1.1.43

- Apply declared GenerateBlocks Query card-summary limits to source improvements before review, while still allowing a new summary to repair an invalid existing one.

### 1.1.42

- Fixed targeted GenerateBlocks CSS refreshes so the upstream regeneration marker survives invalidation and warm failures fail closed with exact prior file, registry, and metadata restoration; unsafe global destructive deletion is rejected before mutation.
- Added a versioned Grid Projection rollout that invalidates regenerable CSS registry entries once when projected native attributes change, ensuring existing pages rebuild with the current geometry.

### 1.1.41

- Preserves equal and proportional visible GenerateBlocks grid widths by distributing each row's native gutter across its percentage columns at every responsive breakpoint.

### 1.1.40
- Renders caller-authorized request-local GenerateBlocks content with native inline CSS while leaving canonical generated-CSS files, metadata, and registry state unchanged.

### 1.1.39
- Supplies GenerateBlocks design markers through the vendor-neutral Content Write Gate Adapter.

### 1.1.38
- Adds an explicit-only, bounded GenerateBlocks Query card-summary contract with no generated full-content fallback.
- Exposes token-preserving visible and accessible card actions to Devenia Workflow and validates translated direct-child inventories.

### 1.1.37
- Fixed targeted GenerateBlocks cache invalidation so it preserves unrelated registry entries, while a full clear rebuilds its warm set from authoritative published WordPress content instead of trusting regenerable cache metadata.

### 1.1.36
- Added one opt-in GenerateBlocks Grid Projection Module shared by dynamic-CSS parsing, rendered blocks, and Workflow publication, with responsive LTR/RTL native spacing and no page, language, text, ID, or CSS inference.
- Moved GenerateBlocks post-regeneration ownership out of Workflow and made cache clearing invalidate the actual generated-post registry before optionally warming the captured post set.

### 1.1.35
- Fixed multi-post GenerateBlocks CSS warming so every requested post receives and verifies its own generated file instead of only the first post succeeding.

### 1.1.34
- Fixed native GeneratePress disable-elements meta keys that use the `_generate-` prefix.

### 1.1.33
- Fixed Pattern Library list/search responses so they no longer expose public key hints or preview markup by default.

### 1.1.32
- Fixed Pattern Library DTO field normalization so GenerateBlocks library values are read correctly.

### 1.1.31
- Fixed Pattern Library abilities to read the GenerateBlocks library registry directly so enabled, disabled, custom, and local libraries are discoverable by library ID without exposing public keys.

### 1.1.30
- Added `generateblocks/list-pattern-libraries`, `generateblocks/list-pattern-categories`, and `generateblocks/search-pattern-library` for editor-native GenerateBlocks Pattern Library discovery, including custom/local design libraries by library ID.

### 1.1.29
- Fixed optional-input ability callbacks so null MCP input is normalized to an empty array.

### 1.1.28
- Fixed optional-input ability schemas so they match the PHP array transport shape used by the MCP adapter.

### 1.1.27
- Fixed optional-input schema compatibility so null is not passed into array-typed ability callbacks.

### 1.1.26
- Fixed optional-input GeneratePress and GenerateBlocks abilities so empty MCP input objects are accepted reliably.

### 1.1.25
- Added `generatepress/audit-page-layout-meta` to audit page families for expected GeneratePress layout meta and optional content markers, with idempotent meta repair.

### 1.1.24
- Added verification guardrails to `generatepress/update-page-meta`: requested GeneratePress page meta is read back after write, and layout-sensitive updates verify frontend body classes by default.
- Added optional expected/forbidden body-class checks so special GeneratePress setups can be verified without hardcoding site-specific layouts.

### 1.1.23
- Fixed automatic page-layout sync so it detects GenerateBlocks headline H1 blocks, not only core heading blocks.
- Fixed `generatepress/get-settings` layout output so it includes regular page, blog/archive, and single post sidebar layout defaults.

### 1.1.22
- Fixed `generatepress/get-custom-css` compatibility schema so it is applied to the correct ability.

### 1.1.21
- Fixed `generatepress/get-custom-css` to expose an explicit compatibility input property so MCP clients that reject empty object schemas can call it reliably.

### 1.1.20
- Added `generatepress/get-custom-css`, `generatepress/update-custom-css`, `generatepress/patch-custom-css`, and `generatepress/clear-custom-css` for managing WordPress Custom CSS through the GeneratePress MCP surface.
- Added `custom_css_post_id` to the supported theme-mod surface so Custom CSS state is visible alongside other GeneratePress customizer data.
- Added `generatepress/list-control-surface` to discover GeneratePress, GeneratePress Premium, GenerateBlocks, and Pro control surfaces on each site.
- Added `generatepress/list-module-settings` and expanded module settings abilities so agents can discover and update any stored `generate_*_settings` option, not only a fixed module list.
- Added `generateblocks/list-options`, `generateblocks/get-options`, and `generateblocks/update-options` for bounded GenerateBlocks and GenerateBlocks Pro option management.

### 1.1.19
- Added automatic GeneratePress headline disabling for pages whose Gutenberg content already contains an H1, preventing duplicate visible titles.
- Added `generatepress/audit-duplicate-headlines` to find and optionally fix existing duplicate-title pages.

### 1.1.18
- Updated `Tested up to` to WordPress 7.0 for Plugin Check compliance.

### 1.1.17
- Added typography groups for common GeneratePress surfaces beyond body/navigation/buttons/headings: `html`, `site_title`, `mobile_navigation_site_title`, `site_tagline`, `subnavigation`, `entry_meta`, `sidebar_widget_title`, `sidebar_widget_text`, `footer_widget_title`, `footer_widget_text`, and `footer_bar_text`.
- Fixed selector-only global typography updates so agents can use `generatepress/update-global-design-settings` instead of raw `generate_settings.typography` option writes.

### 1.1.16
- Fixed GenerateBlocks CSS generation to include matching GeneratePress Block Element content for archive contexts, so posts-page hero/CTA Elements are styled by the page CSS file.

### 1.1.13
- Fixed featured-image audit/regeneration abilities to use the supported `site` ability category so they register reliably.

### 1.1.12
- Added `generatepress/list-setting-keys` to discover live GeneratePress settings, classify keys, and expose module/theme-mod/image-size control surfaces.
- Added theme-mod, native blog archive, featured-image size audit/regeneration, and GenerateBlocks control-surface abilities.
- Expanded `generatepress/update-global-design-settings` with the spacing group so global spacing belongs in GeneratePress settings too.

### 1.1.11
- Added `letterSpacing` support to `generatepress/update-global-design-settings` typography groups.
- Added a flat `settings` object to `generatepress/update-global-design-settings` so the global settings workflow can update any GeneratePress global setting, not just the named convenience sections.

### 1.1.10
- Added `generatepress/update-global-design-settings` for site-wide typography, colors, layout, buttons, and site identity changes.
- Hardened `generatepress/update-settings` so global design keys and nested design-section objects are rejected instead of bypassing the global design-settings workflow.

### 1.1.9
- Fixed targeted destructive GenerateBlocks cache clears so `delete_files=true` with `post_ids` deletes only those posts' expected CSS files.

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

## Contributing

PRs welcome. Keep changes focused on the plugin's WordPress ability surface and preserve authenticated, explicit workflows.

## License

GPL-2.0+

## Author

[Devenia](https://devenia.com) - We've been doing SEO and web development since 1993.

## Links

- [Plugin Page](https://devenia.com/plugins/mcp-expose-abilities/#add-ons)
- [MCP Expose Abilities](https://devenia.com/plugins/mcp-expose-abilities/)
- [GitHub Releases](https://github.com/bjornfix/mcp-abilities-generatepress/releases)

## Star and Share

If this plugin saves you time or makes WordPress maintenance easier to verify, please:

- star the repo
- share it with people running WordPress sites
- point them to the main plugin page so they can see what the ecosystem can actually do

Why do it?

Because agent-friendly open WordPress tooling helps more of the boring but important work get done.
