# Releasing pdftoolkit/opentype

Use independent semantic versions and annotated `vX.Y.Z` tags. Before 1.0,
features or API changes increment the minor version; compatible fixes and
documentation increment the patch version. The first release is 0.1.0.

1. Update both changelogs and finish coherent atomic commits. Keep a clean tree.
2. Validate Composer, install the lock, check platform requirements, audit the
   lock and run `composer check` on PHP 8.3, 8.4 and 8.5. Run independent font
   validators and check original reference identity.
3. Push the candidate to main and require Quality jobs for its exact commit on
   all three PHP versions.
4. Prepare notes describing implemented scope and limitations. Check the tag is
   unused, create an annotated tag at the validated commit, push it and publish
   the GitHub release using the prepared notes file.
5. Verify local and remote peeled tags, release and tag checks. Submit to
   Packagist if new; verify stable source and distribution references and a fresh
   isolated consumer with no custom repositories.
6. Pin that version and exact commit in shared documentation. Build the bilingual
   API and guides, verify deployment and publish its independent release.

Never move a published tag or add a Composer version field. Runtime dependencies
must not bring in PDF packages. Reference PDFs and development fixtures stay out
of Composer distributions.
