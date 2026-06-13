<?php
/**
 * Popup / modal markup for STC Product Enquiry.
 *
 * @package STC_Product_Enquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STC_PE_Popup
 *
 * Renders the enquiry modal in the site footer. The modal markup is shared
 * for all products; product-specific data is injected via JavaScript into the
 * hidden fields when the user clicks an "Enquire Now" button.
 *
 * @since 1.0.0
 */
class STC_PE_Popup {

	/**
	 * Constructor: register hooks.
	 */
	public function __construct() {
		add_action( 'wp_footer', array( $this, 'render_modal' ), 99 );
	}

	/**
	 * Render the modal markup.
	 *
	 * @return void
	 */
	public function render_modal(): void {
		// Only render on the frontend.
		if ( is_admin() ) {
			return;
		}

		$heading  = apply_filters( 'stc_pe_popup_heading', __( 'Product Enquiry', 'stc-product-enquiry' ) );
		$subtext  = apply_filters( 'stc_pe_popup_subtext', __( 'Fill in your details and we will get back to you shortly.', 'stc-product-enquiry' ) );
		$submit   = apply_filters( 'stc_pe_popup_submit_label', __( 'Submit Enquiry', 'stc-product-enquiry' ) );
		?>
		<div class="stc-pe-modal-overlay" id="stc-pe-modal" role="dialog" aria-modal="true" aria-labelledby="stc-pe-modal-title" hidden>
			<div class="stc-pe-modal" role="document">
				<button type="button" class="stc-pe-modal-close" data-stc-pe-close="1" aria-label="<?php esc_attr_e( 'Close', 'stc-product-enquiry' ); ?>">&times;</button>

				<div class="stc-pe-modal-header">
					<h2 class="stc-pe-modal-title" id="stc-pe-modal-title"><?php echo esc_html( $heading ); ?></h2>
					<p class="stc-pe-modal-subtext"><?php echo esc_html( $subtext ); ?></p>
					<p class="stc-pe-modal-product" data-stc-pe-product-label hidden></p>
				</div>

				<form class="stc-pe-form" id="stc-pe-form" novalidate>
					<?php wp_nonce_field( 'stc_pe_submit', 'stc_pe_nonce' ); ?>

					<!-- Hidden product fields -->
					<input type="hidden" name="product_id" value="" />
					<input type="hidden" name="product_name" value="" />
					<input type="hidden" name="product_sku" value="" />
					<input type="hidden" name="product_url" value="" />

					<!-- Honeypot anti-spam field (should remain empty) -->
					<div class="stc-pe-hp" aria-hidden="true">
						<label for="stc-pe-website"><?php esc_html_e( 'Leave this field empty', 'stc-product-enquiry' ); ?></label>
						<input type="text" id="stc-pe-website" name="stc_pe_website" tabindex="-1" autocomplete="off" value="" />
					</div>

					<div class="stc-pe-field">
						<label for="stc-pe-name"><?php esc_html_e( 'Name', 'stc-product-enquiry' ); ?> <span class="stc-pe-required">*</span></label>
						<input type="text" id="stc-pe-name" name="customer_name" required autocomplete="name" placeholder="<?php esc_attr_e( 'Your full name', 'stc-product-enquiry' ); ?>" />
					</div>

					<div class="stc-pe-field">
						<label for="stc-pe-mobile"><?php esc_html_e( 'Mobile Number', 'stc-product-enquiry' ); ?> <span class="stc-pe-required">*</span></label>
						<input type="tel" id="stc-pe-mobile" name="mobile" required autocomplete="tel" inputmode="tel" placeholder="<?php esc_attr_e( 'e.g. +91 98765 43210', 'stc-product-enquiry' ); ?>" />
					</div>

					<div class="stc-pe-message" id="stc-pe-message" role="alert" aria-live="polite" hidden></div>

					<div class="stc-pe-actions">
						<button type="submit" class="stc-pe-submit"><?php echo esc_html( $submit ); ?></button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}
}
