<?php
/**
 * Welcart packing calculator.
 *
 * @package ShonanOnlineShortCode
 */

namespace ShonanOnline\ShortCode\Packing;

use DVDoug\BoxPacker\Packer;
use DVDoug\BoxPacker\BoxList;

final class Calculator {
	const BUDGET = 2000;

	public function calculate( $cart, $entry ) {
		global $wpdb, $usces;
		if ( ! class_exists( '\\DVDoug\\BoxPacker\\Packer' ) ) {
			return new \WP_Error( 'packing_library_missing', '現在、配送方法を計算できません。時間をおいて再度お試しください。' );
		}
		$delivery_id    = isset( $entry['order']['delivery_method'] ) ? absint( $entry['order']['delivery_method'] ) : 0;
		$pref           = isset( $entry['delivery']['pref'] ) ? $entry['delivery']['pref'] : ( isset( $entry['customer']['pref'] ) ? $entry['customer']['pref'] : '' );
		$country        = isset( $entry['delivery']['country'] ) && $entry['delivery']['country'] ? $entry['delivery']['country'] : ( isset( $entry['customer']['country'] ) ? $entry['customer']['country'] : 'JP' );
		$items          = array();
		$item_rows      = array();
		$quantity       = 0;
		$total_weight   = 0;
		$cool           = false;
		$individual_fee = 0.0;
		$sku_cache      = array();
		$config_cache   = array();
		$product_cache  = array();

		foreach ( (array) $cart as $row ) {
			$post_id = isset( $row['post_id'] ) ? absint( $row['post_id'] ) : 0;
			$qty     = isset( $row['quantity'] ) ? absint( $row['quantity'] ) : 0;
			if ( ! $post_id || ! $qty || ( method_exists( $usces, 'getItemDivision' ) && 'shipped' !== $usces->getItemDivision( $post_id ) ) ) {
				continue;
			}
			if ( method_exists( $usces, 'getItemIndividualSCharge' ) && $usces->getItemIndividualSCharge( $post_id ) ) {
				$charge_id       = $this->individual_charge_id( $delivery_id, $post_id );
				$individual_fee += $qty * $this->shipping_amount( $charge_id, $country, $pref );
				continue;
			}
			$sku_code = isset( $row['sku'] ) ? urldecode( $row['sku'] ) : '';
			$sku_key  = $post_id . ':' . $sku_code;
			if ( ! array_key_exists( $sku_key, $sku_cache ) ) {
				$sku_cache[ $sku_key ] = $wpdb->get_row( $wpdb->prepare( "SELECT meta_id,code,weight FROM {$wpdb->prefix}usces_skus WHERE post_id=%d AND code=%s LIMIT 1", $post_id, $sku_code ) );
			}
			$sku = $sku_cache[ $sku_key ];
			if ( ! $sku ) {
				return new \WP_Error( 'sku_not_found', '現在、この商品を含むご注文の配送方法を確定できません。対象商品をカートから外すか、店舗へお問い合わせください。' );
			}
			$config_key = $post_id . ':' . (int) $sku->meta_id;
			if ( ! array_key_exists( $config_key, $config_cache ) ) {
				$config_cache[ $config_key ] = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Database::table( 'skus' ) . ' WHERE post_id=%d AND sku_meta_id=%d', $post_id, $sku->meta_id ) );
			}
			$config = $config_cache[ $config_key ];
			if ( ! $config || $config->width < 1 || $config->length < 1 || $config->depth < 1 ) {
				return new \WP_Error( 'sku_configuration_missing', '現在、この商品を含むご注文の配送方法を確定できません。対象商品をカートから外すか、店舗へお問い合わせください。' );
			}
			if ( ! array_key_exists( $post_id, $product_cache ) ) {
				$product_cache[ $post_id ] = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Database::table( 'products' ) . ' WHERE post_id=%d', $post_id ) );
			}
			$product = $product_cache[ $post_id ];
			$margin  = '1' === (string) Settings::get( 'use_margin' ) && $config->margin_required ? (int) $config->margin : 0;
			if ( $margin < 0 || $margin > 1000 || $config->width + 2 * $margin > 10000 || $config->length + 2 * $margin > 10000 || $config->depth + 2 * $margin > 10000 || ( '1' === (string) Settings::get( 'use_margin' ) && $config->margin_required && $margin < 1 ) ) {
				return new \WP_Error( 'sku_configuration_missing', '現在、この商品を含むご注文の配送方法を確定できません。対象商品をカートから外すか、店舗へお問い合わせください。' );
			}
			$weight = '1' === (string) Settings::get( 'use_weight' ) ? absint( $sku->weight ) : 0;
			if ( '1' === (string) Settings::get( 'use_weight' ) && $weight < 1 ) {
				return new \WP_Error( 'sku_weight_missing', '現在、この商品を含むご注文の配送方法を確定できません。対象商品をカートから外すか、店舗へお問い合わせください。' );
			}
			$keep_flat     = '1' === (string) Settings::get( 'use_keep_flat' ) && is_object( $product ) && 1 === (int) $product->keep_flat;
			$item          = new PackingItem( $post_id . ':' . $sku->meta_id, $config->width + 2 * $margin, $config->length + 2 * $margin, $config->depth + 2 * $margin, $weight, $keep_flat );
			$items[]       = array(
				'item' => $item,
				'qty'  => $qty,
			);
			$item_rows[]   = array(
				'post_id'        => $post_id,
				'sku_meta_id'    => (int) $sku->meta_id,
				'sku_code'       => $sku_code,
				'quantity'       => $qty,
				'base_width'     => (int) $config->width,
				'base_length'    => (int) $config->length,
				'base_depth'     => (int) $config->depth,
				'margin'         => $margin,
				'packing_width'  => (int) $config->width + 2 * $margin,
				'packing_length' => (int) $config->length + 2 * $margin,
				'packing_depth'  => (int) $config->depth + 2 * $margin,
				'unit_weight'    => $weight,
				'keep_flat'      => '1' === (string) Settings::get( 'use_keep_flat' ) && $product && $product->keep_flat,
				'cool_required'  => '1' === (string) Settings::get( 'use_cool' ) && $product && $product->cool_required,
			);
			$quantity     += $qty;
			$total_weight += $qty * $weight;
			$cool          = $cool || ( '1' === (string) Settings::get( 'use_cool' ) && $product && $product->cool_required );
		}

		if ( 0 === $quantity ) {
			return array( 'status' => 'not_applicable' );
		}
		if ( $quantity > (int) Settings::get( 'quantity_limit' ) ) {
			return new \WP_Error( 'quantity_limit', sprintf( 'このご注文では、対象商品を合計%d点まで同時に購入できます。数量を減らすか、注文を分けてください。', (int) Settings::get( 'quantity_limit' ) ) );
		}
		if ( count( $items ) > (int) Settings::get( 'sku_limit' ) ) {
			return $this->individual( 'sku_limit', $cart, $entry, $total_weight );
		}
		if ( '1' === (string) Settings::get( 'use_weight' ) && $total_weight > (int) Settings::get( 'weight_limit' ) ) {
			return $this->individual( 'weight_over', $cart, $entry, $total_weight );
		}

		$boxes = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Database::table( 'boxes' ) . ' WHERE enabled=1 AND delivery_method_id=%d AND inner_width BETWEEN 1 AND 10000 AND inner_length BETWEEN 1 AND 10000 AND inner_depth BETWEEN 1 AND 10000 ORDER BY (inner_width * inner_length * inner_depth),priority,id', $delivery_id ) );
		$boxes = array_values(
			array_filter(
				$boxes,
				function ( $box ) use ( $country, $pref ) {
					global $usces;
					if ( false === $usces->get_delivery_method_index( $box->delivery_method_id ) ) {
						return false;
					}
					try {
						$this->shipping_amount( $box->shipping_charge_id, $country, $pref );
						return true;
					} catch ( \RuntimeException $exception ) {
						return false;
					}
				}
			)
		);
		if ( $cool ) {
			$boxes = array_values( array_filter( $boxes, array( $this, 'is_cool_box' ) ) );
		}
		if ( ! $boxes ) {
			return new \WP_Error( 'no_available_box', '現在、この配送先で利用できる配送方法を確定できません。配送方法を変更するか、店舗へお問い合わせください。' );
		}
		$runs   = count( $boxes ) + 1;
		$budget = $quantity * $runs * ( 1 + (int) ceil( count( $items ) / 20 ) );
		if ( $budget > self::BUDGET ) {
			return $this->individual( 'calculation_budget', $cart, $entry, $total_weight );
		}

		try {
			$box_map = array();
			foreach ( $boxes as $box ) {
				$box_map[ $box->id ] = $box;
			}
			$candidates = array();
			$attempts   = array();
			$executions = 0;
			$this->try_candidate( 'mixed_box_types', $items, $boxes, $box_map, $country, $pref, $individual_fee, $candidates, $attempts, $executions, $runs );
			foreach ( $boxes as $box ) {
				$this->try_candidate( 'single_box_type_' . (int) $box->id, $items, array( $box ), $box_map, $country, $pref, $individual_fee, $candidates, $attempts, $executions, $runs );
			}
			if ( ! $candidates ) {
				return new \WP_Error( 'item_does_not_fit', '現在、この商品を含むご注文の配送方法を確定できません。対象商品をカートから外すか、店舗へお問い合わせください。' );
			}
			usort( $candidates, array( $this, 'compare_candidates' ) );
			$selected = $candidates[0];
			return array(
				'status'          => 'packed',
				'shipping'        => $selected['shipping'],
				'boxes'           => $selected['boxes'],
				'items'           => $item_rows,
				'weight'          => $total_weight,
				'cool'            => $cool,
				'budget'          => $budget,
				'candidate_count' => count( $candidates ),
				'attempts'        => $attempts,
				'executions'      => $executions,
			);
		} catch ( \Throwable $exception ) {
			Database::log( 'packing_exception', array( 'class' => get_class( $exception ) ) );
			return $this->individual( 'packing_error', $cart, $entry, $total_weight );
		}
	}

	private function try_candidate( $mode, $items, $boxes, $box_map, $country, $pref, $individual_fee, &$candidates, &$attempts, &$executions, $runs ) {
		if ( $executions >= $runs ) {
			$attempts[] = array(
				'mode'   => $mode,
				'status' => 'budget_reserved',
			);
			return false;
		}
		++$executions;
		try {
			$packed = $this->pack( $items, $boxes );
		} catch ( \DVDoug\BoxPacker\NoBoxesAvailableException $exception ) {
			$attempts[] = array(
				'mode'    => $mode,
				'status'  => 'does_not_fit',
				'box_ids' => wp_list_pluck( $boxes, 'id' ),
			);
			return false;
		}
		$candidate = $this->describe_candidate( $packed, $box_map, $country, $pref, $individual_fee );
		$key       = hash(
			'sha256',
			wp_json_encode(
				array_map(
					function ( $box ) {
						return array(
							'id'         => $box['id'],
							'placements' => $box['placements'],
						);
					},
					$candidate['boxes']
				)
			)
		);
		if ( isset( $candidates[ $key ] ) ) {
			$attempts[] = array(
				'mode'   => $mode,
				'status' => 'duplicate',
			);
			return false;
		}
		$candidate['mode']  = $mode;
		$candidates[ $key ] = $candidate;
		$attempts[]         = array(
			'mode'   => $mode,
			'status' => 'completed',
		);
		return true;
	}

	private function pack( $items, $boxes ) {
		$packer = new Packer();
		$packer->setMaxBoxesToBalanceWeight( 0 );
		foreach ( $items as $set ) {
			$packer->addItem( $set['item'], $set['qty'] );
		}
		$packing_boxes = array_map(
			function ( $box ) {
				return new PackingBox( $box );
			},
			$boxes
		);
		$packer->setBoxes( BoxList::fromArray( $packing_boxes, true ) );
		return $packer->pack();
	}

	private function describe_candidate( $packed, $box_map, $country, $pref, $individual_fee ) {
		global $usces;
		$result_boxes = array();
		$shipping     = $individual_fee;
		$empty_volume = 0;
		$total_volume = 0;
		$priority     = 0;
		foreach ( $packed as $packed_box ) {
			$box_id        = (int) $packed_box->getBox()->getReference();
			$box           = $box_map[ $box_id ];
			$amount        = $this->shipping_amount( $box->shipping_charge_id, $country, $pref );
			$shipping     += $amount;
			$total_volume += (int) $box->inner_width * (int) $box->inner_length * (int) $box->inner_depth;
			$empty_volume += ( (int) $box->inner_width * (int) $box->inner_length * (int) $box->inner_depth ) - $packed_box->getUsedVolume();
			$priority     += (int) $box->priority;
			$method_index  = $usces->get_delivery_method_index( $box->delivery_method_id );
			$charge_index  = $usces->get_shipping_charge_index( $box->shipping_charge_id );
			$placements    = array();
			foreach ( $packed_box->getItems() as $packed_item ) {
				$placements[] = array(
					'reference' => $packed_item->getItem()->getDescription(),
					'x'         => $packed_item->getX(),
					'y'         => $packed_item->getY(),
					'z'         => $packed_item->getZ(),
					'width'     => $packed_item->getWidth(),
					'length'    => $packed_item->getLength(),
					'depth'     => $packed_item->getDepth(),
				);
			}
			$result_boxes[] = array(
				'id'                   => $box_id,
				'name'                 => $box->name,
				'shipping'             => $amount,
				'weight'               => $packed_box->getWeight(),
				'delivery_method_id'   => (int) $box->delivery_method_id,
				'delivery_method_name' => false !== $method_index && isset( $usces->options['delivery_method'][ $method_index ]['name'] ) ? $usces->options['delivery_method'][ $method_index ]['name'] : '',
				'shipping_charge_id'   => (int) $box->shipping_charge_id,
				'shipping_charge_name' => false !== $charge_index && isset( $usces->options['shipping_charge'][ $charge_index ]['name'] ) ? $usces->options['shipping_charge'][ $charge_index ]['name'] : '',
				'placements'           => $placements,
			);
		}
		return array(
			'shipping'     => $shipping,
			'total_volume' => $total_volume,
			'empty_volume' => $empty_volume,
			'box_count'    => count( $result_boxes ),
			'priority'     => $priority,
			'boxes'        => $result_boxes,
		);
	}

	public function compare_candidates( $a, $b ) {
		foreach ( array( 'box_count', 'total_volume', 'shipping', 'priority' ) as $key ) {
			if ( $a[ $key ] < $b[ $key ] ) {
				return -1; }
			if ( $a[ $key ] > $b[ $key ] ) {
				return 1; }
		}
		return strcmp( wp_json_encode( wp_list_pluck( $a['boxes'], 'id' ) ), wp_json_encode( wp_list_pluck( $b['boxes'], 'id' ) ) );
	}

	private function individual( $reason, $cart, $entry, $weight ) {
		$id = absint( Settings::get( 'individual_shipping_id' ) );
		if ( ! $id ) {
			return new \WP_Error( 'individual_shipping_missing', '現在、このご注文の配送方法を確定できません。店舗へお問い合わせください。' );
		}
		$pref    = isset( $entry['delivery']['pref'] ) ? $entry['delivery']['pref'] : ( isset( $entry['customer']['pref'] ) ? $entry['customer']['pref'] : '' );
		$country = isset( $entry['delivery']['country'] ) && $entry['delivery']['country'] ? $entry['delivery']['country'] : ( isset( $entry['customer']['country'] ) ? $entry['customer']['country'] : 'JP' );
		return array(
			'status'   => 'individual',
			'shipping' => $this->shipping_amount( $id, $country, $pref ),
			'reason'   => $reason,
			'weight'   => $weight,
			'boxes'    => array(),
		);
	}

	private function shipping_amount( $charge_id, $country, $pref ) {
		global $usces;
		$index = $usces->get_shipping_charge_index( $charge_id );
		if ( false === $index || ! isset( $usces->options['shipping_charge'][ $index ][ $country ][ $pref ] ) ) {
			throw new \RuntimeException( 'Shipping charge is not configured.' );
		}
		return (float) $usces->options['shipping_charge'][ $index ][ $country ][ $pref ];
	}

	private function individual_charge_id( $delivery_id, $post_id ) {
		global $usces;
		$method_index = $usces->get_delivery_method_index( $delivery_id );
		if ( false !== $method_index && isset( $usces->options['delivery_method'][ $method_index ]['charge'] ) && -1 !== (int) $usces->options['delivery_method'][ $method_index ]['charge'] ) {
			return (int) $usces->options['delivery_method'][ $method_index ]['charge'];
		}
		return (int) $usces->getItemShippingCharge( $post_id );
	}

	public function is_cool_box( $box ) {
		global $usces;
		$index = $usces->get_delivery_method_index( $box->delivery_method_id );
		return false !== $index && ! empty( $usces->options['delivery_method'][ $index ]['cool_category'] );
	}
}
