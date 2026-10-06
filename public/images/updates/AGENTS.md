# Announcement images

- Put new announcement hero images in `hero/` and social preview images in `social/`.
- Use stable, descriptive filenames. Store their public paths as `images/updates/hero/<filename>` or `images/updates/social/<filename>`.
- These folders feed the respective Filament image pickers. Commit new assets so deployment makes them available.
- Keep existing images at their current paths. Do not move, rename, overwrite, or delete published assets without checking existing announcement, Markdown, email, and shared URL references.
- Images used only inside announcement Markdown can remain outside the hero and social folders. Neither picker browses the legacy files directly under this directory.
- The picker labels images as unused by announcements based on saved hero, social, and Markdown references across all announcement states. This does not establish that an image is unused elsewhere or safe to delete.
