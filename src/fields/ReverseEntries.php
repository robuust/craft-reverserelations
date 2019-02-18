<?php

namespace robuust\reverserelations\fields;

use Craft;
use craft\base\Field;
use craft\base\Element;
use craft\db\Table;
use craft\db\Query;
use craft\elements\Entry;
use craft\helpers\Db;
use craft\fields\Matrix;
use craft\fields\Entries;
use craft\base\FieldInterface;
use craft\base\ElementInterface;
use craft\elements\db\ElementQuery;

/**
 * Reverse Relations Entries Field.
 *
 * @author    Bob Olde Hampsink <bob@robuust.digital>
 * @copyright Copyright (c) 2019, Robuust
 * @license   MIT
 *
 * @see       https://robuust.digital
 */
class ReverseEntries extends Entries
{
    /**
     * @var int Target field setting
     */
    public $targetFieldId;

    /**
     * @var bool Read-only setting
     */
    public $readOnly;

    /**
     * {@inheritdoc}
     */
    public $allowLimit = false;

    /**
     * {@inheritdoc}
     */
    protected $sortable = false;

    /**
     * @var array
     */
    private $oldSources = [];

    /**
     * {@inheritdoc}
     */
    public static function displayName(): string
    {
        return Craft::t('reverserelations', 'Reverse Entry Relations');
    }

    /**
     * {@inheritdoc}
     */
    public function getSettingsHtml()
    {
        // Get parent settings
        $settings = parent::getSettingsHtml();

        // Get available fields
        $fields = [];
        /** @var Field $field */
        foreach (Craft::$app->fields->getAllFields() as $field) {
            $fields[$field->id] = $field->name;
        }

        // Add "field" select template
        $fieldSelectTemplate = Craft::$app->view->renderTemplate(
            'reverserelations/_settings', [
                'fields' => $fields,
                'settings' => $this->getSettings(),
            ]
        );

        // Return both
        return $settings.$fieldSelectTemplate;
    }

    /**
     * {@inheritdoc}
     */
    public function normalizeValue($value, ElementInterface $element = null)
    {
        /** @var Element|null $element */
        $query = parent::normalizeValue($value, $element);

        // Overwrite inner join to switch sourceId and targetId
        if (!is_array($value) && $value !== '' && $element && $element->id) {
            $targetField = Craft::$app->fields->getFieldByUid($this->targetFieldId);

            $query->join = [];
            $query
                ->innerJoin(
                    '{{%relations}} relations',
                    [
                        'and',
                        '[[relations.sourceId]] = [[elements.id]]',
                        [
                            'relations.targetId' => $element->id,
                            'relations.fieldId' => $targetField->id,
                        ],
                        [
                            'or',
                            ['relations.sourceSiteId' => null],
                            ['relations.sourceSiteId' => $element->siteId],
                        ],
                    ]
                )
                ->where(['entries.sectionId' => $this->inputSourceIds()]);
        }

        return $query;
    }

    /**
     * Get original relations so we can diff those
     * with the new value and find out which ones need to be deleted.
     *
     * {@inheritdoc}
     */
    public function beforeElementSave(ElementInterface $element, bool $isNew): bool
    {
        if (!$isNew) {
            // Get cached element
            $entry = Craft::$app->getEntries()->getEntryById($element->id);

            // Get old sources
            $this->oldSources = $entry->{$this->handle}->all();
        }

        return parent::beforeElementSave($element, $isNew);
    }

    /**
     * Save relations on the other side.
     *
     * {@inheritdoc}
     */
    public function afterElementSave(ElementInterface $element, bool $isNew)
    {
        /** @var Element $element */
        /** @var Field $field */
        $field = Craft::$app->fields->getFieldByUid($this->targetFieldId);

        // Determine if a field can save a reverse relation
        if (!$this->canSaveReverseRelation($field)) {
            return;
        }

        // Get sources
        $sources = $element->getFieldValue($this->handle)->all();

        // Find out which ones to delete
        $delete = array_diff($this->oldSources, $sources);

        // Loop through sources
        /** @var ElementInterface $source */
        foreach ($sources as $source) {
            $target = $source->getFieldValue($field->handle);

            // Set this element on that entry
            $this->saveRelations(
                $field,
                $source,
                array_merge($target->ids(), [$element->id])
            );
        }

        // Loop through deleted sources
        foreach ($delete as $source) {
            $this->deleteRelations($field, $source, [$element->id]);
        }

        Field::afterElementSave($element, $isNew);
    }

    /**
     * {@inheritdoc}
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        /** @var Element|null $element */
        if ($element !== null && $element->hasEagerLoadedElements($this->handle)) {
            $value = $element->getEagerLoadedElements($this->handle);
        }

        // Get variables
        /** @var ElementQuery|array $value */
        $variables = $this->inputTemplateVariables($value, $element);

        // Disable adding if we can't save a reverse relation
        $field = Craft::$app->fields->getFieldByUid($this->targetFieldId);
        $variables['readOnly'] = $this->readOnly || !$this->canSaveReverseRelation($field);

        // Return input template (local override if exists)
        $template = 'reverserelations/'.$this->inputTemplate;
        $template = Craft::$app->view->doesTemplateExist($template) ? $template : $this->inputTemplate;

        return Craft::$app->view->renderTemplate($template, $variables);
    }

    /**
     * {@inheritdoc}
     */
    public function settingsAttributes(): array
    {
        $attributes = parent::settingsAttributes();
        $attributes[] = 'targetFieldId';
        $attributes[] = 'readOnly';

        return $attributes;
    }

    /**
     * Get allowed input source ids.
     *
     * @return array
     */
    private function inputSourceIds(): array
    {
        $sources = [];
        foreach ($this->inputSources() as $source) {
            list($type, $uid) = explode(':', $source);
            $sources[] = $uid;
        }

        return Db::idsByUids(Table::SECTIONS, $sources);
    }

    /**
     * Determine if a field can save a reverse relation.
     *
     * @param FieldInterface $field
     *
     * @return bool
     */
    private function canSaveReverseRelation(FieldInterface $field): bool
    {
        if ($field instanceof Matrix) {
            return false;
        }

        return true;
    }

    /**
     * Saves some relations for a field.
     *
     * @param Entries $field
     * @param Entry   $source
     * @param array   $targetIds
     */
    private function saveRelations(Entries $field, Entry $source, array $targetIds)
    {
        if ($field->localizeRelations) {
            $sourceSiteId = $source->siteId;
        } else {
            $sourceSiteId = null;
        }

        foreach ($targetIds as $sortOrder => $targetId) {
            $criteria = [
                'fieldId' => $field->id,
                'sourceId' => $source->id,
                'sourceSiteId' => $sourceSiteId,
                'targetId' => $targetId,
            ];

            if (!(new Query())->select('id')->from('{{%relations}}')->where($criteria)->exists()) {
                Craft::$app->getDb()->createCommand()
                    ->insert('{{%relations}}', array_merge($criteria, ['sortOrder' => 1]))
                    ->execute();
            }
        }
    }

    /**
     * Deletes some relations for a field.
     *
     * @param Entries $field
     * @param Entry   $source
     * @param array   $targetIds
     */
    private function deleteRelations(Entries $field, Entry $source, array $targetIds)
    {
        // Delete the existing relations
        $oldRelationConditions = [
            'and',
            [
                'fieldId' => $field->id,
                'sourceId' => $source->id,
                'targetId' => $targetIds,
            ],
        ];

        if ($field->localizeRelations) {
            $oldRelationConditions[] = [
                'or',
                ['sourceSiteId' => null],
                ['sourceSiteId' => $source->siteId],
            ];
        }

        Craft::$app->getDb()->createCommand()
            ->delete('{{%relations}}', $oldRelationConditions)
            ->execute();
    }
}
