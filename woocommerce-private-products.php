<?php

/**
 * Plugin Name: WooCommerce Private Products
 * Description: This plugin allows you to sell some products to a select range of customers. 
 * Version: 1.0.0
 * Author: MySite Digital
 * Author URI: https://mysite.digital
 * Requires at least: 5.2
 */

namespace MySiteDigital\WooCommerce;


if ( ! defined( 'ABSPATH' ) ) {

    exit; // Exit if accessed directly.

}

if ( ! class_exists( 'PrivateProducts' ) ) {

    /**
     * Main PrivateProducts Class.
     *
     * @class PrivateProducts
     * @version    1.0.0
     */

    final class PrivateProducts {
        
         /**
         * ID selector for the dropdown menu
         * on admin's panel.
         *
         * @var string $select_id
         */
        private $select_id;

        private $assets_path;

        /**
         * Constructor
         */
        public function __construct() {
            $this->select_id = "restrict-user-list";
            $this->assets_path = trailingslashit( plugin_dir_url(__FILE__) . 'assets' );
            $this->init();
        }

         /**
         * Initialize and hook proper callbacks
         */
        public function init() {
            add_action( 'pre_get_posts', [ $this, 'hide_products' ] );
             
            // add the filter 
            add_filter( 'woocommerce_related_products', [ $this, 'hide_private_products_from_related_posts' ], 10, 3 ); 
                      
            add_action( 'woocommerce_product_options_pricing', [ $this, 'output_customer_dropdown' ] );
            add_action( 'woocommerce_process_product_meta', [ $this, 'save_private_users' ] );

            add_action( 'admin_enqueue_scripts', [ $this, 'load_assets' ] );
        }


         /**
         * Hide user-restricted products on the main shop and category pages
         * Woocommerce product loop
         * 
         * @param $query
         */
        public function hide_products( $query ) {

            if ( ! is_admin() &&  ( ( isset( $query->query['post_type'] ) && $query->query['post_type'] === 'product' ) || array_key_exists( 'product_cat', $query->query ) ) ) {
                $meta_query = [
                    'relation' => 'OR',
                    //if the current user can purchase the product
			        [
                        'key'     => $this->select_id,
                        'value'   => json_encode( strval( get_current_user_id() ) ),
                        'compare' => 'LIKE'
                    ],
                    //or the product can be purchased by anyone
			        [
		                'key'     => $this->select_id,
		                'compare' => 'NOT EXISTS', // works!
                        'value' => '' // This is ignored, but is necessary...
                    ]
        		];

                $query->set( 'meta_query', $meta_query );
            }

	        return $query;
        }

        public function hide_private_products_from_related_posts( $related_posts, $product_id, $query_args ){ 
            global $wpdb; 

            $restricted_posts = $wpdb->get_results( 'SELECT post_id FROM '.  $wpdb->prefix . 'postmeta WHERE meta_key = "' . $this->select_id . '";' ); 

            if( $restricted_posts ){
                foreach ( $restricted_posts as $restricted_post ) {
                    if ( ( $key = array_search( $restricted_post->post_id, $related_posts ) ) !== false ) {
                        unset( $related_posts[ $key ] );
                    }
                } 
            }
            return $related_posts; 
        }

         /**
         * Returns the user's name to print
         * in the dropdown list.
         *
         * @return string $user_name
         */
        protected function get_current_user() {
            $user = wp_get_current_user();
            $user_name = $user->data->user_login;

            return $user_name;
        }


         /**
         * Shows the select field for custom user choosing
         */
        public function output_customer_dropdown() {
            global $post;

            $meta_value = get_post_meta( $post->ID, $this->select_id, true );
            $values = json_decode( $meta_value, true );

            $users = get_users(); ?>
            
            <div class="options-group private-products">
                <p><span class="dashicons dashicons-lock"></span><?php _e( 'Set the visibility of this product to specified users.', 'woo-private-product' ); ?></p>
                <p class="form-field">
                    <label for="restrict-user-list"><?php _e( 'Private to: ', 'woo-private-product' ); ?></label>
                    <select data-searchplaceholder="<?php _e( 'Search', 'woo-private-product' ); ?>" data-searchtext="<?php _e( 'No results', 'woo-private-product' ); ?>" class="short" name="restrict-user-list[]" id="restrict-user-list" multiple>
                        <option data-placeholder="true"><?php _e( 'Choose users...', 'woo-private-product' ); ?></option>
                        <?php foreach ( $users as $key => $user ):
                            $user_name = $user->data->display_name; 
                            $selected = in_array( $user->ID, $values ) ? 'selected="selected"' : ''; ?>

                            <option <?= $selected; ?> value="<?= $user->ID; ?>"><?= $user_name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
            </div>

            <?php
        }


         /**
         * Saves the product's meta.
         * 
         * @param int $post_id
         */
        public function save_private_users( $post_id ) {

            if( ! isset( $_POST[ $this->select_id ] ) ){
                delete_post_meta( $post_id, $this->select_id ); 
                return false;
            }

            # Always store as array
            $data = (array) $_POST[ $this->select_id ];
            if ( !( isset( $_POST['woocommerce_meta_nonce'], $data ) || wp_verify_nonce( sanitize_key( $_POST['woocommerce_meta_nonce'] ), 'woocommerce_save_data' ) ) ) {
                return false;
            }
            
            $private_user = json_encode( $data );
            
            update_post_meta(
                $post_id,
                $this->select_id,
                $private_user
            ); 
        }

        /**
         * Enqueue required scripts, including slim-select.
         */
        public function load_assets() {
            global $typenow;

            # Only call scripts on product page.
            if ( ! ( is_admin() && $typenow === 'product' ) ){
                return;
            }

            # Slim Select
            wp_enqueue_script( 'slim-select-js', 'https://cdnjs.cloudflare.com/ajax/libs/slim-select/1.18.7/slimselect.min.js' );
            wp_enqueue_style( 'slim-select-css', 'https://cdnjs.cloudflare.com/ajax/libs/slim-select/1.18.7/slimselect.min.css' );

            # Main script/style files
            wp_enqueue_script( 'wpp-main-js', $this->assets_path . 'js/min/wpp.min.js', [ 'jquery', 'slim-select-js' ], false, true );
            wp_enqueue_style( 'wpp-main-css', $this->assets_path . 'css/wpp.css' );

            
        }

    }

}

new PrivateProducts();
