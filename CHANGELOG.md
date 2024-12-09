## 3.0.3 - 2024-12-09
### Changed
- Improved readonly implementation (thanks to @d-karstens)

## 3.0.2 - 2024-12-05
### Changed
- Added Craft 5.3+ compatibility (thanks to @Marcuzz)

## 3.0.1 - 2024-11-12
### Changed
- This plugin is not (yet) compatible with Craft 5.3+

## 3.0.0 - 2024-05-29
### Added
- Added Craft 5 compatibility

## 2.0.0 - 2022-05-18
### Added
- Added Craft 4 compatibility

## 1.3.2 - 2022-03-29
### Fixed
- Bring input more in line with latest Craft changes, closes #34

## 1.3.1 - 2021-12-14
### Changed
- Omit saving of relations that were not modified

## 1.3.0 - 2021-08-25
### Changed
- Added Craft 3.7 compatibility (thanks to @brandonkelly)

## 1.2.5 - 2021-06-08
### Changed
- Show field handle in fields list

## 1.2.4 - 2021-03-04
### Added
- Added PHP8 support

## 1.2.3 - 2020-12-23
### Fixed
- Fixed PHP error when source was set to "*"

## 1.2.2 - 2020-09-21
### Fixed
- Fixed issue where only enabled elements were saved

## 1.2.1 - 2020-05-13
### Fixed
- Fixed issue where no fields could be found

## 1.2.0 - 2020-05-08
### Added
- Added support for Categories

### Fixed
- Fixed error when saving from a SuperTable field

## 1.1.8 - 2020-03-17
### Fixed
- Fixes issue when the cached element didn't have any old sources

## 1.1.7 - 2019-11-28
### Fixed
- Fixes issue where the cached element could be queried from the target site, while it should come from the source site

## 1.1.6 - 2019-11-27
### Fixed
- Fixes issue where we reversed to the primary site rather than the current site
- Fixes issue where a cached element could not be found, but the field still relied on it

## 1.1.5 - 2019-05-24
### Fixed
- Fixed issue where SQL errors could occur if the Craft installation's tables were prefixed

## 1.1.4 - 2019-05-09
### Fixed
- Fixed a PHP error that could occur of "all sources" was checked

## 1.1.3 - 2019-05-09
### Changed
- You can now select Entries fields in all contexts as target field

### Fixed
- Prevented selection of another reverse entries field as target field

## 1.1.2 - 2019-05-09
### Fixed
- Fixed eager loading map

## 1.1.1 - 2019-03-29
### Changed
- You can now only select Entries fields as target field.

## 1.1.0 - 2019-03-15
### Added
- Added Craft 3.1 compatibility

### Removed
- Removed Schematic compatibility (not compatible with Craft 3.1)

## 1.0.4 - 2018-12-10
### Fixed
- Fixed saving new reverse relations
- Fixed deleting reverse relations
- Fixed bug when importing with Schematic when the target field didn't yet exist
- Fixed bug where targets where validated

## 1.0.3 - 2018-12-07
### Fixed
- Fixed bug when importing with Schematic

## 1.0.2 - 2018-12-07
### Added
- Added Schematic support

## 1.0.1 - 2018-12-07
### Added
- Added plugin icon
- Added Dutch translation

### Fixed
- Fixed allowed sources implementation
- Fixed deprecation warning

## 1.0.0 - 2018-12-07
### Added
- Initial release
