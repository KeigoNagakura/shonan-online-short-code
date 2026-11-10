<?php
// Run with: wp eval-file /tmp/documents-fixture.php create|paid|reduced|status|cleanup
global $wpdb;
$mode = $args[0] ?? '';
$marker = 'sosc-documents-fixture-20261020';
$email = 'documents-fixture-20261020@example.invalid';
$table = $wpdb->prefix . 'usces_order';
$meta_table = $wpdb->prefix . 'usces_order_meta';
$owner_key = '_sosc_documents_fixture';
$order_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE order_email = %s AND order_note = %s", $email, $marker ) );
$id = 1 === $order_count ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $table WHERE order_email = %s AND order_note = %s", $email, $marker ) ) : 0;
$owned = $id && (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $meta_table WHERE order_id = %d AND meta_key = %s AND meta_value = %s", $id, $owner_key, $marker ) ) > 0;
if ( 'create' === $mode ) {
    $posts = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_name = %s", $marker ) );
    if ( $order_count || $posts ) { WP_CLI::error( 'Document fixture names already exist; inspect ownership before creating or cleaning up.' ); }
    $ok = $wpdb->insert( $table, array(
        'order_email' => $email, 'order_name1' => 'テスト', 'order_name2' => '注文',
        'order_pref' => '東京都', 'order_address1' => 'テスト住所', 'order_tel' => '0000000000',
        'order_delivery_time' => '', 'order_payment_name' => '銀行振込',
        'order_date' => current_time( 'mysql' ), 'order_status' => 'new',
        'order_cart' => maybe_serialize( array() ), 'order_delivery' => maybe_serialize( array() ),
        'order_condition' => maybe_serialize( array( 'tax_display' => 'activate', 'tax_target' => 'all', 'tax_mode' => 'exclude', 'tax_rate' => 10, 'applicable_taxrate' => 'standard' ) ),
        'order_item_total_price' => 1000, 'order_tax' => 100,
        'order_note' => $marker,
    ) );
    if ( ! $ok ) { throw new RuntimeException( $wpdb->last_error ); }
    $id = (int) $wpdb->insert_id;
    if ( false === $wpdb->insert( $meta_table, array( 'order_id' => $id, 'meta_key' => $owner_key, 'meta_value' => $marker ) ) ) {
        $wpdb->delete( $table, array( 'ID' => $id, 'order_email' => $email, 'order_note' => $marker ) );
        throw new RuntimeException( 'Document fixture ownership marker could not be saved.' );
    }
    $product = wp_insert_post( array(
        'post_title' => '帳票テスト商品', 'post_name' => $marker,
        'post_status' => 'publish', 'post_type' => 'item', 'meta_input' => array( $owner_key => $marker ),
    ), true );
    if ( is_wp_error( $product ) ) { throw new RuntimeException( $product->get_error_message() ); }
    $cart = $wpdb->insert( $wpdb->prefix . 'usces_ordercart', array(
        'order_id' => $id, 'group_id' => 0, 'row_index' => 0,
        'post_id' => $product, 'item_code' => 'TEST-DOC-001',
        'item_name' => '帳票テスト商品', 'sku_code' => 'TEST-DOC-001',
        'sku_name' => '標準', 'cprice' => 1000, 'price' => 1000,
        'quantity' => 1, 'unit' => '個', 'tax' => 100,
    ) );
    if ( ! $cart ) { throw new RuntimeException( $wpdb->last_error ); }
    $page = wp_insert_post( array( 'post_title' => $marker, 'post_name' => $marker, 'post_status' => 'publish', 'post_type' => 'page', 'post_content' => '[sosc_wc_documents]', 'meta_input' => array( $owner_key => $marker ) ), true );
    if ( is_wp_error( $page ) ) { throw new RuntimeException( $page->get_error_message() ); }
    echo "order=$id product=$product page=$page email=$email url=" . get_permalink( $page ) . PHP_EOL;
} elseif ( 'paid' === $mode ) {
    if ( ! $owned ) { WP_CLI::error( 'Owned document fixture is missing.' ); }
    if ( false === $wpdb->update( $table, array( 'order_status' => 'new,receipted' ), array( 'ID' => $id ) ) ) { throw new RuntimeException( $wpdb->last_error ); }
    echo "paid=$id" . PHP_EOL;
} elseif ( 'reduced' === $mode ) {
    if ( ! $owned ) { WP_CLI::error( 'Owned document fixture is missing.' ); }
    $cart_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT cart_id FROM {$wpdb->prefix}usces_ordercart WHERE order_id = %d AND item_code = %s", $id, 'TEST-DOC-001' ) );
    if ( ! $cart_id ) { throw new RuntimeException( 'Fixture cart missing' ); }
    $meta_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}usces_ordercart_meta WHERE cart_id = %d AND meta_type = %s AND meta_key = %s", $cart_id, 'taxrate', 'reduced' ) );
    if ( ! $meta_count ) {
        if ( false === $wpdb->insert( $wpdb->prefix . 'usces_ordercart_meta', array( 'cart_id' => $cart_id, 'meta_type' => 'taxrate', 'meta_key' => 'reduced', 'meta_value' => '' ) ) ) { throw new RuntimeException( $wpdb->last_error ); }
    }
    if ( false === $wpdb->update( $table, array(
        'order_condition' => maybe_serialize( array( 'tax_display' => 'activate', 'tax_target' => 'all', 'tax_mode' => 'exclude', 'tax_rate' => 10, 'tax_rate_reduced' => 8, 'applicable_taxrate' => 'reduced' ) ),
        'order_tax' => 80,
    ), array( 'ID' => $id ) ) ) { throw new RuntimeException( $wpdb->last_error ); }
    echo "reduced=$id" . PHP_EOL;
} elseif ( 'status' === $mode ) {
    $products = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'item'", $marker ) );
    $pages = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'page'", $marker ) );
    echo "orders=$order_count products=$products pages=$pages" . PHP_EOL;
} elseif ( 'cleanup' === $mode ) {
    if ( $owned ) {
        $cart_ids = $wpdb->get_col( $wpdb->prepare( "SELECT cart_id FROM {$wpdb->prefix}usces_ordercart WHERE order_id = %d", $id ) );
        foreach ( $cart_ids as $cart_id ) {
            $wpdb->delete( $wpdb->prefix . 'usces_ordercart_meta', array( 'cart_id' => $cart_id ) );
        }
        $wpdb->delete( $wpdb->prefix . 'usces_ordercart', array( 'order_id' => $id ) );
        $wpdb->delete( $meta_table, array( 'order_id' => $id ) );
        $wpdb->delete( $table, array( 'ID' => $id, 'order_email' => $email, 'order_note' => $marker ) );
    }
    $products = get_posts( array( 'post_type' => 'item', 'post_status' => 'any', 'name' => $marker, 'posts_per_page' => -1 ) );
    foreach ( $products as $product ) { if ( $marker === get_post_meta( $product->ID, $owner_key, true ) ) { wp_delete_post( $product->ID, true ); } }
    $pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'name' => $marker, 'posts_per_page' => -1 ) );
    foreach ( $pages as $page ) { if ( $marker === get_post_meta( $page->ID, $owner_key, true ) ) { wp_delete_post( $page->ID, true ); } }
    $remaining_orders = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE order_email = %s AND order_note = %s", $email, $marker ) );
    $remaining_products = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'item'", $marker ) );
    $remaining_pages = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'page'", $marker ) );
    if ( $remaining_orders || $remaining_products || $remaining_pages ) {
        WP_CLI::error( "Document fixture names remain; inspect ownership: orders=$remaining_orders products=$remaining_products pages=$remaining_pages" );
    }
    echo 'cleaned' . PHP_EOL;
} else {
    throw new RuntimeException( 'Unknown mode' );
}
