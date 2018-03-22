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
		//add_filter( 'gform_pre_render', array( $plugin, 'populate_field_choices' ) );
		//add_filter( 'gform_pre_validation', array( $plugin, 'populate_field_choices' ) );
		//add_filter( 'gform_pre_submission_filter', array( $plugin, 'populate_field_choices' ) );
		//add_filter( 'gform_admin_pre_render', array( $plugin, 'populate_field_choices' ) );

		// Populate the session survey form.
		//add_filter( 'gform_pre_render_9', array( $this, 'populate_session_survey_form' ) );
		//add_filter( 'gform_pre_validation_9', array( $this, 'populate_session_survey_form' ) );
		//add_filter( 'gform_admin_pre_render_9', array( $this, 'populate_session_survey_form' ) );
		//add_filter( 'gform_pre_submission_filter_9', array( $this, 'populate_session_survey_form' ) );

		// Process 2018 speaker application.
		add_action( 'gform_after_submission_30', array( $plugin, 'process_2018_speaker_application' ), 10, 2 );

		// Process speaker confirmation.
		//add_filter( 'gform_get_form_filter_5', array( $plugin, 'filter_online_speaker_confirmation_form' ), 100, 2 );
		//add_filter( 'gform_get_form_filter_8', array( $plugin, 'filter_2017_speaker_confirmation_form' ), 100, 2 );
		//add_action( 'gform_after_submission_8', array( $plugin, 'process_2017_speaker_confirmation' ), 10, 2 );

		// Process 2017 speaker questionnaire.
		//add_filter( 'gform_get_form_filter_13', array( $plugin, 'filter_2017_speaker_questionnaire' ), 100, 2 );
		//add_action( 'gform_after_submission_13', array( $plugin, 'process_2017_speaker_questionnaire' ), 10, 2 );

		// Manually update session information from speaker confirmations.
		// TODO: Setup to run when form is submitted.
		//add_action( 'admin_init', array( $this, 'update_sessions_from_speaker_confirmations' ) );

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
	 * Does this speaker have a partner?
	 * Returns the primary speaker ID. They will
	 * be the only speaker who can edit the
	 * session information.
	 *
	 * @TODO:
	 *  - Get to work with new system.
	 */
	public function get_form_session_primary_speaker( $session_id ) {
		global $wpdb;

		// Make sure we have a session ID.
		if ( ! $session_id ) {
			$session_id = $this->get_form_speaker_id();
		}

		if ( ! $session_id ) {
			return 0;
		}

		$primary_speaker_id = $wpdb->get_var( $wpdb->prepare( "SELECT speakers.ID FROM {$wpdb->posts} speakers
			INNER JOIN {$wpdb->postmeta} meta ON meta.meta_value = speakers.ID AND meta.meta_key = 'conf_sch_event_speaker' AND meta.post_id = %s
			INNER JOIN {$wpdb->posts} schedule ON schedule.ID = meta.post_id AND schedule.post_type = 'profile'
			WHERE speakers.post_type = 'speakers' ORDER BY speakers.ID ASC LIMIT 1", $session_id ) );

		return $primary_speaker_id > 0 ? $primary_speaker_id : 0;
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
		$speaker_confirmation_id = wpcampus_speakers()->get_proposal_confirmation_id( $speaker_id );

		return $form_confirmation_id === $speaker_confirmation_id;
	}

	/**
	 * Filter field values.
	 */
	public function filter_field_value( $value, $field, $name ) {

		//$is_2017_speaker_form = ( 7 == $blog_id && in_array( $field->formId, array( 8, 13 ) ) );

		// Get the speaker and session ID.
		//$speaker_id = $this->get_form_speaker_id();
		//$session_id = $this->get_form_session_id();

		// Get the speaker and session post.
		//$speaker_post = $speaker_id > 0 ? $this->get_form_speaker_post( $speaker_id ) : null;
		//$session_post = $session_id > 0 ? $this->get_form_session_post( $session_id ) : null;

		switch ( $name ) {

			//case 'speaker_primary':
			//	return $session_id ? $this->get_form_session_primary_speaker( $session_id ) : null;

			//case 'speaker_name':
			//	return ! empty( $speaker_post->post_title ) ? $speaker_post->post_title : null;

			//case 'speaker_bio':
			//	return ! empty( $speaker_post->post_content ) ? $speaker_post->post_content : null;

			//case 'speaker_email':
			//	return $speaker_id ? get_post_meta( $speaker_id, 'conf_sch_speaker_email', true ) : null;

			//case 'speaker_website':
			//	return $speaker_id ? get_post_meta( $speaker_id, 'conf_sch_speaker_url', true ) : null;

			//case 'speaker_company':
			//	return $speaker_id ? get_post_meta( $speaker_id, 'conf_sch_speaker_company', true ) : null;

			//case 'speaker_company_website':
			//	return $speaker_id ? get_post_meta( $speaker_id, 'conf_sch_speaker_company_url', true ) : null;

			//case 'speaker_position':
			//	return $speaker_id ? get_post_meta( $speaker_id, 'conf_sch_speaker_position', true ) : null;

			//case 'speaker_twitter':
			//	return $speaker_id ? get_post_meta( $speaker_id, 'conf_sch_speaker_twitter', true ) : null;

			//case 'speaker_linkedin':
			//	return $speaker_id ? get_post_meta( $speaker_id, 'conf_sch_speaker_linkedin', true ) : null;

			//case 'session_title':
			//	return ! empty( $session_post->post_title ) ? $session_post->post_title : null;

			//case 'session_desc':
			//	return ! empty( $session_post->post_content ) ? $session_post->post_content : null;

			// Get user information.
			case 'speaker_first_name':
			case 'speaker_last_name':
			case 'speaker_email':
			case 'speaker_website':
				$current_user = wp_get_current_user();

				// Return the user data.
				if ( 'speaker_first_name' == $name && ! empty( $current_user->user_firstname ) ) {
					return $current_user->user_firstname;
				}

				if ( 'speaker_last_name' == $name && ! empty( $current_user->user_lastname ) ) {
					return $current_user->user_lastname;
				}

				if ( 'speaker_email' == $name && ! empty( $current_user->user_email ) ) {
					return $current_user->user_email;
				}

				if ( 'speaker_website' == $name && ! empty( $current_user->user_url ) ) {
					return $current_user->user_url;
				}

				break;

			// Get user meta.
			case 'speaker_twitter':
				return get_user_meta( get_current_user_id(), 'twitter', true );

			// Populate the current user ID.
			case 'userid':
			case 'user_id':
				return get_current_user_id();

		}

		return $value;
	}

	/**
	 * Dynamically populate field choices.
	 */
	public function populate_field_choices( $form ) {

		return $form;

		//$is_2017_speaker_form = ( 7 == $blog_id && in_array( $form['id'], array( 8, 13 ) ) );

		// Get the speaker and session ID.
		//$speaker_id = $this->get_form_speaker_id();
		//$session_id = $this->get_form_session_id();

		// Get the session's speakers.
		//$session_speakers = $this->get_form_session_speakers( $session_id );

		/*
		 * Does this speaker have a partner?
		 * Get the primary speaker ID. They will
		 * be the only speaker who can edit the
		 * session information.
		 *
		 * If so, disable all session edit fields.
		 */
		//$session_primary_speaker = $session_id > 0 ? $this->get_form_session_primary_speaker( $session_id ) : 0;

		foreach ( $form['fields'] as &$field ) {

			// Hide this message for single or primary speakers.
			/*if ( 'Session Edit Message' == $field->label && ! is_admin() ) {

				if ( count( $session_speakers ) < 2 ) {
					$field->type = 'hidden';
					$field->visibility = 'hidden';
				} else {

					// Get the primary speaker title.
					$session_primary_speaker_title = get_post_field( 'post_title', $session_primary_speaker );

					// Edit the content.
					$field->content .= '<p><em><strong>' . ( ( $session_primary_speaker == $speaker_id ) ? 'You have' : ( $session_primary_speaker_title . ' has' ) ) . ' the ability to edit your session information.</strong></em></p>';

				}

				// Wrap the content
				$field->content = '<div class="callout">' . $field->content . '</div>';

			}*/

			switch ( $field->inputName ) {

				// Hide if multiple speakers.
				case 'session_desc':
				case 'session_title':
					/*if ( $session_primary_speaker != $speaker_id && ! is_admin() ) {
						$field->type = 'hidden';
						$field->visibility = 'hidden';
					}*/
					break;

				// The "Session Categories" and "Session Technical" taxonomy form field.
				// TODO: Right now we're using the GF CPT extension
				case 'session_categories':
				case 'session_technical':
					// Hide if multiple speakers.
					/*if ( $session_primary_speaker != $speaker_id ) {
						if ( ! is_admin() ) {
							$field->type = 'hidden';
							$field->visibility = 'hidden';
						}
					} else {*/

						// Get the terms.
						/*$terms = get_terms( array(
							'taxonomy'   => $field->inputName,
							'hide_empty' => false,
							'orderby'    => 'name',
							'order'      => 'ASC',
							'fields'     => 'all',
						) );
						if ( ! empty( $terms ) ) {

							// Add the terms as choices.
							$choices = array();
							$inputs  = array();

							// Will hold selected terms.
							$selected_terms = array();

							// We need the speaker and session ID.
							if ( $speaker_id > 0 && $session_id > 0 ) {

								// Get the speaker's terms.
								$selected_terms = wp_get_object_terms( $session_id, $field->inputName, array( 'fields' => 'ids' ) );
								if ( empty( $terms ) || is_wp_error( $terms ) ) {
									$selected_terms = array();
								}
							}

							$term_index = 1;
							foreach ( $terms as $term ) {

								// Add the choice.
								$choices[] = array(
									'text'       => $term->name,
									'value'      => $term->term_id,
									'isSelected' => in_array( $term->term_id, $selected_terms ),
								);

								// Add the input.
								$inputs[] = array(
									'id'    => $field->id . '.' . $term_index,
									'label' => $term->name,
								);

								$term_index ++;

							}

							// Assign the new choices and inputs
							$field->choices = $choices;
							$field->inputs  = $inputs;

						}*/
					//}

					break;
			}
		}

		return $form;
	}

	/**
	 * Populate the session survey form.
	 *
	 * @access  public
	 * @param   $form - array - the form information.
	 * @return  array - the filtered form.
	 */
	public function populate_session_survey_form( $form ) {

		return $form;

		// Get the post.
		$session_id = get_query_var( 'session' );
		if ( ! $session_id ) {
			return $form;
		}

		// Get session information.
		$session_post = get_post( $session_id );
		if ( ! $session_post ) {
			return $form;
		}

		// Loop through the fields.
		foreach ( $form['fields'] as &$field ) {

			switch ( $field->inputName ) {

				// Get the title.
				case 'sessiontitle':
					$session_title = get_the_title( $session_id );
					if ( ! empty( $session_title ) ) {

						// Set title.
						$field->defaultValue = $session_title;

						// Add CSS class so read only.
						$field->cssClass .= ' gf-read-only';

					}

					break;

				case 'speakername':
					$event_speaker_ids = get_post_meta( $session_id, 'conf_sch_event_speaker', false );
					if ( ! empty( $event_speaker_ids ) ) {

						// Get speakers info.
						$speakers = array();
						foreach ( $event_speaker_ids as $speaker_id ) {
							$speakers[] = get_the_title( $speaker_id );
						}

						// If we have speakers...
						if ( ! empty( $speakers ) ) {

							// Set speakers.
							$field->defaultValue = implode( ', ', $speakers );

							// Add CSS class so read only.
							$field->cssClass .= ' gf-read-only';

						}
					}

					break;
			}
		}

		?>
		<script type="text/javascript">
			jQuery(document).ready(function(){
				jQuery('li.gf-read-only input').attr('readonly','readonly');
			});
		</script>
		<?php

		return $form;
	}

	/**
	 * Process the WPCampus 2018 speaker application.
	 */
	public function process_2018_speaker_application( $entry, $form ) {

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
				$field_value = rgar( $entry, $field->id );

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
						$speaker['twitter'] = preg_replace( '/[^a-z0-9]/i', '', $field_value );
						break;

					// Remove any non alphanumeric characters.
					case 'speaker2_twitter':
						$speaker2['twitter'] = preg_replace( '/[^a-z0-9]/i', '', $field_value );
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

					case 'other_session_categories':
						$session['other_categories'] = strip_tags( $field_value );
						break;

					case 'user_id':
						$session['user_id'] = $field_value;
						break;

				}
			}
		}

		// Set the "other" categories for the session.
		if ( ! empty( $session['other_categories'] ) ) {

			// Convert to array.
			$other_categories = explode( ',', $session['other_categories'] );
			if ( ! empty( $other_categories ) ) {

				// Will hold final term IDs.
				$other_category_ids = array();

				// Add term.
				foreach ( $other_categories as $new_term_string ) {

					// Does the term already exist?
					$term_exists = term_exists( $new_term_string, 'subjects' );

					if ( ! empty( $term_exists['term_id'] ) ) {
						$other_category_ids[] = (int) $term_exists['term_id'];
					} else {

						// Create the term.
						$new_term = wp_insert_term( $new_term_string, 'subjects' );
						if ( ! is_wp_error( $new_term ) && ! empty( $new_term['term_id'] ) ) {

							// Add to list to assign later.
							$other_category_ids[] = $new_term['term_id'];

						}
					}
				}

				// Add all new categories to session.
				if ( ! empty( $other_category_ids ) ) {
					wp_set_object_terms( $post_id, $other_category_ids, 'subjects', true );
				}
			}
		}

		// Set the event to "WPCampus 2018".
		$event_term = term_exists( 'wpcampus-2018', 'proposal_event' );

		if ( ! empty( $event_term['term_id'] ) ) {
			$event_term_id = (int) $event_term['term_id'];

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
				'post_status'  => 'pending',
				'post_title'   => $speaker_name,
				'post_content' => $this_speaker['bio'],
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
	public function filter_2017_speaker_confirmation_form( $form_string, $form ) {

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
		$message = '<div class="callout">
			<p><strong>Hello ' . $speaker_post->post_title . '!</strong> You have been selected to present on "' . $session_post->post_title . '" as a ' . $format . '.</p>
			<p>Congratulations and thank you from all of us in the WPCampus community.</p>
			<p><strong>Please review and confirm your acceptance to present as soon as you can, and no later than Wednesday, April 19.</strong></p>
			<p>We\'re really grateful to have you present and share your knowledge and experience at WPCampus 2017. Please answer a few questions to confirm your session and help ensure a great conference.</p>
		</div>';

		return $message . $form_string;
	}

	/**
	 * Process the WPCampus 2017 speaker confirmation.
	 */
	public function process_2017_speaker_confirmation( $entry, $form ) {

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

		echo '<pre>';
		print_r( $entry );
		echo '</pre>';

		exit;

		/*// First, check to see if the entry has already been processed.
		$entry_post = wpcampus_forms()->get_entry_post( $entry_id );

		// If this entry has already been processed, then skip.
		if ( $entry_post && isset( $entry_post->ID ) ) {
			return false;
		}

		// Set the schedule post ID.
		$schedule_post_id = $entry['post_id'];

		// Hold speaker information.
		$speaker = array();
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
			} elseif ( 'session_categories' == $field->inputName ) {

				// Get all the categories and place in array.
				$session['categories'] = array();

				foreach ( $field->inputs as $input ) {
					if ( $this_data = rgar( $entry, $input['id'] ) ) {
						$session['categories'][] = $this_data;
					}
				}

				// Make sure we have categories.
				if ( ! empty( $session['categories'] ) ) {

					// Make sure its all integers.
					$session['categories'] = array_map( 'intval', $session['categories'] );

				}
			} elseif ( 'session_technical' == $field->inputName ) {

				// Get all the skill levels and place in array.
				$session['levels'] = array();

				foreach ( $field->inputs as $input ) {
					if ( $this_data = rgar( $entry, $input['id'] ) ) {
						$session['levels'][] = $this_data;
					}
				}

				// Make sure we have levels.
				if ( ! empty( $session['levels'] ) ) {

					// Make sure its all integers.
					$session['levels'] = array_map( 'intval', $session['levels'] );

				}
			} else {

				// Get the field value.
				$field_value = rgar( $entry, $field->id );

				// Process the speaker photos.
				if ( 'Speaker Photo' == $field->adminLabel ) {

					$speaker['photo'] = $field_value;

				} elseif ( 'Speaker Two Photo' == $field->adminLabel ) {

					$speaker2['photo'] = $field_value;

				} else {

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

						case 'speaker_twitter':

							// Remove any non alphanumeric characters.
							$speaker['twitter'] = preg_replace( '/[^a-z0-9]/i', '', $field_value );
							break;

						case 'speaker2_twitter':

							// Remove any non alphanumeric characters.
							$speaker2['twitter'] = preg_replace( '/[^a-z0-9]/i', '', $field_value );
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

						case 'other_session_categories':
							$session['other_categories'] = $field_value;
							break;

					}
				}
			}
		}

		// If no schedule post was made, create a post.
		if ( ! $schedule_post_id ) {
			$schedule_post_id = wp_insert_post( array(
				'post_type'     => 'proposal',
				'post_status'   => 'pending',
				'post_title'    => $session['title'],
				'post_content'  => $session['desc'],
			));
		} else {

			// Otherwise, make sure the post is updated.
			$schedule_post_id = wp_insert_post( array(
				'ID'            => $schedule_post_id,
				'post_type'     => 'proposal',
				'post_status'   => 'pending',
				'post_title'    => $session['title'],
				'post_content'  => $session['desc'],
			));

		}

		// No point in continuing if no schedule post ID.
		if ( is_wp_error( $schedule_post_id ) || ! $schedule_post_id ) {
			return false;
		}

		// Set the categories for the session.
		if ( ! empty( $session['categories'] ) ) {
			wp_set_object_terms( $schedule_post_id, $session['categories'], 'session_categories', false );
		}

		// Set the "other" categories for the session.
		if ( ! empty( $session['other_categories'] ) ) {

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
		}

		// Set the technical levels for the session.
		if ( ! empty( $session['levels'] ) ) {
			wp_set_object_terms( $schedule_post_id, $session['levels'], 'session_technical', false );
		}

		// Set the event type to "session".
		wp_set_object_terms( $schedule_post_id, 'session', 'event_types', false );

		// Store the GF entry ID for the schedule post.
		add_post_meta( $schedule_post_id, 'gf_entry_id', $entry_id, true );

		// Will hold the speaker post IDs for the schedule post.
		$schedule_post_speakers = array();

		// Create speaker posts.
		foreach ( array( $speaker, $speaker2 ) as $this_speaker ) {

			// Make sure they have an email.
			if ( empty( $this_speaker['email'] ) ) {
				continue;
			}

			// See if a WP user exists for their email.
			$wp_user = get_user_by( 'email', $this_speaker['email'] );

			// Build the speaker name.
			$name = '';

			// Build from form.
			if ( ! empty( $this_speaker['first_name'] ) ) {
				$name .= $this_speaker['first_name'];

				if ( ! empty( $this_speaker['last_name'] ) ) {
					$name .= ' ' . $this_speaker['last_name'];
				}
			}

			// If no name but found WP user, get from user data.
			if ( empty( $name ) && is_a( $wp_user, 'WP_User' ) ) {
				$speaker_display_name = $wp_user->get( 'display_name' );
				if ( ! empty( $speaker_display_name ) ) {
					$name = $speaker_display_name;
				}
			}

			// No point if no name.
			if ( ! $name ) {
				continue;
			}

			// Create the speaker.
			$speaker_post_id = wp_insert_post( array(
				'post_type'     => 'speakers',
				'post_status'   => 'pending',
				'post_title'    => $name,
				'post_content'  => $this_speaker['bio'],
			));

			// Make sure the post was created before continuing.
			if ( ! $speaker_post_id ) {
				continue;
			}

			// Store the WordPress user ID.
			if ( ! empty( $wp_user->ID ) && $wp_user->ID > 0 ) {
				add_post_meta( $speaker_post_id, 'conf_sch_speaker_user_id', $wp_user->ID, true );
			}

			// Store speaker post meta.
			add_post_meta( $speaker_post_id, 'conf_sch_speaker_email', $this_speaker['email'], true );
			add_post_meta( $speaker_post_id, 'conf_sch_speaker_url', $this_speaker['website'], true );
			add_post_meta( $speaker_post_id, 'conf_sch_speaker_company', $this_speaker['company'], true );
			add_post_meta( $speaker_post_id, 'conf_sch_speaker_company_url', $this_speaker['company_website'], true );
			add_post_meta( $speaker_post_id, 'conf_sch_speaker_position', $this_speaker['position'], true );
			add_post_meta( $speaker_post_id, 'conf_sch_speaker_twitter', $this_speaker['twitter'], true );
			add_post_meta( $speaker_post_id, 'conf_sch_speaker_linkedin', $this_speaker['linkedin'], true );

			// Add the speaker photo.
			if ( ! empty( $this_speaker['photo'] ) ) {

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
						$speaker_image_id = media_handle_sideload( $file_array, $speaker_post_id, $name );

						// Assign image to the speaker.
						if ( ! is_wp_error( $speaker_image_id ) ) {
							set_post_thumbnail( $speaker_post_id, $speaker_image_id );
						}
					}
				}
			}

			// Store for the schedule post.
			$schedule_post_speakers[] = $speaker_post_id;

			// Store the GF entry ID.
			add_post_meta( $speaker_post_id, 'gf_entry_id', $entry_id, true );

		}

		// Assign speakers to schedule post.
		if ( ! empty( $schedule_post_speakers ) ) {
			foreach ( $schedule_post_speakers as $speaker_id ) {
				add_post_meta( $schedule_post_id, 'conf_sch_event_speaker', $speaker_id, false );
			}
		}*/
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
		$speaker_blog_post['post_content'] = $speaker_blog_content;

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
	 * Manually update session information
	 * from ALL speaker confirmations.
	 *
	 * @TODO:
	 * - create an admin button for this?
	 */
	public function update_sessions_from_speaker_confirmations() {

		return;

		// Make sure GFAPI exists.
		if ( ! class_exists( 'GFAPI' ) ) {
			return false;
		}

		/*
		 * !!!!!!
		 * @TODO
		 * !!!!!!
		 *
		 * Keep from running on every page load.
		 *
		 * !!!!!
		 */

		// ID for speaker confirmation form.
		$form_id = 8;

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
				//$this->update_session_from_speaker_confirmation( $entry, $form );
			}
		}
	}

	/**
	 * Update session information from
	 * a SPECIFIC speaker confirmation.
	 *
	 * Can pass entry ID or object.
	 * Can oass form ID or object.
	 *
	 * @TODO:
	 * - create an admin button for this?
	 */
	public function update_session_from_speaker_confirmation( $entry, $form ) {
		global $wpdb;

		return;

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

		// Set the entry id.
		$entry_id = $entry['id'];

		// Will hold the speaker and session ID.
		$speaker_id = 0;
		$session_id = null;

		// Is the meta value the entry ID is being stored under.
		$speaker_post_type      = 'speakers';
		$speaker_entry_meta_key = 'gf_speaker_conf_entry_id';

		// Get the entry's speaker ID.
		foreach ( $form['fields']  as $field ) {

			// Get out of here if speaker and session ID have been checked.
			if ( isset( $speaker_id ) && isset( $session_id ) ) {
				break;
			}

			// Get speaker and session IDs.
			if ( 'speaker' == $field->inputName ) {
				$speaker_id = rgar( $entry, $field->id );
				if ( ! ( $speaker_id > 0 ) ) {
					$speaker_id = null;
				}
			} elseif ( 'session' == $field->inputName ) {
				$session_id = rgar( $entry, $field->id );
				if ( ! ( $session_id > 0 ) ) {
					$session_id = null;
				}
			}
		}

		// If no speaker ID, get out of here.
		if ( ! $speaker_id ) {
			return false;
		}

		// Check to see if the speaker has already been processed.
		$speaker_post = $wpdb->get_row( $wpdb->prepare( "SELECT posts.*, meta.meta_value AS gf_entry_id FROM {$wpdb->posts} posts INNER JOIN {$wpdb->postmeta} meta ON meta.post_id = posts.ID AND meta.meta_key = %s AND meta.meta_value = %s WHERE posts.post_type = %s", $speaker_entry_meta_key, $entry_id, $speaker_post_type ) );

		// If this speaker has already been processed, then skip.
		if ( ! empty( $speaker_post->ID ) && $speaker_post->ID == $speaker_id ) {
			return false;
		}

		// Straightforward input to post meta fields.
		$speaker_meta_input_fields = array(
			'speaker_website'         => 'conf_sch_speaker_url',
			'speaker_company'         => 'conf_sch_speaker_company',
			'speaker_company_website' => 'conf_sch_speaker_company_url',
			'speaker_position'        => 'conf_sch_speaker_position',
			'speaker_twitter'         => 'conf_sch_speaker_twitter',
			'speaker_linkedin'        => 'conf_sch_speaker_linkedin',
			'speaker_email'           => 'conf_sch_speaker_email',
			'speaker_phone'           => 'conf_sch_speaker_phone',
		);

		// Will hold speaker post information to update.
		$update_speaker_post = array();

		// Will hold session post information to update.
		$update_session_post = array();

		// Process one field at a time.
		foreach ( $form['fields'] as $field ) {

			// Don't worry about these types.
			if ( in_array( $field->type, array( 'html', 'section' ) ) ) {
				continue;
			}

			// Get the field value.
			$field_value = rgar( $entry, $field->id );

			// Get confirmation status.
			if ( 'Speaker Confirmation' == $field->label ) {
				if ( ! empty( $field_value ) ) {

					// Set the status.
					if ( in_array( $field_value, array( 'I can attend and speak at WPCampus 2017' ) ) ) {
						$speaker_status = 'confirmed';
					} else {
						$speaker_status = 'declined';
					}

					// Update the speaker's status.
					// @TODO: Now uses "proposal_status" for proposal post type.
					//update_post_meta( $speaker_id, 'conf_sch_speaker_status', $speaker_status );

				}
			} elseif ( ! empty( $speaker_meta_input_fields[ $field->inputName ] ) ) {

				// Update the speaker's post meta from the input.
				if ( ! empty( $field_value ) ) {
					update_post_meta( $speaker_id, $speaker_meta_input_fields[ $field->inputName ], sanitize_text_field( $field_value ) );
				}
			} elseif ( 'speaker_name' == $field->inputName ) {

				// Set the speaker title to be updated.
				if ( ! empty( $field_value ) ) {
					$update_speaker_post['post_title'] = sanitize_text_field( $field_value );
				}
			} elseif ( 'speaker_bio' == $field->inputName ) {

				// Set the speaker biography to be updated.
				if ( ! empty( $field_value ) ) {
					$update_speaker_post['post_content'] = wpcampus_speakers()->strip_content_tags( $field_value );
				}
			} elseif ( 'session_title' == $field->inputName ) {

				// Set the session title to be updated.
				if ( ! empty( $field_value ) ) {
					$update_session_post['post_title'] = sanitize_text_field( $field_value );
				}
			} elseif ( 'session_desc' == $field->inputName ) {

				// Set the session description to be updated.
				if ( ! empty( $field_value ) ) {
					$update_session_post['post_content'] = wpcampus_speakers()->strip_content_tags( $field_value );
				}
			} elseif ( 'Technology' == $field->adminLabel ) {

				// Update the speaker's technology.
				if ( ! empty( $field_value ) ) {
					update_post_meta( $speaker_id, 'wpc_speaker_technology', sanitize_text_field( $field_value ) );
				}
			} elseif ( 'Video Release' == $field->adminLabel ) {

				// Update the speaker's video release.
				if ( ! empty( $field_value ) ) {
					$allowable_tags = '<a><ul><ol><li><em><strong><b><br><br />';
					update_post_meta( $speaker_id, 'wpc_speaker_video_release', wpcampus_speakers()->strip_content_tags( $field_value, $allowable_tags ) );
				}
			} elseif ( 'Special Requests' == $field->adminLabel ) {

				// Update the speaker's special requests.
				if ( ! empty( $field_value ) ) {
					update_post_meta( $speaker_id, 'wpc_speaker_special_requests', wpcampus_speakers()->strip_content_tags( $field_value ) );
				}
			} elseif ( 'Arrival' == $field->adminLabel ) {

				// Update the speaker's arrival.
				if ( ! empty( $field_value ) ) {
					update_post_meta( $speaker_id, 'wpc_speaker_arrival', sanitize_text_field( $field_value ) );
				}
			} elseif ( 'Session Unavailability' == $field->label ) {

				// Get all the input data and place in array.
				$unavailability = array();
				foreach ( $field->inputs as $input ) {
					$this_data = rgar( $entry, $input['id'] );
					if ( ! empty( $this_data ) ) {
						$unavailability[] = sanitize_text_field( $this_data );
					}
				}

				// Update the speaker's unavailability.
				if ( ! empty( $unavailability ) ) {
					update_post_meta( $speaker_id, 'wpc_speaker_unavailability', implode( ', ', $unavailability ) );
				}
			} elseif ( in_array( $field->inputName, array( 'session_categories', 'session_technical' ) ) ) {

				// Make sure we have a session ID.
				if ( $session_id > 0 ) {

					// Get all the input data and place in array.
					$term_ids = array();
					foreach ( $field->inputs as $input ) {
						$this_data = rgar( $entry, $input['id'] );
						if ( ! empty( $this_data ) ) {
							$term_ids[] = $this_data;
						}
					}

					// Make sure they're integers.
					$term_ids = array_map( 'intval', $term_ids );

					// Update the terms.
					wp_set_post_terms( $session_id, $term_ids, $field->inputName );

				}
			}
		}

		// Update the speaker post.
		if ( $speaker_id > 0 && ! empty( $update_speaker_post ) ) {

			// Add the speaker ID.
			$update_speaker_post['ID']        = $speaker_id;
			$update_speaker_post['post_type'] = 'speakers';

			// Update the speaker post.
			wp_update_post( $update_speaker_post );

		}

		// Update the session post.
		if ( $session_id > 0 && ! empty( $update_session_post ) ) {

			// Add the session ID.
			$update_session_post['ID']        = $session_id;
			$update_session_post['post_type'] = 'proposal';

			// Update the session post.
			wp_update_post( $update_session_post );

		}

		// Store entry ID in post.
		update_post_meta( $speaker_id, $speaker_entry_meta_key, $entry_id );

		return true;
	}
}
WPCampus_Speakers_Forms::register();
