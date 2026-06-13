<?php
/**
 * Email notification handler for STC Product Enquiry.
 *
 * @package STC_Product_Enquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STC_PE_Email
 *
 * @since 1.0.0
 */
class STC_PE_Email {

	/**
	 * Get the notification recipient address.
	 *
	 * @return string
	 */
	public function get_recipient(): string {
		$recipient = get_option( 'stc_pe_notification_email', STC_PE_DEFAULT_EMAIL );

		if ( ! is_email( $recipient ) ) {
			$recipient = STC_PE_DEFAULT_EMAIL;
		}

		/**
		 * Filter the enquiry notification recipient.
		 *
		 * @param string $recipient Email address.
		 */
		return (string) apply_filters( 'stc_pe_notification_email', $recipient );
	}

	/**
	 * Send a notification email for a new enquiry.
	 *
	 * @param array<string,mixed> $data Sanitized enquiry data.
	 * @return bool Whether the email was accepted for delivery.
	 */
	public function send_notification( array $data ): bool {
		$product_name  = isset( $data['product_name'] ) ? (string) $data['product_name'] : '';
		$sku           = isset( $data['sku'] ) ? (string) $data['sku'] : '';
		$product_url   = isset( $data['product_url'] ) ? (string) $data['product_url'] : '';
		$customer_name = isset( $data['customer_name'] ) ? (string) $data['customer_name'] : '';
		$mobile        = isset( $data['mobile'] ) ? (string) $data['mobile'] : '';
		$created_at    = isset( $data['created_at'] ) ? (string) $data['created_at'] : current_time( 'mysql' );

		$recipient = $this->get_recipient();

		/* translators: %s: product name. */
		$subject = sprintf( __( 'New Product Enquiry - %s', 'stc-product-enquiry' ), $product_name );

		$date_display = mysql2date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			$created_at
		);

		$rows = array(
			__( 'Product Name', 'stc-product-enquiry' )  => $product_name,
			__( 'Product SKU', 'stc-product-enquiry' )   => '' !== $sku ? $sku : __( 'N/A', 'stc-product-enquiry' ),
			__( 'Product URL', 'stc-product-enquiry' )   => $product_url,
			__( 'Customer Name', 'stc-product-enquiry' ) => $customer_name,
			__( 'Mobile Number', 'stc-product-enquiry' ) => $mobile,
			__( 'Date & Time', 'stc-product-enquiry' )   => $date_display,
		);

		$message = $this->build_html_body( $subject, $rows );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);

		/**
		 * Filter the email headers.
		 *
		 * @param array $headers Email headers.
		 * @param array $data    Enquiry data.
		 */
		$headers = apply_filters( 'stc_pe_email_headers', $headers, $data );

		/**
		 * Filter the email subject.
		 *
		 * @param string $subject Subject line.
		 * @param array  $data    Enquiry data.
		 */
		$subject = apply_filters( 'stc_pe_email_subject', $subject, $data );

		return (bool) wp_mail( $recipient, $subject, $message, $headers );
	}

	/**
	 * Build an HTML email body.
	 *
	 * @param string                $title Email title.
	 * @param array<string,string>  $rows  Label => value pairs.
	 * @return string
	 */
	private function build_html_body( string $title, array $rows ): string {
		$brand = '#ff5a00';

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="utf-8" />
			<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		</head>
		<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;color:#333;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:24px 0;">
				<tr>
					<td align="center">
						<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,0.08);">
							<tr>
								<td style="background:<?php echo esc_attr( $brand ); ?>;padding:20px 28px;">
									<h1 style="margin:0;font-size:20px;color:#ffffff;"><?php echo esc_html( $title ); ?></h1>
								</td>
							</tr>
							<tr>
								<td style="padding:24px 28px;">
									<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
										<?php foreach ( $rows as $label => $value ) : ?>
											<tr>
												<td style="padding:10px 0;border-bottom:1px solid #eee;font-weight:bold;width:40%;vertical-align:top;color:#555;">
													<?php echo esc_html( $label ); ?>
												</td>
												<td style="padding:10px 0;border-bottom:1px solid #eee;vertical-align:top;">
													<?php echo wp_kses_post( $this->maybe_link( $label, $value ) ); ?>
												</td>
											</tr>
										<?php endforeach; ?>
									</table>
								</td>
							</tr>
							<tr>
								<td style="padding:16px 28px;background:#fafafa;color:#999;font-size:12px;">
									<?php echo esc_html__( 'This enquiry was submitted via STC Product Enquiry.', 'stc-product-enquiry' ); ?>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</body>
		</html>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the URL value as a clickable link.
	 *
	 * @param string $label Field label.
	 * @param string $value Field value.
	 * @return string
	 */
	private function maybe_link( string $label, string $value ): string {
		if ( '' === $value ) {
			return '&mdash;';
		}

		if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return '<a href="' . esc_url( $value ) . '" style="color:#ff5a00;">' . esc_html( $value ) . '</a>';
		}

		return esc_html( $value );
	}
}
