<?php
/**
 * The class that sets up form functionality.
 *
 * This class is initiated on every page
 * load and does not have to be instantiated.
 *
 * @class       WPCampus_Speakers_Forms
 * @category    Class
 * @package     WPCampus Speakers
 */
final class WPCampus_Speakers_Forms {

	// If true, will update info from speaker confirmations.
	private $enable_speaker_confirmation_update;

	/**
	 * We don't need to instantiate this class.
	 */
	protected function __construct() {}

	/**
	 * Registers all of our hooks and what not.
	 */
	public static function register() {
		$plugin = new self();

		// Filter field values.
		add_filter( 'gform_field_value', array( $plugin, 'filter_field_value' ), 10, 3 );

		// Populate field choices.
		add_filter( 'gform_pre_render', array( $plugin, 'populate_field_choices' ), 100 );
		add_filter( 'gform_pre_validation', array( $plugin, 'populate_field_choices' ), 100 );
		add_filter( 'gform_pre_submission_filter', array( $plugin, 'populate_field_choices' ), 100 );
		add_filter( 'gform_admin_pre_render', array( $plugin, 'populate_field_choices' ), 100 );

		// Load ACF field choices.
		add_filter( 'acf/load_field/name=wpc_speaker_app_form_id', array( $plugin, 'load_gravity_form_field_choices' ) );
		add_filter( 'acf/load_field/name=wpc_speaker_confirmation_form_id', array( $plugin, 'load_gravity_form_field_choices' ) );

		// Process speaker application.
		$speaker_app_id = wpcampus_speakers()->get_speaker_app_form_id();
		if ( $speaker_app_id > 0 ) {
			add_action( 'gform_after_submission_' . $speaker_app_id, array( $plugin, 'process_speaker_application' ), 10, 2 );
		}

		// Process the speaker applications.
		add_action( 'current_screen', array( $plugin, 'process_speaker_applications' ) );

		// Process speaker confirmation.
		$speaker_confirmation_form_id = wpcampus_speakers()->get_speaker_confirmation_form_id();
		if ( $speaker_confirmation_form_id > 0 ) {
			//add_filter( 'gform_get_form_filter_5', array( $plugin, 'filter_online_speaker_confirmation_form' ), 100, 2 );
			add_filter( 'gform_get_form_filter_' . $speaker_confirmation_form_id, array( $plugin, 'filter_speaker_confirmation_form' ), 100, 2 );
			add_action( 'gform_after_submission_' . $speaker_confirmation_form_id, array( $plugin, 'process_speaker_confirmation' ), 10, 2 );
		}

		add_action( 'current_screen', array( $plugin, 'update_proposals_from_speaker_confirmations' ) );

		// Process 2017 speaker questionnaire.
		//add_filter( 'gform_get_form_filter_13', array( $plugin, 'filter_2017_speaker_questionnaire' ), 100, 2 );
		//add_action( 'gform_after_submission_13', array( $plugin, 'process_2017_speaker_questionnaire' ), 10, 2 );

	}

	/**
	 * @return bool
	 */
	public function load_gravity_form_field_choices( $field ) {

		// Reset choices.
		$field['choices'] = array();

		// Get list of forms.
		$forms = wpcampus_speakers()->get_gravity_forms();

		if ( empty( $forms ) ) {
			return $field;
		}

		foreach ( $forms as $form ) {
			$field['choices'][ $form['id'] ] = $form['title'];
		}

		return $field;
	}

	public function is_enable_speaker_confirmation_update() {
		if ( isset( $this->enable_speaker_confirmation_update ) ) {
			return $this->enable_speaker_confirmation_update;
		}
		$this->enable_speaker_confirmation_update = (bool) get_option( 'wpc_enable_speaker_confirmation_update' );
		return $this->enable_speaker_confirmation_update;
	}

	/**
	 * Get the speaker ID for a form.
	 */
	public function get_form_speaker_id() {
		return ! empty( $_GET['speaker'] ) && is_numeric( $_GET['speaker'] ) ? (int) $_GET['speaker'] : 0;
	}

	/**
	 * Get the session ID for a form.
	 */
	public function get_form_session_id() {
		return ! empty( $_GET['session'] ) && is_numeric( $_GET['session'] ) ? (int) $_GET['session'] : 0;
	}

	/**
	 * Get the speaker's post for a form.
	 *
	 * @TODO:
	 *  - We don't have speaker post types anymore.
	 */
	public function get_form_speaker_post( $speaker_id = 0 ) {

		// @TODO: We don't have speaker post types anymore.
		return false;

		// Make sure we have the speaker ID.
		if ( ! $speaker_id ) {
			$speaker_id = $this->get_form_speaker_id();
		}

		if ( ! $speaker_id ) {
			return false;
		}

		// Get the speaker post.
		$speaker_post = get_post( $speaker_id );
		if ( empty( $speaker_post ) || ! is_a( $speaker_post, 'WP_Post' ) ) {
			return false;
		}

		return $speaker_post;
	}

	/**
	 * Get the session's post for a form.
	 *
	 * @TODO:
	 *  - We don't have session post types anymore.
	 */
	public function get_form_session_post( $session_id = 0 ) {

		// @TODO: We don't have session post types anymore.
		return false;

		// Make sure we have the session ID.
		if ( ! $session_id ) {
			$session_id = $this->get_form_session_id();
		}

		if ( ! $session_id ) {
			return false;
		}

		// Get the session post.
		$session_post = get_post( $session_id );
		if ( empty( $session_post ) || ! is_a( $session_post, 'WP_Post' ) ) {
			return false;
		}

		return $session_post;
	}

	/**
	 * Get the session's speakers for a form.
	 *
	 * @TODO:
	 *  - Get to work with new system.
	 */
	public function get_form_session_speakers( $session_id ) {
		global $wpdb;

		// Make sure we have a session ID.
		if ( ! $session_id ) {
			$session_id = $this->get_form_speaker_id();
		}

		if ( ! $session_id ) {
			return false;
		}

		// Get the speaker IDs.
		return $wpdb->get_col( $wpdb->prepare( "SELECT speakers.ID FROM {$wpdb->posts} speakers
			INNER JOIN {$wpdb->postmeta} meta ON meta.meta_value = speakers.ID AND meta.meta_key = 'conf_sch_event_speaker' AND meta.post_id = %s
			INNER JOIN {$wpdb->posts} schedule ON schedule.ID = meta.post_id AND schedule.post_type = 'profile'
			WHERE speakers.post_type = 'speakers'", $session_id ) );
	}

	/**
	 * Check the confirmation ID for a speaker.
	 *
	 * @TODO:
	 * - Instead assign to users/speakers?
	 */
	public function check_form_speaker_confirmation_id( $speaker_id = 0 ) {

		// Get the form confirmation ID.
		$form_confirmation_id = isset( $_GET['c'] ) ? $_GET['c'] : 0;
		if ( ! $form_confirmation_id ) {
			return false;
		}

		// Make sure we have the speaker ID.
		if ( ! $speaker_id ) {
			$speaker_id = $this->get_form_speaker_id();
		}

		if ( ! $speaker_id ) {
			return false;
		}

		// Get speaker's confirmation id.
		$speaker_confirmation_id = wpcampus_speakers()->get_confirmation_id( $speaker_id );

		return $form_confirmation_id === $speaker_confirmation_id;
	}

	/**
	 * Filter field values.
	 */
	public function filter_field_value( $value, $field, $name ) {
		global $wpc_proposal, $wpc_profile;

		switch ( $name ) {

			// Populate the current user ID.
			case 'userid':
			case 'user_id':
				return get_current_user_id();

			// Populate proposal info.
			case 'proposal_id':
				return ! empty( $wpc_proposal->ID ) ? $wpc_proposal->ID : '';

			case 'proposal_primary_speaker':
				return ! empty( $wpc_proposal->ID ) ? wpcampus_speakers()->get_proposal_primary_speaker( $wpc_proposal->ID ) : null;

			case 'proposal_title':
				return ! empty( $wpc_proposal->title ) ? $wpc_proposal->title : '';

			case 'proposal_content':
				return ! empty( $wpc_proposal->content['rendered'] ) ? $wpc_proposal->content['rendered'] : '';

			// Populate profile info.
			case 'profile_id':
				return ! empty( $wpc_profile->ID ) ? $wpc_profile->ID : '';

			case 'profile_first_name':
				return ! empty( $wpc_profile->first_name ) ? $wpc_profile->first_name : '';

			case 'profile_last_name':
				return ! empty( $wpc_profile->last_name ) ? $wpc_profile->last_name : '';

			case 'profile_display_name':
				return ! empty( $wpc_profile->display_name ) ? $wpc_profile->display_name : '';

			case 'profile_email':
				return ! empty( $wpc_profile->email ) ? $wpc_profile->email : '';

			case 'profile_phone':
				return ! empty( $wpc_profile->phone ) ? $wpc_profile->phone : '';

			case 'profile_website':
				return ! empty( $wpc_profile->website ) ? $wpc_profile->website : '';

			case 'profile_content':
				return ! empty( $wpc_profile->content['rendered'] ) ? $wpc_profile->content['rendered'] : '';

			case 'profile_company':
				return ! empty( $wpc_profile->company ) ? $wpc_profile->company : '';

			case 'profile_company_website':
				return ! empty( $wpc_profile->company_website ) ? $wpc_profile->company_website : '';

			case 'profile_company_position':
				return ! empty( $wpc_profile->company_position ) ? $wpc_profile->company_position : '';

			case 'profile_twitter':
				return ! empty( $wpc_profile->twitter ) ? $wpc_profile->twitter : '';

			case 'profile_linkedin':
				return ! empty( $wpc_profile->linkedin ) ? $wpc_profile->linkedin : '';

		}

		return $value;
	}

	/**
	 * Dynamically populate field choices.
	 */
	public function populate_field_choices( $form ) {
		global $wpc_proposal, $wpc_profile;

		// If logged in, see if have a speaker profile.
		$wp_user = is_user_logged_in() ? wp_get_current_user() : null;

		/*
		 * Does this speaker have a partner?
		 * Get the primary speaker ID. They will
		 * be the only speaker who can edit the
		 * session information.
		 *
		 * If so, disable all session edit fields.
		 */
		$proposal_primary_speaker = ! empty( $wpc_proposal->ID ) ? wpcampus_speakers()->get_proposal_primary_speaker( $wpc_proposal->ID ) : null;

		foreach ( $form['fields'] as &$field ) {

			// Hide this message for single or primary speakers.
			if ( 'Session Edit Message' == $field->label && ! is_admin() ) {

				if ( empty( $wpc_proposal->speakers ) || count( $wpc_proposal->speakers ) < 2 ) {
					$field->type = 'hidden';
					$field->visibility = 'hidden';
				} elseif ( $proposal_primary_speaker > 0 ) {

					if ( ! empty( $wpc_profile->ID ) && $proposal_primary_speaker == $wpc_profile->ID ) {
						$field->content .= '<p><em><strong>You have the ability to edit your session information.</strong></em></p>';
					} else {

						$proposal_primary_speaker_title = '';
						foreach ( $wpc_proposal->speakers as $speaker ) {
							if ( $speaker->ID == $proposal_primary_speaker ) {
								$proposal_primary_speaker_title = $speaker->title;
							}
						}

						// Edit the content.
						if ( ! empty( $proposal_primary_speaker_title ) ) {
							$field->content .= '<p><em><strong>' . $proposal_primary_speaker_title . ' has the ability to edit your session information.</strong></em></p>';
						}
					}
				}

				// Wrap the content
				$field->content = '<div class="panel blue">' . $field->content . '</div>';

			}

			switch ( $field->inputName ) {

				// Hide if multiple speakers.
				case 'proposal_title':
				case 'proposal_content':
					if ( ! is_admin() && ! empty( $wpc_profile->ID ) && $proposal_primary_speaker != $wpc_profile->ID ) {
						$field->type = 'hidden';
						$field->visibility = 'hidden';
					}
					break;

				// The "Session Categories" and "Session Technical" taxonomy form field.
				// TODO: Right now we're using the GF CPT extension
				case 'proposal_subjects':
				case 'proposal_technical':
				case 'preferred_session_format':

					if ( 'preferred_session_format' == $field->inputName ) {
						$taxonomy = 'preferred_session_format';
					} else if ( 'proposal_subjects' == $field->inputName ) {
						$taxonomy = 'subjects';
					} else {
						$taxonomy = 'session_technical';
					}

					// Hide if multiple speakers.
					if ( ! empty( $wpc_profile->ID ) && $proposal_primary_speaker != $wpc_profile->ID ) {
						if ( ! is_admin() ) {
							$field->type = 'hidden';
							$field->visibility = 'hidden';
						}
					} elseif ( ! empty( $wpc_proposal->ID ) && ! empty( $field->choices ) ) {

						// Get the selected terms.
						$selected_terms = wp_get_object_terms( $wpc_proposal->ID, $taxonomy, array( 'fields' => 'ids' ) );
						if ( empty( $selected_terms ) || is_wp_error( $selected_terms ) ) {
							$selected_terms = array();
						}

						foreach ( $field->choices as &$choice ) {
							$choice['isSelected'] = in_array( $choice['value'], $selected_terms );
							$choice['isChecked'] = in_array( $choice['value'], $selected_terms );
						}
					} else {

						$field->choices = array();

						$subjects = get_terms( $taxonomy, array( 'hide_empty' => false ) );

						foreach( $subjects as $subject ) {
							if ( ! empty( $subject->parent ) ) {
								continue;
							}
							$field->choices[] = array(
								'value' => (int) $subject->term_id,
								'text' => $subject->name,
								'isSelected' => false,
							);
						}
					}

					break;

				case 'speaker_email':
					if ( ! empty( $wp_user->user_email ) ) {
						foreach ( $field->inputs as &$input ) {
							$input['defaultValue'] = $wp_user->user_email;
						}
					}
					break;

				case 'speaker_bio':
					if ( ! empty( $wp_user->ID ) ) {
						$speaker_bio = get_the_author_meta( 'description', $wp_user->ID );
						if ( ! empty( $speaker_bio ) ) {
							$field['defaultValue'] = $speaker_bio;
						}
					}
					break;

				case 'speaker_website':
					if ( ! empty( $wp_user->user_url ) ) {
						$field['defaultValue'] = $wp_user->user_url;
					}
					break;

				case 'speaker_twitter':
					if ( ! empty( $wp_user->ID ) ) {
						$speaker_twitter = get_the_author_meta( 'twitter', $wp_user->ID );
						if ( ! empty( $speaker_twitter ) ) {
							$field['defaultValue'] = $speaker_twitter;
						}
					}
					break;

				case 'speaker_company':
					if ( ! empty( $wp_user->ID ) ) {
						$speaker_company = get_the_author_meta( 'company', $wp_user->ID );
						if ( ! empty( $speaker_company ) ) {
							$field['defaultValue'] = $speaker_company;
						}
					}
					break;

				case 'speaker_position':
					if ( ! empty( $wp_user->ID ) ) {
						$speaker_position = get_the_author_meta( 'company_position', $wp_user->ID );
						if ( ! empty( $speaker_position ) ) {
							$field['defaultValue'] = $speaker_position;
						}
					}
					break;
			}

			// Auto populate the primary speaker name.
			if ( 'name' == $field->type && 'Speaker Name' == $field->adminLabel ) {

				foreach ( $field->inputs as &$input ) {

					switch( $input['name'] ) {

						case 'speaker_first_name':
							if ( ! empty( $wp_user->user_firstname ) ) {
								$input['defaultValue'] = $wp_user->user_firstname;
							}
							break;

						case 'speaker_last_name':
							if ( ! empty( $wp_user->user_lastname ) ) {
								$input['defaultValue'] = $wp_user->user_lastname;
							}
							break;
					}
				}
			}
		}

		return $form;
	}

	/**
	 * Force a process of all speaker applications.
	 */
	public function process_speaker_applications() {
		if ( ! is_admin() ) {
			return;
		}

		if ( empty( $_GET['force_application_process'] ) ) {
			return;
		}

		$current_screen = get_current_screen();

		if ( 'proposal' != $current_screen->post_type ) {
			return;
		}

		if ( 'edit' != $current_screen->base ) {
			return;
		}

		// @TODO tweak capability?
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! class_exists( 'GFAPI' ) ) {
			return;
		}

		$speaker_app_id = wpcampus_speakers()->get_speaker_app_form_id();
		if ( ! ( $speaker_app_id > 0 ) ) {
			return;
		}

		$form = GFAPI::get_form( $speaker_app_id );
		if ( empty( $form['id'] ) ) {
			return;
		}

		$entries = GFAPI::get_entries( $speaker_app_id );
		if ( empty( $entries ) ) {
			return;
		}

		foreach ( $entries as $entry ) {
			$this->process_speaker_application( $entry, $form );
		}
	}

	/**
	 * Process the WPCampus speaker application.
	 */
	public function process_speaker_application( $entry, $form ) {

		// Make sure the form is active.
		if ( empty( $form['is_active'] ) ) {
			return false;
		}

		// Set the entry and post ID.
		$entry_id = ! empty( $entry['id'] ) ? (int) $entry['id'] : 0;
		$post_id  = ! empty( $entry['post_id'] ) ? (int) $entry['post_id'] : 0;

		// Make sure we have an entry and post ID.
		if ( ! $entry_id || ! $post_id ) {
			return false;
		}

		// First, check to see if the entry has already been processed.
		$entry_post = wpcampus_forms()->get_entry_post( $entry_id );

		// If this entry has already been processed, then skip.
		if ( ! empty( $entry_post->ID ) ) {
			return false;
		}

		// Hold speaker information.
		$speaker  = array( 'primary' => true );
		$speaker2 = array();

		// Hold session information.
		$session = array();

		// Process one field at a time.
		foreach ( $form['fields'] as $field ) {

			// Skip certain types.
			if ( in_array( $field->type, array( 'section' ) ) ) {
				continue;
			}

			// Process names.
			if ( 'name' == $field->type ) {

				// Process each name part.
				foreach ( $field->inputs as $input ) {

					// Get the input value.
					$input_value = rgar( $entry, $input['id'] );

					switch ( $input['name'] ) {

						case 'speaker_first_name':
							$speaker['first_name'] = $input_value;
							break;

						case 'speaker2_first_name':
							$speaker2['first_name'] = $input_value;
							break;

						case 'speaker_last_name':
							$speaker['last_name'] = $input_value;
							break;

						case 'speaker2_last_name':
							$speaker2['last_name'] = $input_value;
							break;

					}
				}
			} else {

				// Get the field value.
				$field_value = $field->get_value_export( $entry ); //rgar( $entry, $field->id );

				// Process other input names.
				switch ( $field->inputName ) {

					case 'speaker_email':
						$speaker['email'] = $field_value;
						break;

					case 'speaker2_email':
						$speaker2['email'] = $field_value;
						break;

					case 'speaker_bio':
						$speaker['bio'] = $field_value;
						break;

					case 'speaker2_bio':
						$speaker2['bio'] = $field_value;
						break;

					case 'speaker_website':
						$speaker['website'] = $field_value;
						break;

					case 'speaker2_website':
						$speaker2['website'] = $field_value;
						break;

					// Remove any non alphanumeric characters.
					case 'speaker_twitter':
						$speaker['twitter'] = preg_replace( '/[^a-z0-9\_]/i', '', $field_value );
						break;

					// Remove any non alphanumeric characters.
					case 'speaker2_twitter':
						$speaker2['twitter'] = preg_replace( '/[^a-z0-9\_]/i', '', $field_value );
						break;

					case 'speaker_linkedin':
						$speaker['linkedin'] = $field_value;
						break;

					case 'speaker2_linkedin':
						$speaker2['linkedin'] = $field_value;
						break;

					case 'speaker_company':
						$speaker['company'] = $field_value;
						break;

					case 'speaker2_company':
						$speaker2['company'] = $field_value;
						break;

					case 'speaker_company_website':
						$speaker['company_website'] = $field_value;
						break;

					case 'speaker2_company_website':
						$speaker2['company_website'] = $field_value;
						break;

					case 'speaker_position':
						$speaker['position'] = $field_value;
						break;

					case 'speaker2_position':
						$speaker2['position'] = $field_value;
						break;

					case 'session_title':
						$session['title'] = $field_value;
						break;

					case 'session_desc':
						$session['desc'] = $field_value;
						break;

					case 'proposal_subjects':
						$session['categories'] = explode( ',', $field_value );
						$session['categories'] = array_map( 'trim', $session['categories'] );
						$session['categories'] = array_filter( $session['categories'] );
						break;

					case 'other_session_categories':
						$session['other_categories'] = strip_tags( $field_value );
						break;

					case 'preferred_session_format':
						$session['preferred_session_format'] = explode( ',', $field_value );
						$session['preferred_session_format'] = array_map( 'trim', $session['preferred_session_format'] );
						$session['preferred_session_format'] = array_map( 'intval', $session['preferred_session_format'] );
						$session['preferred_session_format'] = array_filter( $session['preferred_session_format'] );
						break;

					case 'user_id':
						$session['user_id'] = $field_value;
						break;

				}
			}
		}

		// Store preferred session format.
		if ( ! empty( $session['preferred_session_format'] ) ) {
			wp_set_object_terms( $post_id, $session['preferred_session_format'], 'preferred_session_format', true );
		}

		$category_ids = array();

		// Set the "other" categories for the session.
		if ( ! empty( $session['categories'] ) ) {
			foreach( $session['categories'] as $category_id ) {
				$category_ids[] = (int) $category_id;
			}
		}

		// Set the "other" categories for the session.
		if ( ! empty( $session['other_categories'] ) ) {

			// Convert to array.
			$other_categories = explode( ',', $session['other_categories'] );
			$other_categories = array_map( 'trim', $other_categories );
			if ( ! empty( $other_categories ) ) {

				// Add term.
				foreach ( $other_categories as $new_term_string ) {

					// Does the term already exist?
					$term_exists = term_exists( $new_term_string, 'subjects' );

					if ( ! empty( $term_exists['term_id'] ) ) {
						$category_ids[] = (int) $term_exists['term_id'];
					} else {

						// Create the term.
						$new_term = wp_insert_term( $new_term_string, 'subjects' );
						if ( ! is_wp_error( $new_term ) && ! empty( $new_term['term_id'] ) ) {

							// Add to list to assign later.
							$category_ids[] = $new_term['term_id'];

						}
					}
				}
			}
		}

		// Add all categories to session.
		if ( ! empty( $category_ids ) ) {
			wp_set_object_terms( $post_id, $category_ids, 'subjects', true );
		}

		// Set the event.
		$event_term_id = wpcampus_speakers()->get_speaker_app_event_id();
		if ( ! empty( $event_term_id ) ) {

			wp_set_object_terms( $post_id, $event_term_id, 'proposal_event', false );
			add_post_meta( $post_id, 'proposal_event', $event_term_id, true );

		}

		// Store the GF entry ID for the post.
		add_post_meta( $post_id, 'gf_entry_id', $entry_id, true );

		// Will hold the speaker post IDs for the post.
		$post_speakers = array();

		// Create speaker posts.
		foreach ( array( $speaker, $speaker2 ) as $this_speaker ) {

			// Will holder WP user object for speaker.
			$wp_user = null;

			// If primary, get current user.
			if ( isset( $this_speaker['primary'] ) && true == $this_speaker['primary'] ) {
				$wp_user = wp_get_current_user();
			} else {

				// See if a WP user exists for their email.
				if ( ! empty( $this_speaker['email'] ) ) {
					$wp_user = get_user_by( 'email', $this_speaker['email'] );
				}
			}

			// Build the speaker name.
			$speaker_name = '';

			// Build name from form.
			if ( ! empty( $this_speaker['first_name'] ) ) {
				$speaker_name .= $this_speaker['first_name'];

				if ( ! empty( $this_speaker['last_name'] ) ) {
					$speaker_name .= ' ' . $this_speaker['last_name'];
				}
			}

			// If no name but found WP user, get from user data.
			if ( empty( $speaker_name ) && is_a( $wp_user, 'WP_User' ) ) {
				$speaker_display_name = $wp_user->get( 'display_name' );
				if ( ! empty( $speaker_display_name ) ) {
					$speaker_name = $speaker_display_name;
				}
			}

			// No point if no name.
			if ( ! $speaker_name ) {
				continue;
			}

			// Create the speaker profile.
			$profile_post_id = wp_insert_post( array(
				'post_type'    => 'profile',
				'post_status'  => 'publish',
				'post_title'   => $speaker_name,
				'post_content' => wpcampus_speakers()->strip_content_tags( $this_speaker['bio'] ),
			));

			// Make sure the post was created before continuing.
			if ( ! $profile_post_id ) {
				continue;
			}

			// Store the WordPress user ID.
			if ( ! empty( $wp_user->ID ) && $wp_user->ID > 0 ) {
				add_post_meta( $profile_post_id, 'wordpress_user', $wp_user->ID, true );
			}

			// Store speaker post meta.
			add_post_meta( $profile_post_id, 'first_name', $this_speaker['first_name'], true );
			add_post_meta( $profile_post_id, 'last_name', $this_speaker['last_name'], true );
			add_post_meta( $profile_post_id, 'display_name', $speaker_name, true );
			add_post_meta( $profile_post_id, 'email', $this_speaker['email'], true );
			add_post_meta( $profile_post_id, 'website', $this_speaker['website'], true );
			add_post_meta( $profile_post_id, 'company', $this_speaker['company'], true );
			add_post_meta( $profile_post_id, 'company_website', $this_speaker['company_website'], true );
			add_post_meta( $profile_post_id, 'company_position', $this_speaker['position'], true );
			add_post_meta( $profile_post_id, 'twitter', $this_speaker['twitter'], true );
			add_post_meta( $profile_post_id, 'linkedin', $this_speaker['linkedin'], true );

			// Add the speaker photo.
			// TODO: We moved this to the speaker confirmation form.
			/*if ( ! empty( $this_speaker['photo'] ) ) {

				// Set variables for storage, fix file filename for query strings.
				preg_match( '/[^\?]+\.(jpe?g|png)\b/i', $this_speaker['photo'], $matches );
				if ( ! empty( $matches[0] ) ) {

					// Make sure we have the files we need.
					require_once( ABSPATH . 'wp-admin/includes/file.php' );
					require_once( ABSPATH . 'wp-admin/includes/image.php' );
					require_once( ABSPATH . 'wp-admin/includes/media.php' );

					// Download the file to temp location.
					$tmp_file = download_url( $this_speaker['photo'] );

					// Setup the file info.
					$file_array = array(
						'name'     => basename( $matches[0] ),
						'tmp_name' => $tmp_file,
					);

					// If no issues with the file...
					if ( ! is_wp_error( $file_array['tmp_name'] ) ) {

						// Upload the image to the media library.
						$speaker_image_id = media_handle_sideload( $file_array, $profile_post_id, $speaker_name );

						// Assign image to the speaker.
						if ( ! is_wp_error( $speaker_image_id ) ) {
							set_post_thumbnail( $profile_post_id, $speaker_image_id );
						}
					}
				}
			}*/

			// Store for the post.
			$post_speakers[] = $profile_post_id;

			// Store the GF entry ID.
			add_post_meta( $profile_post_id, 'gf_entry_id', $entry_id, true );

		}

		// Assign speakers to post.
		if ( ! empty( $post_speakers ) ) {
			$speaker_index = 0;
			foreach ( $post_speakers as $speaker_id ) {
				add_post_meta( $post_id, "speakers_{$speaker_index}_speaker", $speaker_id, true );
				$speaker_index++;
			}
			add_post_meta( $post_id, 'speakers', $speaker_index, true );
		}
	}

	/**
	 * Filter the output for the 2017 speaker confirmation form.
	 *
	 * @access  public
	 * @param   $form_string - string - the default form HTML.
	 * @param   $form - array - the form array
	 * @return  string - the filtered HTML.
	 */
	public function filter_online_speaker_confirmation_form( $form_string, $form ) {

		return $form_string;

		// Only on online website.
		if ( 6 != $blog_id ) {
			return false;
		}

		// Build error message.
		$error_message = '<div class="panel gray">
			<p>Oops! It looks like we\'re missing some important information to confirm your session.</p>
			<p>Try the link from your confirmation email again and, if the form continues to fail, please <a href="/contact/">let us know</a>.</p>
		</div>';

		// Get the speaker ID
		$speaker_id = $this->get_form_speaker_id();
		if ( ! $speaker_id ) {
			return $error_message;
		}

		// Check the confirmation ID.
		$check_confirmation_id = $this->check_form_speaker_confirmation_id( $speaker_id );
		if ( ! $check_confirmation_id ) {
			return $error_message;
		}

		// Get the speaker post, session ID and session post.
		$speaker_post = $this->get_form_speaker_post( $speaker_id );
		$session_id   = $this->get_form_session_id();
		$session_post = $this->get_form_session_post( $session_id );

		if ( ! $speaker_post || ! $session_id || ! $session_post ) {
			return $error_message;
		}

		// Get time.
		$event_start_time        = null;
		$event_start_time_string = get_post_meta( $session_id, 'conf_sch_event_start_time', true );

		if ( $event_start_time_string ) {
			$event_start_time_ts = strtotime( $event_start_time_string );
			if ( false !== $event_start_time_ts ) {
				$event_start_time = date( 'g:i a', $event_start_time_ts );
			}
		}

		// Get location.
		$event_location_id = get_post_meta( $session_id, 'conf_sch_event_location', true );
		$event_location    = ( 10 == $event_location_id ) ? 2 : 1;

		// Build format string.
		$format_key = get_post_meta( $session_id, 'conf_sch_event_format', true );
		switch ( $format_key ) {

			case 'lightning':
				$format = 'lightning talk';
				break;

			case 'workshop':
				$format = 'workshop';
				break;

			default:
				$format = '45-minute session';
				break;
		}

		// Add message.
		$message = '<div class="panel gray">
			<p><strong>Hello ' . $speaker_post->post_title . '!</strong> You have been selected to present on "' . $session_post->post_title . '" as a ' . $format . '.</p>
			<p>We have scheduled your session for Tuesday, January 30, 2018 at ' . $event_start_time . ' CST in Room ' . $event_location . '.</p>
			<p>Thank you from all of us in the WPCampus community.<br />We\'re grateful to have you present and share your knowledge and experience at WPCampus Online 2018.</p>
			<p><strong>Please review and confirm your acceptance to present by Friday, December 8, 2017. If you need more time, simply <a href="mailto:speakers@wpcampus.org">let us know</a>.</strong></p>
		</div>';

		return $message . $form_string;
	}

	/**
	 * Filter the output for the 2017 speaker confirmation form.
	 *
	 * @access  public
	 * @param   $form_string - string - the default form HTML.
	 * @param   $form - array - the form array
	 * @return  string - the filtered HTML.
	 */
	public function filter_speaker_confirmation_form( $form_string, $form ) {
		global $wpc_proposal, $wpc_profile;

		if ( ! is_user_logged_in() ) {
			return '<p><em>You must be logged-in to view this content.</em></p>' . wp_login_form( array( 'echo' => false ) );
		}

		// Build error message.
		$error_message = '<div class="panel blue">
			<p>Oops! It looks like we\'re missing some important information to confirm your session.</p>
			<p>Try the link from your confirmation email again and, if the form continues to fail, please <a href="/contact/">let us know</a>.</p>
		</div>';

		if ( empty( $_GET['proposal'] ) || empty( $_GET['profile'] ) || ! function_exists( 'wpcampus_speakers' ) ) {
			return $error_message;
		}

		if ( empty( $wpc_proposal->ID ) || empty( $wpc_proposal->speakers ) || empty( $wpc_profile->ID ) || empty( $wpc_profile->wordpress_user ) ) {
			return $error_message;
		}

		// Check that the user is logged in.
		if ( get_current_user_id() != $wpc_profile->wordpress_user ) {
			if ( ! current_user_can( 'view_wpc_proposal_confirmation' ) ) {
				return $error_message;
			}
		}

		$feedback = get_post_meta( $wpc_proposal->ID, 'proposal_feedback', true );

		// Get string of other speaker names.
		$other_speakers = array();
		foreach ( $wpc_proposal->speakers as $speaker ) {
			if ( $speaker->ID != $wpc_profile->ID ) {
				$other_speakers[] = $speaker->display_name;
			}
		}

		if ( ! empty( $wpc_proposal->format_name ) ) {
			$format_name = $wpc_proposal->format_name;
		} else {
			$format_name = null;
		}

		if ( ! empty( $wpc_proposal->format_slug ) ) {
			$format_slug = $wpc_proposal->format_slug;
		} else {
			$format_slug = $format_name;
		}

		$message = '<div class="panel royal-blue"><strong>This form is meant for ' . $wpc_profile->display_name . '.</strong> If you are not ' . $wpc_profile->first_name . ', please do not submit this form.</div>
		<div class="panel blue">
			<p><strong>Hello ' . $wpc_profile->first_name . '!</strong></p>
			<p>You have been selected to present on <strong>"' . $wpc_proposal->title . '"</strong>';

		if ( ! empty( $format_name ) ) {
			$message .= ' as a <strong>' . $format_name . '</strong>';
		}

		if ( ! empty( $other_speakers ) ) {
			$message .= ' with ' . implode( ' and ', $other_speakers ) . '';
		}

		$message .= '.';

		if ( ! empty( $format_slug ) ) {
			if ( in_array( $format_slug, [ 'workshop', 'Hands-on Workshop' ] ) ) {
				$message .= ' We are finalizing the exact times for workshops on Thursday, July 25. But you can expect it to last at least 3 hours. 4 hours at most, which will include breaks.';
			} else if ( in_array( $format_slug, [ 'lightning-talk', 'Lightning Talk' ] ) ) {
				$message .= ' All lightning talks will be presented the morning of Friday, July 26. Each talk will last no more than 10 minutes. There will be no live Q&A.</p>';
			} else {
				$message .= ' Your session will last 45 minutes, including time for Q&A.</p>';
			}
		}

		$message .= '<p>Congratulations and thank you from the entire WPCampus community.</p>
			<p><strong>Please review and confirm your acceptance by Tuesday, May 28.</strong>';

		if ( ! empty( $feedback ) ) {
			$message .= ' We\'ve also included feedback for you to consider as you confirm and work on your session. Let us know if you have any questions about our notes.';
		}

		$message .= '</p><p>We\'re extremely grateful to have you present and share your knowledge and experience at WPCampus 2019. Please answer a few questions to confirm your session and help ensure a great conference.</p>
		</div>';

		if ( ! empty( $feedback ) ) {
			$message.= '<div class="panel light-red"><strong>Session feedback:</strong> ' . $feedback . '</div>';
		}

		return $message . $form_string;
	}

	/**
	 * Filter the output for the 2017 speaker confirmation form.
	 *
	 * @access  public
	 * @param   $form_string - string - the default form HTML.
	 * @param   $form - array - the form array
	 * @return  string - the filtered HTML.
	 */
	public function filter_2017_speaker_questionnaire( $form_string, $form ) {

		return $form_string;

		// Only on 2017 website.
		if ( 7 != $blog_id ) {
			return false;
		}

		// Build error message.
		$error_message = '<div class="callout">
			<p>Oops! It looks like we\'re missing some important information to confirm your session.</p>
			<p>Try the link from your confirmation email again and, if the form continues to fail, please <a href="/contact/">let us know</a>.</p>
		</div>';

		// Get the speaker ID
		$speaker_id = $this->get_form_speaker_id();
		if ( ! $speaker_id ) {
			return $error_message;
		}

		// Get the speaker post.
		$speaker_post = $this->get_form_speaker_post( $speaker_id );
		if ( ! $speaker_post ) {
			return $error_message;
		}

		// Add message.
		$message = '<div class="callout">
			<p><strong>Hello ' . $speaker_post->post_title . '!</strong></p>
			<p>Congratulations and thank you from all of us in the WPCampus community.</p>
			<p><strong>Please review and confirm your acceptance to present as soon as you can, and no later than Wednesday, April 19.</strong></p>
			<p>We\'re really grateful to have you present and share your knowledge and experience at WPCampus 2017. Please answer a few questions to confirm your session and help ensure a great conference.</p>
		</div>';

		return $message . $form_string;
	}

	/**
	 * Process the WPCampus 2017 speaker questionnaire.
	 */
	public function process_2017_speaker_questionnaire( $entry, $form ) {

		return;

		// Only on 2017 website.
		if ( 7 != $blog_id ) {
			return false;
		}

		// Make sure the form is active.
		if ( ! isset( $form['is_active'] ) || ! $form['is_active'] ) {
			return false;
		}

		// Set the entry ID.
		$entry_id = $entry['id'];

		// Make sure we have an entry ID.
		if ( ! $entry_id ) {
			return false;
		}

		// First, check to see if the entry has already been processed.
		$entry_post = wpcampus_forms()->get_entry_post( $entry_id, 'post' );

		// If this entry has already been processed, then skip.
		if ( $entry_post && isset( $entry_post->ID ) ) {
			return false;
		}

		// Build post information.
		$speaker_blog_post = array(
			'post_type'   => 'post',
			'post_status' => 'pending',
		);

		// Build post content.
		$speaker_blog_content = '';

		// Process one field at a time.
		foreach ( $form['fields'] as $field ) {

			// Skip certain types.
			if ( in_array( $field->type, array( 'section' ) ) ) {
				continue;
			}

			// Get the field value.
			$field_value = rgar( $entry, $field->id );

			// Populate blog info.
			if ( 'speaker_name' == $field->inputName ) {
				$speaker_blog_post['post_title'] = $field_value;
			} elseif ( preg_match( '/Question\s([0-9]+)/i', $field->adminLabel ) ) {

				// Add line breaks.
				if ( ! empty( $speaker_blog_content ) ) {
					$speaker_blog_content .= "\n\n";
				}

				// Add question and response.
				$speaker_blog_content .= $field->label;
				$speaker_blog_content .= "\n{$field_value}";

			}
		}

		// Add blog content.
		$speaker_blog_post['post_content'] = wpcampus_speakers()->strip_content_tags( $speaker_blog_content );

		// Make sure we have post info.
		if ( empty( $speaker_blog_post ) ) {
			return false;
		}

		// Create the pending post.
		$blog_post_id = wp_insert_post( $speaker_blog_post );

		// No point in continuing if no blog post ID.
		if ( is_wp_error( $blog_post_id ) || ! $blog_post_id ) {
			return false;
		}

		// Set the speakers category.
		wp_set_object_terms( $blog_post_id, 'speakers', 'category', true );

		// Store the GF entry ID for the schedule post.
		add_post_meta( $blog_post_id, 'gf_entry_id', $entry_id, true );

	}

	/**
	 * Process the WPCampus speaker confirmation.
	 */
	public function process_speaker_confirmation( $entry, $form ) {
		$this->update_proposal_from_speaker_confirmation( $entry, $form );
	}

	/**
	 * Manually update proposals and profiles
	 * from ALL speaker confirmations.
	 *
	 * @TODO:
	 * - create an admin button for this?
	 */
	public function update_proposals_from_speaker_confirmations() {
		global $current_screen;

		// The action is only run in the admin but just in case.
		if ( ! is_admin() ) {
			return;
		}

		// Only run for Rachel.
		if ( 1 != get_current_user_id() ) {
			return;
		}

		// Only run on the proposals screen.
		if ( empty( $current_screen->base ) || 'edit' != $current_screen->base ) {
			return;
		}

		if ( empty( $current_screen->post_type ) || 'proposal' != $current_screen->post_type ) {
			return;
		}

		// Check if we want to check confirmations.
		if ( empty( get_option( 'wpc_check_speaker_confirmations' ) ) ) {
			return;
		}

		// Make sure GFAPI exists.
		if ( ! class_exists( 'GFAPI' ) ) {
			return;
		}

		// ID for speaker confirmation form.
		$form_id = wpcampus_speakers()->get_speaker_confirmation_form_id();

		if ( empty( $form_id ) ) {
			return;
		}

		// What entry should we start on?
		$entry_offset = 0;

		// How many entries?
		$entry_count = 100;

		// Get entries.
		$entries = GFAPI::get_entries( $form_id,
			array(
				'status' => 'active',
			),
			array(),
			array(
				'offset'    => $entry_offset,
				'page_size' => $entry_count,
			)
		);

		if ( ! empty( $entries ) ) {

			// Get form data.
			$form = GFAPI::get_form( $form_id );

			// Process each entry.
			foreach ( $entries as $entry ) {
				$this->update_proposal_from_speaker_confirmation( $entry, $form );
			}
		}
	}

	/**
	 * Update session information from
	 * a SPECIFIC speaker confirmation.
	 *
	 * Can pass entry ID or object.
	 * Can pass form ID or object.
	 *
	 * @TODO:
	 * - create an admin button for this?
	 */
	public function update_proposal_from_speaker_confirmation( $entry, $form ) {

		// Make sure GFAPI exists.
		if ( ! class_exists( 'GFAPI' ) ) {
			return false;
		}

		// If ID, get the entry.
		if ( is_numeric( $entry ) && $entry > 0 ) {
			$entry = GFAPI::get_entry( $entry );
		}

		// If ID, get the form.
		if ( is_numeric( $form ) && $form > 0 ) {
			$form = GFAPI::get_form( $form );
		}

		// Make sure we have some info.
		if ( ! $entry || ! $form ) {
			return false;
		}

		// Make sure the form is active.
		if ( empty( $form['is_active'] ) ) {
			return false;
		}

		// Set the entry ID.
		$entry_id = $entry['id'];

		// Make sure we have an entry ID.
		if ( empty( $entry_id ) ) {
			return false;
		}

		// Store info.
		$user_id = 0;
		$proposal_id = 0;
		$profile_id = 0;
		$primary_speaker_id = 0;
		$speaker_confirm = false;
		$entry_info = array();

		// If true, will update info from speaker confirmations.
		$debug_update = (bool) get_option( 'options_wpc_debug_speaker_confirmation_update' );
		$enable_update = $debug_update ? false : $this->is_enable_speaker_confirmation_update();

		// Keeps track of what changed.
		$changed = array();

		// Process one field at a time.
		foreach ( $form['fields'] as $field ) {

			// Ignore these types.
			if ( in_array( $field->type, array( 'section', 'html' ) ) ) {
				continue;
			}

			if ( 'name' == $field->type ) {

				// Process each name part.
				foreach ( $field->inputs as $input ) {

					switch ( $input['name'] ) {

						case 'profile_first_name':
						case 'profile_last_name':
							$entry_info[ $input['name'] ] = rgar( $entry, $input['id'] );
							break;
					}
				}
			} elseif ( in_array( $field->type, array( 'checkbox' ) ) ) {

				$values = array();

				foreach ( $field->inputs as $input ) {
					$value = rgar( $entry, $input['id'] );
					if ( ! empty( $value ) ) {
						$values[] = $value;
					}
				}

				$input_name = $field['inputName'];
				switch ( $input_name ) {

					case 'proposal_subjects':
					case 'proposal_technical':
						$values = array_filter( $values, 'is_numeric' );
						$values = array_map( 'intval', $values );
						$entry_info[ $input_name ] = ! empty( $values ) ? $values : array();
						break;

					case 'event_coc':
						$entry_info[ $input_name ] = ! empty( $values ) ? implode( ', ', $values ) : array();
						break;

					case 'event_unavailability':
					default:
						$entry_info[ $input_name ] = ! empty( $values ) ? $values : array();
						break;
				}
			} elseif ( ! empty( $field['inputName'] ) ) {

				$input_name = $field['inputName'];
				$input_value = rgar( $entry, $field['id'] );

				switch ( $input_name ) {

					case 'userid':
						$user_id = (int) $input_value;
						break;

					case 'proposal_id':
						$proposal_id = (int) $input_value;
						break;

					case 'profile_id':
						$profile_id = (int) $input_value;
						break;

					case 'proposal_primary_speaker':
						$primary_speaker_id = (int) $input_value;
						break;

					case 'speaker_confirmation':
						$speaker_confirm = ( 'yes' == $input_value ) ? true : false;
						break;

					case 'profile_display_name':
					case 'profile_email':
					case 'profile_phone':
					case 'profile_website':
					case 'profile_content':
					case 'profile_company':
					case 'profile_company_website':
					case 'profile_company_position':
					case 'profile_linkedin':
					case 'proposal_title':
					case 'proposal_content':
					case 'event_technology':
					case 'event_video_release':
					case 'event_special_requests':
						$entry_info[ $input_name ] = $input_value;
						break;

					case 'profile_twitter':
						$entry_info[ $input_name ] = preg_replace( '/[^a-z0-9\_]/i', '', $input_value );
						break;

					// Use to make sure all names have logic.
					/*default:
						echo "<br><br>name: {$input_name}:<pre>";
						print_r( $input_value );
						echo "</pre>";
						break;*/

				}
			} else {

				switch ( $field->id ) {

					// [label] => Headshot
					case 53:
						$entry_info['profile_headshot'] = rgar( $entry, $field['id'] );
						break;

					// Use to make sure all fields have a name.
					/*default:
						echo "NEEDs NAME:<pre>";
						print_r( $field );
						echo "</pre>";
						break;*/
				}
			}
		}

		if ( empty( $entry_info ) ) {
			return false;
		}

		// No point if no user ID.
		if ( empty( $user_id ) ) {
			return false;
		}

		// No point if no proposal.
		if ( empty( $proposal_id ) ) {
			return false;
		}

		$proposal_post = wpcampus_speakers()->get_proposal( $proposal_id );
		if ( empty( $proposal_post->ID ) || $proposal_post->ID != $proposal_id ) {
			return false;
		}

		// No point if no profile.
		if ( empty( $profile_id ) ) {
			return false;
		}

		$profile_post = wpcampus_speakers()->get_profile( $profile_id );
		if ( empty( $profile_post->ID ) || $profile_post->ID != $profile_id ) {
			return false;
		}

		// Make sure is admin or WordPress user matches entry user.
		$can_confirm_proposals = current_user_can( 'confirm_wpc_proposals' );
		if ( ! $can_confirm_proposals && ( empty( $profile_post->wordpress_user ) || $profile_post->wordpress_user != $user_id ) ) {
			return false;
		}

		// Is this the primary speaker?
		$is_primary_speaker = ! empty( $primary_speaker_id ) && $primary_speaker_id == $profile_post->ID;

		// If primary speaker and didn't confirm, change proposal status.
		if ( $is_primary_speaker ) {

			if ( $speaker_confirm ) {
				$new_proposal_status = 'confirmed';
			} else {
				$new_proposal_status = 'declined';
			}

			if ( $new_proposal_status != $proposal_post->proposal_status ) {

				// Update status.
				if ( $enable_update ) {
					wpcampus_speakers()->update_proposal_status( $proposal_id, $new_proposal_status );
				}

				// Mark as changed.
				$changed[] = array(
					'field'    => 'proposal_status',
					'original' => $proposal_post->proposal_status,
					'new'      => $new_proposal_status,
				);
			}
		}

		$wpcampus_speakers = wpcampus_speakers();
		$profile_update_fields = array(
			'profile_first_name' => array(
				'meta_key'    => 'first_name',
				'allow_empty' => false,
				'update'      => array( $wpcampus_speakers, 'update_profile_first_name' ),
			),
			'profile_last_name' => array(
				'meta_key'    => 'last_name',
				'allow_empty' => false,
				'update'      => array( $wpcampus_speakers, 'update_profile_last_name' ),
			),
			'profile_display_name' => array(
				'meta_key'    => 'display_name',
				'allow_empty' => false,
				'update'      => array( $wpcampus_speakers, 'update_profile_display_name' ),
			),
			'profile_email' => array(
				'meta_key'    => 'email',
				'allow_empty' => false,
				'update'      => array( $wpcampus_speakers, 'update_profile_email' ),
			),
			'profile_phone' => array(
				'meta_key'    => 'phone',
				'allow_empty' => false,
				'update'      => array( $wpcampus_speakers, 'update_profile_phone' ),
			),
			'profile_website' => array(
				'meta_key'    => 'website',
				'allow_empty' => true,
				'update'      => array( $wpcampus_speakers, 'update_profile_website' ),
			),
			'profile_company' => array(
				'meta_key'    => 'company',
				'allow_empty' => true,
				'update'      => array( $wpcampus_speakers, 'update_profile_company' ),
			),
			'profile_company_website' => array(
				'meta_key'    => 'company_website',
				'allow_empty' => true,
				'update'      => array( $wpcampus_speakers, 'update_profile_company_website' ),
			),
			'profile_company_position' => array(
				'meta_key'    => 'company_position',
				'allow_empty' => true,
				'update'      => array( $wpcampus_speakers, 'update_profile_company_position' ),
			),
			'profile_twitter' => array(
				'meta_key'    => 'twitter',
				'allow_empty' => true,
				'update'      => array( $wpcampus_speakers, 'update_profile_twitter' ),
			),
			'profile_linkedin' => array(
				'meta_key'    => 'linkedin',
				'allow_empty' => true,
				'update'      => array( $wpcampus_speakers, 'update_profile_linkedin' ),
			),
		);

		// Update the profile.
		foreach ( $profile_update_fields as $field => $info ) {
			if ( ! isset( $entry_info[ $field ] ) ) {
				continue;
			}
			$value = $entry_info[ $field ];

			// Means we don't want to be updated with empty value.
			if ( ! $info['allow_empty'] && empty( $value ) ) {
				continue;
			}

			$meta_key = $info['meta_key'];
			if ( isset( $profile_post->{$meta_key} ) ) {
				$original_value = $profile_post->{$meta_key};
				if ( $value != $original_value ) {

					if ( $enable_update ) {
						call_user_func( $info['update'], $profile_id, $value );
					}

					// Mark as changed.
					$changed[] = array(
						'field'    => $field,
						'original' => $original_value,
						'new'      => $value,
					);
				}
			}
		}

		$event_profile_fields = array(
			'event_unavailability' => array(
				'allow_empty' => true,
				'get'         => wpcampus_speakers()->get_profile_event_unavailability( $profile_id ),
				'update'      => array( $wpcampus_speakers, 'update_profile_event_unavailability' ),
			),
			'event_coc' => array(
				'allow_empty' => true,
				'get'         => wpcampus_speakers()->get_profile_event_coc( $profile_id ),
				'update'      => array( $wpcampus_speakers, 'update_profile_event_coc' ),
			),
			'event_technology' => array(
				'allow_empty' => true,
				'get'         => wpcampus_speakers()->get_profile_event_technology( $profile_id ),
				'update'      => array( $wpcampus_speakers, 'update_profile_event_technology' ),
			),
			'event_video_release' => array(
				'allow_empty' => true,
				'get'         => wpcampus_speakers()->get_profile_event_video_release( $profile_id ),
				'update'      => array( $wpcampus_speakers, 'update_profile_event_video_release' ),
			),
			'event_special_requests' => array(
				'allow_empty' => true,
				'get'         => wpcampus_speakers()->get_profile_event_special_requests( $profile_id ),
				'update'      => array( $wpcampus_speakers, 'update_profile_event_special_requests' ),
			),
		);

		foreach ( $event_profile_fields as $field => $info ) {
			if ( ! isset( $entry_info[ $field ] ) ) {
				continue;
			}
			$value = $entry_info[ $field ];

			// Means we don't want to be updated with empty value.
			if ( ! $info['allow_empty'] && empty( $value ) ) {
				continue;
			}

			$original_value = $info['get'];
			if ( $value != $original_value ) {

				if ( $enable_update ) {
					call_user_func( $info['update'], $profile_id, $value );
				}

				// Mark as changed.
				$changed[] = array(
					'field'    => $field,
					'original' => $original_value,
					'new'      => $value,
				);
			}
		}

		// Update profile content.
		if ( ! empty( $entry_info['profile_content'] ) ) {
			$value = $entry_info['profile_content'];
			$original_value = $profile_post->content['raw'];
			if ( $value != $original_value ) {

				if ( $enable_update ) {
					wp_update_post( array(
						'ID'           => $profile_id,
						'post_content' => wpcampus_speakers()->strip_content_tags( $value ),
					));
				}

				// Mark as changed.
				$changed[] = array(
					'field'    => 'profile_content',
					'original' => $original_value,
					'new'      => $value,
				);
			}
		}

		if ( ! empty( $entry_info['profile_headshot'] ) ) {

			// Headshot path should be the path to a file in the upload directory.
			$new_headshot_url   = $entry_info['profile_headshot'];
			$headshot_url_parse = parse_url( $new_headshot_url );
			$new_headshot_path  = ! empty( $headshot_url_parse['path'] ) ? $headshot_url_parse['path'] : '';

			if ( ! empty( $new_headshot_path ) ) {

				// Check to make sure this hasn't already been processed.
				$current_headshot_path = get_post_meta( $profile_id, 'gf_profile_headshot', true );

				if ( $new_headshot_path != $current_headshot_path ) {

					if ( $enable_update ) {

						// Check the type of file. We'll use this as the 'post_mime_type'.
						$filetype = wp_check_filetype( basename( $new_headshot_path ), null );

						// Get the path to the upload directory.
						$wp_upload_dir = wp_upload_dir();

						// Prepare an array of post data for the attachment.
						$attachment = array(
							'guid'           => $wp_upload_dir['url'] . '/' . basename( $new_headshot_path ),
							'post_mime_type' => $filetype['type'],
							'post_title'     => $profile_post->title . ' headshot',
							'post_content'   => '',
							'post_status'    => 'inherit',
						);

						// Insert the attachment.
						$attach_id = wp_insert_attachment( $attachment, $new_headshot_path, $profile_id );

						// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
						require_once( ABSPATH . 'wp-admin/includes/image.php' );

						// Generate the metadata for the attachment, and update the database record.
						$attach_data = wp_generate_attachment_metadata( $attach_id, $new_headshot_path );
						wp_update_attachment_metadata( $attach_id, $attach_data );

						set_post_thumbnail( $profile_id, $attach_id );

						// Store alt text.
						update_post_meta( $attach_id, '_wp_attachment_image_alt', 'Headshot of ' . $profile_post->title );

						// Store used GF headshot path.
						update_post_meta( $profile_id, 'gf_profile_headshot', $new_headshot_path );

					}

					$changed[] = array(
						'field'    => 'headshot',
						'original' => $current_headshot_path,
						'value'    => $new_headshot_path,
					);
				}
			}
		}

		// Update proposal if primary speaker.
		if ( $is_primary_speaker ) {

			$update_proposal_post = array();

			if ( ! empty( $entry_info['proposal_title'] ) ) {
				$value = $entry_info['proposal_title'];
				$original_value = $proposal_post->title;
				if ( $value != $original_value ) {

					$update_proposal_post['post_title'] = $value;

					// Mark as changed.
					$changed[] = array(
						'field'    => 'proposal_title',
						'original' => $original_value,
						'new'      => $value,
					);
				}
			}

			/*
			 * @TODO:
			 */
			if ( ! empty( $entry_info['proposal_content'] ) ) {
				$value = $entry_info['proposal_content'];
				$original_value = $proposal_post->content['raw'];
				if ( $value != $original_value ) {

					$update_proposal_post['post_content'] = wpcampus_speakers()->strip_content_tags( $value );

					// Mark as changed.
					$changed[] = array(
						'field'    => 'proposal_content',
						'original' => $original_value,
						'new'      => $value,
					);
				}
			}

			// Update the proposal post.
			if ( $enable_update && ! empty( $update_proposal_post ) ) {
				$update_proposal_post['ID'] = $proposal_id;
				wp_update_post( $update_proposal_post );
			}

			// Set the taxonomies.
			$subjects = wp_get_object_terms( $proposal_id, 'subjects', array( 'fields' => 'ids' ) );
			$subjects_intersect = array_intersect( $subjects, $entry_info['proposal_subjects'] );
			$subjects_diff = array_merge( array_diff( $subjects, $subjects_intersect ), array_diff( $entry_info['proposal_subjects'], $subjects_intersect ) );
			if ( ! empty( $subjects_diff ) ) {

				if ( $enable_update ) {
					wp_set_object_terms( $proposal_id, $entry_info['proposal_subjects'], 'subjects', false );
				}

				// Mark as changed.
				$changed[] = array(
					'field'    => 'proposal_subjects',
					'original' => $subjects,
					'new'      => $entry_info['proposal_subjects'],
				);
			}

			$technical = wp_get_object_terms( $proposal_id, 'session_technical', array( 'fields' => 'ids' ) );
			$technical_intersect = array_intersect( $technical, $entry_info['proposal_technical'] );
			$technical_diff = array_merge( array_diff( $technical, $technical_intersect ), array_diff( $entry_info['proposal_technical'], $technical_intersect ) );
			if ( ! empty( $technical_diff ) ) {

				if ( $enable_update ) {
					wp_set_object_terms( $proposal_id, $entry_info['proposal_technical'], 'session_technical', false );
				}

				// Mark as changed.
				$changed[] = array(
					'field'    => 'proposal_technical',
					'original' => $technical,
					'new'      => $entry_info['proposal_technical'],
				);
			}

			// TODO: Do we need this?
			// Set the "other" categories for the session.
			/*if ( ! empty( $session['other_categories'] ) ) {

				// Convert to array.
				$other_categories = explode( ',', $session['other_categories'] );
				if ( ! empty( $other_categories ) ) {

					// Will hold final term IDs.
					$other_category_ids = array();

					// Add term.
					foreach ( $other_categories as $new_term_string ) {

						// Create the term.
						$new_term = wp_insert_term( $new_term_string, 'session_categories' );
						if ( ! is_wp_error( $new_term ) && ! empty( $new_term['term_id'] ) ) {

							// Add to list to assign later.
							$other_category_ids[] = $new_term['term_id'];

						}
					}

					// Assign all new categories to session.
					if ( ! empty( $other_category_ids ) ) {
						wp_set_object_terms( $schedule_post_id, $other_category_ids, 'session_categories', false );
					}
				}
			}*/
		}

		// Add a timestamp and store what changed.
		if ( ! empty( $changed ) ) {

			$time            = time();
			$changed['time'] = $time;

			if ( $enable_update ) {
				add_post_meta( $proposal_id, "changed_{$time}", $changed, false );
				add_post_meta( $profile_id, "changed_{$time}", $changed, false );
			}
		}

		if ( $debug_update ) {

			echo "<br>User ID: {$user_id}";
			echo "<br>Primary speaker ID: {$primary_speaker_id}";
			echo "<br>Proposal ID: {$proposal_id}";
			echo "<br>Profile ID: {$profile_id}";
			echo "<br>Speaker confirm: {$speaker_confirm}";

			echo "<br><br>Proposal:<pre>";
			print_r( $proposal_post );
			echo "</pre>";

			echo "<br><br>Profile:<pre>";
			print_r( $profile_post );
			echo "</pre>";

			echo "<br><br>Entry:<pre>";
			print_r( $entry_info );
			echo "</pre>";

			echo "<br><br>What changed:<pre>";
			print_r( $changed );
			echo "</pre>";
			exit;
		}

		return true;
	}
}
WPCampus_Speakers_Forms::register();
