<?php
/**
 * Plugin Name: File Templates for Contact Form 7
 * Plugin URI: https://github.com/fleiflei/cf7-templates
 * Description: Use files to store your Contact Form 7 form definitions.
 * Author: Florian Eickhorst <a href="http://www.fleimedia.de">fleimedia.de</a>
 * Version: 0.1
 */

class Flei_CF7_Templates
{

    const PLUGIN_SLUG = 'cf7-templates';

    private static $instance;

    private $form_properties = array();
    private $form_instances = array();

    public function __construct()
    {
        add_action('plugins_loaded', array($this, 'init_plugin'));
    }

    public static function get_instance()
    {

        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function init_plugin()
    {
        if (function_exists('wpcf7_contact_form')) {
            $this->add_actions();
            $this->add_filters();
        }
    }

    private function add_filters()
    {
        add_filter('wpcf7_contact_form_properties', array($this, 'filter_contact_form_properties'), 10, 2);
        add_filter('wpcf7_editor_panels', array($this, 'admin_filter_hide_panels'), null, 1);
        add_filter('update_post_metadata', array($this, 'filter_update_post_metadata'), null, 5);
    }

    public function filter_update_post_metadata($value, $object_id, $meta_key, $meta_value, $prev_value)
    {

        if ($meta_key == '_form' && get_post_type($object_id) == WPCF7_ContactForm::post_type && $this->_template_is_active($object_id)) {
            return false;
        }
    }

    public function filter_intercept_form_html($x, $cf7_post_id, $meta_key, $single)
    {
        if (!is_admin() && $meta_key == '_form') {

            $cf7_post = get_post($cf7_post_id);
            if ($cf7_post->post_type == WPCF7_ContactForm::post_type) {
                $template_output = $this->load_form_template($cf7_post);

                if ($single) {
                    return $template_output;
                } else {
                    return array($template_output);
                }
            }
        }
    }

    public function filter_get_post_metadata($value, $object_id, $meta_key, $single)
    {

        if (
            $meta_key == '_form'
            && get_post_type($object_id) == WPCF7_ContactForm::post_type
            && $this->_template_is_active($object_id)
        ) {

            $template_output = $this->_load_form_template($object_id);

            if ($single) {
                return $template_output;
            } else {
                return array($template_output);
            }
        }
    }

    private function add_actions()
    {
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_styles'));
    }

    public function admin_enqueue_styles() {
        wp_register_style(self::PLUGIN_SLUG, plugins_url( 'cf7_templates.css', __FILE__ ));
        wp_enqueue_style( self::PLUGIN_SLUG );
    }

    private function _template_exists($cf7_form_id)
    {

        $cf7_templates_subfolder = $this->_get_template_folder();
        $cf7_template_filename = $this->_get_template_filename($cf7_form_id);
        $cf7_template_file = $cf7_templates_subfolder . $cf7_template_filename;

        $file_exists = locate_template(array($cf7_template_file), false);

        return $file_exists;
    }

    private function _template_disabled($cf7_form_id)
    {
        $disabled_templates = apply_filters("flei/cf7_templates/disabled", array());

        return in_array($cf7_form_id, $disabled_templates);
    }

    private function _template_is_active($cf7_form_id)
    {
        return $this->_template_exists($cf7_form_id) && !$this->_template_disabled($cf7_form_id);
    }

    private function _get_template_filename($cf7_form_id)
    {

        $template_name = '';
        $cf7_form_instance = $this->_get_cf7_form_instance($cf7_form_id);
        if ($cf7_form_instance) {
            $template_name = sanitize_title($cf7_form_instance->title());
        }

        return apply_filters('flei/cf7_templates/filename/' . $cf7_form_id, $template_name . '.php', $cf7_form_id);
    }

    private function _get_template_folder()
    {
        return trailingslashit(apply_filters('flei/cf7_templates/subfolder', 'cf7_templates'));
    }

    private function _load_form_template($cf7_form_id, $raw_output = false)
    {

        $template_html = '';

        $file_exists = $this->_template_exists($cf7_form_id);

        if (!$this->_template_disabled($cf7_form_id) && $file_exists) {
            if (!$raw_output) {
                ob_start();
                load_template($file_exists, false);
                $template_html = ob_get_contents();
                ob_end_clean();
            } else {
                $template_html = file_get_contents($file_exists);
            }
        }

        return $template_html;

    }

    public function filter_contact_form_properties($properties, $cf7_form_instance)
    {
        if (gettype($cf7_form_instance) == 'object' && get_class($cf7_form_instance) == 'WPCF7_ContactForm') {

            $cf7_form_id = $this->_get_cf7_form_id($cf7_form_instance);

            if (is_admin() && (isset($_GET['page']) && $_GET['page'] == 'wpcf7' && isset($_GET['post']) && $_GET['post'] == $cf7_form_id && isset($_REQUEST['action']) && $_REQUEST['action'] == 'save')) {
//                return $properties;
            }

            $this->form_instances[$cf7_form_id] = $cf7_form_instance;

//            if (isset($properties['form']) && !empty($properties['form'])) {
            $properties['form'] = $this->replace_form_property_form($properties['form'], $cf7_form_id);
//            }

            if (isset($properties['mail'])) {

            }

            if (isset($properties['mail2'])) {

            }
        }

        return $properties;
    }

    private function replace_form_property_form($form_html, $cf7_form_id)
    {

        if ($this->_get_local_form_property('form', $cf7_form_id)) {
            $form_html = $this->form_properties[$cf7_form_id]['form'];
        } else {
            if ($this->_template_is_active($cf7_form_id)) {

                $template_output = $this->_load_form_template($cf7_form_id);

                $form_html = $template_output;

                $this->_store_local_form_property('form', $form_html, $cf7_form_id);
            }
        }

        if (!is_admin() && apply_filters('flei/cf7_templates/admin/show_notice', false) && current_user_can('manage_options')) {
            $admin_notice = '<div style="display:block;border:2px dashed #ccc;margin:20px 0;padding:10px;color:red;">';
            if ($this->_template_exists($cf7_form_id)) {
                $admin_notice .= __('Using template', 'cf7_templates') . ' ' . $this->_template_exists($cf7_form_id);
            } else {
                $admin_notice .= __('No template found, place here: ', 'cf7_templates') . trailingslashit(get_stylesheet_directory()) . $this->_get_template_folder() . $this->_get_template_filename($cf7_form_id);
            }

            $admin_notice .= '</div>';

            $form_html = $admin_notice . $form_html . $admin_notice;
        }

        return $form_html;

    }

    /**
     * @param $property_name
     * @param $property_value
     * @param $cf7_form_id
     */
    private function _store_local_form_property($property_name, $property_value, $cf7_form_id)
    {

        if (!isset($this->form_properties[$cf7_form_id])) {
            $this->form_properties[$cf7_form_id] = array();
        }
        $this->form_properties[$cf7_form_id][$property_name] = $property_value;
    }

    /**
     * @param $property_name
     * @param $cf7_form_id
     * @return bool|mixed
     */
    private function _get_local_form_property($property_name, $cf7_form_id)
    {
        if (isset($this->form_properties[$cf7_form_id]) && isset($this->form_properties[$cf7_form_id][$property_name])) {
            return $this->form_properties[$cf7_form_id][$property_name];
        }

        return false;
    }

    /**
     * @param $cf7_form_instance
     * @return mixed
     */
    private function _get_cf7_form_id($cf7_form_instance = null)
    {
        if (is_admin()) {
            if (!$cf7_form_instance) {
                $cf7_form_instance = wpcf7_get_current_contact_form();
            }
        }

        if (!$cf7_form_instance) {
            return false;
        } else {
            $cf7_form_id = $cf7_form_instance->id();

            $this->form_instances[$cf7_form_id] = $cf7_form_instance;

            return $cf7_form_instance->id();
        }

    }

    private function _get_cf7_form_instance($cf7_form_id)
    {

        if ($cf7_form_id && !empty($this->form_instances) && isset($this->form_instances[$cf7_form_id])) {
            return $this->form_instances[$cf7_form_id];
        } else {
            $cf7_forms = WPCF7_ContactForm::find(array('ID' => $cf7_form_id));
            if ($find_form = reset($cf7_forms)) {
                $this->form_instances[$cf7_form_id] = $find_form;

                return $find_form;
            }
        }

        return false;

    }

    public function admin_filter_hide_panels($panels)
    {

        if (!$cf7_form_id = $this->_get_cf7_form_id()) {
            return $panels;
        }

        if ($this->_template_is_active($cf7_form_id)) {
            if (isset($panels['form-panel'])) {
                $panels['form-panel']['callback'] = array($this, 'admin_panel_form_replacement');
            }
        }

        $panels['cf7_templates_panel'] = array(
            'title'    => __('Template', 'cf7_templates') . ' (' . ($this->_template_is_active($cf7_form_id) ? __('active', 'cf7_templates') : __('inactive', 'cf7_templates')) . ')',
            'callback' => array($this, 'admin_panel_template'),
        );

        return $panels;
    }

    public function admin_panel_form_replacement($cf7_form_instance)
    {

        $cf7_form_id = $this->_get_cf7_form_id($cf7_form_instance);
        $display_raw_template = apply_filters('flei/cf7_templates/admin/display_raw_template', false);

        echo '<h2>' . _('Using <strong>template</strong>:<br> ') . '</h2>';
        echo '<div class="code">' . $this->_template_exists($cf7_form_id) . '</div>';
        echo '<br><br><textarea cols="100" rows="24" class="large-text code" readonly>' . $this->_load_form_template($cf7_form_id, $display_raw_template) . '</textarea>';

    }

    public function admin_panel_template()
    {
        $cf7_form_id = $this->_get_cf7_form_id();
        $template_path = $this->_template_exists($cf7_form_id);
        $template_disabled = $this->_template_disabled($cf7_form_id);
        ?>
        <h2><?php echo esc_html(__('Template', 'cf7_templates')) . ' ' . ($this->_template_is_active($cf7_form_id) ? __('active', 'cf7_templates') : __('inactive', 'cf7_templates')); ?></h2>
        <fieldset>
            <?php if ($template_path):
                echo '<strong>' . __('Template found!', 'cf7_templates') . '</strong>';
            else:
                echo '<strong>' . __('No template found. Create it here to enable it:', 'cf7_templates') . '</strong>';
                $template_path = trailingslashit(get_stylesheet_directory()) . $this->_get_template_folder() . $this->_get_template_filename($cf7_form_id);
            endif;
            echo '<br>Template path: <div class="code">' . $template_path . '</div>';
            if ($template_disabled):
                echo '<br><strong>' . __('Template disabled. Remove its ID from filter "flei/cf7_templates/disabled" to enable it.') . '</strong>';
                echo '<br>ID: <div class="code">' . $cf7_form_id . '</div>';
            endif;
            ?>
        </fieldset>
        <?php
    }

}

$flei_cf7_templates = Flei_CF7_Templates::get_instance();