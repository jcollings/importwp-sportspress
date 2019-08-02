<?php
/**
 * ImportWP Pro - SportsPress Player Integration
 *
 * @author James Collings<james@jclabs.co.uk>
 * @version 0.1
 * @since 02/08/2019
 */

/**
 * Class IWP_SportsPress_Player
 */
class IWP_SportsPress_Player {

	private $positions = array();
	private $leagues = array();
	private $seasons = array();
	private $metrics = array();
	private $past_teams = array();
	private $current_teams = array();

	public function __construct() {
		add_filter( 'iwp/before_mapper_process', array( $this, 'iwp_before_mapper_process' ), 20 );
		add_filter( 'iwp/custom_fields/list', array( $this, 'iwp_custom_fields_list' ), 20 );
		add_filter( 'iwp/custom_field', array( $this, 'iwp_custom_field' ), 10, 4 );
		add_action( 'iwp_after_row_save', array( $this, 'iwp_after_row_save' ), 10, 3 );
	}

	/**
	 * Check to see if we are using the correct post_type sp_player
	 *
	 * @param IWP_Template $template
	 * @param \ImportWP\Importer\ParsedData $data
	 * @param \ImportWP\Importer $importer
	 */
	function iwp_after_row_save( IWP_Template $template, \ImportWP\Importer\ParsedData $data, \ImportWP\Importer $importer ) {

		if('post' === $template->get_import_type() && 'sp_player' === $template->get_import_type_name()){
			$this->process_virtual_fields( $data->getValue( 'ID' ) );
		}
	}

	/**
	 * Process complex custom fields
	 *
	 * @param int $player_id
	 */
	function process_virtual_fields( $player_id ) {

		wp_set_object_terms( $player_id, $this->positions, 'sp_position', false );
		wp_set_object_terms( $player_id, $this->leagues, 'sp_league', false );
		wp_set_object_terms( $player_id, $this->seasons, 'sp_season', false );

		if ( ! empty( $this->current_teams ) ) {
			foreach ( $this->current_teams as $team ) {
				$team_id = $this->store_team( $team );
				add_post_meta( $player_id, 'sp_current_team', $team_id );
				add_post_meta( $player_id, 'sp_team', $team_id );
			}
		}

		if ( ! empty( $this->past_teams ) ) {
			foreach ( $this->past_teams as $team ) {
				$team_id = $this->store_team( $team );
				add_post_meta( $player_id, 'sp_team', $team_id );
			}
		}

		$metrics = $this->metrics;
		if ( ! empty( $metrics ) ) {
			update_post_meta( $player_id, 'sp_metrics', $metrics );
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
			'sp_position',
			'sp_league',
			'sp_season',
			'sp_past_team',
			'sp_current_team'
		);

		$delimiter = apply_filters('iwp/delimiter', ',');
		$delimiter = apply_filters('iwp/delimiter/sportspress', $delimiter);

		// Update positions
		$positions_delimiter = apply_filters('iwp/delimiter/sp_position', $delimiter);
		$this->positions = explode( $positions_delimiter, $data->getValue( 'sp_position', 'custom_fields' ) );

		// Update leagues
		$leagues_delimiter = apply_filters('iwp/delimiter/sp_league', $delimiter);
		$this->leagues = explode( $leagues_delimiter, $data->getValue( 'sp_league', 'custom_fields' ) );

		// Update seasons
		$seasons_delimiter = apply_filters('iwp/delimiter/sp_season', $delimiter);
		$this->seasons = explode( $seasons_delimiter, $data->getValue( 'sp_season', 'custom_fields' ) );

		// Past Teams
		$past_teams_delimiter = apply_filters('iwp/delimiter/sp_past_team', $delimiter);
		$this->past_teams = explode( $past_teams_delimiter, $data->getValue( 'sp_past_team', 'custom_fields' ) );

		// Current Teams
		$current_teams_delimiter = apply_filters('iwp/delimiter/sp_current_team', $delimiter);
		$this->current_teams = explode( $current_teams_delimiter, $data->getValue( 'sp_current_team', 'custom_fields' ) );

		// metrics
		$metrics = $this->get_player_metrics();
		if(!empty($metrics)){
			foreach($metrics as $metric){
				$metric_key = 'sp_metric_' . $metric;
				$this->metrics[$metric] = $data->getValue($metric_key, 'custom_fields');
				$fields_to_clear[] = $metric_key;
			}
		}

		$custom_fields   = $data->getData( 'custom_fields' );

		// clear virtual fields so they dont get set
		foreach ( $fields_to_clear as $key ) {
			if ( isset( $custom_fields[ $key ] ) ) {
				unset( $custom_fields[ $key ] );
			}
		}

		$data->replace( $custom_fields, 'custom_fields' );

		return $data;
	}

	/**
	 * Alter basic custom fields
	 *
	 * @param string $value
	 * @param string $output
	 * @param string $key
	 * @param IWP_Mapper_Post $mapper
	 *
	 * @return int|string|WP_Error
	 */
	function iwp_custom_field( $value, $output, $key, $mapper ) {

		switch ( $key ) {

			case 'sp_team':

				$value = $this->store_team( $value );
				break;
		}

		return $value;
	}

	/**
	 * Insert or fetch SportsPress team
	 *
	 * @param $team
	 *
	 * @return int|WP_Error
	 */
	function store_team( $team ) {
		$item = get_page_by_title( $team, OBJECT, 'sp_team' );
		if ( $item ) {
			$team_id = $item->ID;
		} else {
			$team_id = wp_insert_post( array( 'post_type'   => 'sp_team',
			                                  'post_status' => 'publish',
			                                  'post_title'  => wp_strip_all_tags( $team )
			) );
			wp_set_object_terms( $team_id, $this->leagues, 'sp_league', false );
			wp_set_object_terms( $team_id, $this->seasons, 'sp_season', false );
		}

		return $team_id;
	}

	/**
	 * Get list of SportsPress player metrics
	 *
	 * @return array
	 */
	function get_player_metrics(){

		$output = array();

		$args = array(
			'post_type' => 'sp_metric',
			'numberposts' => -1,
			'posts_per_page' => -1,
			'orderby' => 'menu_order',
			'order' => 'ASC',
		);

		$vars = get_posts( $args );
		if($vars){
			foreach($vars as $var){
				$output[] = $var->post_name;
			}
		}

		return $output;
	}

	/**
	 * Populate custom fields list
	 *
	 * @param array $list
	 *
	 * @return array
	 */
	function iwp_custom_fields_list( $list ) {

		$type      = JCI()->importer->get_template()->get_import_type(); // post, user, tax
		$post_type = JCI()->importer->get_template()->get_import_type_name();

		if('post' === $type && 'sp_player' === $post_type){

			$fields = array(
				'sp_number'       => __( 'Squad Number', 'sportspress' ),
				'sp_nationality'  => __( 'Nationality', 'sportspress' ),
				'sp_current_team' => __( 'Current Team', 'sportspress' ),
				'sp_past_team'    => __( 'Past Teams', 'sportspress' ),
				'sp_position'     => __( 'Position', 'sportspress' ),
				'sp_league'       => __( 'Leagues', 'sportspress' ),
				'sp_season'       => __( 'Seasons', 'sportspress' ),
			);

			$metrics = $this->get_player_metrics();
			if(!empty($metrics)){
				foreach($metrics as $metric){
					$metric_key = 'sp_metric_' . $metric;
					$fields[$metric_key] = 'Metric: ' . $metric;
				}
			}

			$list['SportsPress Player'] = $fields;

		}

		return $list;
	}
}

new IWP_SportsPress_Player();

