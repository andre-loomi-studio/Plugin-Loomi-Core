<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Loomi_Settings_Tab {
	public function slug() : string;
	public function label() : string;
	public function render( array $settings ) : void;
}
