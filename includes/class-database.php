<?php
/**
 * Database handler for STC Product Enquiry.
 *
 * @package STC_Product_Enquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the custom enquiries table and all related CRUD operations.
 *
 * @since 1.0.0
 */
class STC_PE_Database {

	/**
	 * Unprefixed table slug.
	 *
	 * @var string
	 */
	const TABLE = 'stc_product_enquiries';

	/**
	 * Get the fully prefixed table name.
	 *
	 * @return string
	 */
	public function get_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Create the custom database table.
	 *
	 * @return void
	 */
	public function create_table(): void {
		global $wpdb;

		$table_name      = $this->get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			enquiry_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			product_name VARCHAR(255) NOT NULL DEFAULT '',
			sku VARCHAR(100) NOT NULL DEFAULT '',
			product_url VARCHAR(255) NOT NULL DEFAULT '',
			customer_name VARCHAR(190) NOT NULL DEFAULT '',
			mobile VARCHAR(40) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (enquiry_id),
			KEY product_id (product_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'stc_pe_db_version', STC_PE_VERSION );
	}

	/**
	 * Create or upgrade the table when the stored version is out of date.
	 *
	 * @return void
	 */
	public function maybe_upgrade(): void {
		$installed = get_option( 'stc_pe_db_version', '0' );

		if ( version_compare( (string) $installed, STC_PE_VERSION, '<' ) || ! $this->table_exists() ) {
			$this->create_table();
		}
	}

	/**
	 * Check whether the table exists.
	 *
	 * @return bool
	 */
	public function table_exists(): bool {
		global $wpdb;

		$table_name = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		return $found === $table_name;
	}

	/**
	 * Insert an enquiry row.
	 *
	 * @param array<string,mixed> $data Sanitized enquiry data.
	 * @return int|false Inserted enquiry ID or false on failure.
	 */
	public function insert( array $data ): int|false {
		global $wpdb;

		$defaults = array(
			'product_id'    => 0,
			'product_name'  => '',
			'sku'           => '',
			'product_url'   => '',
			'customer_name' => '',
			'mobile'        => '',
			'created_at'    => current_time( 'mysql' ),
		);

		$row = wp_parse_args( $data, $defaults );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$this->get_table_name(),
			array(
				'product_id'    => (int) $row['product_id'],
				'product_name'  => (string) $row['product_name'],
				'sku'           => (string) $row['sku'],
				'product_url'   => (string) $row['product_url'],
				'customer_name' => (string) $row['customer_name'],
				'mobile'        => (string) $row['mobile'],
				'created_at'    => (string) $row['created_at'],
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get a single enquiry by ID.
	 *
	 * @param int $enquiry_id Enquiry ID.
	 * @return object|null
	 */
	public function get( int $enquiry_id ): ?object {
		global $wpdb;

		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE enquiry_id = %d", $enquiry_id ) );

		return $row ?: null;
	}

	/**
	 * Delete an enquiry by ID.
	 *
	 * @param int $enquiry_id Enquiry ID.
	 * @return bool
	 */
	public function delete( int $enquiry_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->delete(
			$this->get_table_name(),
			array( 'enquiry_id' => $enquiry_id ),
			array( '%d' )
		);

		return (bool) $result;
	}

	/**
	 * Delete multiple enquiries by IDs.
	 *
	 * @param int[] $ids Enquiry IDs.
	 * @return int Number of rows deleted.
	 */
	public function delete_many( array $ids ): int {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', $ids ) );

		if ( empty( $ids ) ) {
			return 0;
		}

		$table        = $this->get_table_name();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE enquiry_id IN ({$placeholders})", ...$ids ) );

		return (int) $deleted;
	}

	/**
	 * Query enquiries with optional search, date filter, ordering and pagination.
	 *
	 * @param array<string,mixed> $args Query arguments.
	 * @return object[] Array of enquiry rows.
	 */
	public function query( array $args = array() ): array {
		global $wpdb;

		$args = $this->parse_query_args( $args );

		$table = $this->get_table_name();

		[ $where_sql, $where_values ] = $this->build_where( $args );

		$orderby = $this->sanitize_orderby( $args['orderby'] );
		$order   = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';

		$limit  = (int) $args['per_page'];
		$offset = ( max( 1, (int) $args['paged'] ) - 1 ) * $limit;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$sql    = "SELECT * FROM {$table} {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$values = array_merge( $where_values, array( $limit, $offset ) );

		$prepared = $wpdb->prepare( $sql, ...$values );
		$results  = $wpdb->get_results( $prepared );
		// phpcs:enable

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Count enquiries matching the supplied filters.
	 *
	 * @param array<string,mixed> $args Query arguments.
	 * @return int
	 */
	public function count( array $args = array() ): int {
		global $wpdb;

		$args  = $this->parse_query_args( $args );
		$table = $this->get_table_name();

		[ $where_sql, $where_values ] = $this->build_where( $args );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";

		if ( ! empty( $where_values ) ) {
			$sql = $wpdb->prepare( $sql, ...$where_values );
		}

		$total = $wpdb->get_var( $sql );
		// phpcs:enable

		return (int) $total;
	}

	/**
	 * Get all rows matching filters for export (no pagination).
	 *
	 * @param array<string,mixed> $args Query arguments.
	 * @return object[]
	 */
	public function get_for_export( array $args = array() ): array {
		global $wpdb;

		$args  = $this->parse_query_args( $args );
		$table = $this->get_table_name();

		[ $where_sql, $where_values ] = $this->build_where( $args );

		$orderby = $this->sanitize_orderby( $args['orderby'] );
		$order   = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$sql = "SELECT * FROM {$table} {$where_sql} ORDER BY {$orderby} {$order}";

		if ( ! empty( $where_values ) ) {
			$sql = $wpdb->prepare( $sql, ...$where_values );
		}

		$results = $wpdb->get_results( $sql );
		// phpcs:enable

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Normalize query arguments.
	 *
	 * @param array<string,mixed> $args Raw arguments.
	 * @return array<string,mixed>
	 */
	private function parse_query_args( array $args ): array {
		return wp_parse_args(
			$args,
			array(
				'search'    => '',
				'date_from' => '',
				'date_to'   => '',
				'orderby'   => 'enquiry_id',
				'order'     => 'DESC',
				'paged'     => 1,
				'per_page'  => 20,
			)
		);
	}

	/**
	 * Build a WHERE clause and its bound values from filter arguments.
	 *
	 * @param array<string,mixed> $args Parsed query args.
	 * @return array{0:string,1:array<int,mixed>}
	 */
	private function build_where( array $args ): array {
		global $wpdb;

		$clauses = array();
		$values  = array();

		$search = trim( (string) $args['search'] );
		if ( '' !== $search ) {
			$like      = '%' . $wpdb->esc_like( $search ) . '%';
			$clauses[] = '(product_name LIKE %s OR sku LIKE %s OR customer_name LIKE %s OR mobile LIKE %s)';
			array_push( $values, $like, $like, $like, $like );
		}

		$date_from = trim( (string) $args['date_from'] );
		if ( '' !== $date_from ) {
			$clauses[] = 'created_at >= %s';
			$values[]  = $date_from . ' 00:00:00';
		}

		$date_to = trim( (string) $args['date_to'] );
		if ( '' !== $date_to ) {
			$clauses[] = 'created_at <= %s';
			$values[]  = $date_to . ' 23:59:59';
		}

		$where_sql = empty( $clauses ) ? '' : 'WHERE ' . implode( ' AND ', $clauses );

		return array( $where_sql, $values );
	}

	/**
	 * Whitelist the orderby column.
	 *
	 * @param string $orderby Requested column.
	 * @return string
	 */
	private function sanitize_orderby( string $orderby ): string {
		$allowed = array( 'enquiry_id', 'product_name', 'sku', 'customer_name', 'mobile', 'created_at' );

		return in_array( $orderby, $allowed, true ) ? $orderby : 'enquiry_id';
	}
}
