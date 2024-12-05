<?php

namespace robuust\reverserelations\fields;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\FieldInterface;
use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;
use craft\fields\Matrix;

/**
 * Reverse Relations Trait.
 *
 * @author    Bob Olde Hampsink <bob@robuust.digital>
 * @copyright Copyright (c) 2020, Robuust
 * @license   MIT
 *
 * @see       https://robuust.digital
 */
trait ReverseRelationsTrait
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
     * @var array
     */
    protected $oldSources = [];

    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();

        $this->allowLimit = false;
        $this->sortable = false;
    }

    /**
     * {@inheritdoc}
     */
    public static function dbType(): array|string|null
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getSettingsHtml(): ?string
    {
        // Get parent settings
        $settings = parent::getSettingsHtml();

        // Add "field" select template
        $fieldSelectTemplate = Craft::$app->view->renderTemplate(
            'reverserelations/_settings',
            [
                'fields' => $this->getFields(),
                'settings' => $this->getSettings(),
            ]
        );

        // Return both
        return $settings.$fieldSelectTemplate;
    }

    /**
     * Save relations on the other side.
     *
     * {@inheritdoc}
     */
    public function afterElementSave(ElementInterface $element, bool $isNew): void
    {
        /** @var Element $element */
        /** @var Field $field */
        $field = Craft::$app->fields->getFieldByUid($this->targetFieldId);

        // Skip if nothing changed, or the element is just propagating and we're not localizing relations,
        // or if the field can't save reverse relations
        if (
            !$element->isFieldDirty($this->handle)
            || ($element->propagating && !$this->localizeRelations)
            || !$this->canSaveReverseRelation($field)
        ) {
            Field::afterElementSave($element, $isNew);

            return;
        }

        // Get sources
        $sources = (clone $element->getFieldValue($this->handle))->anyStatus()->all();

        // Find out which ones to add
        $add = array_diff($sources, $this->oldSources);

        // Find out which ones to delete
        $delete = array_diff($this->oldSources, $sources);

        // Loop through all items that need to be added
        /** @var ElementInterface $add */
        foreach ($add as $source) {
            $target = (clone $source->getFieldValue($field->handle))->anyStatus();
            $targetIds = array_merge($target->ids(), [$element->getCanonicalId()]);

            // Set this element on that element
            $source->setFieldValue($field->handle, $targetIds);
            Craft::$app->getElements()->saveElement($source);
        }

        // Loop through deleted sources
        foreach ($delete as $source) {
            $target = (clone $source->getFieldValue($field->handle))->anyStatus();
            $targetIds = array_diff($target->ids(), [$element->getCanonicalId()]);

            // Set this element on that element
            $source->setFieldValue($field->handle, $targetIds);
            Craft::$app->getElements()->saveElement($source);
        }

        Field::afterElementSave($element, $isNew);
    }

    /**
     * {@inheritdoc}
     */
    public function getInputHtml($value, ?ElementInterface $element = null): string
    {
        if ($value instanceof ElementQueryInterface) {
            $value = $value->status(null);
        }

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
     * Determine if a field can save a reverse relation.
     *
     * @param FieldInterface $field
     *
     * @return bool
     */
    protected function canSaveReverseRelation(FieldInterface $field): bool
    {
        if ($field instanceof Matrix || $field instanceof \verbb\supertable\fields\SuperTableField) {
            return false;
        }

        return true;
    }
}
