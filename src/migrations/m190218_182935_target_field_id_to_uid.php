<?php

namespace robuust\reverserelations\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\Json;

/**
 * m190218_182935_target_field_id_to_uid migration.
 */
class m190218_182935_target_field_id_to_uid extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        if (Craft::$app->projectConfig->get('plugins.reverserelations.schemaVersion', true) === null) {
            $field = (new Query())
                ->select(['id', 'settings', 'type'])
                ->from([Table::FIELDS])
                ->where(['handle' => 'reverserelations'])
                ->all();

            if ($field['settings']) {
                $settings = Json::decodeIfJson($field['settings']) ?: [];
            } else {
                $settings = [];
            }

            if (!empty($settings['targetSiteId'])) {
                $siteIds[] = $settings['targetSiteId'];
            }

            if (!empty($settings['sources']) && is_array($settings['sources'])) {
                foreach ($settings['sources'] as $source) {
                    if (strpos($source, ':') !== false) {
                        list(, $sectionIds[]) = explode(':', $source);
                    }
                }
            }

            if (!empty($settings['targetFieldId'])) {
                $fieldIds[] = $settings['targetFieldId'];
            }

            $sites = (new Query())
                ->select(['id', 'uid'])
                ->from([Table::SITES])
                ->where(['id' => $siteIds])
                ->pairs();

            $sections = (new Query())
                ->select(['id', 'uid'])
                ->from([Table::SECTIONS])
                ->where(['id' => $sectionIds])
                ->pairs();

            $fields = (new Query())
                ->select(['id', 'uid'])
                ->from([Table::FIELDS])
                ->where(['id' => $fieldIds])
                ->pairs();

            if ($field['settings']) {
                $settings = Json::decodeIfJson($field['settings']) ?: [];
            } else {
                $settings = [];
            }

            if (array_key_exists('targetSiteId', $settings)) {
                $settings['targetSiteId'] = $sites[$settings['targetSiteId']] ?? null;
            }

            if (!empty($settings['sources']) && is_array($settings['sources'])) {
                $newSources = [];

                foreach ($settings['sources'] as $source) {
                    $source = explode(':', $source);
                    if (count($source) > 1) {
                        $newSources[] = $source[0].':'.($sections[$source[1]] ?? $source[1]);
                    } else {
                        $newSources[] = $source[0];
                    }
                }

                $settings['sources'] = $newSources;
            }

            if (array_key_exists('targetFieldId', $settings)) {
                $settings['targetFieldId'] = $sites[$settings['targetFieldId']] ?? null;
            }

            $settings = Json::encode($settings);

            $this->update(Table::FIELDS, ['settings' => $settings], ['id' => $field['id']], [], false);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m190218_182935_target_field_id_to_uid cannot be reverted.\n";

        return false;
    }
}
