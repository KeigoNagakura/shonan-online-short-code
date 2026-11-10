<?php
/**
 * BoxPacker box adapter.
 *
 * @package ShonanOnlineShortCode
 */

namespace ShonanOnline\ShortCode\Packing;

use DVDoug\BoxPacker\Box;

final class PackingBox implements Box {
	private $data;
	public function __construct( $data ) {
		$this->data = $data; }
	public function getReference(): string {
		return (string) $this->data->id; }
	public function getOuterWidth(): int {
		return (int) $this->data->inner_width; }
	public function getOuterLength(): int {
		return (int) $this->data->inner_length; }
	public function getOuterDepth(): int {
		return (int) $this->data->inner_depth; }
	public function getEmptyWeight(): int {
		return 0; }
	public function getInnerWidth(): int {
		return (int) $this->data->inner_width; }
	public function getInnerLength(): int {
		return (int) $this->data->inner_length; }
	public function getInnerDepth(): int {
		return (int) $this->data->inner_depth; }
	public function getMaxWeight(): int {
		return PHP_INT_MAX; }
}
