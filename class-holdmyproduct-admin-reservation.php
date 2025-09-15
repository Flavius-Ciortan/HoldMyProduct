<?php

//  Show the checkbox in Product > Edit (General tab)

add_action( 'woocommerce_product_options_general_product_data', function () {
    echo '<div class="options_group">';
    woocommerce_wp_checkbox( [
        'id'          => '_hmp_reservations_enabled',
        'label'       => __( 'Enable reservations', 'holdmyproduct' ),
        'desc_tip'    => true,
        'description' => __( 'Allow this product to be reserved via HoldMyProduct.', 'holdmyproduct' ),
    ] );
    echo '</div>';
} );

//  Save it when the product is saved

add_action( 'woocommerce_admin_process_product_object', function ( WC_Product $product ) {
    $enabled = isset( $_POST['_hmp_reservations_enabled'] ) ? 'yes' : 'no';
    $product->update_meta_data( '_hmp_reservations_enabled', $enabled );
} );


// Insert column in Products list


add_filter( 'manage_edit-product_columns', function( $cols ) {
    $new = [];
    foreach ( $cols as $key => $label ) {
        $new[ $key ] = $label;
        if ( $key === 'sku' ) {
            $new['hmp_reservations'] = __( 'Reservations', 'holdmyproduct' );
        }
    }
    if ( ! isset( $new['hmp_reservations'] ) ) {
        $new['hmp_reservations'] = __( 'Reservations', 'holdmyproduct' );
    }
    return $new;
} );


 // Column cell (button shows the ACTION to take)


add_action('manage_product_posts_custom_column', function ($column, $post_id) {
    if ($column !== 'hmp_reservations') return;

    $val   = get_post_meta($post_id, '_hmp_reservations_enabled', true);
    $is_on = ($val === 'yes');
    $state = $is_on ? 'on' : 'off';
    $label = $is_on ? __('Disable', 'holdmyproduct') : __('Enable', 'holdmyproduct');

    printf(
        '<button type="button" class="button hmp-res-toggle %1$s" aria-pressed="%2$s" data-product-id="%3$d" data-state="%1$s">
            <span class="hmp-res-toggle-label">%4$s</span>
        </button>
        <span class="spinner" style="float:none;"></span>',
        esc_attr($state),
        $is_on ? 'true' : 'false',
        (int) $post_id,
        esc_html($label)
    );
}, 10, 2);

add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( $hook !== 'edit.php' || ( $_GET['post_type'] ?? '' ) !== 'product' ) return;

    wp_enqueue_script(
        'hmp-res-toggle',
        plugins_url( 'hmp-res-toggle.js', __FILE__ ),
        [ 'jquery' ],
        '1.0',
        true
    );
    wp_localize_script( 'hmp-res-toggle', 'hmpResToggle', [
        'ajax'  => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'hmp_toggle_res' ),
        'enable'    => __( 'Enabled', 'holdmyproduct' ),
        'disable'   => __( 'Disabled', 'holdmyproduct' ),
    ] );

} );

add_action( 'wp_ajax_hmp_toggle_res', function () {
    if ( ! current_user_can( 'edit_products' ) ) {
        wp_send_json_error( [ 'message' => __( 'Forbidden', 'holdmyproduct' ) ], 403 );
    }

    check_ajax_referer( 'hmp_toggle_res', 'nonce' );

    $pid = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
    $new = ( isset( $_POST['new'] ) && $_POST['new'] === 'yes' ) ? 'yes' : 'no';

    if ( ! $pid ) {
        wp_send_json_error( [ 'message' => __( 'Invalid product', 'holdmyproduct' ) ], 400 );
    }

    update_post_meta( $pid, '_hmp_reservations_enabled', $new );

    wp_send_json_success( [
        'new'   => $new,
        'label' => ( $new === 'yes' ) ? __( 'Enabled', 'holdmyproduct' ) : __( 'Disabled', 'holdmyproduct' ),
    ] );
} );
