<?php

namespace robuust\reverserelations;

use CraftCms\Cms\Plugin\Plugin as CraftPlugin;
use Override;
use robuust\reverserelations\fields\ReverseEntries;

/** Registers the native Reverse Entry Relations field. */
class Plugin extends CraftPlugin
{
    /** {@inheritdoc} */
    #[Override]
    protected array $fieldTypes = [ReverseEntries::class];
}
