# Reverse Relations for Craft

Reverse Entry Relations shows entries that link to the current entry through a configured Entries field. Editors can add or remove those links from the control panel. The field is also available as an entry relation in Twig and GraphQL.

## Requirements

Version 4.0 requires Craft CMS 6.0.0-beta.2 or later and PHP 8.5 or later. It runs without the Yii2 adapter.

## Installation

From your Craft project, run:

```sh
composer require robuust/craft-reverserelations:^4.0
```

Install Reverse Relations under **Settings → Plugins**. Add a **Reverse Entry Relations** field and select the **Target Field** that holds the forward links. Turn on **Read-only** to display the links without allowing editors to change them.

## Upgrading from 3.x

Existing Reverse Entry Relations fields keep their handles, target field, sources, site settings, and read-only setting. Upgrade Craft to 6 before installing 4.0. If your project also uses Reverse Category Relations, convert those fields before moving to this version.
