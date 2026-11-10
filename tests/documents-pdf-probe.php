<?php
// Local integration probe. Copy temporarily to wp-content/mu-plugins and remove after the test.
add_action( 'usces_action_order_print_cart_row', static function ( $pdf, $data, $args ) {
    $row = $args['cart_row'];
    file_put_contents( '/tmp/sosc-pdf-row.json', wp_json_encode( array(
        'type' => isset( $_REQUEST['type'] ) ? $_REQUEST['type'] : '',
        'item_name' => $row['item_name'],
        'quantity' => (float) $row['quantity'],
        'price' => (float) $row['price'],
        'total' => (float) $data->order['total_full_price'],
    ), JSON_UNESCAPED_UNICODE ) );
}, 10, 3 );
