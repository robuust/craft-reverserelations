<?php

namespace robuust\reverserelations;

use craft\events\RegisterComponentTypesEvent;
use craft\services\Fields;
use robuust\reverserelations\fields\ReverseCategories;
use robuust\reverserelations\fields\ReverseEntries;
use yii\base\Event;

/**
 * Reverse Relations Plugin.
 *
 * @author    Bob Olde Hampsink <bob@robuust.digital>
 * @copyright Copyright (c) 2019, Robuust
 * @license   MIT
 *
 * @see       https://robuust.digital
 */
class Plugin extends \craft\base\Plugin
{
    /**
     * Initializes the plugin.
     */
    public function init()
    {
        parent::init();

        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function (RegisterComponentTypesEvent $event) {
            $event->types[] = ReverseCategories::class;
            $event->types[] = ReverseEntries::class;
        });
    }
}
