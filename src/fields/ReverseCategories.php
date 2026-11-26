<?php

namespace robuust\reverserelations\fields;

use Craft;
use craft\fields\Categories;
use CraftCms\Yii2Adapter\Database\DeprecatedTable;
use CraftCms\Yii2Adapter\DeprecatedConcepts;
use Illuminate\Support\Str;
use Override;

use function CraftCms\Cms\t;

// Project Config can reference this class even when the legacy fields are unavailable.
if (!class_exists(DeprecatedConcepts::class) || !DeprecatedConcepts::supportsCategories()) {
    return;
}

/** Displays incoming category relations when Craft supports legacy categories. */
class ReverseCategories extends Categories
{
    use ReverseRelationsTrait;

    /** {@inheritdoc} */
    #[Override]
    public function init(): void
    {
        parent::init();

        $this->allowLimit = false;
    }

    /** {@inheritdoc} */
    #[Override]
    public static function displayName(): string
    {
        return t('Reverse Category Relations', category: 'reverserelations');
    }

    /** {@inheritdoc} */
    #[Override]
    public function getInputSelectionCriteria(): array
    {
        $criteria = parent::getInputSelectionCriteria();
        $scope = $this->sourceScope();

        return $scope === null ? $criteria : [...$criteria, 'groupId' => $scope['ids']];
    }

    /** {@inheritdoc} */
    protected function sourceScope(): ?array
    {
        if (!$this->source || $this->source === self::ALL_SOURCES) {
            return null;
        }

        $group = Craft::$app->getCategories()->getGroupByUid(Str::after($this->source, ':'));

        return [
            'table' => DeprecatedTable::CATEGORIES,
            'column' => 'groupId',
            'ids' => $group ? [$group->id] : [],
        ];
    }

    /** {@inheritdoc} */
    protected function invalidSourceMessage(): string
    {
        return t('A related category does not have the configured target field or is outside the selected sources.', category: 'reverserelations');
    }
}
