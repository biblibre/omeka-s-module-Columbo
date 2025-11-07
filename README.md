# Columb'O

[Columb'O](https://github.com/biblibre/omeka-s-module-Columbo) is a module for
Omeka S that gathers various informations about your Omeka S installation and
show them all in the same place.

This includes (non-exhaustive list):

* Number of item sets, items, media (grouped by visibility)
* Number of items with/without media
* Number of items with/without item sets
* Number of media per items (min, max, average)
* Number of resources without resource template
* Number of resources without class
* Number of media per media type
* Number of item sets, items, pages per site
* List of themes and how many sites use them
* List of total media size per site
* List of properties used and how many resources use them
* List of classes and resource templates and how many resources use them
* Number of users per role

## Disk usage calculations

* Total media size is the sum of the registered media sizes in the database. Could slightly differ than the one on disk.
* Total /files size is the size of the /file directory of Omeka
* Total asset size is the is the size of the /file/asset directory of Omeka
* Total Omeka install size is the size of the Omeka directory where it is installed. It may be not exactly accurate due to permission issues.

## Quick start

* Install the module
* Browse the "Columb'O" navigation menu
* The module does NOT need to be configured at all

## License

Columb'O is distributed under the GNU General Public License version 3 (GPLv3).
The full text of this license is given in the `LICENSE` file.
