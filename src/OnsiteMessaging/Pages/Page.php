<?php

namespace Krokedil\Klarna\OnsiteMessaging\Pages;

use Krokedil\Klarna\OnsiteMessaging\Utility;
use Krokedil\Klarna\OnsiteMessaging\Settings;
/**
 * The Page class is the shared foundation for the Cart Page and Product Page widgets.
 * @internal
 */
abstract class Page
{
    /**
     * Whether this page widget is enabled.
     *
     * @var bool
     */
    protected $enabled = \false;
    /**
     * The theme.
     *
     * @var string
     */
    protected $theme = 'default';
    /**
     * The data key.
     *
     * @var string
     */
    protected $key;
    /**
     * The client ID.
     *
     * @var string
     */
    protected $client_id;
    /**
     * The placement ID.
     *
     * @var string
     */
    protected $placement_id;
    /**
     * The purchase amount
     *
     * @var int
     */
    protected $purchase_amount = 0;
    /**
     * The hook name for location of the placement.
     *
     * @var string
     */
    protected $target = '';
    /**
     * The order in which it should appear at on the target location.
     *
     * @var string
     */
    protected $priority;
    /**
     * The relevant setting keys for the current page in the Settings class.
     *
     * @var array
     */
    protected $properties;
    /**
     * The KOSM settings
     *
     * @var Settings
     */
    protected $settings;
    /**
     * Class constructor.
     *
     * @param Settings $settings The KOSM settings.
     * @param array    $properties The relevant setting keys for the current page in the Settings class.
     */
    public function __construct($settings, $properties)
    {
        $this->properties = $properties;
        $this->settings = $settings;
        \add_action('init', array($this, 'init_settings'), 10, 0);
    }
    /**
     * Initialize the settings.
     *
     * @return void
     */
    public function init_settings()
    {
        $this->update($this->settings);
        $this->enabled = !empty($this->placement_id);
    }
    /**
     * Update the internal settings.
     *
     * @param Settings $settings The KOSM settings.
     * @return void
     */
    protected function update($settings)
    {
        foreach ($this->properties as $key => $value) {
            // Maybe get the client id from the KP settings.
            if ('client_id' === $key && \function_exists('kp_get_client_id_by_currency')) {
                $this->{$key} = \kp_get_client_id_by_currency();
                continue;
            }
            if (\is_array($value)) {
                $this->{$key} = $settings->get($value['setting'], $value['default']);
            } else {
                $this->{$key} = $settings->get($value);
            }
            if (\in_array($this->{$key}, array('yes', 'no'))) {
                $this->{$key} = \wc_string_to_bool($this->{$key});
            }
        }
    }
    /**
     * Print the HTML placement.
     *
     * @return void
     */
    public function display_placement()
    {
        if (!empty($this->client_id)) {
            $args = array('key' => $this->key, 'theme' => $this->theme, 'purchase-amount' => '', 'client_id' => $this->client_id);
            Utility::print_placement($args);
        }
    }
}
