# Columb'O

[Columb'O](https://github.com/biblibre/omeka-s-module-Columbo) is a module for
Omeka S that gathers various information about your Omeka S installation and
shows it all in the same place.

## Quick start

* Install the module (no configuration needed)
* Open the "Columb'O" entry of the admin menu

## Statistics

Statistics are grouped in tabs on a single page:

| Tab | Tables |
|---|---|
| Sites | Sites (items, item sets, pages, users per role, media size), themes and how many sites use them |
| Resources | Item sets, items and media by visibility and without template or class; items with/without media or item set and media per item (min, max, average); item sets (items, media, media size, sites); media per file type; resource templates; vocabularies |
| Resource metadata | Properties and classes and how many resources use them, values linking to another resource by type of the linked resource, value annotations |
| Users | Active and inactive users per role |
| Modules | Modules with their version, installed version and state |
| Storage information | Media, assets, files, remote storage and Omeka S directory sizes |

Each tab can be opened directly from its URL, for example
`/admin/columbo/resources`.

## Tables

* Every column can be sorted, with the sort box or by clicking the column
  header. Sizes are sorted by their value in bytes.
* Rows can be filtered by text.
* "Download CSV" exports a table as displayed (sorted and filtered rows).
* "Export all (CSV)" exports every statistic in a single CSV row.

## Performance

* A tab is loaded only when it is first displayed, and prefetched when the
  pointer hovers it. A loaded tab is kept in memory until "Refresh" is used.
* Directory and remote storage sizes, the slowest calculations, are computed
  after the "Storage information" tab is displayed, one after another.

## Disk usage calculations

* Total media size is the sum of the media sizes registered in the database.
  It may slightly differ from the size on disk.
* Total assets size is the size of the `files/asset` directory of Omeka S.
* Total ingested files is the size of the `files` directory of Omeka S.
* Total remote ingested files is the size of the remote storage, when files
  are stored with the AnyCloud module.
* Total Omeka install size is the size of the directory where Omeka S is
  installed. It may be inaccurate due to permission issues.

Directory sizes are computed with `du` when available, with a slower PHP
fallback otherwise.

## License

Columb'O is distributed under the GNU General Public License version 3 (GPLv3).
The full text of this license is given in the `LICENSE` file.
