<?php

namespace Krokedil\Klarna\OnsiteMessaging;

if (!\defined('ABSPATH')) {
    exit;
}
/**
 * Class for accessing and setting plugin settings.
 * @internal
 */
class Settings
{
    /**
     * The KOSM settings.
     *
     * @var array
     */
    private $settings;
    /**
     * Class constructor.
     *
     * @param array $settings Any existing KOSM settings.
     */
    public function __construct($settings = array())
    {
        $default = $this->default();
        foreach (\wp_parse_args($settings, $default) as $setting => $value) {
            if (\array_key_exists($setting, $default)) {
                $this->settings[$setting] = $value;
            }
        }
    }
    /**
     * Retrieve the value of a setting.
     *
     * @param string $key The setting name.
     * @param mixed  $default The default value if $key does not exist. Default is null.
     * @return string|int|null The setting's string or integer value. NULL if $key does not exist.
     */
    public function get($key, $default = null)
    {
        return \array_key_exists($key, $this->settings) ? $this->settings[$key] : $default;
    }
    /**
     * Extend your plugin with the required KOSM settings.
     *
     * @param array $settings Your plugin settings as an array.
     * @return array
     */
    public function extend_settings($settings)
    {
        $default = $this->default();
        $settings['onsite_messaging'] = array('title' => 'Klarna On-Site Messaging', 'type' => 'title');
        $settings['onsite_messaging_test_mode'] = array('title' => \__('Test mode', 'klarna-onsite-messaging-for-woocommerce'), 'type' => 'checkbox', 'label' => \__('Enable Test Mode', 'klarna-onsite-messaging-for-woocommerce'), 'default' => $default['onsite_messaging_test_mode']);
        $settings['data_client_id'] = array('title' => \__('Client ID', 'klarna-onsite-messaging-for-woocommerce'), 'type' => 'text', 'description' => \__('Enter the client ID given by Klarna for Klarna On-Site Messaging', 'klarna-onsite-messaging-for-woocommerce'), 'default' => $default['data_client_id'], 'desc_tip' => \true);
        $settings['onsite_messaging_enabled_product'] = array('title' => \__('Enable/Disable the Product placement', 'klarna-onsite-messaging-for-woocommerce'), 'type' => 'checkbox', 'label' => \__('Enable/Disable the Product placement', 'klarna-onsite-messaging-for-woocommerce'), 'default' => $default['onsite_messaging_enabled_product']);
        $settings['placement_data_key_product'] = array('title' => \__('Product page placement data key', 'klarna-onsite-messaging-for-woocommerce'), 'type' => 'text', 'description' => \__('Enter the placement data key for the product page.', 'klarna-onsite-messaging-for-woocommerce'), 'default' => $default['placement_data_key_product'], 'desc_tip' => \true);
        $settings['onsite_messaging_product_location'] = array('title' => \__('Product On-Site Messaging placement', 'klarna-onsite-messaging-for-woocommerce'), 'desc' => \__('Select where to display the widget in your product pages', 'klarna-onsite-messaging-for-woocommerce'), 'id' => '', 'default' => $default['onsite_messaging_product_location'], 'type' => 'select', 'options' => array('4' => \__('Above Title', 'klarna-onsite-messaging-for-woocommerce'), '7' => \__('Between Title and Price', 'klarna-onsite-messaging-for-woocommerce'), '15' => \__('Between Price and Excerpt', 'klarna-onsite-messaging-for-woocommerce'), '25' => \__('Between Excerpt and Add to cart button', 'klarna-onsite-messaging-for-woocommerce'), '35' => \__('Between Add to cart button and Product meta', 'klarna-onsite-messaging-for-woocommerce'), '45' => \__('Between Product meta and Product sharing buttons', 'klarna-onsite-messaging-for-woocommerce'), '55' => \__('After Product sharing buttons', 'klarna-onsite-messaging-for-woocommerce')));
        $settings['onsite_messaging_theme_product'] = array('title' => \__('Product Placement Theme', 'klarna-onsite-messaging-for-woocommerce'), 'desc' => \__('Select which theme to use for the product pages.', 'klarna-onsite-messaging-for-woocommerce'), 'id' => '', 'default' => $default['onsite_messaging_theme_product'], 'type' => 'select', 'options' => array('default' => \__('Default', 'klarna-onsite-messaging-for-woocommerce'), 'dark' => \__('Dark', 'klarna-onsite-messaging-for-woocommerce'), 'custom' => \__('Custom', 'klarna-onsite-messaging-for-woocommerce')));
        $settings['onsite_messaging_enabled_cart'] = array('title' => \__('Enable/Disable the Cart placement', 'klarna-onsite-messaging-for-woocommerce'), 'type' => 'checkbox', 'label' => \__('Enable/Disable the Cart placement', 'klarna-onsite-messaging-for-woocommerce'), 'default' => $default['onsite_messaging_enabled_cart']);
        $settings['placement_data_key_cart'] = array('title' => \__('Cart page placement data key', 'klarna-onsite-messaging-for-woocommerce'), 'type' => 'text', 'description' => \__('Enter the placement data key for the cart page.', 'klarna-onsite-messaging-for-woocommerce'), 'default' => $default['placement_data_key_cart'], 'desc_tip' => \true);
        $settings['onsite_messaging_cart_location'] = array('title' => \__('Cart On-Site Messaging placement', 'klarna-onsite-messaging-for-woocommerce'), 'desc' => \__('Select where to display the widget on your cart page', 'klarna-onsite-messaging-for-woocommerce'), 'id' => '', 'default' => $default['onsite_messaging_cart_location'], 'type' => 'select', 'options' => array('woocommerce_cart_collaterals' => \__('Above cross sell', 'klarna-onsite-messaging-for-woocommerce'), 'woocommerce_before_cart_totals' => \__('Above cart totals', 'klarna-onsite-messaging-for-woocommerce'), 'woocommerce_proceed_to_checkout' => \__('Between cart totals and proceed to checkout button', 'klarna-onsite-messaging-for-woocommerce'), 'woocommerce_after_cart_totals' => \__('After proceed to checkout button', 'klarna-onsite-messaging-for-woocommerce'), 'woocommerce_after_cart' => \__('Bottom of the page', 'klarna-onsite-messaging-for-woocommerce')));
        $settings['onsite_messaging_theme_cart'] = array('title' => \__('Cart Placement Theme', 'klarna-onsite-messaging-for-woocommerce'), 'desc' => \__('Select which theme to use for the cart page.', 'klarna-onsite-messaging-for-woocommerce'), 'id' => '', 'default' => $default['onsite_messaging_theme_cart'], 'type' => 'select', 'options' => array('default' => \__('Default', 'klarna-onsite-messaging-for-woocommerce'), 'dark' => \__('Dark', 'klarna-onsite-messaging-for-woocommerce'), 'custom' => \__('Custom', 'klarna-onsite-messaging-for-woocommerce')));
        $settings['custom_product_page_widget_enabled'] = array('title' => \__('Enable custom placement hook', 'klarna-onsite-messaging-for-woocommerce'), 'type' => 'checkbox', 'default' => $default['custom_product_page_widget_enabled']);
        $settings['custom_product_page_placement_hook'] = array('title' => \__('Custom placement hook', 'klarna-onsite-messaging-for-woocommerce'), 'desc_tip' => \__('Enter a custom hook where you want the OSM widget to be placed.', 'klarna-onsite-messaging-for-woocommerce'), 'type' => 'text', 'default' => $default['custom_product_page_placement_hook']);
        $settings['custom_product_page_placement_priority'] = array('title' => \__('Custom placement hook priority', 'klarna-onsite-messaging-for-woocommerce'), 'desc_tip' => \__('Enter a priority for the custom hook where you want the OSM widget to be placed.', 'klarna-onsite-messaging-for-woocommerce'), 'type' => 'number', 'default' => $default['custom_product_page_placement_priority']);
        return $settings;
    }
    /**
     * Extend with the V2 settings used by Klarna Payments
     *
     * @param array $settings The settings array.
     *
     * @return array
     */
    public function extend_v2_settings($settings)
    {
        $default = $this->default();
        $settings['onsite_messaging'] = array('id' => 'kosm', 'title' => 'On-Site Messaging', 'description' => \__('Add personalized messaging throughout the shopper journey for higher conversion rates and increased spend.', 'klarna-onsite-messaging-for-woocommerce'), 'links' => array(array('url' => 'https://docs.klarna.com/conversion-boosters/on-site-messaging/additional-resources/osm-customise-merchant-portal/', 'title' => \__('Further customization is possible in the Klarna Merchant Portal.', 'klarna-onsite-messaging-for-woocommerce'))), 'type' => 'kp_section_start');
        $settings['onsite_messaging_enabled'] = array('title' => \__('Enable/Disable', 'klarna-onsite-messaging-for-woocommerce'), 'label' => \__('Enable On-site Messaging', 'klarna-onsite-messaging-for-woocommerce'), 'type' => 'checkbox', 'description' => '', 'default' => 'yes');
        $settings['placement_data_key_cart'] = array('title' => \__('Cart Page Placement', 'klarna-onsite-messaging-for-woocommerce'), 'type' => 'select', 'description' => \__('Select the placement for the cart page.', 'klarna-onsite-messaging-for-woocommerce'), 'default' => $default['placement_data_key_cart'], 'desc_tip' => \true, 'options' => array('' => \__('Don\'t show', 'klarna-onsite-messaging-for-woocommerce'), 'credit-promotion-badge' => \__('Show with Klarna badge  (recommended)', 'klarna-onsite-messaging-for-woocommerce'), 'credit-promotion-auto-size' => \__('Show without Klarna badge', 'klarna-onsite-messaging-for-woocommerce')));
        $settings['onsite_messaging_cart_location'] = array('title' => \__('Cart Page Location', 'klarna-onsite-messaging-for-woocommerce'), 'desc' => \__('Select where to display the widget on your cart page', 'klarna-onsite-messaging-for-woocommerce'), 'id' => '', 'default' => $default['onsite_messaging_cart_location'], 'type' => 'select', 'options' => array('woocommerce_cart_collaterals' => \__('Above cross sell', 'klarna-onsite-messaging-for-woocommerce'), 'woocommerce_before_cart_totals' => \__('Above cart totals', 'klarna-onsite-messaging-for-woocommerce'), 'woocommerce_proceed_to_checkout' => \__('Between cart totals and proceed to checkout button', 'klarna-onsite-messaging-for-woocommerce'), 'woocommerce_after_cart_totals' => \__('After proceed to checkout button', 'klarna-onsite-messaging-for-woocommerce'), 'woocommerce_after_cart' => \__('Bottom of the page', 'klarna-onsite-messaging-for-woocommerce')));
        $settings['onsite_messaging_theme_cart'] = array('title' => \__('Cart Page Theme', 'klarna-onsite-messaging-for-woocommerce'), 'desc' => \__('Select which theme to use for the cart page.', 'klarna-onsite-messaging-for-woocommerce'), 'id' => '', 'default' => $default['onsite_messaging_theme_cart'], 'type' => 'select', 'options' => array('default' => \__('Light', 'klarna-onsite-messaging-for-woocommerce'), 'dark' => \__('Dark', 'klarna-onsite-messaging-for-woocommerce'), 'custom' => \__('Custom', 'klarna-onsite-messaging-for-woocommerce')));
        $settings['placement_data_key_product'] = array('title' => \__('Product Page Placement', 'klarna-onsite-messaging-for-woocommerce'), 'type' => 'select', 'description' => \__('Select the placement type that you want to show on the product pages.', 'klarna-onsite-messaging-for-woocommerce'), 'default' => $default['placement_data_key_product'], 'desc_tip' => \true, 'options' => array('' => \__('Don\'t show', 'klarna-onsite-messaging-for-woocommerce'), 'credit-promotion-badge' => \__('Show with Klarna badge (recommended)', 'klarna-onsite-messaging-for-woocommerce'), 'credit-promotion-auto-size' => \__('Show without Klarna badge', 'klarna-onsite-messaging-for-woocommerce')));
        $settings['onsite_messaging_product_location'] = array('title' => \__('Product Page Location', 'klarna-onsite-messaging-for-woocommerce'), 'desc' => \__('Select where to display the widget in your product pages', 'klarna-onsite-messaging-for-woocommerce'), 'id' => '', 'default' => $default['onsite_messaging_product_location'], 'type' => 'select', 'options' => array('4' => \__('Above Title', 'klarna-onsite-messaging-for-woocommerce'), '7' => \__('Between Title and Price', 'klarna-onsite-messaging-for-woocommerce'), '15' => \__('Between Price and Excerpt', 'klarna-onsite-messaging-for-woocommerce'), '25' => \__('Between Excerpt and Add to cart button', 'klarna-onsite-messaging-for-woocommerce'), '35' => \__('Between Add to cart button and Product meta', 'klarna-onsite-messaging-for-woocommerce'), '45' => \__('Between Product meta and Product sharing buttons', 'klarna-onsite-messaging-for-woocommerce'), '55' => \__('After Product sharing buttons', 'klarna-onsite-messaging-for-woocommerce')));
        $settings['onsite_messaging_theme_product'] = array('title' => \__('Product Page Theme', 'klarna-onsite-messaging-for-woocommerce'), 'description' => \__('The On-site messaging placements come in Light and Dark theme to fit into the look and feel of your website.', 'klarna-onsite-messaging-for-woocommerce'), 'id' => '', 'default' => $default['onsite_messaging_theme_product'], 'desc_tip' => \true, 'type' => 'select', 'options' => array('default' => \__('Light', 'klarna-onsite-messaging-for-woocommerce'), 'dark' => \__('Dark', 'klarna-onsite-messaging-for-woocommerce'), 'custom' => \__('Custom', 'klarna-onsite-messaging-for-woocommerce')));
        $settings['onsite_messaging_end'] = array('type' => 'kp_section_end', 'previews' => array(array('title' => \__('Cart preview', 'klarna-onsite-messaging-for-woocommerce'), 'image' => $this->get_preview_image($this->settings['placement_data_key_cart'] ?? '', $this->settings['onsite_messaging_theme_cart'] ?? '', 'cart')), array('title' => \__('Product preview', 'klarna-onsite-messaging-for-woocommerce'), 'image' => $this->get_preview_image($this->settings['placement_data_key_product'] ?? '', $this->settings['onsite_messaging_theme_product'] ?? '', 'product'))));
        return $settings;
    }
    /**
     * Get the cart or product page preview image url.
     *
     * @param string $key The key for the preview image.
     * @param string $theme The theme for the preview image.
     * @param string $type The type of preview image.
     *
     * @return string The preview image url.
     */
    private function get_preview_image($key, $theme, $type)
    {
        // Convert default values to proper values.
        if ('default' === $theme || 'custom' === $theme || '' === $theme) {
            $theme = 'light';
        }
        if ('' === $key) {
            $key = 'credit-promotion-badge';
        }
        $url = \plugin_dir_url(__FILE__) . "assets/img/preview-{$type}-{$theme}-{$key}.jpg";
        return $url;
    }
    /**
     * Returns the default state for all the mutable
     *
     * @return array<string,string|int>
     */
    private function default()
    {
        return array('onsite_messaging_enabled' => 'yes', 'onsite_messaging_enabled_product' => 'yes', 'placement_data_key_product' => 'credit-promotion-badge', 'onsite_messaging_product_location' => '45', 'onsite_messaging_theme_product' => 'default', 'onsite_messaging_enabled_cart' => 'yes', 'placement_data_key_cart' => 'credit-promotion-badge', 'onsite_messaging_cart_location' => 'woocommerce_cart_collaterals', 'onsite_messaging_theme_cart' => '', 'custom_product_page_widget_enabled' => 'no', 'custom_product_page_placement_hook' => 'woocommerce_single_product_summary', 'custom_product_page_placement_priority' => 35);
    }
    /**
     * Get the enabled status for On-Site Messaging.
     *
     * @return bool
     */
    public function is_enabled()
    {
        $kp_unavailable_feature_ids = \get_option('kp_unavailable_feature_ids', array());
        if (\in_array('onsite_messaging', $kp_unavailable_feature_ids)) {
            return \false;
        }
        return 'yes' === $this->settings['onsite_messaging_enabled'] ?? 'yes';
    }
}
