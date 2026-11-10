<?php
if (!defined('ABSPATH')) exit;
?>

<div>
    <div class="table-wrap sosc-shortcode-table-wrap">
        <table class="table table-striped fs-7 sosc-shortcode-table">
            <thead>
                <tr>
                    <th scope="col" class="bg-head">種別</th>
                    <th scope="col" class="bg-head">名前</th>
                    <th scope="col" class="bg-head">ショートコード</th>
                </tr>
            </thead>
            <tbody>
                <!-- ================================================================== -->
                <tr>
                    <td rowspan="5">リスト</td>
                    <td>お知らせ01</td>
                    <td>
                        <span class="fw-bold">
                            [sosc_info_list01 slug=*カテゴリスラッグ list=件数 addClass=追加クラス]
                            <br>
                            説明：
                        </span>
                        特になし
                    </td>
                </tr>

                <tr>
                    <td>お知らせ02</td>
                    <td>
                        <span class="fw-bold">
                            [sosc_info_list02 slug=*カテゴリスラッグ list=件数 addClass=追加クラス]
                            <br>
                            説明：
                        </span>
                        年度(和暦)表示
                    </td>
                </tr>

                <tr>
                    <td>お知らせ03</td>
                    <td>
                        <span class="fw-bold">
                            [sosc_info_list03 slug=*カテゴリスラッグ list=件数 addClass=追加クラス orderSlug=発注元スラッグ親スラッグ yearSlug=年度親スラッグ areaSlug=エリア親スラッグ]
                            <br>                        
                            説明：
                        </span>
                        発注元・内容・エリア・年度を表示します。<br>
                        指定しない場合、親カテゴリに、order・area・yearが必要です。
                    </td>
                </tr>

                <tr>
                    <td>お知らせ04</td>
                    <td>
                        <span class="fw-bold">
                            [sosc_info_list04 slug=*カテゴリスラッグ list=件数 addClass=追加クラス]
                            <br>
                            説明：
                        </span>
                        ブログカード01を指定件数表示
                    </td>
                </tr>

                <tr>
                    <td>お知らせ05</td>
                    <td>
                        <span class="fw-bold">
                            [sosc_info_list05 slug=*カテゴリスラッグ list=件数 addClass=追加クラス]
                            <br>
                            説明：
                        </span>
                        特になし
                    </td>
                </tr>

                <!-- ================================================================== -->
                <tr>
                    <td>タグ</td>
                    <td>タグ01</td>
                    <td>
                        <span class="fw-bold">
                            [sosc_tag_view01 tag=*タグ名 addClass=追加クラス]
                            <br>
                            説明：
                        </span>
                        タグ名のスラッグ
                    </td>
                </tr>
                <!-- ================================================================== -->
                <tr>
                    <td rowspan="1">カード</td>
                    <td>ブログカード01</td>
                    <td>
                        <span class="fw-bold">
                            [sosc_blog_card01 slug=*スラッグ link=リンク先 addClass=追加クラス]
                            <br>
                            説明：
                        </span>
                        特になし
                    </td>
                </tr>
                <!-- ================================================================== -->
                <tr>
                    <td>その他</td>
                    <td>別のLPの記事を表示</td>
                    <td>
                        <span class="fw-bold">
                            [sosc_target_content slug=*LPスラッグ]
                            <br>
                            説明：
                        </span>
                        内容を共通化したい記事をLPに設定し、それを本ショートコードで読み込む
                    </td>
                </tr>

                <!-- ================================================================== -->
            </tbody>
        </table>
    </div>
</div>
