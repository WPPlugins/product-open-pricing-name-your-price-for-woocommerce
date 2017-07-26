<?php
/**
 * Product Open Pricing for WooCommerce - Core Class
 *
 * @version 1.0.0
 * @since   1.0.0
 * @author  Algoritmika Ltd.
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Alg_WC_Product_Open_Pricing_Core' ) ) :

class Alg_WC_Product_Open_Pricing_Core {

	/**
	 * Constructor.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function __construct() {
		if ( 'yes' === get_option( 'alg_wc_product_open_pricing_enabled', 'yes' ) ) {
			add_filter( 'woocommerce_get_price',                  array( $this, 'get_open_price' ), PHP_INT_MAX, 2 );
			add_filter( 'woocommerce_get_price_html',             array( $this, 'hide_original_price' ), PHP_INT_MAX, 2 );
			add_filter( 'woocommerce_get_variation_price_html',   array( $this, 'hide_original_price' ), PHP_INT_MAX, 2 );
			add_filter( 'woocommerce_is_sold_individually',       array( $this, 'hide_quantity_input_field' ), PHP_INT_MAX, 2 );
			add_filter( 'woocommerce_is_purchasable',             array( $this, 'is_purchasable' ), PHP_INT_MAX, 2 );
			add_filter( 'woocommerce_product_supports',           array( $this, 'disable_add_to_cart_ajax' ), PHP_INT_MAX, 3 );
			add_filter( 'woocommerce_product_add_to_cart_url',    array( $this, 'add_to_cart_url' ), PHP_INT_MAX, 2 );
			add_filter( 'woocommerce_product_add_to_cart_text',   array( $this, 'add_to_cart_text' ), PHP_INT_MAX, 2 );
			add_action( 'woocommerce_before_add_to_cart_button',  array( $this, 'add_open_price_input_field_to_frontend' ), PHP_INT_MAX );
			add_filter( 'woocommerce_add_to_cart_validation',     array( $this, 'validate_open_price_on_add_to_cart' ), PHP_INT_MAX, 2 );
			add_filter( 'woocommerce_add_cart_item_data',         array( $this, 'add_open_price_to_cart_item_data' ), PHP_INT_MAX, 3 );
			add_filter( 'woocommerce_add_cart_item',              array( $this, 'add_open_price_to_cart_item' ), PHP_INT_MAX, 2 );
			add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_open_price_from_session' ), PHP_INT_MAX, 3 );
		}
	}

	/**
	 * is_open_price_product.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function is_open_price_product( $_product ) {
		return ( 'yes' === get_post_meta( $_product->id, '_' . 'alg_wc_product_open_pricing_enabled', true ) ) ? true : false;
	}

	/**
	 * disable_add_to_cart_ajax.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function disable_add_to_cart_ajax( $supports, $feature, $_product ) {
		if ( $this->is_open_price_product( $_product ) && 'ajax_add_to_cart' === $feature ) {
			$supports = false;
		}
		return $supports;
	}

	/**
	 * is_purchasable.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function is_purchasable( $purchasable, $_product ) {
		if ( $this->is_open_price_product( $_product ) ) {
			$purchasable = true;

			// Products must exist of course
			if ( ! $_product->exists() ) {
				$purchasable = false;

			// Other products types need a price to be set
			/* } elseif ( $_product->get_price() === '' ) {
				$purchasable = false; */

			// Check the product is published
			} elseif ( $_product->post->post_status !== 'publish' && ! current_user_can( 'edit_post', $_product->id ) ) {
				$purchasable = false;
			}
		}
		return $purchasable;
	}

	/**
	 * add_to_cart_text.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function add_to_cart_text( $text, $_product ) {
		return ( $this->is_open_price_product( $_product ) ) ? __( 'Read more', 'woocommerce' ) : $text;
	}

	/**
	 * add_to_cart_url.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function add_to_cart_url( $url, $_product ) {
		return ( $this->is_open_price_product( $_product ) ) ? get_permalink( $_product->id ) : $url;
	}

	/**
	 * hide_quantity_input_field.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function hide_quantity_input_field( $return, $_product ) {
		return ( $this->is_open_price_product( $_product ) ) ? true : $return;
	}

	/**
	 * hide_original_price.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function hide_original_price( $price, $_product ) {
		return ( $this->is_open_price_product( $_product ) ) ? '' : $price;
	}

	/**
	 * get_open_price.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function get_open_price( $price, $_product ) {
		return ( $this->is_open_price_product( $_product ) && isset( $_product->alg_open_price ) ) ? $_product->alg_open_price : $price;
	}

	/**
	 * validate_open_price_on_add_to_cart.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function validate_open_price_on_add_to_cart( $passed, $product_id ) {
		$the_product = wc_get_product( $product_id );
		if ( $this->is_open_price_product( $the_product ) ) {
			$min_price = get_post_meta( $product_id, '_' . 'alg_wc_product_open_pricing_min_price', true );
			$max_price = get_post_meta( $product_id, '_' . 'alg_wc_product_open_pricing_max_price', true );
			if ( $min_price > 0 ) {
				if ( ! isset( $_POST['alg_open_price'] ) || '' === $_POST['alg_open_price'] ) {
					wc_add_notice( get_option( 'alg_wc_product_open_pricing_messages_required', __( 'Price is required!', 'product-open-pricing-for-woocommerce' ) ), 'error' );
					return false;
				}
				if ( $_POST['alg_open_price'] < $min_price ) {
					wc_add_notice( get_option( 'alg_wc_product_open_pricing_messages_too_small', __( 'Entered price is too small!', 'product-open-pricing-for-woocommerce' ) ), 'error' );
					return false;
				}
			}
			if ( $max_price > 0 ) {
				if ( isset( $_POST['alg_open_price'] ) && $_POST['alg_open_price'] > $max_price ) {
					wc_add_notice( get_option( 'alg_wc_product_open_pricing_messages_too_big', __( 'Entered price is too big!', 'product-open-pricing-for-woocommerce' ) ), 'error' );
					return false;
				}
			}
		}
		return $passed;
	}

	/**
	 * get_cart_item_open_price_from_session.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function get_cart_item_open_price_from_session( $item, $values, $key ) {
		if ( array_key_exists( 'alg_open_price', $values ) ) {
			$item['data']->alg_open_price = $values['alg_open_price'];
		}
		return $item;
	}

	/**
	 * add_open_price_to_cart_item_data.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function add_open_price_to_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		if ( isset( $_POST['alg_open_price'] ) ) {
			$cart_item_data['alg_open_price'] = $_POST['alg_open_price'];
		}
		return $cart_item_data;
	}

	/**
	 * add_open_price_to_cart_item.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function add_open_price_to_cart_item( $cart_item_data, $cart_item_key ) {
		if ( isset( $cart_item_data['alg_open_price'] ) ) {
			$cart_item_data['data']->alg_open_price = $cart_item_data['alg_open_price'];
		}
		return $cart_item_data;
	}

	/**
	 * add_open_price_input_field_to_frontend.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @todo    set min and max as in product's settings
	 * @todo    step on per product basis
	 */
	function add_open_price_input_field_to_frontend() {
		$the_product = wc_get_product();
		if ( $this->is_open_price_product( $the_product ) ) {
			// Title
			$title = get_option( 'alg_wc_product_open_pricing_label_frontend', __( 'Name Your Price', 'product-open-pricing-for-woocommerce' ) );
			// The field - Value
			$value = ( isset( $_POST['alg_open_price'] ) ) ? $_POST['alg_open_price'] : get_post_meta( $the_product->id, '_' . 'alg_wc_product_open_pricing_default_price', true );
			// The field - Custom attributes
			$custom_attributes = '';
			$default_price_step = 1 / pow( 10, absint( get_option( 'woocommerce_price_num_decimals', 2 ) ) );
			$custom_attributes .= 'step="' . get_option( 'alg_wc_product_open_pricing_price_step', $default_price_step ) . '" ';
			$custom_attributes .= 'min="0" ';
			// The field - Final assembly
			$input_field = '<input '
				. 'type="number" '
				. 'class="text" '
				. 'style="width:75px;text-align:center;" '
				. 'name="alg_open_price" '
				. 'id="alg_open_price" '
				. 'value="' . $value . '" '
				. $custom_attributes . '>';
			// Currency symbol
			$currency_symbol = get_woocommerce_currency_symbol();
			// Output
			echo str_replace(
				array( '%frontend_label%', '%open_price_input%', '%currency_symbol%' ),
				array( $title,             $input_field,         $currency_symbol ),
				get_option( 'alg_wc_product_open_pricing_frontend_template', '<label for="alg_open_price">%frontend_label%</label> %open_price_input% %currency_symbol%' )
			);
		}
	}

}

endif;

return new Alg_WC_Product_Open_Pricing_Core();
