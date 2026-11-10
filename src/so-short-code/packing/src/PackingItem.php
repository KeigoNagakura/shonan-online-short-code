<?php
/**
 * BoxPacker item adapter.
 *
 * @package ShonanOnlineShortCode
 */

namespace ShonanOnline\ShortCode\Packing;

use DVDoug\BoxPacker\Item;

final class PackingItem implements Item {
	private $description;
	private $width;
	private $length;
	private $depth;
	private $weight;
	private $keep_flat;

	public function __construct( $description, $width, $length, $depth, $weight, $keep_flat ) {
		$this->description = (string) $description;
		$this->width       = (int) $width;
		$this->length      = (int) $length;
		$this->depth       = (int) $depth;
		$this->weight      = (int) $weight;
		$this->keep_flat   = (bool) $keep_flat;
	}
	public function getDescription(): string {
		return $this->description; }
	public function getWidth(): int {
		return $this->width; }
	public function getLength(): int {
		return $this->length; }
	public function getDepth(): int {
		return $this->depth; }
	public function getWeight(): int {
		return $this->weight; }
	public function getKeepFlat(): bool {
		return $this->keep_flat; }
}
