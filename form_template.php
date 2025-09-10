<?php
if ( ! defined('ABSPATH') ) exit;

global $product;

// If $product is not set (e.g., called outside the main product loop), resolve it.


if ( ! $product instanceof WC_Product ) {
    $product_id = get_the_ID();
    if ( ! $product_id ) {
        $product_id = get_queried_object_id();
    }
    if ( $product_id ) {
        $product = wc_get_product( $product_id );
    }
}


// If we still don’t have a product, stop to avoid fatals.


if ( ! $product instanceof WC_Product ) {
    return;
}

$pid = $product->get_id();
?>

<?php
$opts            = get_option('holdmyproduct_options');
$globally_on     = ! empty( $opts['enable_reservation'] );

if ( $globally_on ) : ?>
  <button type="button"
          id="hmp_reserve_product"
          data-productid="<?php echo esc_attr( $pid ); ?>">
    <?php esc_html_e('Reserve', 'holdmyproduct'); ?>
  </button>

<?php endif; ?>

<!-- <button type="button" id="hmp_reserve_product" data-productid="<?php echo esc_attr($pid); ?>">Reserve</button> -->


<!-- Modal Overlay -->


<?php if ( !is_user_logged_in() ) : ?>
  <div id="reservation-modal" class="modal-overlay" title="Reserve Product">
      <div class="modal-box">
          <!-- <button class="modal-close" aria-label="Close">&times;</button> -->

          <!-- <h2>Reserve Product</h2> -->

          <form id="reservation-form">
              <input type="hidden" name="product_id" value="<?php echo get_the_ID(); ?>">
              <input type="text" name="name" placeholder="First Name" required>
              <input type="text" name="surname" placeholder="Last Name" required>
              <input type="email" name="email" id="hmp_email" placeholder="Email Address" required>
              <button type="submit" class="submit-btn">Reserve</button>
          </form>
          
      </div>
  </div>



<?php else : ?>
  <div id="reservation-modal" class="modal-overlay" title="Reserve Product">
    <!-- Modal Box -->
    <div class="modal-box">
      <!-- <button class="modal-close" aria-label="Close">&times;</button> -->
      <!-- <h2>Reserve Product</h2> -->
      <form id="reservation-form">
        <input type="hidden" name="action" value="holdmyproduct_reserve">
        <input type="hidden" name="security" value="<?php echo esc_attr( wp_create_nonce('holdmyproduct_nonce') ); ?>">
        <input type="hidden" name="product_id" id="hmp_product_id" value="<?php echo esc_attr( $pid ); ?>">
        <input type="hidden" name="product_id" value="">
        <p>Are you sure you want to reserve this product?</p>
        <button type="submit" class="submit-btn">Yes, Reserve</button>
      </form>
    </div>
  </div>
<?php endif; ?>

