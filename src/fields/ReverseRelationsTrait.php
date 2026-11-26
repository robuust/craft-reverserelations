<?php

namespace robuust\reverserelations\fields;

use CraftCms\Cms\Database\Table;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\ElementCollection;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Element\Queries\ElementQuery;
use CraftCms\Cms\Field\BaseRelationField;
use CraftCms\Cms\Field\Field;
use CraftCms\Cms\Field\FieldContext;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Ui\Contracts\Control;
use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Nodes\Field as UiField;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Override;

use function CraftCms\Cms\t;

/** Displays incoming relations and edits them through the forward field. */
trait ReverseRelationsTrait
{
    /** The UID of the forward relation field. */
    public ?string $targetFieldId = null;

    /** Whether editors can change the related elements. */
    public bool $readOnly = false;

    /** {@inheritdoc} */
    #[Override]
    public bool $allowLimit = false;

    /** {@inheritdoc} */
    #[Override]
    protected bool $sortable = false;

    /** {@inheritdoc} */
    #[Override]
    public static function dbType(): array|string|null
    {
        return null;
    }

    /** {@inheritdoc} */
    #[Override]
    public function settingsAttributes(): array
    {
        return [...parent::settingsAttributes(), 'targetFieldId', 'readOnly'];
    }

    /** {@inheritdoc} */
    #[Override]
    public function settingsUi(UiContext $context = new UiContext()): Ui
    {
        $options = Fields::getAllFields(false)
            ->filter(fn ($field) => $field instanceof parent && !$field instanceof self)
            ->map(fn (BaseRelationField $field) => [
                'label' => $field->name.' ('.$field->handle.')',
                'value' => $field->uid,
            ])
            ->values()
            ->all();

        return parent::settingsUi($context)->add(
            UiField::make(
                t('Target Field', category: 'reverserelations'),
                Choice::make('targetFieldId')->options($options)->value($this->targetFieldId),
            )->instructions(t('Set the target field of this relation.', category: 'reverserelations')),
            UiField::make(
                t('Read-only', category: 'reverserelations'),
                Lightswitch::make('readOnly')->value($this->readOnly),
            )->instructions(t('Disallow adding or removing of relations.', category: 'reverserelations')),
        );
    }

    /** {@inheritdoc} */
    #[Override]
    public function uiControl(FieldContext $context): Control
    {
        // The legacy HTML bridge cannot track changes from Craft's element selector.
        $control = BaseRelationField::uiControl(new FieldContext(
            path: $context->path,
            value: $this->controlValue($context->value),
            element: $context->element,
            ui: $context->ui,
            mode: $context->mode,
            inline: $context->inline,
        ));

        if ($this->readOnly || $this->targetField() === null) {
            $control->mode(ControlMode::ReadOnly);
        }

        return $control;
    }

    /** {@inheritdoc} */
    #[Override]
    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        return parent::inputHtml($this->controlValue($value), $element, $inline);
    }

    /** {@inheritdoc} */
    #[Override]
    protected function elementSelectHtml(array $variables): string
    {
        if ($this->readOnly || $this->targetField() === null) {
            $variables['allowAdd'] = false;
            $variables['allowRemove'] = false;
        }

        return parent::elementSelectHtml($variables);
    }

    /** {@inheritdoc} */
    #[Override]
    public function normalizeValue(mixed $value, ?ElementInterface $element = null): mixed
    {
        $ownerId = $element?->getCanonicalId();
        if ($value !== null || !$ownerId) {
            return parent::normalizeValue($value, $element);
        }

        $elementType = static::elementType();
        $query = $elementType::find()->siteId($this->targetSiteId($element));
        $targetField = $this->targetField();
        if ($targetField === null) {
            return $query->id(false);
        }

        $relations = $this->incomingRelations($targetField, $element->siteId)
            ->where('incoming.targetId', $ownerId)
            ->select('incoming.sourceId');

        // Keep owner-specific criteria out of the shared eager-loading query.
        return $query->beforeQuery(function (ElementQuery $query) use ($relations) {
            $query->getQuery()->whereIn('elements.id', $relations);
        })->prepForEagerLoading($this->handle, $element);
    }

    /** {@inheritdoc} */
    #[Override]
    public function getEagerLoadingMap(array $sourceElements): array|false|null
    {
        $first = $sourceElements[0] ?? null;
        $targetField = $this->targetField();
        $map = [];

        if ($first && $targetField) {
            $owners = collect($sourceElements)->groupBy(fn (ElementInterface $element) => $element->getCanonicalId());
            $relations = $this->incomingRelations($targetField, $first->siteId)
                ->whereIn('incoming.targetId', $owners->keys()->all())
                ->orderBy('incoming.sortOrder')
                ->get(['incoming.targetId', 'incoming.sourceId']);

            foreach ($relations as $relation) {
                foreach ($owners[$relation->targetId] as $owner) {
                    $map[] = ['source' => $owner->id, 'target' => (int) $relation->sourceId];
                }
            }
        }

        return [
            'elementType' => static::elementType(),
            'map' => $map,
            'criteria' => ['siteId' => $this->targetSiteId($first)],
        ];
    }

    /** {@inheritdoc} */
    #[Override]
    public function getRelationTargetIds(ElementInterface $element): array
    {
        // Craft persists relations centrally. Only the forward field owns relation rows.
        return [];
    }

    /** {@inheritdoc} */
    #[Override]
    public function afterElementSave(ElementInterface $element, bool $isNew): void
    {
        $targetField = $this->targetField();
        if (
            $targetField !== null
            && !$this->readOnly
            && $element->isFieldDirty($this->handle)
            && (!$element->propagating || $this->localizeRelations)
        ) {
            $this->updateForwardRelations($element, $targetField);
        }

        Field::afterElementSave($element, $isNew);
    }

    /**
     * Include disabled elements while keeping the reverse query's site and draft scopes.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    private function controlValue(mixed $value): mixed
    {
        return $value instanceof ElementQueryInterface
            ? ElementCollection::make((clone $value)->status(null)->all())
            : $value;
    }

    /**
     * Resolve the configured forward field.
     *
     * @return BaseRelationField|null
     */
    private function targetField(): ?BaseRelationField
    {
        $field = $this->targetFieldId ? Fields::getFieldByUid($this->targetFieldId) : null;

        return $field instanceof parent && !$field instanceof self ? $field : null;
    }

    /**
     * Get the source table, scope column and allowed IDs, or null when all sources are allowed.
     *
     * @return array{table: string, column: string, ids: list<int>}|null
     */
    abstract protected function sourceScope(): ?array;

    /**
     * Get the validation message for an invalid source element.
     *
     * @return string
     */
    abstract protected function invalidSourceMessage(): string;

    /**
     * Query incoming relation rows in the current source site.
     *
     * @param BaseRelationField $targetField
     * @param int               $siteId
     *
     * @return Builder
     */
    private function incomingRelations(BaseRelationField $targetField, int $siteId): Builder
    {
        $query = DB::table(Table::RELATIONS, 'incoming')
            ->where('incoming.fieldId', $targetField->id)
            ->where(fn (Builder $query) => $query
                ->whereNull('incoming.sourceSiteId')
                ->orWhere('incoming.sourceSiteId', $siteId));

        $scope = $this->sourceScope();
        if ($scope !== null) {
            $query->join($scope['table'].' as relationSources', 'relationSources.id', '=', 'incoming.sourceId')
                ->whereIn('relationSources.'.$scope['column'], $scope['ids']);
        }

        return $query;
    }

    /**
     * Apply changes to each source element's forward field in Craft's save transaction.
     *
     * @param ElementInterface  $element
     * @param BaseRelationField $targetField
     */
    private function updateForwardRelations(ElementInterface $element, BaseRelationField $targetField): void
    {
        $storedIds = $this->normalizeValue(null, $element)->status(null)->eagerly(false)->ignorePlaceholders()->ids();
        $selectedIds = parent::getRelationTargetIds($element);
        $changedIds = [...array_diff($selectedIds, $storedIds), ...array_diff($storedIds, $selectedIds)];

        if (!$changedIds) {
            return;
        }

        $elementType = static::elementType();
        $sources = $elementType::find()
            ->id($changedIds)
            ->siteId($this->targetSiteId($element))
            ->status(null)
            ->ignorePlaceholders()
            ->indexBy('id')
            ->all();
        $scope = $this->sourceScope();
        $ownerId = $element->getCanonicalId();

        foreach ($changedIds as $sourceId) {
            $source = $sources[$sourceId] ?? null;
            $field = $source?->getFieldLayout()?->getFieldByHandle($targetField->handle);
            if (
                !$field instanceof parent
                || $field->id !== $targetField->id
                || ($scope !== null && !in_array($source->{$scope['column']}, $scope['ids'], true))
            ) {
                throw ValidationException::withMessages(['fields.'.$this->handle => $this->invalidSourceMessage()]);
            }

            $ids = $field->getRelationTargetIds($source);
            $updatedIds = in_array($sourceId, $selectedIds)
                ? array_unique([...$ids, $ownerId])
                : array_diff($ids, [$ownerId]);

            $source->setFieldValue($field->handle, array_values($updatedIds));
            if (!Elements::saveElement($source)) {
                throw ValidationException::withMessages(['fields.'.$this->handle => t('Could not save reverse relations for “{title}”.', ['title' => $source->title], category: 'reverserelations')]);
            }
        }
    }
}
