<?php

namespace robuust\reverserelations\converters\fields;

use Craft;
use craft\base\Model;
use NerdsAndCompany\Schematic\Converters\Base\Field;

/**
 * Reverse Relations Entries Field Converter for Schematic.
 *
 * {@inheritdoc}
 */
class ReverseEntries extends Field
{
    /**
     * {@inheritdoc}
     */
    public function getRecordDefinition(Model $record): array
    {
        $definition = parent::getRecordDefinition($record);

        if ($record->targetFieldId) {
            $definition['attributes']['targetField'] = Craft::$app->getFields()->getFieldById($record->targetFieldId)->handle;
        }

        unset($definition['attributes']['targetFieldId']);

        return $definition;
    }

    /**
     * {@inheritdoc}
     */
    public function saveRecord(Model $record, array $definition): bool
    {
        if (array_key_exists('targetField', $definition['attributes'])) {
            $record->targetFieldId = Craft::$app->getFields()->getFieldByHandle($definition['attributes']['targetField']);
        }

        return parent::saveRecord($record, $definition);
    }
}
