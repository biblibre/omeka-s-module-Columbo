# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - 2026-11-24

- Split statistics into tabs, loaded on demand: Sites, Resources, Resource
  metadata, Users, Modules, Storage information
- Add sorting, text filter and CSV download to every table, and an "Export all
  (CSV)" button
- Add storage information: size of each directory of the file store in use
  (local or AnyCloud), difference with the sizes registered in the database,
  size of the Omeka S directory
- Add size of original files per site, item set and file type
- Add item sets table and modules tab
- Make sites and resource count queries faster
- Require Omeka S 4 (Omeka S 3 was declared but not supported)
- Fix PHP 7.4 compatibility and deprecation notices on PHP 8.4

## [0.1.4] - 2025-06-24

- Fix "Items with(out) media" counts
- Fix french translation

## [0.1.3] - 2025-06-06

- Remove unnecessary joins to make queries faster

## [0.1.2] - 2025-06-06

- Make the users query faster by removing a join

## [0.1.1] - 2025-06-06

- Make the resource templates query faster by removing two joins

## [0.1.0] - 2025-04-10

Initial release

[0.2.0]: https://github.com/biblibre/omeka-s-module-Columbo/releases/tag/v0.2.0
[0.1.4]: https://github.com/biblibre/omeka-s-module-Columbo/releases/tag/v0.1.4
[0.1.3]: https://github.com/biblibre/omeka-s-module-Columbo/releases/tag/v0.1.3
[0.1.2]: https://github.com/biblibre/omeka-s-module-Columbo/releases/tag/v0.1.2
[0.1.1]: https://github.com/biblibre/omeka-s-module-Columbo/releases/tag/v0.1.1
[0.1.0]: https://github.com/biblibre/omeka-s-module-Columbo/releases/tag/v0.1.0
