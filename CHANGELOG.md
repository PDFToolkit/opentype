# Changelog

English | [Français](CHANGELOG.fr.md)

## Unreleased

## 0.1.0 — 2026-10-14

- Introduce independent immutable OpenType objects for PHP 8.3+ on 64-bit platforms, without production dependencies.
- Read individual SFNT directories, preserve tables and validate bounded offsets, counts, glyph identities and checksums.
- Read Unicode cmap formats 4, 12 and 14 and horizontal metrics.
- Model GSUB scripts, language systems, features and lookups; apply single, multiple, alternate, ligature and extension substitutions with explicit selection and source clusters.
- Reject unsupported selected mechanisms. Contextual substitution, GDEF-dependent flags, GPOS, variation processing and outline interpretation remain pending.
- Preserve the supplied Adobe OTGuide.pdf unchanged; document sources and coverage in English and French.
- Compare supported results independently with fontTools and HarfBuzz. CI covers PHP 8.3, 8.4 and 8.5.
