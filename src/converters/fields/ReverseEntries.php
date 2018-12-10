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
            $targetField = Craft::$app->getFields()->getFieldById($record->targetFieldId);
            if ($targetField) {
                $definition['attributes']['targetField'] = $targetField->handle;
            }
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
            $targetField = Craft::$app->getFields()->getFieldByHandle($definition['attributes']['targetField']);
            if ($targetField) {
                $record->targetFieldId = $targetField->id;
            }
        }

        return parent::saveRecord($record, $definition);
    }
}
