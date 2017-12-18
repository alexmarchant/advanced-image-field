<?php
/**
 * Advanced Image Field plugin for Craft 3.0
 * @copyright 2017 Alex Marchant
 */

namespace alexmarchant\advancedimage;

use alexmarchant\advancedimage\fields\AdvancedImageField;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\services\Fields;
use craft\events\PluginEvent;
use craft\events\RegisterComponentTypesEvent;

use yii\base\Event;

class AdvancedImage extends Plugin
{
    public static $plugin;

    public function init()
    {
        parent::init();
        self::$plugin = $this;

        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = AdvancedImageField::class;
            }
        );
    }
}
