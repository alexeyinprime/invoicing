<?php
/**
 * Displays a gateway setting in the payment form editor
 *
 * This template can be overridden by copying it to yourtheme/invoicing/payment-forms-admin/edit/gateway_select.php.
 *
 * @version 1.0.19
 */

defined( 'ABSPATH' ) || exit;

?>

<div class='form-group'>
    <label :for="active_form_element.id + '_edit'"><?php esc_html_e( 'The gateway select text', 'invoicing' ); ?></label>
    <textarea :id="active_form_element.id + '_edit'" v-model='active_form_element.text' class='form-control' rows='3'></textarea>
</div>
