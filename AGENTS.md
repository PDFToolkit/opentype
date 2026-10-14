# OpenType contributor instructions

- This package owns independent OpenType objects and bounded font parsing. It has no production dependency on PDFToolkit's PDF packages.
- Follow the official OpenType specification terminology. Use immutable value objects and explicit reader, selection and execution objects. Avoid static factories, singletons, global registries, implicit file access and hidden feature activation.
- Public readers consume application-supplied bytes. Validate relative offsets, counts, indices and resource limits before allocating or following references. Preserving a table's bytes does not implement its semantics.
- Preserve supplied Adobe references under their original filename and original bytes. The usage guide explains features; technical definitions use the official OpenType specification. Document sources and coverage separately.
- Require PHP 8.3 or newer on 64-bit platforms. Validate the exact release candidate on PHP 8.3, 8.4 and 8.5 and run the independent fontTools and HarfBuzz checks.
- Keep atomic commits. Publish annotated version tags and GitHub releases for completed changes. Never move a published tag. Verify fresh stable consumers through Packagist.
- English is the default: README.md and docs/*.md. Maintain complete French counterparts in README.fr.md and docs/fr/*.md, with reciprocal links and equivalent examples.
- The shared site lives in PDFToolkit/pdftoolkit.github.io. After publication, update its exact released source pin and publish a separate documentation release. Do not release unrelated packages.
