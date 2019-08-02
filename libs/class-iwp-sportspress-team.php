<?php
/**
 * ImportWP Pro - SportsPress Team Integration
 *
 * @author James Collings<james@jclabs.co.uk>
 * @version 0.1
 * @since 02/08/2019
 */

/**
 * Class IWP_SportsPress_Team
 */
class IWP_SportsPress_Team extends IWP_SportsPress_Base {

	private $leagues = array();
	private $seasons = array();
	private $installed = false;

	public function __construct() {

		add_filter( 'iwp/custom_fields/list', array( $this, 'iwp_custom_fields_list' ), 20 );
		add_action( 'jci/before_import', array( $this, 'install_hooks' ) );
	}

	public function install_hooks() {

		if ( $this->installed || ! $this->is_template_active( JCI()->importer->get_template(), 'post', 'sp_team' ) ) {
			return;
		}

		// Install Hooks
		add_filter( 'iwp/before_mapper_process', array( $this, 'iwp_before_mapper_process' ), 20 );
		add_action( 'iwp_after_row_save', array( $this, 'iwp_after_row_save' ), 10, 3 );

		$this->installed = true;
	}

	/**
	 * Populate custom fields list
	 *
	 * @param array $list
	 *
	 * @return array
	 */
	function iwp_custom_fields_list( $list ) {

		if ( $this->is_template_active( JCI()->importer->get_template(), 'post', 'sp_team' ) ) {

			$fields = array(
				'sp_league'       => __( 'Leagues', 'sportspress' ),
				'sp_season'       => __( 'Seasons', 'sportspress' ),
				'sp_url'          => __( 'Site URL', 'sportspress' ),
				'sp_abbreviation' => __( 'Abbreviation', 'sportspress' ),
				'sp_venue'        => __( 'Home', 'sportspress' ),
				'sp_short_name'   => __( 'Short Name', 'sportspress' ),
			);

			$list['SportsPress Team'] = $fields;

		}

		return $list;
	}

	/**
	 * Check to see if we are using the correct post_type sp_player
	 *
	 * @param IWP_Template $template
	 * @param \ImportWP\Importer\ParsedData $data
	 * @param \ImportWP\Importer $importer
	 */
	function iwp_after_row_save( IWP_Template $template, \ImportWP\Importer\ParsedData $data, \ImportWP\Importer $importer ) {

		$this->process_virtual_fields( $data->getValue( 'ID' ) );
	}

	/**
	 * Process complex custom fields
	 *
	 * @param int $team_id
	 */
	function process_virtual_fields( $team_id ) {

		if ( false !== $this->leagues ) {
			wp_set_object_terms( $team_id, $this->leagues, 'sp_league', false );
		}

		if ( false !== $this->seasons ) {
			wp_set_object_terms( $team_id, $this->seasons, 'sp_season', false );
		}
	}

	/**
	 * Capture values of custom fields that require more processing that updating a single post_meta value
	 *
	 * @param \ImportWP\Importer\ParsedData $data
	 *
	 * @return \ImportWP\Importer\ParsedData
	 */
	function iwp_before_mapper_process( $data ) {

		$fields_to_clear = array(
			'sp_league',
			'sp_season',
		);

		$delimiter = apply_filters( 'iwp/delimiter', ',' );
		$delimiter = apply_filters( 'iwp/delimiter/sportspress', $delimiter );

		// Update leagues
		$this->leagues = $this->get_taxonomy_value( 'sp_league', $delimiter, $data );

		// Update seasons
		$this->seasons = $this->get_taxonomy_value( 'sp_season', $delimiter, $data );

		$custom_fields = $data->getData( 'custom_fields' );

		// clear virtual fields so they dont get set
		foreach ( $fields_to_clear as $key ) {
			if ( isset( $custom_fields[ $key ] ) ) {
				unset( $custom_fields[ $key ] );
			}
		}

		$data->replace( $custom_fields, 'custom_fields' );

		return $data;
	}
}

new IWP_SportsPress_Team();