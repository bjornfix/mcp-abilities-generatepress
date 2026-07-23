# Domain Context

## GenerateBlocks Request Content Projection

The deep Module that turns one caller-authorized, request-local Gutenberg
document into native GenerateBlocks CSS without mutating the canonical post,
generated CSS files, post metadata, or cache registry. Its Interface accepts a
document through `mcp_abilities_generatepress_generateblocks_request_content`;
the caller owns authorization and GP-MCP owns parser input and inline output
mode, including full rebuilds. The one upstream GenerateBlocks implementation
continues to generate the CSS. Normal canonical requests retain the
upstream file/cache lifecycle. Workflow supplies Source and Translation staged
preview Adapters only after exact capability and host validation.
