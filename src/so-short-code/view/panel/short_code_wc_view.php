<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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
				<tr>
					<td><?php esc_html_e( '帳票', 'so-short-code' ); ?></td>
					<td><?php esc_html_e( '請求書・領収書', 'so-short-code' ); ?></td>
					<td><span class="fw-bold">[sosc_wc_documents]</span><br><?php esc_html_e( 'Welcartが有効な場合のみ使用できます。固定ページのショートコードブロックに配置してください。注文時のメールアドレスと注文番号でPDFを取得。領収書は入金済みのみ。キャンセル・見積りは対象外です。', 'so-short-code' ); ?></td>
				</tr>
				<!-- ================================================================== -->
				<tr>
					<td rowspan="3">リスト</td>
					<td>リスト01</td>
					<td>
						<span class="fw-bold">
							[sosc_wc_item_list01 slug=*カテゴリスラッグ list=件数 addClass=追加クラス]
							<br>
							説明：
						</span>
						Welcart用ブログカードリスト※Welcart必須
					</td>
				</tr>

				<tr>
					<td>リスト02</td>
					<td>
						<span class="fw-bold">
							[sosc_wc_item_list02 slug=*カテゴリスラッグ list=件数 addClass=追加クラス]
							<br>
							説明：
						</span>
						Welcart用ブログカードリスト※Welcart必須
					</td>
				</tr>

				<tr>
					<td>リスト03</td>
					<td>
						<span class="fw-bold">
							[sosc_wc_item_list03 slug=*カテゴリスラッグ list=件数 addClass=追加クラス]
							<br>
							説明：
						</span>
						Welcart用ブログカードリスト※Welcart必須
					</td>
				</tr>


				<!-- ================================================================== -->
				<tr>
					<td rowspan="3">カード</td>
					<td>WCカード01</td>
					<td>
						<span class="fw-bold">
							[sosc_wc_item_card01 post_name=*スラッグ addClass=追加クラス]
							<br>
							説明：
						</span>
						Welcart用ブログカード※Welcart必須
					</td>
				</tr>

				<tr>
					<td>WCカード02</td>
					<td>
						<span class="fw-bold">
							[sosc_wc_item_card02 post_name=*スラッグ addClass=追加クラス]
							<br>
							説明：
						</span>
						Welcart用ブログカード※Welcart必須
					</td>
				</tr>

				<tr>
					<td>WCカード03</td>
					<td>
						<span class="fw-bold">
							[sosc_wc_item_card03 post_name=*スラッグ addClass=追加クラス]
							<br>
							説明：
						</span>
						Welcart用ブログカード※Welcart必須
					</td>
				</tr>

				<!-- ================================================================== -->
			</tbody>
		</table>
	</div>
</div>
