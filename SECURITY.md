# Security policy

English | [Français](SECURITY.fr.md)

Report vulnerabilities privately through [GitHub Security Advisories](https://github.com/PDFToolkit/opentype/security/advisories/new), with the version, minimal input and configured limits.

Readers receive application-supplied bytes. They do not open paths or URLs or
activate execution implicitly. `ReadLimits` bounds source bytes, tables, layout records,
glyph counts, layout operations, nested lookup depth and output. Binary views bound reads; readers validate
indices, ordering and referenced glyphs. Unsupported selected mechanisms fail.

Tests exercise truncated and malformed input, invalid offsets and indices,
checksum failures and bounded expansion. Independent checks compare supported
results with fontTools and HarfBuzz. These checks cover the implemented readers;
this package is not a sanitizer for arbitrary font tables. Applications control
file access and execution-time and memory quotas.

GSUB and GPOS execution own their mutable buffers and attachment graphs. Recursive
lookups consume explicit depth and operation budgets. GDEF variation instances
precompute native deltas; Device scale and outline-point capabilities are supplied
explicitly. TrueType assembly and CFF/CFF2 Type 2 execution use `OutlineLimits`
for aggregate records, bytes, operations, depth, program bytes and expanded points.
Composite/subroutine cycles are refused; CFF random input is an explicit capability.
The library does not execute TrueType instructions, apply rasterizer hints, run
external scripts/engines or decompress web-font containers.

The `0.3.x` series receives security fixes. PHP 8.3+ on a 64-bit platform is
required; CI covers PHP 8.3, 8.4 and 8.5.
