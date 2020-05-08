<?php

namespace robuust\reverserelations\fields;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\db\Query;
use craft\db\Table;
use craft\fields\Categories;
use craft\helpers\Db;

/**
 * Reverse Relations Categories Field.
 *
 * @author    Bob Olde Hampsink <bob@robuust.digital>
 * @copyright Copyright (c) 2020, Robuust
 * @license   MIT
 *
 * @see       https://robuust.digital
 */
class ReverseCategories extends Categories
{
    use ReverseRelationsTrait;

    /**
     * {@inheritdoc}
     */
    public static function displayName(): string
    {
        return Craft::t('reverserelations', 'Reverse Category Relations');
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
            $query->innerJoin('{{%relations}} relations', [
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
            ]);

            $inputSourceIds = $this->inputSourceIds();
            if ($inputSourceIds != '*') {
                $query->where(['categories.groupId' => $inputSourceIds]);
            }
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
            $category = Craft::$app->getCategories()->getCategoryById($element->id, $element->siteId);

            // Get old sources
            if ($category && $category->{$this->handle}) {
                $this->oldSources = $category->{$this->handle}->all();
            }
        }

        return parent::beforeElementSave($element, $isNew);
    }

    /**
     * {@inheritdoc}
     */
    public function getEagerLoadingMap(array $sourceElements)
    {
        $targetField = Craft::$app->fields->getFieldByUid($this->targetFieldId);

        /** @var Element|null $firstElement */
        $firstElement = $sourceElements[0] ?? null;

        // Get the source element IDs
        $sourceElementIds = [];

        foreach ($sourceElements as $sourceElement) {
            $sourceElementIds[] = $sourceElement->id;
        }

        // Return any relation data on these elements, defined with this field
        $map = (new Query())
            ->select(['sourceId as target', 'targetId as source'])
            ->from('{{%relations}} relations')
            ->innerJoin('{{%categories}} categories', '[[relations.sourceId]] = [[categories.id]]')
            ->where([
                'and',
                [
                    'fieldId' => $targetField->id,
                    'targetId' => $sourceElementIds,
                ],
                [
                    'or',
                    ['sourceSiteId' => $firstElement ? $firstElement->siteId : null],
                    ['sourceSiteId' => null],
                ],
            ])
            ->where(['categories.groupId' => $this->inputSourceIds()])
            ->orderBy(['sortOrder' => SORT_ASC])
            ->all();

        // Figure out which target site to use
        $targetSite = $this->targetSiteId($firstElement);

        return [
            'elementType' => static::elementType(),
            'map' => $map,
            'criteria' => [
                'siteId' => $targetSite,
            ],
        ];
    }

    /**
     * Get available fields.
     */
    protected function getFields()
    {
        $fields = [];
        /** @var Field $field */
        foreach (Craft::$app->fields->getAllFields(false) as $field) {
            if ($field instanceof Categories && !($field instanceof $this)) {
                $fields[$field->uid] = $field->name;
            }
        }
    }

    /**
     * Get allowed input source ids.
     *
     * @return array
     */
    protected function inputSourceIds(): array
    {
        $inputSources = $this->inputSources();

        if ($inputSources == '*') {
            return $inputSources;
        }

        $sources = [];
        foreach ($inputSources as $source) {
            list($type, $uid) = explode(':', $source);
            $sources[] = $uid;
        }

        return Db::idsByUids(Table::CATEGORYGROUPS, $sources);
    }
}
