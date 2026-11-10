<?php
if (!defined('ABSPATH')) exit;
// /**
//  * Postsテーブルにデータがあれば上書き・なければ挿入(PostNameで検索)
//  */
// function SoScSavePostsTableOfPostName($wpdb, $setPostData)
// {
//     // 現在の日時
//     $nowDateTime = date('Y-m-d H:i:s');
//     // 書き込みデータ補完用
//     $savePost = [
//         'post_author' => 1,
//         'post_date' => $nowDateTime,
//         'post_date_gmt' => $nowDateTime,
//         'post_title' => '',
//         'post_excerpt' => '',
//         'post_status' => 'publish',
//         'comment_status' => 'closed',
//         'ping_status' => 'closed',
//         'post_name' => '',
//         'post_modified' => $nowDateTime,
//         'post_modified_gmt' => $nowDateTime,
//         'post_parent' => 0,
//         'menu_order' => 0,
//         'post_type' => 'post',
//         'comment_count' => 0
//     ];
//     // 不足データを補完
//     foreach ($setPostData as $key => $val) {
//         $savePost[$key] = $val;
//     }
//     try {
//         // 指定データがあるか確認
//         $targetPost = SoScGetActivePostName($savePost['post_name']);
//         if ($targetPost['status'] == 'nothing') {
//             // データなし
//             $result = $wpdb->insert(
//                 $wpdb->posts,
//                 $savePost
//             );
//             // 確認用
//             if ($result === false || empty($wpdb->insert_id)) {
//                 $wpdb->query('ROLLBACK');
//                 return  ['status' => 'error'];
//             } else {
//                 $postId = $wpdb->insert_id;
//             }
//         } else {
//             // データあり
//             // 取得データに書き込みデータを上書き
//             $targetPost = $targetPost['getData'];
//             foreach ($savePost as $key => $val) {
//                 $targetPost[$key] = $val;
//             }
//             // 更新条件
//             $where = ['ID' => $targetPost['ID']];
//             // テーブルを更新
//             $updated = $wpdb->update(
//                 $wpdb->posts,
//                 $targetPost,
//                 $where
//             );
//             if ($updated === false) {
//                 $wpdb->query('ROLLBACK');
//                 return  ['status' => 'error'];
//             } else {
//                 $postId = $targetPost['ID'];
//             }
//         }
//         // guidをアップデート
//         // 更新条件
//         $where = ['ID' => $postId];
//         // テーブルを更新
//         $setGuid = ['guid' => home_url() . '/?page_id=' . $postId];
//         $updated = $wpdb->update(
//             $wpdb->posts,
//             $setGuid,
//             $where
//         );
//         if ($updated === false) {
//             $wpdb->query('ROLLBACK');
//             return  ['status' => 'error'];
//         } else {
//             return  ['status' => 'success', 'postId' => $postId];
//         }
//     } catch (Exception $e) {
//         // エラー発生時にロールバック
//         $wpdb->query('ROLLBACK');
//         return  ['status' => 'error'];
//     }
// }

// /**
//  * カテゴリを紐づけ
//  */
// function SoScSaveCategoryRelation($wpdb, $postId, $categoryId)
// {
//     $getCat = SoScGetCategory($postId, $categoryId);
//     if ($getCat['status'] == 'nothing') {
//         // データがなければ作成
//         $saveCat = [
//             'object_id' => (int)$postId,
//             'term_taxonomy_id' => (int)$categoryId,
//             'term_order' => 0
//         ];
//         try {
//             // データなし
//             $result = $wpdb->insert(
//                 $wpdb->term_relationships,
//                 $saveCat
//             );
//             // 確認用
//             if ($result === false) {
//                 $wpdb->query('ROLLBACK');
//                 return  ['status' => 'error'];
//             }
//         } catch (Exception $e) {
//             // エラー発生時にロールバック
//             $wpdb->query('ROLLBACK');
//             return  ['status' => 'error'];
//         }
//     }
//     return  ['status' => 'success'];
// }

// /**
//  * タグを紐づけ
//  */
// function SoScSaveTagRelation($wpdb, $postId, $categoryId)
// {
//     $getCat = SoScGetTag($postId, $categoryId);
//     if ($getCat['status'] == 'nothing') {
//         // データがなければ作成
//         $saveCat = [
//             'object_id' => (int)$postId,
//             'term_taxonomy_id' => (int)$categoryId,
//             'term_order' => 0
//         ];
//         try {
//             // データなし
//             $result = $wpdb->insert(
//                 $wpdb->term_relationships,
//                 $saveCat
//             );
//             // 確認用
//             if ($result === false) {
//                 $wpdb->query('ROLLBACK');
//                 return  ['status' => 'error'];
//             }
//         } catch (Exception $e) {
//             // エラー発生時にロールバック
//             $wpdb->query('ROLLBACK');
//             return  ['status' => 'error'];
//         }
//     }
//     return  ['status' => 'success'];
// }

/**
 * 和暦に変換
 */
function wareki($year) {

    $eras = array(
        array('year' => 2018, 'name' => '令和'),
        array('year' => 1988, 'name' => '平成'),
        array('year' => 1925, 'name' => '昭和'),
        array('year' => 1911, 'name' => '大正'),
        array('year' => 1867, 'name' => '明治')
    );

    foreach($eras as $era) {
        if ($year > $era['year']) {
            $era_year = $year - $era['year'];
            return $era['name'] . ($era_year === 1 ? '元年' : $era_year . '年');
        }
    }

    return null;
}
