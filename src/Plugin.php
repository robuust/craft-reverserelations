<?php

namespace robuust\reverserelations;

use CraftCms\Cms\Plugin\Plugin as CraftPlugin;
use Override;
use robuust\reverserelations\fields\ReverseCategories;
use robuust\reverserelations\fields\ReverseEntries;

/** Registers the supported reverse relation fields. */
class Plugin extends CraftPlugin
{
    /** {@inheritdoc} */
    #[Override]
    protected array $fieldTypes = [ReverseEntries::class];

    /** {@inheritdoc} */
    #[Override]
    public function bootHasFieldTypes(): void
    {
        if (class_exists(ReverseCategories::class)) {
            $this->fieldTypes[] = ReverseCategories::class;
        }

        parent::bootHasFieldTypes();
    }
}
