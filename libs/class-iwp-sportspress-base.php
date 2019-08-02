<?php

class IWP_SportsPress_Base {

	/**
	 * @param IWP_Template $template
	 * @param string $import_type (post|user|tax)
	 * @param string $import_type_name post_type
	 *
	 * @return bool
	 */
	function is_template_active( IWP_Template $template, $import_type, $import_type_name ) {

		if ( $import_type === $template->get_import_type() && $import_type_name === $template->get_import_type_name() ) {
			return true;
		}

		return false;
	}

	/**
	 * Insert or fetch SportsPress team
	 *
	 * @param string $team Team Name
	 * @param array $leagues
	 * @param array $seasons
	 *
	 * @return int|WP_Error
	 */
	function store_team( $team, $leagues, $seasons ) {
		$item = get_page_by_title( $team, OBJECT, 'sp_team' );
		if ( $item ) {
			$team_id = $item->ID;
		} else {
			$team_id = wp_insert_post( array(
				'post_type'   => 'sp_team',
				'post_status' => 'publish',
				'post_title'  => wp_strip_all_tags( $team )
			) );

			if ( false !== $leagues ) {
				wp_set_object_terms( $team_id, $leagues, 'sp_league', false );
			}

			if ( false !== $seasons ) {
				wp_set_object_terms( $team_id, $seasons, 'sp_season', false );
			}
		}

		return $team_id;
	}

	/**
	 * @param string $key
	 * @param string $delimiter
	 * @param \ImportWP\Importer\ParsedData $data
	 *
	 * @return array|bool
	 */
	function get_taxonomy_value( $key, $delimiter, $data ) {

		$delimiter = apply_filters( 'iwp/delimiter/' . $key, $delimiter );
		$value     = $data->getValue( $key, 'custom_fields' );

		if ( false !== $value ) {
			return explode( $delimiter, $value );
		}

		return false;
	}

}