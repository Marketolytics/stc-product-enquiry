<?php
/**
 * Admin dashboard for STC Product Enquiry.
 *
 * @package STC_Product_Enquiry
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table for displaying enquiries.
 *
 * @since 1.0.0
 */
class STC_PE_List_Table extends WP_List_Table {

	/**
	 * Database handler.
	 *
	 * @var STC_PE_Database
	 */
	private STC_PE_Database $database;

	/**
	 * Constructor.
	 *
	 * @param STC_PE_Database $database Database handler.
	 */
	public function __construct( STC_PE_Database $database ) {
		$this->database = $database;

		parent::__construct(
			array(
				'singular' => 'enquiry',
				'plural'   => 'enquiries',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Define table columns.
	 *
	 * @return array<string,string>
	 */
	public function get_columns(): array {
		return array(
			'cb'            => '<input type="checkbox" />',
			'enquiry_id'    => __( 'ID', 'stc-product-enquiry' ),
			'product_name'  => __( 'Product Name', 'stc-product-enquiry' ),
			'sku'           => __( 'SKU', 'stc-product-enquiry' ),
			'customer_name' => __( 'Customer Name', 'stc-product-enquiry' ),
			'mobile'        => __( 'Mobile Number', 'stc-product-enquiry' ),
			'created_at'    => __( 'Date', 'stc-product-enquiry' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string,array{0:string,1:bool}>
	 */
	protected function get_sortable_columns(): array {
		return array(
			'enquiry_id'    => array( 'enquiry_id', true ),
			'product_name'  => array( 'product_name', false ),
			'sku'           => array( 'sku', false ),
			'customer_name' => array( 'customer_name', false ),
			'created_at'    => array( 'created_at', false ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string,string>
	 */
	protected function get_bulk_actions(): array {
		return array(
			'bulk-delete' => __( 'Delete', 'stc-product-enquiry' ),
		);
	}

	/**
	 * Checkbox column.
	 *
	 * @param object $item Row item.
	 * @return string
	 */
	protected function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="enquiry_ids[]" value="%d" />', (int) $item->enquiry_id );
	}

	/**
	 * Default column renderer.
	 *
	 * @param object $item        Row item.
	 * @param string $column_name Column key.
	 * @return string
	 */
	protected function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'enquiry_id':
				return (string) (int) $item->enquiry_id;
			case 'sku':
				return $item->sku ? esc_html( $item->sku ) : '&mdash;';
			case 'customer_name':
				return esc_html( $item->customer_name );
			case 'mobile':
				return esc_html( $item->mobile );
			case 'created_at':
				return esc_html(
					mysql2date(
						get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
						$item->created_at
					)
				);
			default:
				return '';
		}
	}

	/**
	 * Product Name column with row actions.
	 *
	 * @param object $item Row item.
	 * @return string
	 */
	protected function column_product_name( $item ): string {
		$name = $item->product_name ? esc_html( $item->product_name ) : '&mdash;';

		if ( $item->product_url ) {
			$name = '<a href="' . esc_url( $item->product_url ) . '" target="_blank" rel="noopener noreferrer">' . $name . '</a>';
		}

		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'    => 'stc-product-enquiries',
					'action'  => 'delete',
					'enquiry' => (int) $item->enquiry_id,
				),
				admin_url( 'admin.php' )
			),
			'stc_pe_delete_' . (int) $item->enquiry_id
		);

		$actions = array(
			'delete' => sprintf(
				'<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
				esc_url( $delete_url ),
				esc_js( __( 'Are you sure you want to delete this enquiry?', 'stc-product-enquiry' ) ),
				esc_html__( 'Delete', 'stc-product-enquiry' )
			),
		);

		return $name . $this->row_actions( $actions );
	}

	/**
	 * Message shown when no items exist.
	 *
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No enquiries found.', 'stc-product-enquiry' );
	}

	/**
	 * Prepare items for display.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$per_page     = 20;
		$current_page = $this->get_pagenum();

		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$args = $this->get_request_args();

		$args['paged']    = $current_page;
		$args['per_page'] = $per_page;

		$total_items = $this->database->count( $args );
		$this->items = $this->database->query( $args );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Read and sanitize request arguments (search, filter, ordering).
	 *
	 * @return array<string,mixed>
	 */
	public function get_request_args(): array {
		// Reading list parameters; nonce is verified for destructive actions only.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$search    = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$date_from = isset( $_REQUEST['date_from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_from'] ) ) : '';
		$date_to   = isset( $_REQUEST['date_to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_to'] ) ) : '';
		$orderby   = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'enquiry_id';
		$order     = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'desc';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Validate dates (Y-m-d) and discard anything malformed.
		$date_from = $this->validate_date( $date_from );
		$date_to   = $this->validate_date( $date_to );

		return array(
			'search'    => $search,
			'date_from' => $date_from,
			'date_to'   => $date_to,
			'orderby'   => $orderby,
			'order'     => $order,
		);
	}

	/**
	 * Validate a Y-m-d date string.
	 *
	 * @param string $date Date string.
	 * @return string Valid date or empty string.
	 */
	private function validate_date( string $date ): string {
		if ( '' === $date ) {
			return '';
		}

		$d = DateTime::createFromFormat( 'Y-m-d', $date );

		return ( $d && $d->format( 'Y-m-d' ) === $date ) ? $date : '';
	}

	/**
	 * Render extra filter controls above the table.
	 *
	 * @param string $which Top or bottom.
	 * @return void
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		$args      = $this->get_request_args();
		$date_from = $args['date_from'];
		$date_to   = $args['date_to'];
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="stc-pe-date-from"><?php esc_html_e( 'From date', 'stc-product-enquiry' ); ?></label>
			<input type="date" id="stc-pe-date-from" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" />
			<label class="screen-reader-text" for="stc-pe-date-to"><?php esc_html_e( 'To date', 'stc-product-enquiry' ); ?></label>
			<input type="date" id="stc-pe-date-to" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" />
			<?php submit_button( __( 'Filter', 'stc-product-enquiry' ), 'secondary', 'stc_pe_filter', false ); ?>
		</div>
		<?php
	}
}

/**
 * Admin controller: menu, page rendering, actions and CSV export.
 *
 * @since 1.0.0
 */
class STC_PE_Admin {

	/**
	 * Database handler.
	 *
	 * @var STC_PE_Database
	 */
	private STC_PE_Database $database;

	/**
	 * List table instance.
	 *
	 * @var STC_PE_List_Table|null
	 */
	private ?STC_PE_List_Table $list_table = null;

	/**
	 * Menu page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'stc-product-enquiries';

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	const SETTINGS_SLUG = 'stc-product-enquiries-settings';

	/**
	 * Constructor.
	 *
	 * @param STC_PE_Database $database Database handler.
	 */
	public function __construct( STC_PE_Database $database ) {
		$this->database = $database;

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Register the admin menu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$hook = add_menu_page(
			__( 'Product Enquiries', 'stc-product-enquiry' ),
			__( 'Product Enquiries', 'stc-product-enquiry' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-email-alt',
			56
		);

		add_action( "load-{$hook}", array( $this, 'add_screen_options' ) );

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'All Enquiries', 'stc-product-enquiry' ),
			__( 'All Enquiries', 'stc-product-enquiry' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Settings', 'stc-product-enquiry' ),
			__( 'Settings', 'stc-product-enquiry' ),
			'manage_woocommerce',
			self::SETTINGS_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings (notification email and button label).
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'stc_pe_settings_group',
			'stc_pe_notification_email',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_email_setting' ),
				'default'           => STC_PE_DEFAULT_EMAIL,
			)
		);

		register_setting(
			'stc_pe_settings_group',
			'stc_pe_button_label',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => __( 'Enquire Now', 'stc-product-enquiry' ),
			)
		);

		add_settings_section(
			'stc_pe_main_section',
			__( 'Enquiry Settings', 'stc-product-enquiry' ),
			'__return_false',
			self::SETTINGS_SLUG
		);

		add_settings_field(
			'stc_pe_notification_email',
			__( 'Notification Email', 'stc-product-enquiry' ),
			array( $this, 'field_notification_email' ),
			self::SETTINGS_SLUG,
			'stc_pe_main_section'
		);

		add_settings_field(
			'stc_pe_button_label',
			__( 'Button Label', 'stc-product-enquiry' ),
			array( $this, 'field_button_label' ),
			self::SETTINGS_SLUG,
			'stc_pe_main_section'
		);
	}

	/**
	 * Sanitize the notification email, falling back to the default if invalid.
	 *
	 * @param string $value Submitted value.
	 * @return string
	 */
	public function sanitize_email_setting( $value ): string {
		$value = sanitize_email( (string) $value );

		if ( ! is_email( $value ) ) {
			add_settings_error(
				'stc_pe_notification_email',
				'invalid_email',
				__( 'Please enter a valid email address. The previous value was kept.', 'stc-product-enquiry' ),
				'error'
			);

			return (string) get_option( 'stc_pe_notification_email', STC_PE_DEFAULT_EMAIL );
		}

		return $value;
	}

	/**
	 * Render the notification email field.
	 *
	 * @return void
	 */
	public function field_notification_email(): void {
		$value = get_option( 'stc_pe_notification_email', STC_PE_DEFAULT_EMAIL );
		printf(
			'<input type="email" class="regular-text" name="stc_pe_notification_email" value="%s" required /><p class="description">%s</p>',
			esc_attr( $value ),
			esc_html__( 'New enquiry notifications will be sent to this address.', 'stc-product-enquiry' )
		);
	}

	/**
	 * Render the button label field.
	 *
	 * @return void
	 */
	public function field_button_label(): void {
		$value = get_option( 'stc_pe_button_label', __( 'Enquire Now', 'stc-product-enquiry' ) );
		printf(
			'<input type="text" class="regular-text" name="stc_pe_button_label" value="%s" /><p class="description">%s</p>',
			esc_attr( $value ),
			esc_html__( 'Text shown on the button that replaces Add to Cart / Add to Quote.', 'stc-product-enquiry' )
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'stc-product-enquiry' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Product Enquiry Settings', 'stc-product-enquiry' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'stc_pe_settings_group' );
				do_settings_sections( self::SETTINGS_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Add screen options.
	 *
	 * @return void
	 */
	public function add_screen_options(): void {
		$this->list_table = new STC_PE_List_Table( $this->database );
	}

	/**
	 * Enqueue admin assets on our page only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ): void {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		$inline_css = '
			.stc-pe-admin-toolbar { margin: 12px 0; }
			.stc-pe-admin-toolbar .button-primary { background:#ff5a00; border-color:#e65100; }
			.stc-pe-admin-toolbar .button-primary:hover { background:#e65100; }
		';
		wp_register_style( 'stc-pe-admin', false, array(), STC_PE_VERSION );
		wp_enqueue_style( 'stc-pe-admin' );
		wp_add_inline_style( 'stc-pe-admin', $inline_css );
	}

	/**
	 * Handle delete and CSV export actions before rendering.
	 *
	 * @return void
	 */
	public function handle_actions(): void {
		if ( ! isset( $_REQUEST['page'] ) || self::PAGE_SLUG !== $_REQUEST['page'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Single delete.
		if ( isset( $_GET['action'], $_GET['enquiry'] ) && 'delete' === $_GET['action'] ) {
			$enquiry_id = absint( wp_unslash( $_GET['enquiry'] ) );
			$nonce      = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

			if ( ! wp_verify_nonce( $nonce, 'stc_pe_delete_' . $enquiry_id ) ) {
				wp_die( esc_html__( 'Security check failed.', 'stc-product-enquiry' ) );
			}

			$this->database->delete( $enquiry_id );
			$this->redirect_with_notice( 'deleted' );
		}

		// Bulk delete.
		$action = $this->current_bulk_action();
		if ( 'bulk-delete' === $action && isset( $_REQUEST['enquiry_ids'] ) ) {
			check_admin_referer( 'bulk-enquiries' );

			$ids = array_map( 'absint', (array) wp_unslash( $_REQUEST['enquiry_ids'] ) );
			$this->database->delete_many( $ids );
			$this->redirect_with_notice( 'bulk-deleted' );
		}

		// CSV export.
		if ( isset( $_REQUEST['stc_pe_export'] ) && '1' === $_REQUEST['stc_pe_export'] ) {
			$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';

			if ( ! wp_verify_nonce( $nonce, 'stc_pe_export' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'stc-product-enquiry' ) );
			}

			$this->export_csv();
		}
	}

	/**
	 * Determine the current bulk action from the request.
	 *
	 * @return string
	 */
	private function current_bulk_action(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$action  = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		$action2 = isset( $_REQUEST['action2'] ) ? sanitize_key( wp_unslash( $_REQUEST['action2'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( '' !== $action && '-1' !== $action ) {
			return $action;
		}

		if ( '' !== $action2 && '-1' !== $action2 ) {
			return $action2;
		}

		return '';
	}

	/**
	 * Redirect back to the list with a notice flag.
	 *
	 * @param string $notice Notice key.
	 * @return void
	 */
	private function redirect_with_notice( string $notice ): void {
		$url = add_query_arg(
			array(
				'page'        => self::PAGE_SLUG,
				'stc_notice'  => $notice,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Export the current (filtered) enquiries as a CSV download.
	 *
	 * @return void
	 */
	private function export_csv(): void {
		$table = $this->list_table ?? new STC_PE_List_Table( $this->database );
		$args  = $table->get_request_args();

		$rows = $this->database->get_for_export( $args );

		$filename = 'product-enquiries-' . gmdate( 'Y-m-d-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );

		// UTF-8 BOM for Excel compatibility.
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		fputcsv(
			$output,
			array(
				__( 'ID', 'stc-product-enquiry' ),
				__( 'Product ID', 'stc-product-enquiry' ),
				__( 'Product Name', 'stc-product-enquiry' ),
				__( 'SKU', 'stc-product-enquiry' ),
				__( 'Product URL', 'stc-product-enquiry' ),
				__( 'Customer Name', 'stc-product-enquiry' ),
				__( 'Mobile Number', 'stc-product-enquiry' ),
				__( 'Date', 'stc-product-enquiry' ),
			)
		);

		foreach ( $rows as $row ) {
			fputcsv(
				$output,
				array(
					$row->enquiry_id,
					$row->product_id,
					$row->product_name,
					$row->sku,
					$row->product_url,
					$row->customer_name,
					$row->mobile,
					$row->created_at,
				)
			);
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Render the admin page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'stc-product-enquiry' ) );
		}

		if ( null === $this->list_table ) {
			$this->list_table = new STC_PE_List_Table( $this->database );
		}

		$this->maybe_render_notices();

		$this->list_table->prepare_items();

		$export_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'          => self::PAGE_SLUG,
					'stc_pe_export' => '1',
				),
				admin_url( 'admin.php' )
			),
			'stc_pe_export'
		);

		// Preserve current search/filter in export link.
		$args = $this->list_table->get_request_args();
		foreach ( array( 's', 'date_from', 'date_to' ) as $key ) {
			$value = '';
			if ( 's' === $key ) {
				$value = $args['search'];
			} elseif ( 'date_from' === $key ) {
				$value = $args['date_from'];
			} elseif ( 'date_to' === $key ) {
				$value = $args['date_to'];
			}
			if ( '' !== $value ) {
				$export_url = add_query_arg( $key, rawurlencode( $value ), $export_url );
			}
		}
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Product Enquiries', 'stc-product-enquiry' ); ?></h1>
			<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'stc-product-enquiry' ); ?></a>
			<hr class="wp-header-end" />

			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<?php
				$this->list_table->search_box( __( 'Search Enquiries', 'stc-product-enquiry' ), 'stc-pe-search' );
				$this->list_table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render admin notices based on the stc_notice query arg.
	 *
	 * @return void
	 */
	private function maybe_render_notices(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice = isset( $_GET['stc_notice'] ) ? sanitize_key( wp_unslash( $_GET['stc_notice'] ) ) : '';

		if ( '' === $notice ) {
			return;
		}

		$messages = array(
			'deleted'      => __( 'Enquiry deleted.', 'stc-product-enquiry' ),
			'bulk-deleted' => __( 'Selected enquiries deleted.', 'stc-product-enquiry' ),
		);

		if ( isset( $messages[ $notice ] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( $messages[ $notice ] )
			);
		}
	}
}
