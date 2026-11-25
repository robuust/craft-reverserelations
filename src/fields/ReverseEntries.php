<?php

namespace robuust\reverserelations\fields;

use CraftCms\Cms\Database\Table;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\ElementCollection;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Element\Queries\ElementQuery;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Field\Entries;
use CraftCms\Cms\Field\Field;
use CraftCms\Cms\Field\FieldContext;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\Cms\Ui\Contracts\Control;
use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Nodes\Field as UiField;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Override;

use function CraftCms\Cms\t;

/** Displays incoming entry relations and edits them through the forward field. */
class ReverseEntries extends Entries
{
    /** The UID of the forward Entries field. */
    public ?string $targetFieldId = null;

    /** Whether editors can change the related entries. */
    public bool $readOnly = false;

    /** {@inheritdoc} */
    #[Override]
    public bool $allowLimit = false;

    /** {@inheritdoc} */
    #[Override]
    protected bool $sortable = false;

    /** {@inheritdoc} */
    #[Override]
    public static function displayName(): string
    {
        return t('Reverse Entry Relations', category: 'reverserelations');
    }

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
            ->filter(fn ($field) => $field instanceof Entries && !$field instanceof self)
            ->map(fn (Entries $field) => [
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
        $control = parent::uiControl(new FieldContext(
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

        $query = Entry::find()->siteId($this->targetSiteId($element));
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
            'elementType' => Entry::class,
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
     * Include disabled entries while keeping the reverse query's site and draft scopes.
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
     * @return Entries|null
     */
    private function targetField(): ?Entries
    {
        $field = $this->targetFieldId ? Fields::getFieldByUid($this->targetFieldId) : null;

        return $field instanceof Entries && !$field instanceof self ? $field : null;
    }

    /**
     * Get configured source section IDs, or null when all sources are allowed.
     *
     * @return list<int>|null
     */
    private function sourceSectionIds(): ?array
    {
        if (!$this->sources || $this->sources === self::ALL_SOURCES) {
            return null;
        }

        $sectionUids = array_map(fn (string $source) => Str::after($source, ':'), (array) $this->sources);

        return Sections::getAllSections()->whereIn('uid', $sectionUids)->pluck('id')->all();
    }

    /**
     * Query incoming relation rows in the current source site.
     *
     * @param Entries $targetField
     * @param int     $siteId
     *
     * @return Builder
     */
    private function incomingRelations(Entries $targetField, int $siteId): Builder
    {
        $query = DB::table(Table::RELATIONS, 'incoming')
            ->where('incoming.fieldId', $targetField->id)
            ->where(fn (Builder $query) => $query
                ->whereNull('incoming.sourceSiteId')
                ->orWhere('incoming.sourceSiteId', $siteId));

        $sectionIds = $this->sourceSectionIds();
        if ($sectionIds !== null) {
            $query->join(Table::ENTRIES.' as sourceEntries', 'sourceEntries.id', '=', 'incoming.sourceId')
                ->whereIn('sourceEntries.sectionId', $sectionIds);
        }

        return $query;
    }

    /**
     * Apply changes to each source entry's forward field in Craft's save transaction.
     *
     * @param ElementInterface $element
     * @param Entries          $targetField
     */
    private function updateForwardRelations(ElementInterface $element, Entries $targetField): void
    {
        $storedIds = $this->normalizeValue(null, $element)->status(null)->eagerly(false)->ignorePlaceholders()->ids();
        $selectedIds = parent::getRelationTargetIds($element);
        $changedIds = [...array_diff($selectedIds, $storedIds), ...array_diff($storedIds, $selectedIds)];

        if (!$changedIds) {
            return;
        }

        $sources = Entry::find()
            ->id($changedIds)
            ->siteId($this->targetSiteId($element))
            ->status(null)
            ->ignorePlaceholders()
            ->indexBy('id')
            ->all();
        $sectionIds = $this->sourceSectionIds();
        $ownerId = $element->getCanonicalId();

        foreach ($changedIds as $sourceId) {
            $source = $sources[$sourceId] ?? null;
            $field = $source?->getFieldLayout()?->getFieldByHandle($targetField->handle);
            if (
                !$field instanceof Entries
                || $field->id !== $targetField->id
                || ($sectionIds !== null && !in_array($source->sectionId, $sectionIds, true))
            ) {
                throw ValidationException::withMessages(['fields.'.$this->handle => t('A related entry does not have the configured target field or is outside the selected sources.', category: 'reverserelations')]);
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
