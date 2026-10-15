# Changelog

English | [Français](CHANGELOG.fr.md)

## Unreleased

- Decode bounded loca/glyf outlines and assemble unhinted components with explicit offset policy, cycle/output budgets and quadratic paths; compare all points and drawn geometry with fontTools/HarfBuzz.

- Read vertical metrics and CFF origins; evaluate native HVAR/VVAR/MVAR deltas through explicit instance objects with bounded index maps and independent fontTools/HarfBuzz comparisons.

- Model name versions 0/1 with exact multilingual records and explicit decoders; expose every OS/2 version 0–5 field, version-specific embedding flags and optical ranges.

- Read fvar/avar 1.0 with immutable axis and named-instance objects, explicit user selection, bounded normalization and independent two-axis fontTools/HarfBuzz checks.

## 0.2.0 — 2026-10-15

- Execute GSUB types 5/6 in all contextual formats and reverse chaining type 8, including nested lookup order, bounded recursion and dynamically expanded input indices.
- Read GDEF 1.0/1.2/1.3: glyph/mark classes, mark filtering/attachment classes, attachment points, ligature carets and native item variation data.
- Execute native GPOS types 1–9: single/pair/class adjustments, cursive and mark attachments, all contextual formats and extensions, from explicit initial positions.
- Preserve ligature component identity independently from source clusters; retain attachments through subsequent placement and advance changes.
- Evaluate signed Device formats 1/2/3, native ItemVariationStore interpolation and conditional GSUB/GPOS feature variations at explicit normalized coordinates.
- Keep hinted outline-point access explicit; no TrueType/CFF bytecode, whole-font variation instantiation, Unicode script shaping or bidirectional layout is implied.
- Add original reproducible advanced/variable fonts, malformed/truncation/budget tests, 102 independent HarfBuzz cases, bilingual positioning/variation guides and runnable examples.

## 0.1.0 — 2026-10-14

- Introduce independent immutable OpenType objects for PHP 8.3+ on 64-bit platforms, without production dependencies.
- Read individual SFNT directories, preserve tables and validate bounded offsets, counts, glyph identities and checksums.
- Read Unicode cmap formats 4, 12 and 14 and horizontal metrics.
- Model GSUB scripts, language systems, features and lookups; apply single, multiple, alternate, ligature and extension substitutions with explicit selection and source clusters.
- Reject unsupported selected mechanisms. Contextual substitution, GDEF-dependent flags, GPOS, variation processing and outline interpretation remain pending.
- Preserve the supplied Adobe OTGuide.pdf unchanged; document sources and coverage in English and French.
- Compare supported results independently with fontTools and HarfBuzz. CI covers PHP 8.3, 8.4 and 8.5.
