<?php
/**
 * Displays the cart in a payment form
 *
 * This template can be overridden by copying it to yourtheme/invoicing/payment-forms/cart.php.
 *
 * @version 1.0.19
 */

defined( 'ABSPATH' ) || exit;

// Cart table columns.
$columns = array(
    'name'     => __( 'Item', 'invoicing' ),
    'price'    => __( 'Price', 'invoicing' ),
    'quantity' => __( 'Quantity', 'invoicing' ),
    'subtotal' => __( 'Subtotal', 'invoicing' ),
);

if ( ! empty( $form->invoice ) ) {
    $columns = getpaid_invoice_item_columns( $form->invoice );
}

$columns = apply_filters( 'getpaid_payment_form_cart_table_columns', $columns, $form );

?>
    <div class="getpaid-payment-form-items-cart border">
        <div class="getpaid-payment-form-items-cart-header">
            <div class="form-row">
            <?php foreach ( $columns as $key => $label ) : ?>
                <div class="<?php echo 'name' == $key ? 'col-12 col-sm-5' : 'col-12 col-sm' ?> getpaid-form-cart-item-<?php echo esc_attr( $key ); ?>">
                    <?php echo sanitize_text_field( $label ); ?>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php

            // Display the item totals.
            foreach ( $form->get_items() as $item ) {
                wpinv_get_template( 'payment-forms/cart-item.php', compact( 'form', 'item', 'columns' ) );
            }

            // Display the cart totals.
            wpinv_get_template( 'payment-forms/cart-totals.php', compact( 'form' ) );

        ?>
    </div>
        