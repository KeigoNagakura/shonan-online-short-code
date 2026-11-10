<?php
if (!defined('ABSPATH')) exit;
/*
 * 商品カテゴリ一覧を取得
 */
function SoScGetItemsPostOfCategory($slug, $list)
{
    global $wpdb;
	$slug = sanitize_title($slug);
	$list = sosc_sanitize_list_count($list);
    // Select文実
    $sqlTxt = "SELECT";
    $sqlTxt .= " posts.*";
    $sqlTxt .= "   FROM";
    $sqlTxt .= "     {$wpdb->prefix}terms AS terms";
    $sqlTxt .= "   LEFT JOIN";
    $sqlTxt .= "     {$wpdb->prefix}term_taxonomy AS taxonomy";
    $sqlTxt .= "   ON";
    $sqlTxt .= "     terms.term_id = taxonomy.term_id";
    $sqlTxt .= "   LEFT JOIN";
    $sqlTxt .= "     {$wpdb->prefix}term_relationships AS relationships";
    $sqlTxt .= "   ON";
    $sqlTxt .= "     taxonomy.term_taxonomy_id = relationships.term_taxonomy_id";
    $sqlTxt .= "   LEFT JOIN";
    $sqlTxt .= "     {$wpdb->prefix}posts AS posts";
    $sqlTxt .= "   ON";
    $sqlTxt .= "     relationships.object_id = posts.ID";
    $sqlTxt .= "   WHERE";
    $sqlTxt .= "     posts.post_status = 'publish'";
    $sqlTxt .= "     AND";
    $sqlTxt .= "     posts.post_type = 'post'";
    $sqlTxt .= "     AND";
    $sqlTxt .= "     posts.post_mime_type = 'item'";
    $sqlTxt .= "     AND";
	$sqlTxt .= "     taxonomy.taxonomy = 'category'";
    $sqlTxt .= "     AND";
	$sqlTxt .= "     terms.slug = %s";
    $sqlTxt .= "   ORDER BY posts.post_date DESC";
	$sqlTxt .= "   LIMIT %d";
    // 取得
	$res = $wpdb->get_results($wpdb->prepare($sqlTxt, $slug, $list), ARRAY_A);
    // 戻り値
    return $res;
}
/*
 * 商品POSTを取得
 */
function SoScGetItemPost($postName)
{
    global $wpdb;
	$postName = sanitize_title($postName);
    // Select文実
    $sqlTxt = "SELECT";
    $sqlTxt .= " *";
    $sqlTxt .= "   FROM";
    $sqlTxt .= "     {$wpdb->prefix}posts";
    $sqlTxt .= "   WHERE";
    $sqlTxt .= "     {$wpdb->prefix}posts.post_status = 'publish'";
    $sqlTxt .= "     AND";
    $sqlTxt .= "     {$wpdb->prefix}posts.post_type = 'post'";
    $sqlTxt .= "     AND";
    $sqlTxt .= "     {$wpdb->prefix}posts.post_mime_type = 'item'";
    $sqlTxt .= "     AND";
	$sqlTxt .= "     {$wpdb->prefix}posts.post_name = %s";
    // 取得
	$res = $wpdb->get_row($wpdb->prepare($sqlTxt, $postName), ARRAY_A);
    // 戻り値
    return $res;
}
/*
 * 商品SKUを取得
 */
function SoScGetItemSku($postId)
{
    global $wpdb;
	$postId = absint($postId);
    // Select文実
    $sqlTxt = "SELECT";
    $sqlTxt .= " *";
    $sqlTxt .= "   FROM";
    $sqlTxt .= "     {$wpdb->prefix}usces_skus";
    $sqlTxt .= "   WHERE";
	$sqlTxt .= "     {$wpdb->prefix}usces_skus.post_id = %d";
    $sqlTxt .= "   ORDER BY sort ASC";
    // 取得
	$res = $wpdb->get_results($wpdb->prepare($sqlTxt, $postId), ARRAY_A);
    // 戻り値
    return $res;
}
/*
 * 商品最大金額と最低金額を取得
 */
function SoScGetItemMinAndMaxPayment($postId)
{
    $skuList = SoScGetItemSku($postId);
	if (empty($skuList)) {
		return [
			'min' => null,
			'max' => null,
		];
	}
    // 最少と最大を取得
    $minPay = 9999999999;
    $maxPay = 0;
    foreach($skuList AS $val) {
        if ($minPay > (int)$val['price']) {
            $minPay = (int)$val['price'];
        }
        if ($maxPay < (int)$val['price']) {
            $maxPay = (int)$val['price'];
        }
    }
    $res = [
        'min' => $minPay,
        'max' => $maxPay
    ];
    // 戻り値
    return $res;
}
// /*
//  * POST情報を全て取得
//  */
// function SoScGetPostsAll($postId)
// {
//     global $wpdb;
//     // Select文実
//     $sqlTxt = "SELECT";
//     $sqlTxt .= " *";
//     $sqlTxt .= "    FROM";
//     $sqlTxt .= "        {$wpdb->prefix}posts";
//     $sqlTxt .= "    WHERE";
//     $sqlTxt .= "        {$wpdb->prefix}posts.post_status = 'publish'";
//     $sqlTxt .= "        AND";
//     $sqlTxt .= "        {$wpdb->prefix}posts.ID = $postId";
//     // 取得
//     $res = $wpdb->get_row($sqlTxt, ARRAY_A);
//     // メタ情報を取得・設定
//     // Select文実
//     $sqlTxt = "SELECT";
//     $sqlTxt .= " *";
//     $sqlTxt .= "    FROM";
//     $sqlTxt .= "        {$wpdb->prefix}postmeta";
//     $sqlTxt .= "    WHERE";
//     $sqlTxt .= "        {$wpdb->prefix}postmeta.post_id = $postId";
//     // 取得
//     $metaRes = $wpdb->get_row($sqlTxt, ARRAY_A);
//     $res['meta'] = $metaRes;
//     // サムネイルを追加
//     $res['thumbnail_path'] = get_the_post_thumbnail_url($postId);
//     if ($res['thumbnail_path'] === false) {
//         $res['thumbnail_path'] = SO_SC_IMG_PATH . 'no-image.jpg';
//     }
//     // 戻り値
//     return $res;
// }

// /**
//  * PostIdから投稿名(スラッグ)を取得
//  */
// function SoScGetPostNameToPostId($postId)
// {
//     global $wpdb;
//     // SQL作成
//     $sqlTxt = "";
//     $sqlTxt .= "SELECT";
//     $sqlTxt .= "  {$wpdb->prefix}posts.post_name";
//     $sqlTxt .= "  FROM";
//     $sqlTxt .= "    {$wpdb->prefix}posts";
//     $sqlTxt .= "  WHERE";
//     $sqlTxt .= "    {$wpdb->prefix}posts.ID = '{$postId}'";
//     $sqlTxt .= "    AND";
//     $sqlTxt .= "    {$wpdb->prefix}posts.post_status = 'publish'";
//     // データ取得
//     $postData = $wpdb->get_row($sqlTxt, ARRAY_A);
//     if (empty($postData)) {
//         $res = [
//             'status' => 'error'
//         ];
//     } else {
//         $res = [
//             'status' => 'success',
//             'postName' => $postData['post_name']
//         ];
//     }
//     // 戻り値
//     return $res;
// }

// /**
//  * 投稿名(スラッグ)からPostIdを取得
//  */
// function SoScGetPostIdToPostName($postName)
// {
//     global $wpdb;
//     // SQL作成
//     $sqlTxt = "";
//     $sqlTxt .= "SELECT";
//     $sqlTxt .= "  {$wpdb->prefix}posts.ID";
//     $sqlTxt .= "  FROM";
//     $sqlTxt .= "    {$wpdb->prefix}posts";
//     $sqlTxt .= "  WHERE";
//     $sqlTxt .= "    {$wpdb->prefix}posts.post_name = '{$postName}'";
//     $sqlTxt .= "    AND";
//     $sqlTxt .= "    {$wpdb->prefix}posts.post_status = 'publish'";
//     // データ取得
//     $postData = $wpdb->get_row($sqlTxt, ARRAY_A);
//     if (empty($postData)) {
//         $res = [
//             'status' => 'error'
//         ];
//     } else {
//         $res = [
//             'status' => 'success',
//             'postId' => $postData['ID']
//         ];
//     }
//     // 戻り値
//     return $res;
// }

// /*
//  * post_nameからアクティブなPostを取得
//  */
// function SoScGetActivePostName($postName)
// {
//     global $wpdb;
//     // Select文実行
//     $sqlTxt = "SELECT";
//     $sqlTxt .= " *";
//     $sqlTxt .= "  FROM";
//     $sqlTxt .= "    {$wpdb->prefix}posts";
//     $sqlTxt .= "  WHERE";
//     $sqlTxt .= "    {$wpdb->prefix}posts.post_status = 'publish'";
//     $sqlTxt .= "    AND";
//     $sqlTxt .= "    {$wpdb->prefix}posts.post_name = '{$postName}'";
//     // 取得
//     $getList = $wpdb->get_row($sqlTxt, ARRAY_A);
//     if (empty($getList)) {
//         $res = [
//             'status' => 'nothing'
//         ];
//     } else {
//         // 戻り値
//         $res = [
//             'status' => 'success',
//             'getData' => $getList
//         ];
//     }
//     // 戻り値
//     return $res;
// }

// /*
//  * 画像名から画像Postを取得
//  */
// function SoScGetImageIdToImagePath($imgPath)
// {
//     global $wpdb;
//     // Select文実行
//     $sqlTxt = "SELECT";
//     $sqlTxt .= " *";
//     $sqlTxt .= "  FROM";
//     $sqlTxt .= "    {$wpdb->prefix}posts";
//     $sqlTxt .= "  WHERE";
//     $sqlTxt .= "    {$wpdb->prefix}posts.post_status = 'inherit'";
//     $sqlTxt .= "    AND";
//     $sqlTxt .= "    {$wpdb->prefix}posts.guid LIKE '%{$imgPath}'";
//     // 取得
//     $getList = $wpdb->get_row($sqlTxt, ARRAY_A);
//     if (empty($getList)) {
//         $res = [
//             'status' => 'nothing'
//         ];
//     } else {
//         // 戻り値
//         $res = [
//             'status' => 'success',
//             'getData' => $getList
//         ];
//     }
//     // 戻り値
//     return $res;
// }

// /*
//  * PostIdと紐づく指定カテゴリがあるかチェック
//  */
// function SoScGetCategory($postId, $termId)
// {
//     global $wpdb;
//     // Select文実行
//     $sqlTxt = "SELECT";
//     $sqlTxt .= " *";
//     $sqlTxt .= "  FROM";
//     $sqlTxt .= "    {$wpdb->prefix}term_relationships";
//     $sqlTxt .= "  LEFT JOIN";
//     $sqlTxt .= "    {$wpdb->prefix}term_taxonomy";
//     $sqlTxt .= "    ON";
//     $sqlTxt .= "    {$wpdb->prefix}term_relationships.term_taxonomy_id = {$wpdb->prefix}term_taxonomy.term_id";
//     $sqlTxt .= "  WHERE";
//     $sqlTxt .= "    {$wpdb->prefix}term_relationships.object_id = {$postId}";
//     $sqlTxt .= "    AND";
//     $sqlTxt .= "    {$wpdb->prefix}term_relationships.term_taxonomy_id = {$termId}";
//     $sqlTxt .= "    AND";
//     $sqlTxt .= "    {$wpdb->prefix}term_taxonomy.taxonomy = 'category'";
//     // 取得
//     $getList = $wpdb->get_row($sqlTxt, ARRAY_A);
//     if (empty($getList)) {
//         $res = [
//             'status' => 'nothing'
//         ];
//     } else {
//         // 戻り値
//         $res = [
//             'status' => 'success',
//             'getData' => $getList
//         ];
//     }
//     // 戻り値
//     return $res;
// }

// /*
//  * PostIdと紐づく指定タグがあるかチェック
//  */
// function SoScGetTag($postId, $termId)
// {
//     global $wpdb;
//     // Select文実行
//     $sqlTxt = "SELECT";
//     $sqlTxt .= " *";
//     $sqlTxt .= "  FROM";
//     $sqlTxt .= "    {$wpdb->prefix}term_relationships";
//     $sqlTxt .= "  LEFT JOIN";
//     $sqlTxt .= "    {$wpdb->prefix}term_taxonomy";
//     $sqlTxt .= "    ON";
//     $sqlTxt .= "    {$wpdb->prefix}term_relationships.term_taxonomy_id = {$wpdb->prefix}term_taxonomy.term_id";
//     $sqlTxt .= "  WHERE";
//     $sqlTxt .= "    {$wpdb->prefix}term_relationships.object_id = {$postId}";
//     $sqlTxt .= "    AND";
//     $sqlTxt .= "    {$wpdb->prefix}term_relationships.term_taxonomy_id = {$termId}";
//     $sqlTxt .= "    AND";
//     $sqlTxt .= "    {$wpdb->prefix}term_taxonomy.taxonomy = 'post_tag'";
//     // 取得
//     $getList = $wpdb->get_row($sqlTxt, ARRAY_A);
//     if (empty($getList)) {
//         $res = [
//             'status' => 'nothing'
//         ];
//     } else {
//         // 戻り値
//         $res = [
//             'status' => 'success',
//             'getData' => $getList
//         ];
//     }
//     // 戻り値
//     return $res;
// }

// /**
//  * カテゴリ名からカテゴリ情報を取得
//  */
// function SoScGetCategoryToName($categoryName) {
//     global $wpdb;
//     // Select文実
//     $sqlTxt = "SELECT";
//     $sqlTxt .= " *";
//     $sqlTxt .= "  FROM";
//     $sqlTxt .= "    {$wpdb->prefix}terms";
//     $sqlTxt .= "  LEFT JOIN";
//     $sqlTxt .= "    {$wpdb->prefix}term_taxonomy";
//     $sqlTxt .= "    ON";
//     $sqlTxt .= "    {$wpdb->prefix}terms.term_id = {$wpdb->prefix}term_taxonomy.term_id";
//     $sqlTxt .= "  WHERE";
//     $sqlTxt .= "    {$wpdb->prefix}terms.name = '{$categoryName}'";
//     $sqlTxt .= "    AND";
//     $sqlTxt .= "    {$wpdb->prefix}term_taxonomy.taxonomy = 'category'";
//     // 取得
//     $res = $wpdb->get_row($sqlTxt, ARRAY_A);
//     if (empty($res)) {
//         $res = [
//             'status' => 'nothing'
//         ];
//     } else {
//         // 戻り値
//         $res = [
//             'status' => 'success',
//             'getData' => $res
//         ];
//     }
//     // 戻り値
//     return $res;
// }

// /**
//  * タグ名からタグ情報を取得
//  */
// function SoScGetTagToName($tagName) {
//     global $wpdb;
//     // Select文実
//     $sqlTxt = "SELECT";
//     $sqlTxt .= " *";
//     $sqlTxt .= "  FROM";
//     $sqlTxt .= "    {$wpdb->prefix}terms";
//     $sqlTxt .= "  LEFT JOIN";
//     $sqlTxt .= "    {$wpdb->prefix}term_taxonomy";
//     $sqlTxt .= "    ON";
//     $sqlTxt .= "    {$wpdb->prefix}terms.term_id = {$wpdb->prefix}term_taxonomy.term_id";
//     $sqlTxt .= "  WHERE";
//     $sqlTxt .= "    {$wpdb->prefix}terms.name = '{$tagName}'";
//     $sqlTxt .= "    AND";
//     $sqlTxt .= "    {$wpdb->prefix}term_taxonomy.taxonomy = 'post_tag'";
//     // 取得
//     $res = $wpdb->get_row($sqlTxt, ARRAY_A);
//     if (empty($res)) {
//         $res = [
//             'status' => 'nothing'
//         ];
//     } else {
//         // 戻り値
//         $res = [
//             'status' => 'success',
//             'getData' => $res
//         ];
//     }
//     // 戻り値
//     return $res;
// }
