<?php

namespace robuust\reverserelations\fields;

use CraftCms\Cms\Database\Table;
use CraftCms\Cms\Field\Entries;
use CraftCms\Cms\Support\Facades\Sections;
use Illuminate\Support\Str;
use Override;

use function CraftCms\Cms\t;

/** Displays incoming entry relations. */
class ReverseEntries extends Entries
{
    use ReverseRelationsTrait;

    /** {@inheritdoc} */
    #[Override]
    public static function displayName(): string
    {
        return t('Reverse Entry Relations', category: 'reverserelations');
    }

    /** {@inheritdoc} */
    protected function sourceScope(): ?array
    {
        if (!$this->sources || $this->sources === self::ALL_SOURCES) {
            return null;
        }

        $sectionUids = array_map(fn (string $source) => Str::after($source, ':'), (array) $this->sources);

        return [
            'table' => Table::ENTRIES,
            'column' => 'sectionId',
            'ids' => Sections::getAllSections()->whereIn('uid', $sectionUids)->pluck('id')->all(),
        ];
    }

    /** {@inheritdoc} */
    protected function invalidSourceMessage(): string
    {
        return t('A related entry does not have the configured target field or is outside the selected sources.', category: 'reverserelations');
    }
}
