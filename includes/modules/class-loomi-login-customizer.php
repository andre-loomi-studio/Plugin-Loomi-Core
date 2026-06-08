<?php
/**
 * Customizer integration for the Loomi custom login.
 *
 * Registers a dedicated section/settings/controls in /wp-admin/customize.php and
 * wires live-preview JS so the wp-login.php iframe updates background color,
 * logo image and logo dimensions in real time as the admin tweaks the controls.
 *
 * The button in Settings → Loomi Studio → Custom Login deep-links here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Login_Customizer implements Loomi_Module {

	const SECTION = 'loomi_login';

	public static function register() : void {
		add_action( 'customize_register',     [ __CLASS__, 'register_customizer' ] );
		add_action( 'customize_preview_init', [ __CLASS__, 'enqueue_preview_js' ] );

		// wp-login.php loaded inside the Customizer iframe also needs the preview JS.
		add_action( 'login_enqueue_scripts', [ __CLASS__, 'enqueue_preview_on_login' ], 99 );
	}

	public static function register_customizer( WP_Customize_Manager $wp_customize ) : void {
		$opt = Plugin::OPTION_KEY;

		$wp_customize->add_section( self::SECTION, [
			'title'       => __( 'Tela de Login (Loomi)', 'loomi-studio-setup' ),
			'description' => __( 'Customize a tela de login com preview ao vivo. As mudanças só ficam permanentes quando você clicar em "Publicar".', 'loomi-studio-setup' ),
			'priority'    => 200,
			'capability'  => 'manage_options',
		] );

		// 1. Enable toggle.
		$wp_customize->add_setting( $opt . '[custom_login_enabled]', [
			'type'              => 'option',
			'capability'        => 'manage_options',
			'transport'         => 'refresh',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => false,
		] );
		$wp_customize->add_control( $opt . '[custom_login_enabled]', [
			'type'    => 'checkbox',
			'label'   => __( 'Ativar customização da tela de login', 'loomi-studio-setup' ),
			'section' => self::SECTION,
		] );

		// 2. Background color.
		$wp_customize->add_setting( $opt . '[custom_login_bg_color]', [
			'type'              => 'option',
			'capability'        => 'manage_options',
			'transport'         => 'refresh',
			'sanitize_callback' => 'sanitize_hex_color',
			'default'           => '#000000',
		] );
		$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $opt . '[custom_login_bg_color]', [
			'label'   => __( 'Cor de fundo', 'loomi-studio-setup' ),
			'section' => self::SECTION,
		] ) );

		// 3. Logo image (transport=refresh — image swap requires full reload to fetch URL).
		$wp_customize->add_setting( $opt . '[custom_login_logo_id]', [
			'type'              => 'option',
			'capability'        => 'manage_options',
			'transport'         => 'refresh',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		] );
		$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, $opt . '[custom_login_logo_id]', [
			'label'     => __( 'Logo', 'loomi-studio-setup' ),
			'section'   => self::SECTION,
			'mime_type' => 'image',
		] ) );

		// 4. Logo width.
		$wp_customize->add_setting( $opt . '[custom_login_logo_width]', [
			'type'              => 'option',
			'capability'        => 'manage_options',
			'transport'         => 'refresh',
			'sanitize_callback' => [ __CLASS__, 'sanitize_dim' ],
			'default'           => 320,
		] );
		$wp_customize->add_control( $opt . '[custom_login_logo_width]', [
			'type'        => 'number',
			'label'       => __( 'Largura do logo (px)', 'loomi-studio-setup' ),
			'description' => __( 'Entre 50 e 600.', 'loomi-studio-setup' ),
			'section'     => self::SECTION,
			'input_attrs' => [ 'min' => 50, 'max' => 600, 'step' => 1 ],
		] );

		// 5. Logo height.
		$wp_customize->add_setting( $opt . '[custom_login_logo_height]', [
			'type'              => 'option',
			'capability'        => 'manage_options',
			'transport'         => 'refresh',
			'sanitize_callback' => [ __CLASS__, 'sanitize_dim' ],
			'default'           => 120,
		] );
		$wp_customize->add_control( $opt . '[custom_login_logo_height]', [
			'type'        => 'number',
			'label'       => __( 'Altura do logo (px)', 'loomi-studio-setup' ),
			'description' => __( 'Entre 50 e 600.', 'loomi-studio-setup' ),
			'section'     => self::SECTION,
			'input_attrs' => [ 'min' => 50, 'max' => 600, 'step' => 1 ],
		] );
	}

	public static function sanitize_dim( $value ) : int {
		$value = (int) $value;
		if ( $value < 50 )  { return 50; }
		if ( $value > 600 ) { return 600; }
		return $value;
	}

	/**
	 * Enqueue the live-preview handler in the Customizer iframe (frontend pages).
	 * For wp-login.php, see enqueue_preview_on_login().
	 */
	public static function enqueue_preview_js() : void {
		wp_enqueue_script(
			'loomi-customize-preview',
			LOOMI_STUDIO_URL . 'assets/customize-preview.js',
			[ 'customize-preview', 'jquery' ],
			Plugin::version(),
			true
		);
	}

	/**
	 * When wp-login.php is loaded inside the Customizer iframe (messenger params present),
	 * make sure the preview core + our handler are both enqueued so live updates work there.
	 */
	public static function enqueue_preview_on_login() : void {
		if ( ! Loomi_Login::is_customize_preview_request() ) {
			return;
		}
		wp_enqueue_script( 'customize-preview' );
		wp_enqueue_script(
			'loomi-customize-preview',
			LOOMI_STUDIO_URL . 'assets/customize-preview.js',
			[ 'customize-preview', 'jquery' ],
			Plugin::version(),
			true
		);
	}
}
