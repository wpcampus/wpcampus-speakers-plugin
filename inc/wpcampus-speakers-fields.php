<?php
/**
 * Holds all the functionality needed
 * to register the plugin's ACF fields.
 *
 * @package WPCampus Speakers
 */

/**
 * Register the plugin's ACF fields.
 */
function wpcampus_speakers_add_fields() {
	if ( function_exists( 'acf_add_local_field_group' ) ) :

		acf_add_local_field_group( array(
			'key' => 'group_5a34bf1b846ab',
			'title' => __( 'Proposal: The Event', 'wpcampus' ),
			'fields' => array(
				array(
					'key' => 'field_5a35c6908042b',
					'label' => __( 'Event', 'wpcampus' ),
					'name' => 'event',
					'type' => 'taxonomy',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'taxonomy' => 'event',
					'field_type' => 'select',
					'allow_null' => 1,
					'add_term' => 0,
					'save_terms' => 1,
					'load_terms' => 1,
					'return_format' => 'id',
					'multiple' => 0,
				),
				array(
					'key' => 'field_5a34bf2121f2d',
					'label' => __( 'Proposal Status', 'wpcampus' ),
					'name' => 'proposal_status',
					'type' => 'radio',
					'instructions' => '',
					'required' => 1,
					'conditional_logic' => 0,
					'choices' => array(
						'confirmed' => __( 'Confirmed', 'wpcampus' ),
						'declined' => __( 'Declined', 'wpcampus' ),
						'selected' => __( 'Selected', 'wpcampus' ),
						'submitted' => __( 'Submitted', 'wpcampus' ),
					),
					'allow_null' => 1,
					'other_choice' => 0,
					'save_other_choice' => 0,
					'default_value' => 'submitted',
					'layout' => 'vertical',
					'return_format' => 'value',
				),
			),
			'location' => array(
				array(
					array(
						'param' => 'post_type',
						'operator' => '==',
						'value' => 'proposal',
					),
				),
			),
			'menu_order' => 0,
			'position' => 'normal',
			'style' => 'default',
			'label_placement' => 'left',
			'instruction_placement' => 'field',
			'hide_on_screen' => '',
			'active' => 1,
			'description' => '',
		));

		acf_add_local_field_group( array(
			'key' => 'group_5a34bc2ca0126',
			'title' => __( 'Proposal: The Speaker(s)', 'wpcampus' ),
			'fields' => array(
				array(
					'key' => 'field_5a361d609530c',
					'label' => __( 'Speakers', 'wpcampus' ),
					'name' => 'speakers',
					'type' => 'repeater',
					'instructions' => '',
					'required' => 1,
					'conditional_logic' => 0,
					'collapsed' => 'field_5a361daa9530d',
					'min' => 1,
					'max' => 0,
					'layout' => 'block',
					'button_label' => __( 'Add Speaker', 'wpcampus' ),
					'sub_fields' => array(
						array(
							'key' => 'field_5a361daa9530d',
							'label' => __( 'Speaker', 'wpcampus' ),
							'name' => 'speaker',
							'type' => 'post_object',
							'instructions' => '',
							'required' => 1,
							'conditional_logic' => 0,
							'post_type' => array(
								0 => 'profile',
							),
							'allow_null' => 1,
							'multiple' => 0,
							'return_format' => 'id',
							'ui' => 1,
						),
					),
				),
			),
			'location' => array(
				array(
					array(
						'param' => 'post_type',
						'operator' => '==',
						'value' => 'proposal',
					),
				),
			),
			'menu_order' => 3,
			'position' => 'normal',
			'style' => 'default',
			'label_placement' => 'left',
			'instruction_placement' => 'field',
			'hide_on_screen' => '',
			'active' => 1,
			'description' => '',
		));
	endif;
}
add_action( 'plugins_loaded', 'wpcampus_speakers_add_fields' );
