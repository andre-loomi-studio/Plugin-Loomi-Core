<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Loomi_Module {
	/**
	 * Register hooks/filters. Called once during plugins_loaded.
	 */
	public static function register() : void;
}
