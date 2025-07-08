<?php

namespace robuust\reverserelations\fields;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\behaviors\EventBehavior;
use craft\db\Query;
use craft\db\Table as DbTable;
use craft\elements\db\ElementQuery;
use craft\events\CancelableEvent;
use craft\fields\BaseRelationField;
use craft\fields\Entries;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\web\Request;

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
    use ReverseRelationsTrait;

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
    public function normalizeValue($value, ?ElementInterface $element = null): mixed
    {
        // Use the canonical element
        if ($element) {
            $element = $element->getCanonical();
        }

        $inputSourceIds = $this->inputSourceIds();

        /** @var Element|null $element */
        $query = parent::normalizeValue($value, $element);

        // Overwrite inner join to switch sourceId and targetId
        if (!is_array($value) && $value !== '' && $element && $element->id) {
            $targetField = Craft::$app->fields->getFieldByUid($this->targetFieldId);
            $relationsAlias = sprintf('relations_%s', StringHelper::randomString(10));

            $query->attachBehavior(BaseRelationField::class, new EventBehavior([
                ElementQuery::EVENT_AFTER_PREPARE => function (
                    CancelableEvent $event,
                    ElementQuery $query,
                ) use ($element, $relationsAlias, $targetField) {
                    // Make these changes directly on the prepared queries, so `sortOrder` doesn't ever make it into
                    foreach ([$query->query, $query->subQuery] as $q) {
                        $q->innerJoin(
                            [$relationsAlias => DbTable::RELATIONS],
                            [
                                'and',
                                "[[{$relationsAlias}.sourceId]] = [[elements.id]]",
                                [
                                    "{$relationsAlias}.targetId" => $element->id,
                                    "{$relationsAlias}.fieldId" => $targetField?->id,
                                ],
                                [
                                    'or',
                                    ["{$relationsAlias}.sourceSiteId" => null],
                                    ["{$relationsAlias}.sourceSiteId" => $element->siteId],
                                ],
                            ]
                        );
                    }
                },
            ]));

            if ($inputSourceIds != '*') {
                $query->where(['entries.sectionId' => $inputSourceIds]);
            }
        }

        // Check if this is a GraphQL context by looking at the request
        /** @var Request $request */
        $request = Craft::$app->getRequest();
        if (!$request->getIsConsoleRequest() && $request->getIsGraphql()) {
            // Apply additional filtering for GraphQL to ensure proper section filtering
            if ($inputSourceIds !== '*') {
                $query->andWhere(['entries.sectionId' => $inputSourceIds]);
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
        if (!$isNew || $element->getIsDerivative()) {
            // Get cached element
            $entry = Craft::$app->getEntries()->getEntryById($element->getCanonicalId(), $element->siteId);

            // Get old sources
            if ($entry && $entry->{$this->handle}) {
                $this->oldSources = $entry->{$this->handle}->anyStatus()->all();
            }
        }

        return parent::beforeElementSave($element, $isNew);
    }

    /**
     * {@inheritdoc}
     */
    public function getEagerLoadingMap(array $sourceElements): array|false|null
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
            ->innerJoin('{{%entries}} entries', '[[relations.sourceId]] = [[entries.id]]')
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
            ->andWhere(['entries.sectionId' => $this->inputSourceIds()])
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
     *
     * @return array
     */
    protected function getFields(): array
    {
        $fields = [];
        /** @var Field $field */
        foreach (Craft::$app->fields->getAllFields(false) as $field) {
            if ($field instanceof Entries && !($field instanceof $this)) {
                $fields[$field->uid] = $field->name.' ('.$field->handle.')';
            }
        }

        return $fields;
    }

    /**
     * Get allowed input source ids.
     *
     * @return array|string
     */
    private function inputSourceIds(): array|string
    {
        // Read sources directly from settings instead of using getInputSources()
        $inputSources = $this->settings['sources'] ?? [];

        // Fallback: If sources are empty, treat as '*'
        if ($inputSources == '*' || empty($inputSources)) {
            return '*';
        }

        $sources = [];
        foreach ($inputSources as $source) {
            list($type, $uid) = explode(':', $source);
            $sources[] = $uid;
        }

        return Db::idsByUids(DbTable::SECTIONS, $sources);
    }
}
