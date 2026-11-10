<?php
/** Local visual fixture for the plugin's shortcode layouts. */
global $wpdb;

$mode = $args[0] ?? '';
$marker = 'sosc-visual-20261020';
$owner_meta = '_sosc_visual_fixture';
$sku_code = 'SOSC-VISUAL-20261020-001';
$slugs = array(
    'category' => $marker . '-category',
    'year' => $marker . '-year',
    'order' => $marker . '-order',
    'area' => $marker . '-area',
    'tag' => $marker . '-tag',
    'post' => $marker . '-post',
    'item' => $marker . '-item',
    'lp' => $marker . '-lp',
);

if ( 'create' === $mode ) {
    $existing_posts = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_name LIKE %s", $wpdb->esc_like( $marker ) . '%' ) );
    $existing_terms = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->terms} WHERE slug LIKE %s", $wpdb->esc_like( $marker ) . '%' ) );
    $existing_skus = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}usces_skus WHERE code = %s", $sku_code ) );
    if ( $existing_posts || $existing_terms || $existing_skus ) {
        WP_CLI::error( 'Visual fixture names already exist; inspect ownership before creating or cleaning up.' );
    }
    $term_ids = array();
    foreach ( array( 'category', 'year', 'order', 'area' ) as $key ) {
        $term = wp_insert_term( '視覚確認 ' . $key, 'category', array( 'slug' => $slugs[ $key ] ) );
        if ( is_wp_error( $term ) ) { throw new RuntimeException( $term->get_error_message() ); }
        $term_ids[ $key ] = (int) $term['term_id'];
        update_term_meta( $term_ids[ $key ], $owner_meta, $marker );
    }
    foreach ( array( 'year', 'order', 'area' ) as $key ) {
        $term = wp_insert_term( '視覚確認 子 ' . $key, 'category', array( 'slug' => $slugs[ $key ] . '-child', 'parent' => $term_ids[ $key ] ) );
        if ( is_wp_error( $term ) ) { throw new RuntimeException( $term->get_error_message() ); }
        $term_ids[ $key . '-child' ] = (int) $term['term_id'];
        update_term_meta( $term_ids[ $key . '-child' ], $owner_meta, $marker );
    }
    $tag = wp_insert_term( '視覚確認タグ', 'post_tag', array( 'slug' => $slugs['tag'] ) );
    if ( is_wp_error( $tag ) ) { throw new RuntimeException( $tag->get_error_message() ); }
    update_term_meta( (int) $tag['term_id'], $owner_meta, $marker );

    $fixed_date = array( 'post_date' => '2026-01-01 12:00:00', 'post_date_gmt' => '2026-01-01 03:00:00' );
    $owned_post = array( 'meta_input' => array( $owner_meta => $marker ) );
    $post = wp_insert_post( array_merge( $fixed_date, $owned_post, array( 'post_type' => 'post', 'post_status' => 'publish', 'post_name' => $slugs['post'], 'post_title' => '視覚確認のお知らせ', 'post_content' => 'サンプル本文です。カードや一覧の折返しを確認します。', 'post_excerpt' => 'サンプルの抜粋です。' ) ), true );
    $item = wp_insert_post( array_merge( $fixed_date, $owned_post, array( 'post_type' => 'post', 'post_mime_type' => 'item', 'post_status' => 'publish', 'post_name' => $slugs['item'], 'post_title' => '視覚確認のテスト商品', 'post_content' => '商品カードの表示を確認するためのサンプルです。' ) ), true );
    $lp = wp_insert_post( array_merge( $fixed_date, $owned_post, array( 'post_type' => 'lp', 'post_status' => 'publish', 'post_name' => $slugs['lp'], 'post_title' => '視覚確認の参照先', 'post_content' => '<p>参照先コンテンツの表示確認です。</p>' ) ), true );
    foreach ( array( $post, $item, $lp ) as $created ) {
        if ( is_wp_error( $created ) ) { throw new RuntimeException( $created->get_error_message() ); }
    }
    wp_set_post_terms( $post, array( $term_ids['category'], $term_ids['year-child'], $term_ids['order-child'], $term_ids['area-child'] ), 'category' );
    wp_set_post_terms( $post, array( (int) $tag['term_id'] ), 'post_tag' );
    wp_set_post_terms( $item, array( $term_ids['category'] ), 'category' );
    if ( false === $wpdb->insert( $wpdb->prefix . 'usces_skus', array( 'post_id' => $item, 'code' => $sku_code, 'name' => '標準', 'price' => 1000, 'cprice' => 1000, 'unit' => '個', 'sort' => 0 ) ) ) {
        throw new RuntimeException( 'Visual fixture SKU creation failed.' );
    }

    $sections = array(
        'info' => array(
            '[sosc_info_list01 slug=' . $slugs['category'] . ']',
            '[sosc_info_list02 slug=' . $slugs['category'] . ']',
            '[sosc_info_list03 slug=' . $slugs['category'] . ' yearSlug=' . $slugs['year'] . ' orderSlug=' . $slugs['order'] . ' areaSlug=' . $slugs['area'] . ']',
            '[sosc_info_list04 slug=' . $slugs['category'] . ']',
            '[sosc_info_list05 slug=' . $slugs['category'] . ']',
        ),
        'cards' => array(
            '[sosc_blog_card01 slug=' . $slugs['post'] . ' link=/]',
            '[sosc_tag_view01 tag=視覚確認タグ]',
            '[sosc_target_content slug=' . $slugs['lp'] . ']',
        ),
        'welcart' => array(
            '[sosc_wc_item_list01 slug=' . $slugs['category'] . ']',
            '[sosc_wc_item_list02 slug=' . $slugs['category'] . ']',
            '[sosc_wc_item_list03 slug=' . $slugs['category'] . ']',
            '[sosc_wc_item_card01 post_name=' . $slugs['item'] . ']',
            '[sosc_wc_item_card02 post_name=' . $slugs['item'] . ']',
            '[sosc_wc_item_card03 post_name=' . $slugs['item'] . ']',
            '[sosc_wc_documents]',
        ),
    );
    foreach ( $sections as $key => $codes ) {
        $content = '';
        foreach ( $codes as $index => $code ) {
            $label = str_replace( array( '[', ']' ), array( '&#91;', '&#93;' ), esc_html( $code ) );
            $case = preg_match( '/^\[([a-zA-Z0-9_]+)/', $code, $match ) ? $match[1] : $key . $index;
            $content .= '<section class="sosc-visual-case" data-case="' . esc_attr( $case ) . '"><h2>' . $label . '</h2>' . $code . '</section>' . "\n";
        }
        $page = wp_insert_post( array_merge( $fixed_date, $owned_post, array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $marker . '-' . $key, 'post_title' => '視覚確認 ' . $key, 'post_content' => $content ) ), true );
        if ( is_wp_error( $page ) ) { throw new RuntimeException( $page->get_error_message() ); }
        echo "$key=" . get_permalink( $page ) . PHP_EOL;
    }
} elseif ( 'status' === $mode ) {
    $posts = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_name LIKE %s", $wpdb->esc_like( $marker ) . '%' ) );
    $terms = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->terms} WHERE slug LIKE %s", $wpdb->esc_like( $marker ) . '%' ) );
    $skus = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}usces_skus WHERE code = %s", $sku_code ) );
    echo "posts=$posts terms=$terms skus=$skus" . PHP_EOL;
} elseif ( 'cleanup' === $mode ) {
    $item = get_page_by_path( $slugs['item'], OBJECT, 'post' );
    if ( $item && 'item' === $item->post_mime_type && $marker === get_post_meta( $item->ID, $owner_meta, true ) ) {
        $wpdb->delete( $wpdb->prefix . 'usces_skus', array( 'post_id' => $item->ID, 'code' => $sku_code ) );
    }
    foreach ( array( $marker . '-info', $marker . '-cards', $marker . '-welcart', $slugs['post'], $slugs['item'], $slugs['lp'] ) as $slug ) {
        foreach ( array( 'page', 'post', 'lp' ) as $type ) {
            $found = get_page_by_path( $slug, OBJECT, $type );
            if ( $found && $marker === get_post_meta( $found->ID, $owner_meta, true ) ) { wp_delete_post( $found->ID, true ); }
        }
    }
    foreach ( array( 'category', 'year', 'order', 'area' ) as $key ) {
        $child = get_term_by( 'slug', $slugs[ $key ] . '-child', 'category' );
        if ( $child && $marker === get_term_meta( $child->term_id, $owner_meta, true ) ) { wp_delete_term( $child->term_id, 'category' ); }
        $term = get_term_by( 'slug', $slugs[ $key ], 'category' );
        if ( $term && $marker === get_term_meta( $term->term_id, $owner_meta, true ) ) { wp_delete_term( $term->term_id, 'category' ); }
    }
    $tag = get_term_by( 'slug', $slugs['tag'], 'post_tag' );
    if ( $tag && $marker === get_term_meta( $tag->term_id, $owner_meta, true ) ) { wp_delete_term( $tag->term_id, 'post_tag' ); }
    $remaining_posts = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_name LIKE %s", $wpdb->esc_like( $marker ) . '%' ) );
    $remaining_terms = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->terms} WHERE slug LIKE %s", $wpdb->esc_like( $marker ) . '%' ) );
    $remaining_skus = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}usces_skus WHERE code = %s", $sku_code ) );
    if ( $remaining_posts || $remaining_terms || $remaining_skus ) {
        WP_CLI::error( "Visual fixture cleanup left names requiring ownership inspection: posts=$remaining_posts terms=$remaining_terms skus=$remaining_skus" );
    }
    echo 'visual fixture cleaned' . PHP_EOL;
} else {
    throw new RuntimeException( 'Use create, status or cleanup' );
}
