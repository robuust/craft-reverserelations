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

Existing Reverse Entry Relations fields keep their handles, target field, sources, site settings, and read-only setting. Upgrade Craft to 6 before installing 4.0. Projects using Reverse Category Relations should run the helper below while still on Craft 5 and Reverse Relations 3.0.6.

### Convert Reverse Category Relations on Craft 5

Back up the database and Project Config. Copy [the standalone helper](bin/prepare-craft-6.php) to the Craft 5 project root, outside the web root. Keep the old plugin installed during the conversion.

1. Capture a plan before converting categories.

   ```sh
   php prepare-craft-6.php plan > reverse-categories.json
   ```

2. Run Craft's `entrify/categories` for each group listed in the plan. Keep the category layout on the destination entry type and accept Craft's prompt to convert the ordinary Categories fields. Use a Structure section to keep the category hierarchy. Record the new section handle for each group. [Craft upgrade guide](https://craftcms.com/docs/6.x/upgrade.html)

   ```sh
   php craft entrify/categories topics
   php craft entrify/categories articles
   ```

3. Apply the plan with a `categoryGroup=newSection` mapping for each group. Use the actual destination section handles, including when the group and section handles match.

   ```sh
   php prepare-craft-6.php apply reverse-categories.json topics=topicEntries articles=articleEntries
   ```

The helper checks that the original category IDs are entries in the mapped sections, that the relevant field instances are on those entry types, and that the original forward field has become an Entries field. It retains the reverse field's UID, handle, target field, and settings while replacing the category source with entry sections. Craft generates the Project Config changes. The helper leaves relation rows to Craft's category conversion.

Review the generated Project Config before upgrading to Craft 6 and installing Reverse Relations 4.0. Capture a fresh plan in each environment while its categories still exist, as element IDs are environment-specific. If the helper reports category selection rules or an unsupported hierarchy, adjust the field configuration before running the conversion.
