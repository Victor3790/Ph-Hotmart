<?php 

namespace ph_hotmart;

if( ! class_exists( '\vk_templates\Template' ) )
    require_once namespace\PATH.'includes/vk_libraries/class_vk_template.php';

if( ! class_exists( '\Vk_custom_libs\Settings' ) )
    require_once namespace\PATH.'includes/vk_libraries/class_vk_admin_settings.php';

use Vk_custom_libs\Settings;
use vk_templates\Template;

class Plugin_Admin 
{

    public function add_custom_field() : void 
    {

        global $woocommerce, $post;

        echo '<div class="product_custom_field">';

        woocommerce_wp_text_input(
            array(
                'id' => '_hotmart_product_id',
                'placeholder' => 'Id',
                'label' => 'Hotmart id'
            )
        );

        echo '</div>';

    }

    public function save_custom_field( string $post_id ) : void 
    {

        if( empty( $_POST['_hotmart_product_id'] ) )
            return;

        $hotmart_id = esc_attr( $_POST['_hotmart_product_id'] );

        update_post_meta( $post_id, '_hotmart_product_id', $hotmart_id );

    }

    public function handle_custom_query_var( array $query, array $query_vars ) : array 
    {

        if( empty( $query_vars['_hotmart_product_id'] ) )
            return $query;

        $query['meta_query'][] = array(
            'key' => '_hotmart_product_id',
            'value' => esc_attr( $query_vars['_hotmart_product_id'] )
        );

        return $query;

    }

    public function add_custom_order_status( array $order_statuses ) : array 
    {

        $new_order_statuses = array();

        foreach ($order_statuses as $key => $value) {
        
            $new_order_statuses[$key] = $value;

            if( $key === 'wc-completed' ) {

                $new_order_statuses['wc-hotmart-completed'] = 'Completado en Hotmart';

            }

        }

        return $new_order_statuses;

    }

    public function register_custom_order_status() : void 
    {

        $args = array(
            'label'                     => 'Hotmart Completed',
            'public'                    => true,
            'show_in_admin_status_list' => true, 
            'show_in_admin_all_list'    => true,
            'exclude_from_search'       => false,
            'label_count'               => _n_noop( 'Hotmart Completed <span class="count">(%s)</span>', 'Hotmart Completed <span class="count">(%s)</span>' )
        );
        register_post_status( 'wc-hotmart-completed', $args );

    }

    public function register_page() : void 
    {

        $suffix = add_menu_page(
            'Ph Hotmart',
            'Ph Hotmart',
            'manage_options',
            'ph-hotmart-page',
            [ $this, 'load_dashboard' ],
            '',
            5
        );

    }

    public function register_settings() : void 
    {

        $settings_sections = array(
            'ph-hotmart-section' => array(
                'settings' => array(
                    'hotmart-key' => array(
                        'field_label' => 'Hotmart key',
                        'field_args' => array( 'placeholder' => 'Introduce the key' )
                    )
                )
            )
        );

        $settings = new Settings();
        $settings->add_settings_sections( $settings_sections, 'ph-hotmart-page', 'ph-hotmart-group' );

    }

    public function load_dashboard() : void 
    {

        $template = new Template();

        $file = namespace\PATH.'templates/dashboard.php';
        $view = $template->load( $file );

        echo $view;

    }

}
