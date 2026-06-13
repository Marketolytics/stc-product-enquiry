<?php
/**
 * Frontend handler for STC Product Enquiry.
 *
 * Replaces WooCommerce "Add to Cart" / "Add to Quote" buttons with an
 * "Enquire Now" button across loops, archives, single products, Elementor
 * widgets and Essential Addons (EA) Woo Product Gallery.
 *
 * @package STC_Product_Enquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STC_PE_Frontend
 *
 * @since 1.0.0
 */
class STC_PE_Frontend {

	/**
	 * Constructor: register hooks.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Replace the loop (archive / category / search / custom loops) add-to-cart button.
		add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'replace_loop_button' ), 99, 3 );

		// Replace single product add-to-cart area.
		add_action( 'init', array( $this, 'swap_single_add_to_cart' ) );

		// Catch-all: rewrite any remaining add-to-cart / add-to-quote markup in rendered HTML
		// (covers Elementor, EA Woo Product Gallery and other page builders).
		add_action( 'template_redirect', array( $this, 'maybe_start_buffer' ) );
	}

	/**
	 * Enqueue frontend CSS and JS.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		wp_enqueue_style(
			'stc-pe-frontend',
			STC_PE_URL . 'assets/css/frontend.css',
			array(),
			STC_PE_VERSION
		);

		wp_enqueue_script(
			'stc-pe-frontend',
			STC_PE_URL . 'assets/js/frontend.js',
			array(),
			STC_PE_VERSION,
			true
		);

		wp_localize_script(
			'stc-pe-frontend',
			'STC_PE',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'stc_pe_submit' ),
				'action'       => 'stc_pe_submit_enquiry',
				'buttonLabel'  => $this->get_button_label(),
				'i18n'         => array(
					'sending'        => __( 'Sending…', 'stc-product-enquiry' ),
					'success'        => __( 'Thank you! Your enquiry has been submitted successfully.', 'stc-product-enquiry' ),
					'error'          => __( 'Something went wrong. Please try again.', 'stc-product-enquiry' ),
					'requiredName'   => __( 'Please enter your name.', 'stc-product-enquiry' ),
					'requiredMobile' => __( 'Please enter a valid mobile number.', 'stc-product-enquiry' ),
				),
			)
		);
	}

	/**
	 * The button label, filterable.
	 *
	 * @return string
	 */
	public function get_button_label(): string {
		$label = get_option( 'stc_pe_button_label', __( 'Enquire Now', 'stc-product-enquiry' ) );

		if ( ! is_string( $label ) || '' === trim( $label ) ) {
			$label = __( 'Enquire Now', 'stc-product-enquiry' );
		}

		/**
		 * Filter the Enquire Now button label.
		 *
		 * @param string $label Default label.
		 */
		return (string) apply_filters( 'stc_pe_button_label', $label );
	}

	/**
	 * Build an Enquire Now button for a given product.
	 *
	 * @param WC_Product $product Product object.
	 * @return string HTML.
	 */
	public function build_button( $product ): string {
		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		$product_id   = $product->get_id();
		$product_name = $product->get_name();
		$sku          = (string) $product->get_sku();
		$product_url  = get_permalink( $product_id );

		$label = $this->get_button_label();

		return sprintf(
			'<button type="button" class="button stc-pe-enquire-btn" data-stc-pe-open="1" data-product-id="%1$s" data-product-name="%2$s" data-product-sku="%3$s" data-product-url="%4$s">%5$s</button>',
			esc_attr( (string) $product_id ),
			esc_attr( $product_name ),
			esc_attr( $sku ),
			esc_url( $product_url ),
			esc_html( $label )
		);
	}

	/**
	 * Replace the WooCommerce loop add-to-cart link.
	 *
	 * @param string     $html    Default button HTML.
	 * @param WC_Product $product Product object.
	 * @param array      $args    Button args.
	 * @return string
	 */
	public function replace_loop_button( $html, $product, $args = array() ): string {
		unset( $html, $args );

		return $this->build_button( $product );
	}

	/**
	 * Swap the single product add-to-cart template with the Enquire Now button.
	 *
	 * @return void
	 */
	public function swap_single_add_to_cart(): void {
		// Remove the default add-to-cart forms for every product type.
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );

		// Render our button in its place.
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_single_button' ), 30 );
	}

	/**
	 * Render the Enquire Now button on the single product page.
	 *
	 * @return void
	 */
	public function render_single_button(): void {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			$product = wc_get_product( get_the_ID() );
		}

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		echo '<div class="stc-pe-single-wrap">';
		// Button HTML is fully escaped inside build_button().
		echo $this->build_button( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
	}

	/**
	 * Start output buffering on product-facing pages so we can rewrite
	 * builder-generated buttons (Elementor, EA Woo Product Gallery, etc.).
	 *
	 * @return void
	 */
	public function maybe_start_buffer(): void {
		if ( is_admin() ) {
			return;
		}

		/**
		 * Allow disabling the HTML buffer rewriting (the catch-all layer).
		 *
		 * @param bool $enabled Whether to enable buffering.
		 */
		$enabled = (bool) apply_filters( 'stc_pe_enable_buffer', true );

		if ( ! $enabled ) {
			return;
		}

		// Only buffer where WooCommerce products are likely to appear.
		if ( $this->is_product_context() ) {
			ob_start( array( $this, 'filter_html_output' ) );
		}
	}

	/**
	 * Determine whether the current request is a product-facing context.
	 *
	 * @return bool
	 */
	private function is_product_context(): bool {
		if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
			return true;
		}

		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return true;
		}

		if ( function_exists( 'is_product_category' ) && is_product_category() ) {
			return true;
		}

		if ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
			return true;
		}

		if ( function_exists( 'is_product' ) && is_product() ) {
			return true;
		}

		// Search results and generic pages may include product loops via builders.
		if ( is_search() || is_page() || is_front_page() || is_home() ) {
			return true;
		}

		return false;
	}

	/**
	 * Rewrite the buffered HTML output, replacing add-to-cart / add-to-quote
	 * buttons with Enquire Now buttons.
	 *
	 * @param string $html Buffered HTML.
	 * @return string
	 */
	public function filter_html_output( $html ): string {
		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			return $html;
		}

		$label = $this->get_button_label();

		// 1. Hide WooCommerce / quote add-to-cart anchors and buttons that we cannot
		//    safely convert (we hide them with a class; our JS/CSS removes them).
		//    We target common class signatures used by WooCommerce, EA and quote plugins.
		$patterns = array(
			// <a ... class="...add_to_cart_button..."> ... </a>
			'/<a\b([^>]*class="[^"]*\b(?:add_to_cart_button|product_type_simple|add-to-cart|ajax_add_to_cart)\b[^"]*"[^>]*)>(.*?)<\/a>/is',
			// Quote buttons (YITH / generic): class contains add-to-quote / add_to_quote / yith-ywraq
			'/<a\b([^>]*class="[^"]*\b(?:add-to-quote|add_to_quote|yith-ywraq-add-button|yith_ywraq_add_item_browse)\b[^"]*"[^>]*)>(.*?)<\/a>/is',
			// <button ... class="...single_add_to_cart_button..."> ... </button>
			'/<button\b([^>]*class="[^"]*\b(?:single_add_to_cart_button|add_to_cart_button|add-to-quote|add_to_quote)\b[^"]*"[^>]*)>(.*?)<\/button>/is',
		);

		foreach ( $patterns as $pattern ) {
			$html = preg_replace_callback(
				$pattern,
				function ( $matches ) use ( $label ) {
					return $this->convert_matched_button( $matches, $label );
				},
				$html
			);
		}

		return is_string( $html ) ? $html : '';
	}

	/**
	 * Convert a matched add-to-cart / add-to-quote button into an Enquire Now button.
	 *
	 * Attempts to read the product ID from the original markup so the popup
	 * always knows which product triggered it.
	 *
	 * @param array  $matches Regex matches ( [0]=full, [1]=attributes, [2]=inner ).
	 * @param string $label   Button label.
	 * @return string
	 */
	private function convert_matched_button( array $matches, string $label ): string {
		$attributes = isset( $matches[1] ) ? (string) $matches[1] : '';

		$product_id = $this->extract_product_id( $attributes );

		// If we already converted this (idempotency guard), leave it alone.
		if ( false !== strpos( $attributes, 'stc-pe-enquire-btn' ) ) {
			return $matches[0];
		}

		$product = $product_id ? wc_get_product( $product_id ) : null;

		if ( $product instanceof WC_Product ) {
			return $this->build_button( $product );
		}

		// Fallback: render an enquire button that resolves product data on the client
		// from the closest product container (handled by frontend.js).
		return sprintf(
			'<button type="button" class="button stc-pe-enquire-btn" data-stc-pe-open="1" data-product-id="%1$s">%2$s</button>',
			esc_attr( (string) $product_id ),
			esc_html( $label )
		);
	}

	/**
	 * Extract a product ID from an element's attribute string.
	 *
	 * @param string $attributes Attribute string.
	 * @return int Product ID or 0.
	 */
	private function extract_product_id( string $attributes ): int {
		// data-product_id="123" (WooCommerce) or data-product-id="123".
		if ( preg_match( '/data-product[_-]id="(\d+)"/i', $attributes, $m ) ) {
			return (int) $m[1];
		}

		// add-to-cart query arg: ?add-to-cart=123 inside href.
		if ( preg_match( '/[?&]add-to-cart=(\d+)/i', $attributes, $m ) ) {
			return (int) $m[1];
		}

		// data-quantity / data-product references used by some quote plugins.
		if ( preg_match( '/data-product="(\d+)"/i', $attributes, $m ) ) {
			return (int) $m[1];
		}

		return 0;
	}
}
