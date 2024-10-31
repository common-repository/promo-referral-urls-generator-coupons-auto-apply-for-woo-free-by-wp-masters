<?php
/*
 * Plugin Name: Promo & Referral URLs Generator, Coupons Auto Apply for Woo - Free by WP Masters
 * Plugin URI: https://wp-masters.com/products/wpm-cookie-promocodes-coupons
 * Description: Create virtual promo URLs where your visitors will get coupons applied automatically to their checkout
 * Author: WP-Masters
 * Text Domain: wpm-import-variations
 * Author URI: https://wp-masters.com/
 * Version: 1.0.0
 *
 * @author      WP-Masters
 * @version     v.1.0.0 (11/07/22)
 * @copyright   Copyright (c) 2022
*/

define( 'WPM_PLUGIN_COOKIE_PROMO_PATH', plugins_url( '', __FILE__ ) );

class WPM_CookiePromocodes {
	private $settings;

	/**
	 * Initialize functions
	 */
	public function __construct() {
		// Init Functions
		add_action( 'init', [ $this, 'save_settings' ] );
		add_action( 'init', [ $this, 'load_settings' ] );

		// Include Styles and Scripts
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts_and_styles' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'include_scripts_and_styles' ], 99 );

		// Admin menu
		add_action( 'admin_menu', [ $this, 'register_menu' ] );

		// Share FaceBook without Cache
		add_filter( 'status_header', [ $this, 'create_virtual_cookie_urls' ], 99 );

		// WooCommerce functions
		add_action( 'woocommerce_before_cart_table', [ $this, 'remove_coupon_if_used' ], 99 );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'checkout_coupons_processed' ], 10, 3 );
	}

	/**
	 * Remove already used coupon
	 */
	public function checkout_coupons_processed( $order_id, $posted_data, $order ) {
		// Get all coupons in cart
		$coupons_list = unserialize( get_option( 'wpm_cookie_promo', true ) );
		$coupons      = $order->get_items( 'coupon' );

		// If no coupons used return
		if ( ! $coupons ) {
			return;
		}

		// Run through for each coupons used in cart
		foreach ( $coupons as $coupon ) {
			// If coupon code is free $5 coupon
			$code = $coupon->get_code();
			if ( in_array( $code, $coupons_list['code'] ) ) {
				foreach ( $coupons_list['code'] as $item => $virtual_code ) {
					if ( $virtual_code == $code ) {
						setcookie( "coupon_{$code}", md5( $code ), strtotime( "+{$coupons_list['cookie_days'][$item]} day" ), '/' );
					}
				}
			}
		}
	}

	/**
	 * Remove already used coupon
	 */
	public function remove_coupon_if_used() {
		global $woocommerce;

		// GET Coupons and remove it if already used
		$coupons_list = unserialize( get_option( 'wpm_cookie_promo', true ) );
		$woocommerce->cart->get_applied_coupons();

		// Remove used Coupons from Cart
		foreach ( $coupons_list['code'] as $code ) {
			if ( isset( $_COOKIE[ 'coupon_' . $code ] ) ) {
				$woocommerce->cart->remove_coupon( $code );
			}
		}

		$woocommerce->cart->calculate_totals();
	}

	/**
	 * Create special URLs for set COOKIE coupons
	 */
	public function create_virtual_cookie_urls( $header ) {
		if ( is_404() ) {
			// Get Request URL Parts
			$url          = parse_url( sanitize_url( $_SERVER['REQUEST_URI'] ) );
			$path         = explode( '/', $url['path'] );
			$virtual_urls = unserialize( get_option( 'wpm_cookie_promo', true ) );

			// Search Virtual ID in Path
			foreach ( $virtual_urls['url'] as $item => $url ) {
				if ( in_array( $url, $path ) ) {
					$code        = $virtual_urls['code'][ $item ];
					$cookie_days = $virtual_urls['cookie_days'][ $item ];
				}
			}

			// Add Coupon to Customer
			if ( isset( $code ) && isset( $cookie_days ) && ! isset( $_COOKIE[ 'coupon_' . $code ] ) ) {
				WC()->cart->apply_coupon( $code );
				header( 'Location: ' . get_site_url() );
				exit;
			}
		}

		return $header;
	}

	/**
	 * Save Core Settings to Option
	 */
	public function save_settings() {
		if ( isset( $_POST['wpm_cookie_promo'] ) && is_array( $_POST['wpm_cookie_promo'] ) ) {
			$data = $this->sanitize_array( $_POST['wpm_cookie_promo'] );
			update_option( 'wpm_cookie_promo', serialize( $data ) );
		}
	}

	/**
	 * Load Saved Settings
	 */
	public function load_settings() {
		$this->settings = unserialize( get_option( 'wpm_cookie_promo' ) );
	}

	/**
	 * Sanitize Array Data
	 */
	public function sanitize_array( $data ) {
		$filtered = [];
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $sub_key => $sub_value ) {
					$filtered[ $key ][ $sub_key ] = sanitize_text_field( $sub_value );
				}
			} else {
				$filtered[ $key ] = sanitize_text_field( $value );
			}
		}

		return $filtered;
	}

	/**
	 * Include Scripts And Styles on Admin Pages
	 */
	public function admin_scripts_and_styles() {
		// Register styles
		wp_enqueue_style( 'wpm-font-awesome', plugins_url( 'templates/libs/font-awesome/scripts/all.min.css', __FILE__ ) );
		wp_enqueue_style( 'wpm-core-tips', plugins_url( 'templates/libs/tips/tips.css', __FILE__ ) );
		wp_enqueue_style( 'wpm-core-admin', plugins_url( 'templates/assets/css/admin.css', __FILE__ ) );

		// Register Scripts
		wp_enqueue_script( 'wpm-font-awesome', plugins_url( 'templates/libs/font-awesome/scripts/all.min.js', __FILE__ ) );
		wp_enqueue_script( 'wpm-core-tips', plugins_url( 'templates/libs/tips/tips.js', __FILE__ ) );
		wp_enqueue_script( 'wpm-core-admin', plugins_url( 'templates/assets/js/admin.js', __FILE__ ) );
		wp_localize_script( 'wpm-core-admin', 'admin', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'ajax_nonce' )
		) );
	}

	/**
	 * Include Scripts And Styles on FrontEnd
	 */
	public function include_scripts_and_styles() {
		// Register styles
		wp_enqueue_style( 'wpm-font-awesome', plugins_url( 'templates/libs/font-awesome/scripts/all.min.css', __FILE__ ) );
		wp_enqueue_style( 'wpm-core', plugins_url( 'templates/assets/css/frontend.css', __FILE__ ), false, '1.0.0', 'all' );

		// Register scripts
		wp_enqueue_script( 'wpm-font-awesome', plugins_url( 'templates/libs/font-awesome/scripts/all.min.js', __FILE__ ), array( 'jquery' ), '1.0.2', 'all' );
		wp_enqueue_script( 'wpm-core', plugins_url( 'templates/assets/js/frontend.js', __FILE__ ), array( 'jquery' ), '1.0.2', 'all' );
		wp_localize_script( 'wpm-core', 'admin', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'ajax_nonce' )
		) );
	}

	/**
	 * Add Settings to Admin Menu
	 */
	public function register_menu() {
		add_menu_page( 'WPM Cookie Coupons', 'WPM Cookie Coupons', 'edit_others_posts', 'wpm_cookie_promo_settings' );
		add_submenu_page( 'wpm_cookie_promo_settings', 'WPM Cookie Coupons', 'WPM Cookie Coupons', 'manage_options', 'wpm_cookie_promo_settings', function () {
			global $wp_version, $wpdb;

			// Get Saved Settings
			$settings = $this->settings;

			include 'templates/admin/settings.php';
		} );
	}
}

new WPM_CookiePromocodes();

