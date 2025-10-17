<?php
/**
 * My Account - Reservations
 *
 * @package HoldMyProduct
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>

<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
    <thead>
        <tr>
            <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-product">
                <span class="nobr"><?php esc_html_e( 'Product', 'hold-my-product' ); ?></span>
            </th>
            <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-expires">
                <span class="nobr"><?php esc_html_e( 'Expires', 'hold-my-product' ); ?></span>
            </th>
            <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-time-left">
                <span class="nobr"><?php esc_html_e( 'Time Left', 'hold-my-product' ); ?></span>
            </th>
            <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-actions">
                <span class="nobr"><?php esc_html_e( 'Actions', 'hold-my-product' ); ?></span>
            </th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ( $reservations as $reservation ) : ?>
            <?php
            $product_id = (int) get_post_meta( $reservation->ID, '_hmp_product_id', true );
            $expires_ts = (int) get_post_meta( $reservation->ID, '_hmp_expires_at', true );
            $product = wc_get_product( $product_id );
            
            if ( ! $product ) {
                continue;
            }
            
            $expires_disp = $expires_ts ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expires_ts ) : '—';
            
            // Calculate time left
            $time_left = '';
            $urgency_class = '';
            if ( $expires_ts ) {
                $diff = $expires_ts - current_time( 'timestamp' );
                if ( $diff > 0 ) {
                    $days = floor( $diff / DAY_IN_SECONDS );
                    $hours = floor( ( $diff % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
                    $minutes = floor( ( $diff % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
                    
                    if ( $days > 0 ) {
                        $time_left = sprintf( _n( '%d day', '%d days', $days, 'hold-my-product' ), $days );
                        if ( $hours > 0 ) {
                            $time_left .= sprintf( ', %d hours', $hours );
                        }
                    } elseif ( $hours > 0 ) {
                        $time_left = sprintf( _n( '%d hour', '%d hours', $hours, 'hold-my-product' ), $hours );
                        if ( $minutes > 0 ) {
                            $time_left .= sprintf( ', %d minutes', $minutes );
                        }
                    } else {
                        $time_left = sprintf( _n( '%d minute', '%d minutes', $minutes, 'hold-my-product' ), $minutes );
                    }
                    
                    // Add urgency class for styling
                    if ( $diff < 2 * HOUR_IN_SECONDS ) {
                        $urgency_class = 'urgent';
                    } elseif ( $diff < 6 * HOUR_IN_SECONDS ) {
                        $urgency_class = 'warning';
                    }
                } else {
                    $time_left = esc_html__( 'Expired', 'hold-my-product' );
                    $urgency_class = 'expired';
                }
            }
            
            $add_to_cart_url = esc_url( wc_get_cart_url() . '?add-to-cart=' . $product_id );
            $cancel_url = wp_nonce_url(
                add_query_arg( array( 'hmp_cancel_res' => $reservation->ID ) ),
                'hmp_cancel_res_' . $reservation->ID
            );
            ?>
            
            <tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-active order">
                <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-product" data-title="<?php esc_attr_e( 'Product', 'hold-my-product' ); ?>">
                    <a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>" class="woocommerce-LoopProduct-link">
                        <?php echo esc_html( $product->get_name() ); ?>
                    </a>
                </td>
                <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-expires" data-title="<?php esc_attr_e( 'Expires', 'hold-my-product' ); ?>">
                    <time datetime="<?php echo esc_attr( date( 'c', $expires_ts ) ); ?>">
                        <?php echo esc_html( $expires_disp ); ?>
                    </time>
                </td>
                <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-time-left <?php echo esc_attr( $urgency_class ); ?>" data-title="<?php esc_attr_e( 'Time Left', 'hold-my-product' ); ?>">
                    <span class="time-left <?php echo esc_attr( $urgency_class ); ?>">
                        <?php echo esc_html( $time_left ); ?>
                    </span>
                </td>
                <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-actions" data-title="<?php esc_attr_e( 'Actions', 'hold-my-product' ); ?>">
                    <a href="<?php echo esc_url( $add_to_cart_url ); ?>" class="woocommerce-button button add-to-cart">
                        <?php esc_html_e( 'Add to Cart', 'hold-my-product' ); ?>
                    </a>
                    <a href="<?php echo esc_url( $cancel_url ); ?>" class="woocommerce-button button cancel-reservation" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to cancel this reservation?', 'hold-my-product' ); ?>')">
                        <?php esc_html_e( 'Cancel', 'hold-my-product' ); ?>
                    </a>
                </td>
            </tr>
            
        <?php endforeach; ?>
    </tbody>
</table>
