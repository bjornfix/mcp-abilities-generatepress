# Runtime dependencies

- [WordPress 6.9](https://wordpress.org/documentation/wordpress-version/version-6-9/) supplies the native WordPress runtime and theme settings APIs used by the add-on.
- [PHP 8.0](https://www.php.net/releases/8.0/en.php) is the minimum PHP runtime for the add-on.
- [WordPress Abilities API](https://developer.wordpress.org/apis/abilities-api/) registers the native abilities that the add-on exposes to an AI assistant.
- [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter/) transports the registered WordPress abilities to authenticated MCP clients.

- GeneratePress owns the theme settings and layout interfaces. GeneratePress Premium supplies its optional modules and Elements.
- GenerateBlocks owns block rendering and CSS. GenerateBlocks Pro supplies current Global Styles, Overlay Panels and its premium pattern-library interfaces.
- MCP Expose Abilities provides discovery in the usual Devenia MCP stack.
- Polylang is optional; the translation-copy integration uses its native page-metadata filter.
