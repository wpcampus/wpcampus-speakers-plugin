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
					'name' => 'proposal_event',
					'type' => 'taxonomy',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'taxonomy' => 'proposal_event',
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

	acf_add_local_field_group( array(
		'key' => 'group_5a34bdaa05360',
		'title' => __( 'Profile: Speaker', 'wpcampus' ),
		'fields' => array(
			array(
				'key' => 'field_5a34b9ebae921',
				'label' => __( 'First Name', 'wpcampus' ),
				'name' => 'first_name',
				'type' => 'text',
				'instructions' => __( 'The first name field is used solely for sorting purposes. The name in the "Display Name" field will be used for display.', 'wpcampus' ),
				'required' => 1,
				'conditional_logic' => 0,
				'default_value' => '',
				'placeholder' => __( 'First name', 'wpcampus' ),
				'prepend' => '',
				'append' => '',
				'maxlength' => '',
			),
			array(
				'key' => 'field_5a34ba00ae922',
				'label' => __( 'Last Name', 'wpcampus' ),
				'name' => 'last_name',
				'type' => 'text',
				'instructions' => __( 'The last name field is used solely for sorting purposes. The name in the "Display Name" field will be used for display.', 'wpcampus' ),
				'required' => 1,
				'conditional_logic' => 0,
				'default_value' => '',
				'placeholder' => __( 'Last name', 'wpcampus' ),
				'prepend' => '',
				'append' => '',
				'maxlength' => '',
			),
			array(
				'key' => 'field_5a34bcb2c5dbe',
				'label' => __( 'Display Name', 'wpcampus' ),
				'name' => 'display_name',
				'type' => 'text',
				'instructions' => __( 'How would you like your name displayed? This allows us to use the post title field for administrative purposes.', 'wpcampus' ),
				'required' => 1,
				'conditional_logic' => 0,
				'default_value' => '',
				'placeholder' => __( 'How would you like your name displayed?', 'wpcampus' ),
				'prepend' => '',
				'append' => '',
				'maxlength' => '',
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'profile',
				),
			),
		),
		'menu_order' => 1,
		'position' => 'normal',
		'style' => 'default',
		'label_placement' => 'left',
		'instruction_placement' => 'field',
		'hide_on_screen' => '',
		'active' => 1,
		'description' => '',
	));

	acf_add_local_field_group( array(
		'key' => 'group_5a34b4cd1252a',
		'title' => __( 'Profile: Contact Information (Private)', 'wpcampus' ),
		'fields' => array(
			array(
				'key' => 'field_5a34b4e57b7d7',
				'label' => sprintf( __( '%s User', 'wpcampus' ), 'WordPress' ),
				'name' => 'wordpress_user',
				'type' => 'user',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'role' => '',
				'allow_null' => 1,
				'multiple' => 0,
			),
			array(
				'key' => 'field_5a34b5af7b7d8',
				'label' => __( 'Email Address', 'wpcampus' ),
				'name' => 'email',
				'type' => 'email',
				'instructions' => __( 'Please provide an email address that may be used to contact the speaker.', 'wpcampus' ) . '<br /><em>' . __( 'This information will only be used for administrative purposes and will not be displayed on the front-end of the website.', 'wpcampus' ) . '</em>',
				'required' => 1,
				'conditional_logic' => 0,
				'default_value' => '',
				'placeholder' => __( 'Email address', 'wpcampus' ),
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_5a34b5e57b7d9',
				'label' => __( 'Phone Number', 'wpcampus' ),
				'name' => 'phone',
				'type' => 'text',
				'instructions' => __( 'Please provide a phone number that may be used to contact the speaker.', 'wpcampus' ) . '<br /><em>' . __( 'This information will only be used for administrative purposes and will not be displayed on the front-end of the website.', 'wpcampus' ) . '</em>',
				'required' => 0,
				'conditional_logic' => 0,
				'default_value' => '',
				'placeholder' => __( 'Phone number', 'wpcampus' ),
				'prepend' => '',
				'append' => '',
				'maxlength' => '',
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'profile',
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

	acf_add_local_field_group( array(
		'key' => 'group_5a34be3ed37e2',
		'title' => __( 'Profile: Contact Information (Public)', 'wpcampus' ),
		'fields' => array(
			array(
				'key' => 'field_5a34b8d0a45ad',
				'label' => __( 'Personal Website', 'wpcampus' ),
				'name' => 'website',
				'type' => 'url',
				'instructions' => __( "Please provide the URL for the speaker's personal website.", 'wpcampus' ),
				'required' => 0,
				'conditional_logic' => 0,
				'default_value' => '',
				'placeholder' => __( 'Personal website', 'wpcampus' ),
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'profile',
				),
			),
		),
		'menu_order' => 5,
		'position' => 'normal',
		'style' => 'default',
		'label_placement' => 'left',
		'instruction_placement' => 'field',
		'hide_on_screen' => '',
		'active' => 1,
		'description' => '',
	));

	acf_add_local_field_group( array(
		'key' => 'group_5a34b89e23894',
		'title' => __( 'Profile: Professional Information (Public)', 'wpcampus' ),
		'fields' => array(
			array(
				'key' => 'field_5a34b8e8a45ae',
				'label' => __( 'Company', 'wpcampus' ),
				'name' => 'company',
				'type' => 'text',
				'instructions' => __( 'Where does the speaker work?', 'wpcampus' ),
				'required' => 0,
				'conditional_logic' => 0,
				'default_value' => '',
				'placeholder' => __( 'Where does the speaker work?', 'wpcampus' ),
				'prepend' => '',
				'append' => '',
				'maxlength' => '',
			),
			array(
				'key' => 'field_5a34b91aa45b0',
				'label' => __( 'Company Position', 'wpcampus' ),
				'name' => 'company_position',
				'type' => 'text',
				'instructions' => __( "Please provide the speaker's job title.", 'wpcampus' ),
				'required' => 0,
				'conditional_logic' => 0,
				'default_value' => '',
				'placeholder' => __( 'Job title', 'wpcampus' ),
				'prepend' => '',
				'append' => '',
				'maxlength' => '',
			),
			array(
				'key' => 'field_5a34b906a45af',
				'label' => __( 'Company Website', 'wpcampus' ),
				'name' => 'company_website',
				'type' => 'url',
				'instructions' => __( "Please provide the URL for the speaker's company website.", 'wpcampus' ),
				'required' => 0,
				'conditional_logic' => 0,
				'default_value' => '',
				'placeholder' => __( 'Company website', 'wpcampus' ),
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'profile',
				),
			),
		),
		'menu_order' => 8,
		'position' => 'normal',
		'style' => 'default',
		'label_placement' => 'left',
		'instruction_placement' => 'field',
		'hide_on_screen' => '',
		'active' => 1,
		'description' => '',
	));

	acf_add_local_field_group( array(
		'key' => 'group_5a34b72214e1b',
		'title' => __( 'Profile: Social Media (Public)', 'wpcampus' ),
		'fields' => array(
			array(
				'key' => 'field_5a34b78e600c5',
				'label' => sprintf( __( '%s URL', 'wpcampus' ), 'Facebook' ),
				'name' => 'facebook',
				'type' => 'url',
				'instructions' => sprintf( __( 'Please provide the full %s URL.', 'wpcampus' ), 'Facebook' ),
				'required' => 0,
				'conditional_logic' => 0,
				'default_value' => '',
				'placeholder' => sprintf( __( '%s URL', 'wpcampus' ), 'Facebook' ),
			),
			array(
				'key' => 'field_5a34b7a2600c6',
				'label' => sprintf( __( '%s Username', 'wpcampus' ), 'Instagram' ),
				'name' => 'instagram',
				'type' => 'text',
				'instructions' => sprintf( __( 'Only provide the %s handle or username.', 'wpcampus' ), 'Instagram' ),
				'required' => 0,
				'conditional_logic' => 0,
				'default_value' => '',
				'placeholder' => sprintf( __( '%s handle or username', 'wpcampus' ), 'Instagram' ),
				'prepend' => '',
				'append' => '',
				'maxlength' => '',
			),
			array(
				'key' => 'field_5a34b7c7c416b',
				'label' => sprintf( __( '%s Username', 'wpcampus' ), 'Twitter' ),
				'name' => 'twitter',
				'type' => 'text',
				'instructions' => sprintf( __( 'Only provide the %1$s handle, without the "%2$s".', 'wpcampus' ), 'Twitter', '@' ),
				'required' => 0,
				'conditional_logic' => 0,
				'default_value' => '',
				'placeholder' => sprintf( __( '%1$s handle without the "%2$s"', 'wpcampus' ), 'Twitter', '@' ),
				'prepend' => '',
				'append' => '',
				'maxlength' => '',
			),
			array(
				'key' => 'field_5a34b7e1c416c',
				'label' => sprintf( __( '%s URL', 'wpcampus' ), 'LinkedIn' ),
				'name' => 'linkedin',
				'type' => 'url',
				'instructions' => sprintf( __( 'Please provide the full %s URL.', 'wpcampus' ), 'LinkedIn' ),
				'required' => 0,
				'conditional_logic' => 0,
				'default_value' => '',
				'placeholder' => sprintf( __( '%s URL', 'wpcampus' ), 'LinkedIn' ),
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'profile',
				),
			),
		),
		'menu_order' => 10,
		'position' => 'normal',
		'style' => 'default',
		'label_placement' => 'left',
		'instruction_placement' => 'field',
		'hide_on_screen' => '',
		'active' => 1,
		'description' => '',
	));
}
add_action( 'plugins_loaded', 'wpcampus_speakers_add_fields' );
