<?php
/**
 * Monthly backup result panel.
 *
 * @package ShonanOnlineShortCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( \ShonanOnline\ShortCode\Backup\Plugin::enabled() ) {
	\ShonanOnline\ShortCode\Backup\Admin::render_status();
	require __DIR__ . '/explanation_view.php';
}
