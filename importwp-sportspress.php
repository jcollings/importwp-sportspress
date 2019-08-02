<?php
/*
Plugin Name: ImportWP Pro - SportsPress Importer
Plugin URI: https://www.importwp.com/
Description: Extend ImportWP Pro Custom fields to import into SportsPress.
Author: James Collings <james@jclabs.co.uk>
Author URI: http://www.jamescollings.co.uk
Version: 0.2.0
*/

/**
 * Class IWP_SportsPress
 */
class IWP_SportsPress{

	/**
	 * Plugin base directory
	 *
	 * @var string
	 */
	protected $plugin_dir = false;

	/**
	 * Plugin base url
	 *
	 * @var string
	 */
	protected $plugin_url = false;

	/**
	 * Current Plugin Version
	 *
	 * @var string
	 */
	protected $version = '0.2.0';

	/**
	 * Minimum required version if ImportWP
	 *
	 * @var string
	 */
	protected $min_version = '1.1.7';

	public function __construct() {
		register_activation_hook( __FILE__, array( $this, 'on_activation' ) );

		$this->plugin_dir = plugin_dir_path( __FILE__ );
		$this->plugin_url = plugins_url( '/', __FILE__ );

		add_action('admin_init', array( $this, 'on_init'));
	}

	private function is_importer_active(){
		return is_plugin_active('importwp-pro/importwp-pro.php');
	}

	public function on_activation(){

		if(!$this->is_importer_active() || ! version_compare( JCI()->get_version(), $this->min_version, '>=' )){
			echo 'Please make sure you have installed and activated <strong>ImportWP Pro - v' . esc_attr( $this->min_version ) . '</strong>' ;
			exit;
		}
	}

	public function on_init(){

		if(!is_plugin_active('sportspress/sportspress.php')){
			return;
		}

		require_once __DIR__ . '/libs/class-iwp-sportspress-base.php';
		require_once __DIR__ . '/libs/class-iwp-sportspress-player.php';
		require_once __DIR__ . '/libs/class-iwp-sportspress-team.php';
	}

}

new IWP_SportsPress();