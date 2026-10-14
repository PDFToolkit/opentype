# Original reproducible fixtures

These fonts are original PDFToolkit fixtures under the package's MIT license
(also preserved in `ToolkitLayout.LICENSE.txt`). They do not contain copied
third-party outlines or commercial font data.

- `ToolkitLayout.ttf`: simple outlines, explicit GSUB scripts, languages and
  features. Regenerate with `tools/build_layout_fixture.py`.
- `ToolkitUnicode.ttf` and `ToolkitUnicode.otf`: original TrueType and CFF
  outlines, horizontal metrics and Unicode cmap 4/12/14 including default,
  non-default and Mongolian variation sequences. Regenerate with
  `tools/build_unicode_fixtures.py`.

The generators fix timestamps. Their `--check` modes compare a temporary rebuild
with the committed bytes without modifying the originals. The validators use
fontTools and HarfBuzz separately from PHP.
