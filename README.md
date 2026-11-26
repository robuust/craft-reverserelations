# Reverse Relations for Craft

Reverse Relations shows which elements link to the current element through a configured relation field. Editors can add or remove those links in the control panel. The fields are also available in Twig and GraphQL.

## Requirements

Version 4.0 requires Craft CMS 6.0.0-beta.2 or later and PHP 8.5 or later. **Reverse Entry Relations** runs with or without the Yii2 adapter. **Reverse Category Relations** requires the adapter and active legacy category support.

## Installation

From your Craft project, run:

```sh
composer require robuust/craft-reverserelations:^4.0
```

Install Reverse Relations under **Settings → Plugins**.

### Set up Reverse Entry Relations

Add a standard **Entries** field to the layout of the entries that will hold the forward links. Add a **Reverse Entry Relations** field to the layout where the incoming links should appear, choose the source sections, and select the standard **Entries** field as the **Target Field**. Turn on **Read-only** to display the links without allowing editors to change them.

### Set up Reverse Category Relations on a fresh install

Install the Yii2 adapter in your Craft 6 project and enable legacy categories. Craft's command creates the tables for categories and category groups. [Yii2 adapter command](https://github.com/craftcms/yii2-adapter/blob/6.x/src/Console/AddCategoriesSupportCommand.php)

```sh
composer require craftcms/yii2-adapter
php artisan craft:add-categories-support
```

Create your category groups in the control panel. Add a standard **Categories** field to the layout that will hold the forward links. Add a **Reverse Category Relations** field to the layout where the incoming links should appear, select the source category group, and choose the standard **Categories** field as the **Target Field**. Turn on **Read-only** to display the links without allowing editors to change them.

## Upgrading from 3.x

Back up the database and Project Config before upgrading. Choose the path that fits your existing fields.

### Upgrade existing Reverse Entry Relations

Follow the Craft 6 upgrade guide. Set the Reverse Relations Composer requirement to `^4.0` as part of the upgrade, then run `php artisan craft:up`. Existing **Reverse Entry Relations** fields keep their handles, target field, sources, site settings, and read-only setting. [Craft upgrade guide](https://craftcms.com/docs/6.x/upgrade.html)

If your project and its other plugins are ready to run without the adapter, you can remove `craftcms/yii2-adapter` from the project. [Craft adapter removal guide](https://craftcms.com/docs/6.x/compatibility#ejecting-the-adapter)

### Keep existing Reverse Category Relations

1. Keep the original category groups, field layouts, and both the **Categories** and **Reverse Category Relations** fields in your database and Project Config.
2. Prepare the Craft 6 upgrade with `craftcms/yii2-adapter` in your Composer requirements. Craft's upgrade tool adds the adapter. For a manual upgrade, add the adapter version that matches your Craft CMS version before running the migrations. [Craft adapter guide](https://craftcms.com/docs/6.x/compatibility)
3. Set the Reverse Relations Composer requirement to `^4.0` as part of the upgrade, then run `php artisan craft:up`. The adapter uses the existing category tables, and the existing **Reverse Category Relations** fields continue to use their configured category groups and target fields.
4. Open a category in the control panel and confirm its incoming links. Keep the adapter installed and legacy category support active for as long as those fields are in use.

### Convert Reverse Category Relations on Craft 5

Use this route if you want your categories to become entries. Complete the conversion on Craft 5 with Reverse Relations 3.0.6. Copy [the standalone helper](bin/prepare-craft-6.php) to the Craft 5 project root, outside the web root.

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

Review the generated Project Config, then follow [the steps for upgrading existing Reverse Entry Relations](#upgrade-existing-reverse-entry-relations). Update any Twig templates and GraphQL queries that use category-specific properties or types. Capture a fresh plan in each environment while its categories still exist, as element IDs are environment-specific. If the helper reports category selection rules or an unsupported hierarchy, adjust the field configuration before running the conversion.
