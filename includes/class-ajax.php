<?php
/**
 * AJAX handler for STC Product Enquiry.
 *
 * @package STC_Product_Enquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STC_PE_Ajax
 *
 * Handles the enquiry form submission via WordPress AJAX with nonce
 * validation, sanitization and spam protection.
 *
 * @since 1.0.0
 */
class STC_PE_Ajax {

	/**
	 * Database handler.
	 *
	 * @var STC_PE_Database
	 */
	private STC_PE_Database $database;

	/**
	 * Email handler.
	 *
	 * @var STC_PE_Email
	 */
	private STC_PE_Email $email;

	/**
	 * Constructor.
	 *
	 * @param STC_PE_Database $database Database handler.
	 * @param STC_PE_Email    $email    Email handler.
	 */
	public function __construct( STC_PE_Database $database, STC_PE_Email $email ) {
		$this->database = $database;
		$this->email    = $email;

		add_action( 'wp_ajax_stc_pe_submit_enquiry', array( $this, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_stc_pe_submit_enquiry', array( $this, 'handle_submit' ) );
	}

	/**
	 * Handle the enquiry submission.
	 *
	 * @return void
	 */
	public function handle_submit(): void {
		// 1. Nonce validation.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'stc_pe_submit' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'stc-product-enquiry' ) ),
				403
			);
		}

		// 2. Honeypot anti-spam check.
		$honeypot = isset( $_POST['stc_pe_website'] ) ? trim( (string) wp_unslash( $_POST['stc_pe_website'] ) ) : '';
		if ( '' !== $honeypot ) {
			// Silently accept to avoid tipping off bots, but do not store.
			wp_send_json_success(
				array( 'message' => __( 'Thank you! Your enquiry has been submitted successfully.', 'stc-product-enquiry' ) )
			);
		}

		// 3. Sanitize input.
		$customer_name = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
		$mobile_raw    = isset( $_POST['mobile'] ) ? sanitize_text_field( wp_unslash( $_POST['mobile'] ) ) : '';
		$product_id    = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$product_name  = isset( $_POST['product_name'] ) ? sanitize_text_field( wp_unslash( $_POST['product_name'] ) ) : '';
		$product_sku   = isset( $_POST['product_sku'] ) ? sanitize_text_field( wp_unslash( $_POST['product_sku'] ) ) : '';
		$product_url   = isset( $_POST['product_url'] ) ? esc_url_raw( wp_unslash( $_POST['product_url'] ) ) : '';

		// Normalize the mobile number (keep digits, plus, spaces, dashes, parentheses).
		$mobile = preg_replace( '/[^0-9+\-\s()]/', '', $mobile_raw );
		$mobile = trim( (string) $mobile );

		// 4. Validation.
		$errors = array();

		if ( '' === $customer_name ) {
			$errors[] = __( 'Please enter your name.', 'stc-product-enquiry' );
		}

		$digit_count = strlen( preg_replace( '/\D/', '', $mobile ) );
		if ( '' === $mobile || $digit_count < 7 || $digit_count > 15 ) {
			$errors[] = __( 'Please enter a valid mobile number.', 'stc-product-enquiry' );
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error(
				array(
					'message' => implode( ' ', $errors ),
					'errors'  => $errors,
				),
				400
			);
		}

		// 5. Enrich product data from the WooCommerce product object when possible.
		if ( $product_id > 0 && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );

			if ( $product instanceof WC_Product ) {
				if ( '' === $product_name ) {
					$product_name = $product->get_name();
				}
				if ( '' === $product_sku ) {
					$product_sku = (string) $product->get_sku();
				}
				if ( '' === $product_url ) {
					$product_url = (string) get_permalink( $product_id );
				}
			}
		}

		$created_at = current_time( 'mysql' );

		$record = array(
			'product_id'    => $product_id,
			'product_name'  => $product_name,
			'sku'           => $product_sku,
			'product_url'   => $product_url,
			'customer_name' => $customer_name,
			'mobile'        => $mobile,
			'created_at'    => $created_at,
		);

		// 6. Store in the database.
		$enquiry_id = $this->database->insert( $record );

		if ( false === $enquiry_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Unable to save your enquiry. Please try again.', 'stc-product-enquiry' ) ),
				500
			);
		}

		// 7. Send email notification (non-fatal if it fails).
		$this->email->send_notification( $record );

		/**
		 * Fires after an enquiry has been stored.
		 *
		 * @param int   $enquiry_id Inserted enquiry ID.
		 * @param array $record     Enquiry data.
		 */
		do_action( 'stc_pe_enquiry_saved', $enquiry_id, $record );

		// 8. Success response.
		wp_send_json_success(
			array(
				'message'    => __( 'Thank you! Your enquiry has been submitted successfully.', 'stc-product-enquiry' ),
				'enquiry_id' => $enquiry_id,
			)
		);
	}
}
