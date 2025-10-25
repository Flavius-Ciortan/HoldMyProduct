<?php
if ( ! defined('ABSPATH') ) exit;

global $product;

// If $product is not set, resolve it
if ( ! $product instanceof WC_Product ) {
    $product_id = get_the_ID();
    if ( ! $product_id ) {
        $product_id = get_queried_object_id();
    }
    if ( $product_id ) {
        $product = wc_get_product( $product_id );
    }
}

// If we still don't have a product, stop
if ( ! $product instanceof WC_Product ) {
    return;
}

// Get settings
$pid = $product->get_id();
$options = get_option('holdmyproduct_options');
$globally_on = ! empty( $options['enable_reservation'] );
$guest_reservations_on = ! empty( $options['enable_guest_reservation'] );

// Only show button if reservations are globally enabled AND
// (user is logged in OR guest reservations are enabled)
$show_button = $globally_on && ( is_user_logged_in() || $guest_reservations_on );

// Get customization settings
$is_logged_in = is_user_logged_in();
if ($is_logged_in) {
    $enable_popup_customization = !empty($options['enable_popup_customization_logged_in']);
    $popup_settings = $options['popup_customization_logged_in'] ?? [];
} else {
    $enable_popup_customization = !empty($options['enable_popup_customization_guests']);
    $popup_settings = $options['popup_customization_guests'] ?? [];
}

// Defaults
$border_radius = isset($popup_settings['border_radius']) ? intval($popup_settings['border_radius']) : 8;
$background_color = isset($popup_settings['background_color']) ? $popup_settings['background_color'] : '#ffffff';
$font_family = isset($popup_settings['font_family']) ? $popup_settings['font_family'] : 'Arial, Helvetica, sans-serif';
$font_size = isset($popup_settings['font_size']) ? intval($popup_settings['font_size']) : 16;
$text_color = isset($popup_settings['text_color']) ? $popup_settings['text_color'] : '#222222';

// Google Fonts support
$google_fonts = [
    'Roboto, sans-serif' => 'Roboto',
    'Open Sans, sans-serif' => 'Open+Sans',
    'Lato, sans-serif' => 'Lato',
    'Montserrat, sans-serif' => 'Montserrat',
];
$google_font_link = '';
if ($enable_popup_customization && isset($google_fonts[$font_family])) {
    $google_font_link = 'https://fonts.googleapis.com/css?family=' . $google_fonts[$font_family] . ':400,700&display=swap';
}

// Build inline style for modal box - only apply if customization is enabled
$modal_box_style = '';
if ($enable_popup_customization) {
    $modal_box_style = sprintf(
        'background-color: %s !important; border-radius: %dpx !important; font-family: %s !important; font-size: %dpx !important; color: %s !important;',
        esc_attr($background_color),
        esc_attr($border_radius),
        esc_attr($font_family),
        esc_attr($font_size),
        esc_attr($text_color)
    );
}
?>

<?php if ( $show_button ) : ?>
  <div style="margin-top: 10px;">
    <button type="button"
            id="hmp_reserve_product"
            data-productid="<?php echo esc_attr( $pid ); ?>"
            style="margin-left: 10px;">
      <?php esc_html_e('Reserve Product', 'hold-my-product'); ?>
    </button>
  </div>
<?php endif; ?>

<!-- Google Fonts link if needed -->
<?php if ($google_font_link): ?>
    <link href="<?php echo esc_url($google_font_link); ?>" rel="stylesheet" />
<?php endif; ?>

<!-- Modal for Guest Users -->
<?php if ( ! is_user_logged_in() && $guest_reservations_on ) : ?>
  <div id="reservation-modal" class="modal-overlay" title="Reserve Product" style="display: none;">
      <div class="modal-box" style="<?php echo $modal_box_style; ?>">
          <form id="reservation-form">
              <input type="hidden" name="action" value="holdmyproduct_reserve">
              <input type="hidden" name="security" value="<?php echo esc_attr( wp_create_nonce('holdmyproduct_nonce') ); ?>">
              <input type="hidden" name="product_id" value="<?php echo esc_attr( $pid ); ?>">
              
              <p><strong>Reserve this product</strong></p>
              <p>Fill in your details to reserve this product for 24 hours:</p>
              
              <input type="text" name="name" placeholder="First Name" required>
              <input type="text" name="surname" placeholder="Last Name" required>
              <input type="email" name="email" id="hmp_email" placeholder="Email Address" required>
              
              <p class="description">We'll send you a confirmation email with your reservation details.</p>
              
              <button type="submit" class="submit-btn">Reserve Product</button>
          </form>
      </div>
  </div>

<!-- Modal for Logged-in Users -->
<?php elseif ( is_user_logged_in() && $globally_on ) : ?>
  <div id="reservation-modal" class="modal-overlay" title="Reserve Product" style="display: none;">
    <div class="modal-box" style="<?php echo $modal_box_style; ?>">
      <form id="reservation-form">
        <input type="hidden" name="action" value="holdmyproduct_reserve">
        <input type="hidden" name="security" value="<?php echo esc_attr( wp_create_nonce('holdmyproduct_nonce') ); ?>">
        <input type="hidden" name="product_id" value="<?php echo esc_attr( $pid ); ?>">
        
        <p><strong>Reserve this product</strong></p>
        <p>Are you sure you want to reserve this product for 24 hours?</p>
        
        <button type="submit" class="submit-btn">Yes, Reserve</button>
      </form>
    </div>
  </div>
<?php endif; ?>
    </div>
  </div>

