<?php

namespace robuust\reverserelations\fields;

use Craft;
use craft\fields\Entries;

/**
 * Reverse Relations Entries Field.
 *
 * @author    Bob Olde Hampsink <bob@robuust.digital>
 * @copyright Copyright (c) 2018, Robuust
 * @license   MIT
 *
 * @see       https://robuust.digital
 */
class ReverseEntries extends Entries
{
    // Static
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public static function displayName(): string
    {
        return Craft::t('app', 'Reverse Entries');
    }

    // Properties
    // =========================================================================

    /**
     * @var bool Whether to allow the Limit setting
     */
    public $allowLimit = false;

    /**
     * @var bool Whether the elements have a custom sort order
     */
    protected $sortable = false;

    // Public Methods
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function getSettingsHtml()
    {
        // Get parent settings
        $settings = parent::getSettingsHtml();

        // _components/fieldtypes/elementfieldsettings
        return Craft::$app->getView()->renderTemplate('reverserelations/_settings', [
            'field' => $this,
        ]);
    }
}
