<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin reservations management (list, filters, actions, AJAX).
 */
class Hold_This_Product_Admin_Reservations {
	private $reservations;

	public function __construct( $reservations = null ) {
		$this->reservations = $reservations instanceof Hold_This_Product_Reservations ? $reservations : null;
        add_action( 'wp_ajax_hold_this_product_cancel_admin_reservation', array( $this, 'handle_admin_cancel_reservation' ) );
        add_action( 'wp_ajax_hold_this_product_delete_admin_reservation', array( $this, 'handle_admin_delete_reservation' ) );
        add_action( 'wp_ajax_hold_this_product_approve_reservation', array( $this, 'handle_approve_reservation' ) );
        add_action( 'wp_ajax_hold_this_product_deny_reservation', array( $this, 'handle_deny_reservation' ) );
    }

	private function get_reservations_handler() {
		if ( ! $this->reservations ) {
			$this->reservations = new Hold_This_Product_Reservations();
		}
		return $this->reservations;
	}

    public function enqueue_assets() {
		wp_enqueue_script(
			'hold-this-product-admin-reservations',
			HOLD_THIS_PRODUCT_PLUGIN_URL . 'assets/js/admin-reservations.js',
			array( 'jquery' ),
			HOLD_THIS_PRODUCT_VERSION,
			true
		);
		wp_localize_script(
			'hold-this-product-admin-reservations',
			'holdThisProductReservations',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonces' => array(
					'cancel' => wp_create_nonce( 'hold_this_product_admin_cancel' ),
					'delete' => wp_create_nonce( 'hold_this_product_admin_delete' ),
					'approve' => wp_create_nonce( 'hold_this_product_admin_approve' ),
					'deny' => wp_create_nonce( 'hold_this_product_admin_deny' ),
				),
				'strings' => array(
					'thisProduct' => __( 'this product', 'hold-this-product' ),
					/* translators: 1: customer name, 2: product name. */
					'confirmDelete' => __( 'Are you sure you want to permanently delete the reservation for %1$s on %2$s? This action cannot be undone.', 'hold-this-product' ),
					/* translators: 1: customer name, 2: product name. */
					'confirmApprove' => __( 'Are you sure you want to approve the reservation for %1$s on %2$s?', 'hold-this-product' ),
					/* translators: 1: customer name, 2: product name. */
					'confirmCancel' => __( 'Are you sure you want to cancel the reservation for %1$s on %2$s?', 'hold-this-product' ),
					'denialPrompt' => __( 'Please provide a reason for denying this reservation (optional):', 'hold-this-product' ),
					'deleting' => __( 'Deleting…', 'hold-this-product' ),
					'approving' => __( 'Approving…', 'hold-this-product' ),
					'denying' => __( 'Denying…', 'hold-this-product' ),
					'cancelling' => __( 'Cancelling…', 'hold-this-product' ),
					'delete' => __( 'Delete', 'hold-this-product' ),
					'approve' => __( 'Approve', 'hold-this-product' ),
					'deny' => __( 'Deny', 'hold-this-product' ),
					'cancel' => __( 'Cancel', 'hold-this-product' ),
					'active' => __( 'Active', 'hold-this-product' ),
					'deniedStatus' => __( 'Denied', 'hold-this-product' ),
					'deleted' => __( 'Reservation deleted successfully.', 'hold-this-product' ),
					'approved' => __( 'Reservation approved successfully.', 'hold-this-product' ),
					'denied' => __( 'Reservation denied successfully.', 'hold-this-product' ),
					'missingId' => __( 'Missing reservation ID.', 'hold-this-product' ),
					'requestFailed' => __( 'Request failed. Please try again.', 'hold-this-product' ),
					'errorPrefix' => __( 'Error: ', 'hold-this-product' ),
					/* translators: %d: number of reservations. */
					'reservationCount' => __( '%d reservations', 'hold-this-product' ),
				),
			)
		);
        wp_enqueue_style( 'holdthisproduct-admin-style', HOLD_THIS_PRODUCT_PLUGIN_URL . 'assets/css/admin-style.css', array(), HOLD_THIS_PRODUCT_VERSION );
    }

    public function render_page() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list filters.
		$status_filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';
		$search_query  = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
		$search_type   = isset( $_GET['search_type'] ) ? sanitize_key( wp_unslash( $_GET['search_type'] ) ) : 'email';
		$page          = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$status_filter = in_array( $status_filter, array( 'all', 'pending_approval', 'active', 'expired', 'cancelled', 'fulfilled', 'denied', 'order_cancelled' ), true ) ? $status_filter : 'all';
		$search_type = in_array( $search_type, array( 'email', 'product', 'product_id', 'customer_name' ), true ) ? $search_type : 'email';

		$query = $this->get_filtered_reservations( $status_filter, $search_query, $search_type, $page );
		$reservations = $query->posts;
        $stats        = $this->get_reservations_summary();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Manage Reservations', 'hold-this-product' ); ?></h1>

            <div class="hold-this-product-reservations-stats">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
					<div><strong><?php esc_html_e( 'Pending Approval:', 'hold-this-product' ); ?></strong> <?php echo esc_html( $stats['pending_approval'] ); ?></div>
					<div><strong><?php esc_html_e( 'Active:', 'hold-this-product' ); ?></strong> <?php echo esc_html( $stats['active'] ); ?></div>
					<div><strong><?php esc_html_e( 'Expired:', 'hold-this-product' ); ?></strong> <?php echo esc_html( $stats['expired'] ); ?></div>
					<div><strong><?php esc_html_e( 'Cancelled:', 'hold-this-product' ); ?></strong> <?php echo esc_html( $stats['cancelled'] ); ?></div>
					<div><strong><?php esc_html_e( 'Fulfilled:', 'hold-this-product' ); ?></strong> <?php echo esc_html( $stats['fulfilled'] ); ?></div>
					<div><strong><?php esc_html_e( 'Denied:', 'hold-this-product' ); ?></strong> <?php echo esc_html( $stats['denied'] ); ?></div>
					<div><strong><?php esc_html_e( 'Total:', 'hold-this-product' ); ?></strong> <?php echo esc_html( $stats['total'] ); ?></div>
                </div>
            </div>

            <div class="tablenav top" style="margin: 20px 0;">
                <div class="alignleft actions">
                    <select name="status_filter" id="status-filter">
                        <option value="all" <?php selected( $status_filter, 'all' ); ?>><?php esc_html_e( 'All Statuses', 'hold-this-product' ); ?></option>
                        <option value="pending_approval" <?php selected( $status_filter, 'pending_approval' ); ?>><?php esc_html_e( 'Pending Approval', 'hold-this-product' ); ?></option>
                        <option value="active" <?php selected( $status_filter, 'active' ); ?>><?php esc_html_e( 'Active', 'hold-this-product' ); ?></option>
                        <option value="expired" <?php selected( $status_filter, 'expired' ); ?>><?php esc_html_e( 'Expired', 'hold-this-product' ); ?></option>
                        <option value="cancelled" <?php selected( $status_filter, 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'hold-this-product' ); ?></option>
                        <option value="fulfilled" <?php selected( $status_filter, 'fulfilled' ); ?>><?php esc_html_e( 'Fulfilled', 'hold-this-product' ); ?></option>
                        <option value="denied" <?php selected( $status_filter, 'denied' ); ?>><?php esc_html_e( 'Denied', 'hold-this-product' ); ?></option>
                    </select>

                    <select name="search_type" id="search-type">
                        <option value="email" <?php selected( $search_type, 'email' ); ?>><?php esc_html_e( 'Email', 'hold-this-product' ); ?></option>
                        <option value="product" <?php selected( $search_type, 'product' ); ?>><?php esc_html_e( 'Product Name', 'hold-this-product' ); ?></option>
                        <option value="product_id" <?php selected( $search_type, 'product_id' ); ?>><?php esc_html_e( 'Product ID', 'hold-this-product' ); ?></option>
                        <option value="customer_name" <?php selected( $search_type, 'customer_name' ); ?>><?php esc_html_e( 'Customer Name', 'hold-this-product' ); ?></option>
                    </select>

                    <input type="search" id="reservation-search" placeholder="<?php esc_attr_e( 'Search reservations...', 'hold-this-product' ); ?>" value="<?php echo esc_attr( $search_query ); ?>" style="width: 200px;">

                    <button type="button" class="button" id="filter-reservations"><?php esc_html_e( 'Filter', 'hold-this-product' ); ?></button>
                    <button type="button" class="button" id="clear-filters"><?php esc_html_e( 'Clear', 'hold-this-product' ); ?></button>
                </div>

                <div class="alignright">
					<span class="displaying-num"><?php /* translators: %d: number of reservations. */ printf( esc_html__( '%d reservations', 'hold-this-product' ), esc_html( number_format_i18n( $query->found_posts ) ) ); ?></span>
                </div>
            </div>

            <?php if ( empty( $reservations ) ) : ?>
                <div class="notice notice-info">
                    <p><?php esc_html_e( 'No reservations found matching your criteria.', 'hold-this-product' ); ?></p>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 20%;"><?php esc_html_e( 'Product', 'hold-this-product' ); ?></th>
                            <th style="width: 20%;"><?php esc_html_e( 'Customer', 'hold-this-product' ); ?></th>
                            <th style="width: 12%;"><?php esc_html_e( 'Status', 'hold-this-product' ); ?></th>
                            <th style="width: 12%;"><?php esc_html_e( 'Reserved', 'hold-this-product' ); ?></th>
                            <th style="width: 12%;"><?php esc_html_e( 'Expires', 'hold-this-product' ); ?></th>
                            <th style="width: 12%;"><?php esc_html_e( 'Time Left', 'hold-this-product' ); ?></th>
                            <th style="width: 12%;"><?php esc_html_e( 'Actions', 'hold-this-product' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $reservations as $reservation ) : ?>
                            <?php $this->render_row( $reservation ); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
				<?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( array( 'paged' => '%#%', 'status' => $status_filter, 'search_type' => $search_type, 'search' => $search_query ) ), 'current' => $page, 'total' => max( 1, (int) $query->max_num_pages ) ) ) ); ?>
            <?php endif; ?>
        </div>

        <?php
    }

    public function handle_admin_cancel_reservation() {
        if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'hold-this-product' ), 403 );
        }

        check_ajax_referer( 'hold_this_product_admin_cancel', 'nonce' );

		$reservation_id = isset( $_POST['reservation_id'] ) ? absint( wp_unslash( $_POST['reservation_id'] ) ) : 0;
		if ( ! $reservation_id || 'holdthisproduct_res' !== get_post_type( $reservation_id ) ) {
			wp_send_json_error( __( 'Invalid reservation ID.', 'hold-this-product' ), 400 );
        }

        $status = get_post_meta( $reservation_id, '_hold_this_product_status', true );
        if ( $status !== 'active' ) {
			wp_send_json_error( __( 'Reservation is not active.', 'hold-this-product' ), 409 );
        }

			if ( ! $this->get_reservations_handler()->cancel_reservation( $reservation_id ) ) {
				wp_send_json_error( __( 'The reservation changed before it could be cancelled. Please refresh and try again.', 'hold-this-product' ), 409 );
			}

		update_post_meta( $reservation_id, '_hold_this_product_cancelled_by_admin', time() );
        update_post_meta( $reservation_id, '_hold_this_product_cancelled_by_user', get_current_user_id() );

		wp_send_json_success( __( 'Reservation cancelled successfully.', 'hold-this-product' ) );
    }

    public function handle_admin_delete_reservation() {
        if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'hold-this-product' ), 403 );
        }

        check_ajax_referer( 'hold_this_product_admin_delete', 'nonce' );

		$reservation_id = isset( $_POST['reservation_id'] ) ? absint( wp_unslash( $_POST['reservation_id'] ) ) : 0;
		if ( ! $reservation_id || 'holdthisproduct_res' !== get_post_type( $reservation_id ) ) {
			wp_send_json_error( __( 'Invalid reservation ID.', 'hold-this-product' ), 400 );
        }

        $status = get_post_meta( $reservation_id, '_hold_this_product_status', true );
        if ( $status === 'active' ) {
			wp_send_json_error( __( 'Cannot delete active reservations. Cancel them first.', 'hold-this-product' ), 409 );
        }

        $result = wp_delete_post( $reservation_id, true );
        if ( $result ) {
			wp_send_json_success( __( 'Reservation deleted successfully.', 'hold-this-product' ) );
        }

		wp_send_json_error( __( 'Failed to delete reservation.', 'hold-this-product' ), 500 );
    }

    public function handle_approve_reservation() {
        if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'hold-this-product' ), 403 );
        }

        check_ajax_referer( 'hold_this_product_admin_approve', 'nonce' );

		$reservation_id = isset( $_POST['reservation_id'] ) ? absint( wp_unslash( $_POST['reservation_id'] ) ) : 0;
        if ( ! $reservation_id ) {
			wp_send_json_error( __( 'Invalid reservation ID.', 'hold-this-product' ), 400 );
        }

        $post = get_post( $reservation_id );
        if ( ! $post || $post->post_type !== 'holdthisproduct_res' ) {
			wp_send_json_error( __( 'Invalid reservation.', 'hold-this-product' ), 400 );
        }

		$result = $this->get_reservations_handler()->approve_reservation( $reservation_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        } elseif ( $result ) {
			wp_send_json_success( __( 'Reservation approved successfully.', 'hold-this-product' ) );
        }

		wp_send_json_error( __( 'Failed to approve reservation.', 'hold-this-product' ), 500 );
    }

    public function handle_deny_reservation() {
        if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'hold-this-product' ), 403 );
        }

        check_ajax_referer( 'hold_this_product_admin_deny', 'nonce' );

		$reservation_id = isset( $_POST['reservation_id'] ) ? absint( wp_unslash( $_POST['reservation_id'] ) ) : 0;
		$reason         = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';

        if ( ! $reservation_id ) {
			wp_send_json_error( __( 'Invalid reservation ID.', 'hold-this-product' ), 400 );
        }

        $post = get_post( $reservation_id );
        if ( ! $post || $post->post_type !== 'holdthisproduct_res' ) {
			wp_send_json_error( __( 'Invalid reservation.', 'hold-this-product' ), 400 );
        }

		$result = $this->get_reservations_handler()->deny_reservation( $reservation_id, $reason );

        if ( $result ) {
			wp_send_json_success( __( 'Reservation denied successfully.', 'hold-this-product' ) );
        }

		wp_send_json_error( __( 'Failed to deny reservation.', 'hold-this-product' ), 500 );
    }

	private function get_filtered_reservations( $status_filter = 'all', $search_query = '', $search_type = 'email', $page = 1 ) {
        global $wpdb;

        $meta_query = array();
		$author_ids = null;

        if ( $status_filter !== 'all' ) {
            $meta_query[] = array(
                'key'     => '_hold_this_product_status',
                'value'   => $status_filter,
                'compare' => '=',
            );
        }

        if ( $search_query !== '' ) {
            switch ( $search_type ) {
                case 'email':
                    $meta_query[] = array(
                        'key'     => '_hold_this_product_email',
                        'value'   => $search_query,
                        'compare' => 'LIKE',
                    );
                    break;

                case 'product':
                    $product_ids = $wpdb->get_col( $wpdb->prepare(
                        "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_title LIKE %s",
                        '%' . $wpdb->esc_like( $search_query ) . '%'
                    ) );

                    if ( empty( $product_ids ) ) {
						$args['post__in'] = array( 0 );
						break;
                    }

                    $meta_query[] = array(
                        'key'     => '_hold_this_product_product_id',
                        'value'   => $product_ids,
                        'compare' => 'IN',
                    );
                    break;

                case 'product_id':
                    if ( ! is_numeric( $search_query ) ) {
						$args['post__in'] = array( 0 );
						break;
                    }
                    $meta_query[] = array(
                        'key'     => '_hold_this_product_product_id',
                        'value'   => absint( $search_query ),
                        'compare' => '=',
                    );
                    break;

                case 'customer_name':
					$author_ids = get_users( array( 'search' => '*' . $search_query . '*', 'search_columns' => array( 'display_name', 'user_login', 'user_email' ), 'fields' => 'ids', 'number' => 100 ) );
                    break;
            }
        }

        $args = array(
            'post_type'      => 'holdthisproduct_res',
            'post_status'    => 'publish',
			'posts_per_page' => 25,
			'paged'          => max( 1, absint( $page ) ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        if ( ! empty( $meta_query ) ) {
            $args['meta_query'] = $meta_query;
        }
		if ( null !== $author_ids ) {
			$args['author__in'] = $author_ids ? array_map( 'absint', $author_ids ) : array( 0 );
		}

		return new WP_Query( $args );
    }

    private function get_reservations_summary() {
		$cached = wp_cache_get( 'admin_summary', 'holdthisproduct' );
		if ( false !== $cached ) {
			return $cached;
		}
        global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT pm.meta_value AS reservation_status, COUNT(*) AS reservation_count
			FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_hold_this_product_status'
			WHERE p.post_type = 'holdthisproduct_res' AND p.post_status = 'publish' GROUP BY pm.meta_value",
			OBJECT_K
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No external values are interpolated.
		$summary = array( 'total' => 0, 'pending_approval' => 0, 'active' => 0, 'expired' => 0, 'cancelled' => 0, 'fulfilled' => 0, 'denied' => 0 );
		foreach ( (array) $rows as $status => $row ) {
			if ( isset( $summary[ $status ] ) ) {
				$summary[ $status ] = (int) $row->reservation_count;
				$summary['total'] += (int) $row->reservation_count;
			}
		}
		wp_cache_set( 'admin_summary', $summary, 'holdthisproduct', MINUTE_IN_SECONDS );
		return $summary;
    }

    private function render_row( $reservation ) {
        $product_id  = (int) get_post_meta( $reservation->ID, '_hold_this_product_product_id', true );
        $email       = get_post_meta( $reservation->ID, '_hold_this_product_email', true );
        $name        = get_post_meta( $reservation->ID, '_hold_this_product_name', true );
        $surname     = get_post_meta( $reservation->ID, '_hold_this_product_surname', true );
        $expires_ts  = (int) get_post_meta( $reservation->ID, '_hold_this_product_expires_at', true );
        $status      = get_post_meta( $reservation->ID, '_hold_this_product_status', true );

        $product          = wc_get_product( $product_id );
		$product_name     = $product ? $product->get_name() : sprintf( /* translators: %d: product ID. */ __( 'Unknown Product (ID: %d)', 'hold-this-product' ), $product_id );
        $product_edit_url = $product ? admin_url( 'post.php?post=' . $product_id . '&action=edit' ) : '#';

        if ( $reservation->post_author ) {
            $user           = get_userdata( $reservation->post_author );
			$customer       = $user ? $user->display_name . ' (' . $user->user_email . ')' : __( 'Unknown User', 'hold-this-product' );
			$customer_short = $user ? $user->display_name : __( 'Unknown User', 'hold-this-product' );
        } else {
            $customer_full = trim( $name . ' ' . $surname );
            if ( $customer_full === '' ) {
                $customer       = $email ?: __( 'No email', 'hold-this-product' );
                $customer_short = $email ?: __( 'No email', 'hold-this-product' );
            } else {
                $customer       = $customer_full . ' (' . $email . ')';
                $customer_short = $customer_full;
            }
        }

        $reserved_date = get_the_date( 'M j, Y @ H:i', $reservation );
		$expires_disp  = $expires_ts ? wp_date( 'M j, Y @ H:i', $expires_ts ) : '—';

        $time_left  = '—';
        $time_class = '';
        if ( $expires_ts && $status === 'active' ) {
			$diff = $expires_ts - time();
            if ( $diff > 0 ) {
                $days    = floor( $diff / DAY_IN_SECONDS );
                $hours   = floor( ( $diff % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
                $minutes = floor( ( $diff % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );

                if ( $days > 0 ) {
					$time_left = sprintf( /* translators: 1: days, 2: hours. */ __( '%1$dd %2$dh', 'hold-this-product' ), $days, $hours );
                } elseif ( $hours > 0 ) {
					$time_left = sprintf( /* translators: 1: hours, 2: minutes. */ __( '%1$dh %2$dm', 'hold-this-product' ), $hours, $minutes );
                } else {
					$time_left = sprintf( /* translators: %d: minutes. */ __( '%dm', 'hold-this-product' ), $minutes );
                }

                if ( $diff < 2 * HOUR_IN_SECONDS ) {
                    $time_class = 'time-left-critical';
                } elseif ( $diff < 6 * HOUR_IN_SECONDS ) {
                    $time_class = 'time-left-warning';
                }
            } else {
				$time_left  = __( 'Expired', 'hold-this-product' );
                $time_class = 'time-left-critical';
            }
        }

        $status_class   = 'status-' . str_replace( '_', '-', $status );
		$status_labels = array(
			'pending_approval' => __( 'Pending approval', 'hold-this-product' ),
			'active' => __( 'Active', 'hold-this-product' ),
			'expired' => __( 'Expired', 'hold-this-product' ),
			'cancelled' => __( 'Cancelled', 'hold-this-product' ),
			'fulfilled' => __( 'Fulfilled', 'hold-this-product' ),
			'denied' => __( 'Denied', 'hold-this-product' ),
			'order_cancelled' => __( 'Order cancelled', 'hold-this-product' ),
		);
		$status_display = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : __( 'Unknown', 'hold-this-product' );

        echo '<tr>';
        echo '<td>';
        if ( $product ) {
            echo '<a href="' . esc_url( $product_edit_url ) . '" target="_blank">' . esc_html( $product_name ) . '</a>';
        } else {
            echo esc_html( $product_name );
        }
        echo '</td>';
        echo '<td title="' . esc_attr( $customer ) . '">' . esc_html( $customer ) . '</td>';
        echo '<td><span class="' . esc_attr( $status_class ) . '">' . esc_html( $status_display ) . '</span></td>';
        echo '<td>' . esc_html( $reserved_date ) . '</td>';
        echo '<td>' . esc_html( $expires_disp ) . '</td>';
        echo '<td class="' . esc_attr( $time_class ) . '">' . esc_html( $time_left ) . '</td>';
        echo '<td>';

        if ( $status === 'pending_approval' ) {
            echo '<button type="button" class="button button-small hold-this-product-approve-reservation" ';
            echo 'data-reservation-id="' . esc_attr( $reservation->ID ) . '" ';
            echo 'data-customer="' . esc_attr( $customer_short ) . '" ';
            echo 'data-product="' . esc_attr( $product_name ) . '" style="margin-right: 5px;">';
            echo esc_html__( 'Approve', 'hold-this-product' );
            echo '</button>';

            echo '<button type="button" class="button button-small button-link-delete hold-this-product-deny-reservation" ';
            echo 'data-reservation-id="' . esc_attr( $reservation->ID ) . '" ';
            echo 'data-customer="' . esc_attr( $customer_short ) . '" ';
            echo 'data-product="' . esc_attr( $product_name ) . '">';
            echo esc_html__( 'Deny', 'hold-this-product' );
            echo '</button>';
        } elseif ( $status === 'active' ) {
            echo '<button type="button" class="button button-small hold-this-product-cancel-reservation" ';
            echo 'data-reservation-id="' . esc_attr( $reservation->ID ) . '" ';
            echo 'data-customer="' . esc_attr( $customer_short ) . '" ';
            echo 'data-product="' . esc_attr( $product_name ) . '">';
            echo esc_html__( 'Cancel', 'hold-this-product' );
            echo '</button>';
        } else {
            echo '<button type="button" class="button button-small button-link-delete hold-this-product-delete-reservation" ';
            echo 'data-reservation-id="' . esc_attr( $reservation->ID ) . '" ';
            echo 'data-customer="' . esc_attr( $customer_short ) . '" ';
            echo 'data-product="' . esc_attr( $product_name ) . '">';
            echo esc_html__( 'Delete', 'hold-this-product' );
            echo '</button>';
        }

        echo '</td>';
        echo '</tr>';
    }
}
