<?php
/**
 * Plugin Name: File Templates for Contact Form 7
 * Description: Use files instead of the database to store your form definitions.
 * Author: Florian Eickhorst <a href="http://www.fleimedia.de">fleimedia.de</a>
 * Version: 0.1
 */

//if (class_exists('WPCF7_ContactForm')) {

class Flei_CF7_Templates
{

    const PLUGIN_SLUG = 'cf7-templates';

    private static $instance;

    private $form_post = false;
    private $form_html = null;



    public function __construct()
    {
        // Filters
        $this->add_filters();
        $this->add_actions();
    }

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function add_filters()
    {
//        add_filter('get_post_metadata', array($this, 'filter_intercept_form_html'), 10, 4);
        add_filter('wpcf7_contact_form_properties', array($this, 'filter_contact_form_properties'), 10, 2);
        add_filter('wpcf7_editor_panels', array($this, 'admin_filter_hide_panels'), null, 1);
    }

    private function add_actions()
    {
//        add_meta_box( string $id, string $title, callable $callback, string|array|WP_Screen $screen = null, string $context = 'advanced', string $priority = 'default', array $callback_args = null )
        add_action('add_meta_boxes', array($this, 'admin_add_metabox'));
        add_action( 'wpcf7_save_contact_form', array($this,'admin_action_save_contact_form'),10,4);

        add_action('wpcf7_admin_misc_pub_section', array($this,'admin_pub_meta_box'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_styles'));
    }

    public function admin_enqueue_styles() {
        wp_register_style(self::PLUGIN_SLUG, plugins_url( 'cf7_templates.css', __FILE__ ));
        wp_enqueue_style( self::PLUGIN_SLUG );
    }

    public function admin_action_save_contact_form($contact_form, $args, $context ) {
//        var_dump($contact_form, $args, $context);
//        die();

    }

    public function admin_pub_meta_box() {
        ?>
        <div class="cf7-templates-meta-box-infos">
        <h3><?php echo esc_html( __( 'Template', self::PLUGIN_SLUG ) ); ?></h3>
            <p>Folder: <strong><?php echo $this->get_template_folder();?></strong> <br>
                File: <strong><?php echo $this->get_template_filename();?></strong> <br>
                Status: <strong><?php echo ($this->template_exists()?_('found'):_('not found'))?></strong> </p>
        </div>
<?php
    }

    public function x() {
        echo 'dkfj';
        return 'dskd';
    }

    private function template_exists($post = null)
    {

        $cf7_templates_subfolder = $this->get_template_folder($post);
        $cf7_template_filename = $this->get_template_filename($post);
        $cf7_template_file = $cf7_templates_subfolder . $cf7_template_filename;

        $file_exists = locate_template(array($cf7_template_file), false);
        return $file_exists;
    }

    private function get_template_filename($post = null) {

        $post_name = '';
        if (!$post) {
            $post = wpcf7_get_current_contact_form();

//            var_dump($post);
//            die();

            if ($post) {
                $post_name = $post->title();
            } else {
                $post = get_post();
            }
        } else {

            $post = get_post($post);
            $post_name = $post->post_title;
        }

        $post_name = sanitize_title($post_name);

        return apply_filters('flei/cf7_templates/filename', $post_name . '.php', $post);
    }

    private function get_template_folder() {
        return trailingslashit(apply_filters('flei/cf7_templates/subfolder', 'cf7_templates'));
    }

    private function load_form_template($post)
    {

        if (!$this->form_html) {

            $post = get_post($post);

            $file_exists = $this->template_exists($post);

            if ($file_exists) {
                ob_start();
                load_template($file_exists, false);
                $this->form_html = ob_get_contents();
                ob_end_clean();
            }
        }

        return $this->form_html;
    }

    public function filter_contact_form_properties($properties, $cf7_form_instance) {
        return $properties;
    }

    public function filter_intercept_form_html($x, $cf7_post_id, $meta_key,$single)
    {
        if (!is_admin() && $meta_key == '_form') {

            $cf7_post = get_post($cf7_post_id);
            if ($cf7_post->post_type == WPCF7_ContactForm::post_type) {
                $template_output =$this->load_form_template($cf7_post);

                $show_admin_notice = apply_filters( 'flei/cf7_templates/show_admin_notice', false );

                if ($show_admin_notice && current_user_can('manage_options')) {
                    $admin_notice  = '<div style="display:block;border:2px dashed #ccc;padding:10px;color:red;">';
                    $admin_notice .= __('Using template',self::PLUGIN_SLUG).' '.$this->template_exists($cf7_post_id);
                    $admin_notice .= '</div>';
                    $template_output = $admin_notice . $template_output;
                }

                if ($single) {
                    return $template_output;
                } else {
                    return array($template_output);
                }
            }
        }
    }

    public function admin_filter_hide_panels($panels)
    {

        if ($this->template_exists($this->admin_get_form_post())) {
            if (isset($panels['form-panel'])) {
//                $panels['form-panel']['callback'] = array($this, 'admin_render_replacement_form');
            }
        }

        if (isset($panels['additional-settings'])) {
            // @todo hier weitermachen und das slug feld vor das additional settings feld schreiben
        }

        return $panels;
    }

    public function admin_render_replacement_form()
    {

        if (isset($_GET['post'])) {
            $post = get_post($_GET['post']);

            if ($this->template_exists($post)) {
                echo _('Based on its slug this form is generated through file ');
                echo $this->template_exists($post);
            }
        }
    }

    private function admin_get_form_post()
    {
        if (!$this->form_post) {
            if (isset($_GET['post'])) {
                $this->form_post = get_post($_GET['post']);
            }
        }

        return $this->form_post;
    }

}

$flei_cf7_templates = Flei_CF7_Templates::get_instance();

//    add_filter('get_post_metadata', 'flei_cf7_templates_intercept_cf7_form_html', 10, 3);

function flei_cf7_templates_intercept_cf7_form_html($x, $cf7_post_id, $meta_key)
{

//        if ($meta_key == '_form') {
//            $cf7_post = get_post($cf7_post_id);
//            if ($cf7_post->post_type == WPCF7_ContactForm::post_type) {
//
//                $cf7_folder = apply_filters('cf7')
//                $theme_folder = wp_get_templ
//
//                var_dump($x, $cf7_post, $meta_key);
//                die();
//            }
//
//        }
}
//}
