<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $product;

// If $product is not set, resolve it.
if ( ! $product instanceof WC_Product ) {
    $product_id = get_the_ID();
    if ( ! $product_id ) {
        $product_id = get_queried_object_id();
    }
    if ( $product_id ) {
        $product = wc_get_product( $product_id );
    }
}

if ( ! $product instanceof WC_Product ) {
    return;
}

$pid = $product->get_id();
$options = get_option( 'holdthisproduct_options' );

// Popup customization settings (logged-in only).
$enable_popup_customization = ! empty( $options['enable_popup_customization_logged_in'] );
$popup_settings = $options['popup_customization_logged_in'] ?? array();

// Defaults
$border_radius = isset( $popup_settings['border_radius'] ) ? (int) $popup_settings['border_radius'] : 8;
$background_color = isset( $popup_settings['background_color'] ) ? $popup_settings['background_color'] : '#ffffff';
$font_family = isset( $popup_settings['font_family'] ) ? $popup_settings['font_family'] : 'Arial, Helvetica, sans-serif';
$font_size = isset( $popup_settings['font_size'] ) ? (int) $popup_settings['font_size'] : 16;
$text_color = isset( $popup_settings['text_color'] ) ? $popup_settings['text_color'] : '#222222';

// Google Fonts support
$google_fonts = array(
    'Roboto, sans-serif' => 'Roboto',
    'Open Sans, sans-serif' => 'Open+Sans',
    'Lato, sans-serif' => 'Lato',
    'Montserrat, sans-serif' => 'Montserrat',
);

$google_font_link = '';
if ( $enable_popup_customization && isset( $google_fonts[ $font_family ] ) ) {
    $google_font_link = 'https://fonts.googleapis.com/css?family=' . $google_fonts[ $font_family ] . ':400,700&display=swap';
}

// Build inline style for modal box - only apply if customization is enabled.
$modal_box_style = '';
if ( $enable_popup_customization ) {
    $modal_box_style = sprintf(
        'background-color: %s !important; border-radius: %dpx !important; font-family: %s !important; font-size: %dpx !important; color: %s !important;',
        esc_attr( $background_color ),
        esc_attr( $border_radius ),
        esc_attr( $font_family ),
        esc_attr( $font_size ),
        esc_attr( $text_color )
    );
}
?>

<?php if ( $google_font_link ) : ?>
    <link href="<?php echo esc_url( $google_font_link ); ?>" rel="stylesheet" />
<?php endif; ?>

<div id="reservation-modal" class="modal-overlay" title="Reserve Product" style="display: none;">
    <div class="modal-box" style="<?php echo $modal_box_style; ?>">
        <form id="reservation-form">
            <input type="hidden" name="action" value="holdthisproduct_reserve">
            <input type="hidden" name="security" value="<?php echo esc_attr( wp_create_nonce( 'holdthisproduct_nonce' ) ); ?>">
            <input type="hidden" name="product_id" value="<?php echo esc_attr( $pid ); ?>">

            <p><strong>Reserve this product</strong></p>
            <p>Are you sure you want to reserve this product for 24 hours?</p>

            <button type="submit" class="submit-btn">Yes, Reserve</button>
        </form>
    </div>
</div>

