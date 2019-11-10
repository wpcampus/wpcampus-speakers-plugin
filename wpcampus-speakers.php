<?php
/**
 * Plugin Name:     WPCampus: Speakers
 * Plugin URI:      https://wpcampus.org
 * Description:     Manages the speaker functionality for the main WPCampus website.
 * Version:         1.0.0
 * Author:          WPCampus
 * Author URI:      https://wpcampus.org
 * License:         GPL-2.0+
 * License URI:     http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:     wpc-speakers
 * Domain Path:     /languages
 *
 * @package         WPCampus Speakers
 */

/**
 * @TODO:
 * - Setup confirmation form in new setup (move from sessions plugin).
 * - Remove the dropdown selection status filter from the session plugin.
 * - Move "WPCampus Speaker Information" meta box in sessions plugin to live elsewhere?
 * - Move "Technical Levels" to proposal?
 *
 * - Update confirmation forms to have a proposal ID and a user ID (as opposed to speaker ID).
 */

defined( 'ABSPATH' ) or die();

require_once wpcampus_speakers()->get_plugin_dir() . 'inc/wpcampus-speakers-fields.php';
require_once wpcampus_speakers()->get_plugin_dir() . 'inc/class-wpcampus-speakers-forms.php';
require_once wpcampus_speakers()->get_plugin_dir() . 'inc/class-wpcampus-speakers-api.php';
require_once wpcampus_speakers()->get_plugin_dir() . 'inc/class-wpcampus-speakers-global.php';

if ( is_admin() ) {
	require_once wpcampus_speakers()->get_plugin_dir() . 'inc/class-wpcampus-speakers-admin.php';
}

/**
 * Class that manages and returns plugin data.
 *
 * @class       WPCampus_Speakers
 * @package     WPCampus Speakers
 */
final class WPCampus_Speakers {

	/**
	 * Holds the absolute URL to
	 * the main plugin directory.
	 *
	 * @var     string
	 */
	private $plugin_url;

	/**
	 * Holds the directory path
	 * to the main plugin directory.
	 *
	 * @var     string
	 */
	private $plugin_dir;

	/**
	 * Holds the selected proposal event term ID.
	 *
	 * @var int
	 */
	private $proposal_event,
		$speaker_app_form_id,
		$speaker_app_event_id,
		$speaker_confirmation_form_id,
		$gravity_forms;

	private $proposal_selection_status,
		$proposal_selection_status_options = array(
			'review' => 'Review',
			'selection' => 'Selection',
		),
		$proposal_selection_status_default = 'review',
		$proposal_selection_display_speakers;

	/**
	 * Holds the class instance.
	 *
	 * @var     WPCampus_Speakers
	 */
	private static $instance;

	/**
	 * Returns the instance of this class.
	 *
	 * @return  WPCampus_Speakers
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			$class_name = __CLASS__;
			self::$instance = new $class_name;
		}
		return self::$instance;
	}

	/**
	 * Magic method to output a string if
	 * trying to use the object as a string.
	 *
	 * @return  string
	 */
	public function __toString() {
		return 'WPCampus_Speakers';
	}

	/**
	 * Method to keep our instance
	 * from being cloned or unserialized
	 * and to prevent a fatal error when
	 * calling a method that doesn't exist.
	 *
	 * @return  void
	 */
	public function __clone() {}
	public function __wakeup() {}
	public function __call( $method = '', $args = array() ) {}

	/**
	 * Start your engines.
	 */
	protected function __construct() {

		// Store the plugin URL and DIR.
		$this->plugin_url = plugin_dir_url( __FILE__ );
		$this->plugin_dir = plugin_dir_path( __FILE__ );

	}

	/**
	 * Returns the absolute URL to
	 * the main plugin directory.
	 *
	 * @return  string
	 */
	public function get_plugin_url() {
		return $this->plugin_url;
	}

	/**
	 * Returns the directory path
	 * to the main plugin directory.
	 *
	 * @return  string
	 */
	public function get_plugin_dir() {
		return $this->plugin_dir;
	}

	/**
	 * Properly strip HTML tags including script and style.
	 *
	 * Adapted from wp_strip_all_tags().
	 *
	 * @param   $string - string - String containing HTML tags.
	 * @param   $allowed_tags - string - the tags we don't want to strip.
	 * @return  string - The processed string.
	 */
	public function strip_content_tags( $string, $allowed_tags = '<a><ul><ol><li><em><strong>' ) {

		// Remove <script> and <style> tags.
		/*$string = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $string );*/

		// Remove all but allowed tags.
		$string = strip_tags( $string, $allowed_tags );

		// Remove line breaks.
		//$string = preg_replace( '/[\r\n\t ]+/', ' ', $string );

		return trim( $string );
	}

	/**
	 *
	 */
	public function get_proposal_event() {
		if ( isset( $this->proposal_event ) ) {
			return $this->proposal_event;
		}
		$this->proposal_event = (int) get_option( 'options_wpc_proposal_select_event' );
		return $this->proposal_event;
	}

	public function get_proposal_selection_status_options() {
		return $this->proposal_selection_status_options;
	}

	public function get_proposal_selection_status_default() {
		return $this->proposal_selection_status_default;
	}

	public function get_proposal_selection_status() {
		if ( isset( $this->proposal_selection_status ) ) {
			return $this->proposal_selection_status;
		}
		$selection_status = get_option( 'options_wpc_proposal_selection_status' );
		if ( empty( $selection_status ) || ! in_array( $selection_status, array_keys( $this->proposal_selection_status_options ) ) ) {
			$selection_status = $this->proposal_selection_status_default;
		}
		$this->proposal_selection_status = $selection_status;
		return $this->proposal_selection_status;
	}

	public function proposal_status_is_review() {
		return ( 'review' == $this->get_proposal_selection_status() );
	}

	public function proposal_status_is_selection() {
		return ( 'selection' == $this->get_proposal_selection_status() );
	}

	public function proposal_selection_display_speakers() {
		if ( isset( $this->proposal_selection_display_speakers ) ) {
			return $this->proposal_selection_display_speakers;
		}
		$display_speakers = get_option( 'options_wpc_proposal_selection_speakers' );
		$this->proposal_selection_display_speakers = ! empty( $display_speakers ) ? true : false;
		return $this->proposal_selection_display_speakers;
	}

	/**
	 *
	 */
	public function get_speaker_app_form_id() {
		if ( isset( $this->speaker_app_form_id ) ) {
			return $this->speaker_app_form_id;
		}
		$this->speaker_app_form_id = (int) get_option( 'options_wpc_speaker_app_form_id' );
		return $this->speaker_app_form_id;
	}

	/**
	 *
	 */
	public function get_speaker_confirmation_form_id() {
		if ( isset( $this->speaker_confirmation_form_id ) ) {
			return $this->speaker_confirmation_form_id;
		}
		$this->speaker_confirmation_form_id = (int) get_option( 'options_wpc_speaker_confirmation_form_id' );
		return $this->speaker_confirmation_form_id;
	}

	/**
	 *
	 */
	public function get_speaker_app_event_id() {
		if ( isset( $this->speaker_app_event_id ) ) {
			return $this->speaker_app_event_id;
		}
		$this->speaker_app_event_id = (int) get_option( 'options_wpc_speaker_app_event_id' );
		return $this->speaker_app_event_id;
	}

	/**
	 *
	 */
	public function get_gravity_forms() {
		if ( isset( $this->gravity_forms ) ) {
			return $this->gravity_forms;
		}
		$forms = class_exists( 'GFAPI' ) ? GFAPI::get_forms() : null;
		$this->gravity_forms = $forms;
		return $this->gravity_forms;
	}

	/**
	 *
	 */
	public function is_session_page() {
		global $wp_query;
		if ( ! $wp_query->is_page( 'session' ) ) {
			return false;
		}
		$session_slug = $wp_query->get( 'session' );
		return ! empty( $session_slug ) ? $session_slug : false;
	}

	/**
	 * Build/return a proposal's review permalink.
	 */
	public function get_session_review_permalink( $post_id ) {
		if ( 'proposal' != get_post_type( $post_id ) ) {
			return '';
		}
		return get_bloginfo( 'url' ) . '/session/' . get_post_field( 'post_name', $post_id ) . '/';
	}

	/**
	 * Build/return a proposal's session permalink.
	 */
	public function get_session_permalink( $post_id, $post = null ) {
		if ( empty( $post ) ) {
			$post = get_post( $post_id );
		}

		$library_url = get_bloginfo( 'url' ) . '/library';

		if ( 'proposal' != $post->post_type ) {
			return $library_url;
		}

		if ( ! empty( $post->permalink ) ) {
			return $post->permalink;
		}

		if ( ! empty( $post->event ) ) {
			$event_website = get_term_meta( $post->event , 'event_website', true );
			if ( ! empty( $event_website ) ) {
				return trailingslashit( $event_website ) . 'schedule/' . $post->post_name;
			}
		}

		return $library_url;
	}

	/**
	 * Build and return a video's YouTube
	 * watch URL based on the video ID.
	 */
	public function get_youtube_url( $youtube_id ) {
		$youtube_watch_url = 'https://www.youtube.com/watch';
		return add_query_arg( 'v', $youtube_id, $youtube_watch_url );
	}

	/**
	 * Get the URL for a video post.
	 */
	public function get_video_url( $post_id ) {

		// Get YouTube ID from the video post.
		$youtube_id = $this->get_video_youtube_id( $post_id );
		if ( empty( $youtube_id ) ) {
			return '';
		}

		// Return the YouTube URL.
		return $this->get_youtube_url( $youtube_id );
	}

	/**
	 * Build and return a video's YouTube
	 * watch URL based on the video ID.
	 */
	public function get_video_youtube_id( $post_id ) {
		return get_post_meta( $post_id, 'wpc_youtube_video_id', true );
	}

	/**
	 * Get the proposal's session video post ID.
	 */
	public function get_proposal_video_id( $post_id ) {
		return get_post_meta( $post_id, 'session_video', true );
	}

	/**
	 * Get the proposal's session video URL.
	 */
	public function get_proposal_video_url( $post_id, $proposal_video_id = null ) {

		// If URL is hard-set, return first.
		$video_url = get_post_meta( $post_id, 'session_video_url', true );
		if ( ! empty( $video_url ) ) {
			return $video_url;
		}

		// See if a video is assigned to the post.
		if ( ! isset( $proposal_video_id ) ) {
			$proposal_video_id  = $this->get_proposal_video_id( $post_id );
		}

		// This means its one of our video posts so get its URL.
		if ( ! empty( $proposal_video_id ) ) {
			return $this->get_video_url( $proposal_video_id );
		}

		return '';
	}

	/**
	 * Get the slides URL for a proposal.
	 */
	public function get_proposal_slides_url( $post_id ) {
		return get_post_meta( $post_id, 'session_slides_url', true );
	}

	/**
	 * Does this speaker have a partner?
	 * Returns the primary speaker ID. They will
	 * be the only speaker who can edit the
	 * session information.
	 */
	public function get_proposal_primary_speaker( $proposal_id ) {
		global $wpdb;

		if ( ! $proposal_id ) {
			return 0;
		}

		$primary_speaker_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT DISTINCT profiles.ID FROM {$wpdb->posts} profiles
				INNER JOIN {$wpdb->postmeta} proposalm ON proposalm.meta_value = profiles.ID AND proposalm.meta_key REGEXP 'speakers\_[0-9]+\_speaker' AND proposalm.post_id = %d
				INNER JOIN {$wpdb->posts} proposals ON proposals.ID = proposalm.post_id AND proposals.post_type = 'proposal' AND proposals.post_status = 'publish'
				WHERE profiles.post_type = 'profile' AND profiles.post_status = 'publish' ORDER BY proposalm.meta_key ASC LIMIT 1", $proposal_id
			)
		);

		return $primary_speaker_id > 0 ? $primary_speaker_id : 0;
	}

	/**
	 * Get the speaker IDs for a proposal.
	 *
	 * @args    $proposal_id - int - the proposal ID.
	 * @return  array - the speaker IDs.
	 */
	public function get_proposal_speaker_ids( $proposal_id ) {
		global $wpdb;
		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key REGEXP '^speakers\_[0-9]+\_speaker$'",
				$proposal_id
			)
		);
	}

	public function get_proposal_avg_rating( $proposal_id ) {
		global $wpdb;
		return number_format( (int) $wpdb->get_var( $wpdb->prepare( "SELECT CAST(AVG(meta_value) AS DECIMAL(10,1)) FROM {$wpdb->postmeta} WHERE post_id = %s AND meta_key REGEXP '^wpc\_session\_rating\_[0-9]+$'", $proposal_id ) ), 1 );
	}

	public function get_proposal_user_rating( $proposal_id, $user_id = 0 ) {

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		return (int) get_post_meta( $proposal_id, 'wpc_session_rating_' . (int) $user_id, true );
	}

	public function get_proposal_speakers( $proposal_id ) {
		global $wpdb;

		// Get speaker IDs.
		$speaker_ids = $this->get_proposal_speaker_ids( $proposal_id );
		$speaker_ids = array_filter( $speaker_ids, 'intval' );
		if ( empty( $speaker_ids ) ) {
			return array();
		}

		return $wpdb->get_results(
			"SELECT posts.ID,
			posts.post_author,
			posts.post_content,
			posts.post_excerpt,
			wpuser.meta_value AS wordpress_user,
			dn.meta_value AS display_name,
			email.meta_value AS email,
			website.meta_value AS website,
			company.meta_value AS company,
			co_web.meta_value AS company_website,
			co_pos.meta_value AS company_position,
			twitter.meta_value AS twitter,
			linkedin.meta_value AS linkedin
			FROM {$wpdb->posts} posts
			LEFT JOIN {$wpdb->postmeta} wpuser ON wpuser.post_id = posts.ID AND wpuser.meta_key = 'wordpress_user'
			LEFT JOIN {$wpdb->postmeta} dn ON dn.post_id = posts.ID AND dn.meta_key = 'display_name'
			LEFT JOIN {$wpdb->postmeta} email ON email.post_id = posts.ID AND email.meta_key = 'email'
			LEFT JOIN {$wpdb->postmeta} website ON website.post_id = posts.ID AND website.meta_key = 'website'
			LEFT JOIN {$wpdb->postmeta} company ON company.post_id = posts.ID AND company.meta_key = 'company'
			LEFT JOIN {$wpdb->postmeta} co_web ON co_web.post_id = posts.ID AND co_web.meta_key = 'company_website'
			LEFT JOIN {$wpdb->postmeta} co_pos ON co_pos.post_id = posts.ID AND co_pos.meta_key = 'company_position'
			LEFT JOIN {$wpdb->postmeta} twitter ON twitter.post_id = posts.ID AND twitter.meta_key = 'twitter'
			LEFT JOIN {$wpdb->postmeta} linkedin ON linkedin.post_id = posts.ID AND linkedin.meta_key = 'linkedin'
			WHERE posts.post_type = 'profile' AND posts.post_status = 'publish' AND posts.ID IN (" . implode( ',', $speaker_ids ) . ')'
		);
	}

	/**
	 * Get a proposal's status.
	 *
	 * @TODO:
	 * - Update to use "proposal_status"
	 * where old system used "conf_sch_speaker_status".
	 *
	 * Options:
	 *   - confirmed
	 *   - declined
	 *   - selected
	 *   - submitted (default, if blank)
	 */
	public function get_proposal_status( $proposal_id ) {
		return get_post_meta( $proposal_id, 'proposal_status', true );
	}

	public function update_proposal_status( $proposal_id, $proposal_status ) {
		if ( ! array_key_exists( $proposal_status, $this->get_proposal_status_choices() ) ) {
			return false;
		}
		return update_post_meta( $proposal_id, 'proposal_status', $proposal_status );
	}

	public function get_proposal_status_choices() {
		if ( ! function_exists( 'get_field_object' ) ) {
			return array();
		}
		$proposal_status_field = get_field_object( 'field_5a34bf2121f2d' );
		return ! empty( $proposal_status_field['choices'] ) ? $proposal_status_field['choices'] : array();
	}

	/**
	 *
	 */
	public function get_proposal_gf_entry_id( $proposal_id ) {
		return (int) get_post_meta( $proposal_id, 'gf_entry_id', true );
	}

	/**
	 * Will return true if proposal has either
	 * been "confirmed" or "declined", which means
	 * the speaker has confirmed their status.
	 */
	public function has_proposal_been_confirmed( $proposal_id, $proposal_status = '' ) {
		if ( ! $proposal_status ) {
			$proposal_status = $this->get_proposal_status( $proposal_id );
		}
		return in_array( $proposal_status, array( 'confirmed', 'declined' ) );
	}

	/**
	 * Return the label for a proposal status.
	 */
	public function print_proposal_status_label( $proposal_id, $proposal_status = '' ) {
		if ( ! $proposal_status ) {
			$proposal_status = $this->get_proposal_status( $proposal_id );
		}
		switch ( $proposal_status ) {
			case 'confirmed':
				?>
				<span style="color:green;"><?php _e( 'Confirmed', 'wpcampus' ); ?></span>
				<?php
				break;

			case 'declined':
			case 'no':
				?>
				<span style="color:#900;"><?php echo 'no' == $proposal_status ? __( 'Not selected', 'wpcampus' ) : __( 'Declined', 'wpcampus' ); ?></span>
				<?php
				break;

			case 'selected':
				?>
				<strong><?php _e( 'Pending', 'wpcampus' ); ?></strong>
				<?php
				break;

			case 'backup':
				?>
				<strong><?php _e( 'Backup', 'wpcampus' ); ?></strong>
				<?php
				break;

			case 'maybe':
				?>
				<strong><?php _e( 'Maybe', 'wpcampus' ); ?></strong>
				<?php
				break;

			default:
				?>
				<em><?php _e( 'Submitted', 'wpcampus' ); ?></em>
				<?php
				break;
		}
	}

	/**
	 * Get a proposal or profile's confirmation ID.
	 */
	public function get_confirmation_id( $post_id, $create = false ) {

		// Get confirmation id.
		$confirmation_id = get_post_meta( $post_id, 'confirmation_id', true );

		// If no confirmation ID, create one.
		if ( ! $confirmation_id && $create ) {
			$confirmation_id = $this->create_confirmation_id( $post_id );
		}

		return ! empty( $confirmation_id ) ? $confirmation_id : false;
	}

	/**
	 * Create a proposal or profile's confirmation ID.
	 */
	public function create_confirmation_id( $proposal_id ) {
		global $wpdb;
		$new_id = $wpdb->get_var( 'SELECT SUBSTRING(MD5(RAND()),16)' );
		if ( ! empty( $new_id ) ) {
			update_post_meta( $proposal_id, 'confirmation_id', $new_id );
		}
		return $new_id;
	}

	/**
	 * Get a proposal confirmation's URL.
	 *
	 * @TODO:
	 * - Instead assign to users/speakers?
	 */
	public function get_proposal_confirmation_url( $proposal_id, $profile_id ) {

		// Get proposal confirmation ID.
		$proposal_confirmation_id = $this->get_confirmation_id( $proposal_id, true );

		// Get profile confirmation ID.
		$profile_confirmation_id = $this->get_confirmation_id( $profile_id, true );

		/*
		 * Build confirmation URL.
		 *
		 * TODO:
		 * - We used to pass speaker and
		 * session ID and now its just proposal
		 * ID and then the speaker must be logged in
		 * so we can know who confirmed.
		 *
		 * What to do when multiple speakers?
		 */
		return add_query_arg( array(
			'proposal' => $proposal_id,
			'profile'  => $profile_id,
			'propc'    => $proposal_confirmation_id,
			'profc'    => $profile_confirmation_id,
		), get_bloginfo( 'url' ) . '/speaker-confirmation/' );
	}

	/**
	 *
	 */
	public function get_proposal( $proposal_id, $args = array() ) {
		$args['p'] = $proposal_id;
		return $this->get_proposals( $args );
	}

	/**
	 *
	 */
	public function get_proposals( $args = array() ) {
		global $wpdb;

		// Merge incoming with defaults.
		$args = wp_parse_args( $args, array(
			'p'               => 0,
			'post__in'        => '',
			'orderby'         => 'post_title',
			'order'           => 'ASC',
			'user_id'         => null,
			'get_user_viewed' => false,
			'get_user_rating' => false,
			'get_avg_rating'  => false,
			'by_profile'      => null,
			'by_wp_user'      => null,
			'proposal_event'  => '',
			'proposal_status' => '',
			'assets'          => null,
			'format'          => null,
			'subject'         => '',
			'search'          => '',
			'get_profiles'    => false,
			'get_headshot'    => false,
			'get_feedback'    => false,
			'get_subjects'    => false,
			'get_wp_user'     => false,
		));

		$user_id = 0;

		$get_user_viewed = ( true == $args['get_user_viewed'] );
		$get_user_rating = ( true == $args['get_user_rating'] );

		$get_row = false;

		if ( $get_user_viewed || $get_user_rating ) {

			// Get user ID.
			if ( ! empty( $args['user_id'] ) ) {
				$user_id = (int) $args['user_id'];
			} else {
				$user_id = (int) get_current_user_id();
			}

			// Set to false if no user ID.
			if ( ! $user_id ) {
				$get_user_viewed = false;
				$get_user_rating = false;
			}
		}

		$hasAssets = [];
		$validAssets = [ 'slides', 'video' ];
		if ( ! empty( $args['assets'] ) ) {

		    if ( ! is_array( $args['assets'] ) ) {
			    $args['assets'] = explode( ',', $args['assets'] );
            }

		    foreach ( $args['assets'] as $asset ) {
		        if ( in_array( $asset, $validAssets ) ) {
		            $hasAssets[] = $asset;
                }
            }
        }

		$proposals_query = $wpdb->prepare(
			"SELECT DISTINCT posts.ID,
				posts.post_title,
				posts.post_title AS title,
				posts.post_name,
				posts.post_name AS slug,
				CONCAT( %s, posts.post_name, '/' ) AS permalink,
				posts.post_author,
				posts.post_date,
				posts.post_date_gmt,
				posts.post_modified,
				posts.post_modified_gmt,
				posts.post_content AS content,
				posts.post_excerpt AS excerpt,
				posts.post_status,
				posts.post_type,
				posts.comment_count,
				proposal_event.meta_value AS event,
				proposal_event_term.name AS event_name,
				proposal_event_term.slug AS event_slug,
				IF ( best_session.meta_value = 1, true, false ) AS best_session,
				format.meta_value AS format,
				IF ( format_terms.slug IS NOT NULL, format_terms.slug, null ) AS format_slug,
				IF ( format_terms.name IS NOT NULL, format_terms.name, null ) AS format_name,
				format_preferred.meta_value AS format_preferred,
				IF ( format_preferred_terms.slug IS NOT NULL, format_preferred_terms.slug, null ) AS format_preferred_slug,
				IF ( format_preferred_terms.name IS NOT NULL, format_preferred_terms.name, null ) AS format_preferred_name,
				proposal_status.meta_value AS proposal_status,
				session_slides_url.meta_value AS session_slides_url,
				null AS speakers,
				null AS subjects",
			'' //get_bloginfo( 'url' ) . '/session/'
		);

		$proposals_query .= ", IF ( session_video_post.ID != '', session_video_post.ID, null ) AS session_video_id,
			IF ( session_video_url.meta_value IS NOT NULL AND session_video_url.meta_value != '', session_video_url.meta_value, 
				IF ( session_video_yt.meta_value IS NOT NULL AND session_video_yt.meta_value != '', CONCAT( 'https://www.youtube.com/watch?v=', session_video_yt.meta_value ), NULL ) 
			) AS session_video_url,
			session_video_thumb.meta_value AS session_video_thumbnail";

		if ( $get_user_viewed ) {
			$proposals_query .= ', viewed.meta_value AS viewed';
		}

		if ( $get_user_rating ) {
			$proposals_query .= ', CAST(rating.meta_value AS UNSIGNED) AS rating';
			$proposals_query .= ", (SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = posts.ID AND meta_key REGEXP '^wpc\_session\_rating\_[0-9]+$' AND meta_value > 0) AS rating_count";
		}

		if ( true == $args['get_avg_rating'] ) {
			$proposals_query .= ", (SELECT AVG(meta_value) FROM {$wpdb->postmeta} WHERE post_id = posts.ID AND meta_key REGEXP '^wpc\_session\_rating\_[0-9]+$') AS avg_rating";
		}

		if ( ! empty( $args['get_feedback'] ) ) {
			$proposals_query .= ', feedback.meta_value AS feedback';
		}

		$proposals_query .= " FROM {$wpdb->posts} posts";

		// @TODO remove "proposal_event" post meta?
		$proposals_query .= " LEFT JOIN {$wpdb->postmeta} proposal_event ON proposal_event.post_id = posts.ID AND proposal_event.meta_key = 'proposal_event'";

		// Get event
		$proposals_query .= " LEFT JOIN {$wpdb->term_relationships} e_term_rel ON e_term_rel.object_id = posts.ID";
		$proposals_query .= " LEFT JOIN {$wpdb->term_taxonomy} e_term_tax ON e_term_tax.term_taxonomy_id = e_term_rel.term_taxonomy_id AND e_term_tax.taxonomy = 'proposal_event'";
		$proposals_query .= " LEFT JOIN {$wpdb->terms} proposal_event_term ON proposal_event_term.term_id = e_term_tax.term_id";

		$proposals_query .= " LEFT JOIN {$wpdb->postmeta} best_session ON best_session.post_id = posts.ID AND best_session.meta_key = 'best_session'";

		// Select format is stored in post meta but info retrieved from taxonomy.
		if ( ! empty( $args['format'] ) ) {

			// Make sure its an array.
			if ( ! is_array( $args['format'] ) ) {
				$args['format'] = explode( ',', $args['format'] );
			}

			$is_numeric = false;
			foreach( $args['format'] as $event ) {
				$is_numeric = is_numeric( $event );
				break;
			}

			$proposals_query .= " INNER JOIN {$wpdb->postmeta} format ON format.post_id = posts.ID AND format.meta_key = 'selected_session_format'";
			$proposals_query .= " INNER JOIN {$wpdb->terms} format_terms ON format_terms.term_id = format.meta_value";

			// If numeric, check term IDs. Otherwise, check term slugs.
			if ( $is_numeric ) {
				$args['format'] = array_filter( $args['format'], 'is_numeric' );
				$proposals_query .= ' AND format_terms.term_id IN (' . implode( ',', $args['format'] ) . ')';
			} else {
				$proposals_query .= " AND format_terms.slug IN ('" . implode( "','", $args['format'] ) . "')";
			}
		} else {

			$proposals_query .= " LEFT JOIN {$wpdb->postmeta} format ON format.post_id = posts.ID AND format.meta_key = 'selected_session_format'";
			$proposals_query .= " LEFT JOIN {$wpdb->terms} format_terms ON format_terms.term_id = format.meta_value";

		}

		// Preferred format is stored in post meta but info retrieved from taxonomy.
		$proposals_query .= " LEFT JOIN {$wpdb->postmeta} format_preferred ON format_preferred.post_id = posts.ID AND format_preferred.meta_key = 'preferred_session_format'";
		$proposals_query .= " LEFT JOIN {$wpdb->terms} format_preferred_terms ON format_preferred_terms.term_id = format_preferred.meta_value";

		$proposals_query .= " LEFT JOIN {$wpdb->postmeta} proposal_status ON proposal_status.post_id = posts.ID AND proposal_status.meta_key = 'proposal_status'";

		if ( ! empty( $args['get_feedback'] ) ) {
			$proposals_query .= " LEFT JOIN {$wpdb->postmeta} feedback ON feedback.post_id = posts.ID AND feedback.meta_key = 'proposal_feedback'";
		}

		$proposals_query .= " LEFT JOIN {$wpdb->postmeta} session_video_id ON session_video_id.post_id = posts.ID AND session_video_id.meta_key = 'session_video'";
		$proposals_query .= " LEFT JOIN {$wpdb->postmeta} session_video_url ON session_video_url.post_id = posts.ID AND session_video_url.meta_key = 'session_video_url'";

		$proposals_query .= " LEFT JOIN {$wpdb->posts} session_video_post ON session_video_post.ID = session_video_id.meta_value AND session_video_post.post_type = 'video' AND session_video_post.post_status = 'publish'";
		$proposals_query .= " LEFT JOIN {$wpdb->postmeta} session_video_yt ON session_video_yt.post_id = session_video_post.ID AND session_video_yt.meta_key = 'wpc_youtube_video_id'";
		$proposals_query .= " LEFT JOIN {$wpdb->postmeta} session_video_thumb ON session_video_thumb.post_id = session_video_post.ID AND session_video_thumb.meta_key = 'wpc_youtube_video_thumbnail'";

		$proposals_query .= " LEFT JOIN {$wpdb->postmeta} session_slides_url ON session_slides_url.post_id = posts.ID AND session_slides_url.meta_key = 'session_slides_url'";

		if ( $get_user_viewed ) {
			$viewed_str = "wpc_has_viewed_{$user_id}";
			$proposals_query .= $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} viewed ON viewed.post_id = posts.ID AND viewed.meta_key = %s", $viewed_str );
		}

		if ( $get_user_rating ) {
			$rating_str = "wpc_session_rating_{$user_id}";
			$proposals_query .= $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} rating ON rating.post_id = posts.ID AND rating.meta_key = %s", $rating_str );
		}

		if ( ! empty( $args['subject'] ) ) {

			// Make sure its an array.
			if ( ! is_array( $args['subject'] ) ) {
				$args['subject'] = explode( ',', $args['subject'] );
			}

			$is_numeric = false;
			foreach( $args['subject'] as $event ) {
				$is_numeric = is_numeric( $event );
				break;
			}

			$proposals_query .= " INNER JOIN {$wpdb->term_relationships} s_term_rel ON s_term_rel.object_id = posts.ID";
			$proposals_query .= " INNER JOIN {$wpdb->term_taxonomy} s_term_tax ON s_term_tax.term_taxonomy_id = s_term_rel.term_taxonomy_id AND s_term_tax.taxonomy = 'subjects'";

			// If numeric, check term IDs. Otherwise, check term slugs.
			if ( $is_numeric ) {
				$args['subject'] = array_filter( $args['subject'], 'is_numeric' );
				$proposals_query .= " AND s_term_tax.term_id IN (" . implode( ',', $args['subject'] ) . ')';
			} else {
				$proposals_query .= " INNER JOIN {$wpdb->terms} s_terms ON s_terms.term_id = s_term_tax.term_id AND s_terms.slug IN ('" . implode( "','", $args['subject'] ) . "')";
			}
		}

		if ( ! empty( $args['by_profile'] ) ) {

			// Make sure its an array.
			if ( ! is_array( $args['by_profile'] ) ) {
				$args['by_profile'] = explode( ',', $args['by_profile'] );
			}

			$args['by_profile'] = array_filter( $args['by_profile'], 'is_numeric' );

			$proposals_query .= " INNER JOIN {$wpdb->postmeta} speakersm ON speakersm.post_id = posts.ID AND speakersm.meta_key REGEXP '^speakers\_[0-9]+\_speaker$' AND speakersm.meta_value IN (" . implode( ',', $args['by_profile'] ) . ')';
			$proposals_query .= " INNER JOIN {$wpdb->posts} profiles ON profiles.ID = speakersm.meta_value AND profiles.post_type = 'profile' AND profiles.post_status = 'publish'";

		} elseif ( ! empty( $args['by_wp_user'] ) ) {

			// Make sure its an array.
			if ( ! is_array( $args['by_wp_user'] ) ) {
				$args['by_wp_user'] = explode( ',', $args['by_wp_user'] );
			}

			$args['by_wp_user'] = array_filter( $args['by_wp_user'], 'is_numeric' );

			$proposals_query .= " INNER JOIN {$wpdb->postmeta} speakersm ON speakersm.post_id = posts.ID AND speakersm.meta_key REGEXP '^speakers\_[0-9]+\_speaker$'";
			$proposals_query .= " INNER JOIN {$wpdb->posts} profiles ON profiles.ID = speakersm.meta_value AND profiles.post_type = 'profile' AND profiles.post_status = 'publish'";
			$proposals_query .= " INNER JOIN {$wpdb->postmeta} profile_meta ON profile_meta.post_id = profiles.ID AND profile_meta.meta_key = 'wordpress_user' AND profile_meta.meta_value IN (" . implode( ',', $args['by_wp_user'] ) . ')';

		}

		$proposals_query .= " WHERE posts.post_type = 'proposal' AND posts.post_status = 'publish'";

		if ( ! empty( $hasAssets ) ) {

		    if ( in_array( 'slides', $hasAssets ) ) {
		        $proposals_query .= " AND session_slides_url.meta_value IS NOT NULL AND session_slides_url.meta_value != ''";
            }

			if ( in_array( 'video', $hasAssets ) ) {
				$proposals_query .= " AND ( ( session_video_id.meta_value IS NOT NULL AND session_video_id.meta_value != '' AND session_video_post.ID IS NOT NULL ) OR ( session_video_url.meta_value IS NOT NULL AND session_video_url.meta_value != '' ) )";
			}
        }

		if ( ! empty( $args['proposal_event'] ) ) {

			// Make sure its an array.
			if ( ! is_array( $args['proposal_event'] ) ) {
				$args['proposal_event'] = explode( ',', $args['proposal_event'] );
			}

			$is_numeric = false;
			foreach( $args['proposal_event'] as $event ) {
				$is_numeric = is_numeric( $event );
				break;
			}

			// If numeric, check term IDs. Otherwise, check term slugs.
			if ( $is_numeric ) {
				$args['proposal_event'] = array_filter( $args['proposal_event'], 'is_numeric' );
				$proposals_query .= ' AND proposal_event_term.term_id IS NOT NULL AND proposal_event_term.term_id IN (' . implode( ',', $args['proposal_event'] ) . ')';
			} else {
				$proposals_query .= " AND proposal_event_term.slug IS NOT NULL AND proposal_event_term.slug IN ('" . implode( "','", $args['proposal_event'] ) . "')";
			}
		}

		if ( ! empty( $args['proposal_status'] ) ) {

			// Make sure its an array.
			if ( ! is_array( $args['proposal_status'] ) ) {
				$args['proposal_status'] = explode( ',', $args['proposal_status'] );
			}

			if ( in_array( 'submitted', $args['proposal_status'] ) ) {
				$proposals_query .= " AND ( proposal_status.meta_value IS NULL OR proposal_status.meta_value = '' OR proposal_status.meta_value IN ('" . implode( "','", $args['proposal_status'] ) . "'))";
			} else {
				$proposals_query .= " AND proposal_status.meta_value IS NOT NULL AND proposal_status.meta_value IN ('" . implode( "','", $args['proposal_status'] ) . "')";
			}
		}

		if ( ! empty( $args['p'] ) && is_numeric( $args['p'] ) ) {

			$get_row = true;
			$proposals_query .= $wpdb->prepare( ' AND posts.ID = %d', (int) $args['p'] );

		} elseif ( ! empty( $args['post__in'] ) ) {

			// Make sure its an array.
			if ( ! is_array( $args['post__in'] ) ) {
				$args['post__in'] = explode( ',', $args['post__in'] );
			}

			// Sanitize the array.
			$args['post__in'] = array_filter( $args['post__in'], 'is_numeric' );

			$proposals_query .= ' AND posts.ID IN (' . implode( ',', $args['post__in'] ) . ')';

		}

		// Are we searching?
		if ( ! empty( $args['search'] ) ) {

			$search = $args['search'];

			$proposals_query .= $wpdb->prepare( ' AND (
				posts.post_title LIKE %s OR
				posts.post_content LIKE %s OR
				posts.post_excerpt LIKE %s
			)', array( "%{$search}%", "%{$search}%", "%{$search}%" ) );

		}

		$proposals_query .= " GROUP BY posts.ID";

		$desc = ( 'desc' == strtolower( $args['order'] ) ) ? 'DESC' : 'ASC';

		// Setup orderby.
		switch ( $args['orderby'] ) {

			case 'has_viewed':
				if ( $get_user_viewed ) {
					$proposals_query .= " ORDER BY IF ( viewed.meta_value IS NOT NULL, false, true ) {$desc}, viewed.meta_value DESC, posts.post_title ASC";
				}
				break;

			case 'has_rating':
				if ( $get_user_rating ) {
					$proposals_query .= " ORDER BY IF ( rating.meta_value IS NOT NULL, false, true ) {$desc}, rating.meta_value DESC, posts.post_title ASC";
				}
				break;

			case 'avg_rating':
				if ( true == $args['get_avg_rating'] ) {
					$proposals_query .= " ORDER BY avg_rating {$desc}, posts.post_title ASC";
				}
				break;

			case 'selection':
				$proposals_query .= " ORDER BY IF ( proposal_status = 'confirmed', 1, 0 ) {$desc}, IF ( proposal_status = 'declined', 1, 0 ) {$desc}, IF ( proposal_status = 'selected', 1, 0 ) {$desc}, IF ( proposal_status = 'backup', 1, 0 ) {$desc}, IF ( proposal_status = 'maybe', 1, 0 ) {$desc}, IF ( proposal_status = 'no', 1, 0 ) {$desc}, IF ( proposal_status = 'submitted', 1, 0 ) {$desc}";
				if ( true == $args['get_avg_rating'] ) {
					$proposals_query .= ", avg_rating {$desc}, posts.post_title ASC";
				}
				break;

			case 'post_date':
			case 'date':
				$proposals_query .= " ORDER BY posts.post_date {$desc}";
				break;

			case 'post_title':
			case 'title':
			default:
				$proposals_query .= " ORDER BY posts.post_title {$desc}";
				break;

		}

		$proposals = $wpdb->get_results( $proposals_query );

		if ( ! empty( $proposals ) ) {

			$utc_timezone = new DateTimeZone( 'UTC' );
			$now = new DateTime( 'now', $utc_timezone );

			foreach ( $proposals as &$proposal ) {

				$content = ! empty( $proposal->content ) ? $proposal->content : null;

				$proposal->content = array(
					'raw'      => empty( $content ) ? null : $content,
					'rendered' => empty( $content ) ? null : wpautop( $content ),
				);

				if ( ! empty( $proposal->excerpt ) ) {
					$excerpt = $proposal->excerpt;
				} elseif ( ! empty( $content ) ) {
					$excerpt = $content;
				} else {
					$excerpt = null;
				}

				if ( ! empty( $excerpt ) ) {
					$excerpt = wp_trim_words( $excerpt, 55, '&hellip;' );
				}

				$proposal->excerpt = array(
					'raw'      => empty( $excerpt ) ? null : $excerpt,
					'rendered' =>  empty( $excerpt ) ? null : wpautop( $excerpt ),
				);

				if ( ! empty( $args['get_profiles'] ) ) {
					$proposal->speakers = $this->get_profiles(array(
						'by_proposal'  => $proposal->ID,
						'get_headshot' => $args['get_headshot'],
						'get_wp_user'  => $args['get_wp_user'],
					));
				}

				if ( ! empty( $args['get_subjects'] ) ) {
					$proposal->subjects = wp_get_object_terms( $proposal->ID, 'subjects', array( 'fields' => 'all' ) );
				}

				// Fix the permalink.
				if ( empty( $proposal->permalink ) ) {
					$proposal->permalink = null;
				}

				switch ( $proposal->event_slug ) {

					case 'wpcampus-online-2017':
						$date = '2017-01-23 00:00:00';
						$proposal->post_date = $date;
						$proposal->post_date_gmt = $date;
						$proposal->event_permalink = 'https://online.wpcampus.org/';
						$proposal->permalink = 'https://online.wpcampus.org/schedule/' . $proposal->permalink;
						break;

					case 'wpcampus-online-2018':
						$date = '2018-01-30 00:00:00';
						$proposal->post_date = $date;
						$proposal->post_date_gmt = $date;
						$proposal->event_permalink = 'https://online.wpcampus.org/';
						$proposal->permalink = 'https://online.wpcampus.org/schedule/' . $proposal->permalink;
						break;

					case 'wpcampus-online-2019':
						$date = '2019-01-31 00:00:00';
						$proposal->post_date = $date;
						$proposal->post_date_gmt = $date;
						$proposal->event_permalink = 'https://online.wpcampus.org/';
						$proposal->permalink = 'https://online.wpcampus.org/schedule/' . $proposal->permalink;
						break;

					case 'wpcampus-2016':
						$date = '2016-07-15 00:00:00';
						$proposal->post_date = $date;
						$proposal->post_date_gmt = $date;
						$proposal->event_permalink = 'https://2016.wpcampus.org/';
						$proposal->permalink = 'https://2016.wpcampus.org/schedule/' . $proposal->permalink;
						break;

					case 'wpcampus-2017':
						$date = '2017-07-14 00:00:00';
						$proposal->post_date = $date;
						$proposal->post_date_gmt = $date;
						$proposal->event_permalink = 'https://2017.wpcampus.org/';
						$proposal->permalink = 'https://2017.wpcampus.org/schedule/' . $proposal->permalink;
						break;

					case 'wpcampus-2018':
						$date = '2018-07-13 00:00:00';
						$proposal->post_date = $date;
						$proposal->post_date_gmt = $date;
						$proposal->event_permalink = 'https://2018.wpcampus.org/';
						$proposal->permalink = 'https://2018.wpcampus.org/schedule/' . $proposal->permalink;
						break;

					case 'wpcampus-2019':
						$date = '2019-07-26 00:00:00';
						$proposal->post_date = $date;
						$proposal->post_date_gmt = $date;
						$proposal->event_permalink = 'https://2019.wpcampus.org/';
						$proposal->permalink = 'https://2019.wpcampus.org/schedule/' . $proposal->permalink;
						break;

					default:
						$proposal->permalink = null;
						break;

				}

				$post_date_gmt = new DateTime( $proposal->post_date_gmt, $utc_timezone );

				$proposal->future = $post_date_gmt > $now;

			}

			if ( $get_row ) {
				$proposals = array_shift( $proposals );
			}
		}

		return $proposals;
	}

	public function get_profile_field( $profile_id, $profile_field ) {
		return get_post_meta( $profile_id, $profile_field, true );
	}

	public function update_profile_field( $profile_id, $profile_field, $value ) {
		return update_post_meta( $profile_id, $profile_field, $value );
	}

	public function update_profile_first_name( $profile_id, $value ) {
		return $this->update_profile_field( $profile_id, 'first_name', $value );
	}

	public function update_profile_last_name( $profile_id, $value ) {
		return $this->update_profile_field( $profile_id, 'last_name', $value );
	}

	public function update_profile_display_name( $profile_id, $value ) {
		return $this->update_profile_field( $profile_id, 'display_name', $value );
	}

	public function update_profile_email( $profile_id, $value ) {
		return $this->update_profile_field( $profile_id, 'email', $value );
	}

	public function update_profile_phone( $profile_id, $value ) {
		return $this->update_profile_field( $profile_id, 'phone', $value );
	}

	public function update_profile_website( $profile_id, $value ) {
		return $this->update_profile_field( $profile_id, 'website', $value );
	}

	public function update_profile_company( $profile_id, $value ) {
		return $this->update_profile_field( $profile_id, 'company', $value );
	}

	public function update_profile_company_website( $profile_id, $value ) {
		return $this->update_profile_field( $profile_id, 'company_website', $value );
	}

	public function update_profile_company_position( $profile_id, $value ) {
		return $this->update_profile_field( $profile_id, 'company_position', $value );
	}

	public function update_profile_twitter( $profile_id, $value ) {
		return $this->update_profile_field( $profile_id, 'twitter', $value );
	}

	public function update_profile_linkedin( $profile_id, $value ) {
		return $this->update_profile_field( $profile_id, 'linkedin', $value );
	}

	public function get_profile_event_unavailability( $profile_id ) {
		return $this->get_profile_field( $profile_id, 'event_unavailability' );
	}

	public function update_profile_event_unavailability( $profile_id, $value ) {
		return $this->update_profile_field( $profile_id, 'event_unavailability', $value );
	}

	public function get_profile_event_coc( $profile_id ) {
		return $this->get_profile_field( $profile_id, 'event_coc' );
	}

	public function update_profile_event_coc( $profile_id, $value ) {
		return $this->update_profile_field( $profile_id, 'event_coc', $value );
	}

	public function get_profile_event_technology( $profile_id ) {
		return $this->get_profile_field( $profile_id, 'event_technology' );
	}

	public function update_profile_event_technology( $profile_id, $value ) {
		return $this->update_profile_field( $profile_id, 'event_technology', $value );
	}

	public function get_profile_event_video_release( $profile_id ) {
		return $this->get_profile_field( $profile_id, 'event_video_release' );
	}

	public function update_profile_event_video_release( $profile_id, $value ) {
		return $this->update_profile_field( $profile_id, 'event_video_release', $value );
	}

	public function get_profile_event_special_requests( $profile_id ) {
		return $this->get_profile_field( $profile_id, 'event_special_requests' );
	}

	public function update_profile_event_special_requests( $profile_id, $value ) {
		return $this->update_profile_field( $profile_id, 'event_special_requests', $value );
	}

	public function get_profile( $profile_id, $args = array() ) {
		$args['p'] = $profile_id;
		return $this->get_profiles( $args );
	}

	/**
	 * confirmed
	 * declined
	 * selected
	 * backup
	 * maybe
	 * no
	 * submitted
	 * @param array $args
	 * @return array
	 */
	public function get_profile_emails( $args = array() ) {
		global $wpdb;

		// Get the event.
		$proposal_event = $this->get_proposal_event();

		if ( empty( $proposal_event ) || ! is_numeric( $proposal_event ) ) {
			return array();
		}

		$args = wp_parse_args( $args, array(
			'proposal_status' => null,
		));

		$select = "SELECT DISTINCT email.meta_value FROM {$wpdb->postmeta} email";

		$join = " INNER JOIN {$wpdb->posts} profile ON profile.ID = email.post_id AND profile.post_type = 'profile' AND profile.post_status = 'publish'
			INNER JOIN {$wpdb->postmeta} proposal_sp ON proposal_sp.meta_value = profile.ID AND proposal_sp.meta_key REGEXP 'speakers\_[0-9]+\_speaker'
			INNER JOIN {$wpdb->posts} proposal ON proposal.ID = proposal_sp.post_id AND proposal.post_type = 'proposal' AND proposal.post_status = 'publish'";

		$join .= " LEFT JOIN {$wpdb->postmeta} proposal_status ON proposal_status.post_id = proposal.ID AND proposal_status.meta_key = 'proposal_status'";

		$join .= $wpdb->prepare( " INNER JOIN {$wpdb->term_relationships} term_rel ON term_rel.object_id = proposal.ID
			INNER JOIN {$wpdb->term_taxonomy} term_tax ON term_tax.term_taxonomy_id = term_rel.term_taxonomy_id AND term_tax.term_id = %d", $proposal_event );

		$where = " WHERE email.meta_key = 'email'";

		if ( ! empty( $args['proposal_status'] ) ) {

			// Make sure its an array.
			if ( ! is_array( $args['proposal_status'] ) ) {
				$args['proposal_status'] = explode( ',', $args['proposal_status'] );
			}

			// Get "not selected" proposals.
			$not_selected = array_search( 'not_selected', $args['proposal_status'] );
			if ( $not_selected !== false ) {
				unset( $args['proposal_status'][ $not_selected ] );
				$args['proposal_status'] = array_merge( $args['proposal_status'], array( 'submitted', 'no', 'maybe', 'backup' ) );
			}

			if ( in_array( 'submitted', $args['proposal_status'] ) ) {
				$where .= " AND ( proposal_status.meta_value IS NULL OR proposal_status.meta_value = '' OR proposal_status.meta_value IN ('" . implode( "','", $args['proposal_status'] ) . "'))";
			} else {
				$where .= " AND proposal_status.meta_value IN ('" . implode( "','", $args['proposal_status'] ) . "')";
			}

			// TODO: have to use this method to remove profiles with emails from selected proposals.
			// Can simplify later when we can use the same profile for multiple proposals?
			if ( $not_selected !== false ) {

				$selected_emails = $this->get_profile_emails( array( 'proposal_status' => 'selected,declined,confirmed' ));
				if ( ! empty( $selected_emails ) ) {
					$where .= " AND email.meta_value NOT IN ('" . implode( "','", $selected_emails ) . "')";
				}
			}
		}

		return $wpdb->get_col( $select . $join . $where );
	}

	/**
	 * 
	 */
	public function get_profile_headshots( $args = array() ) {

		// Get the event.
		$proposal_event = $this->get_proposal_event();

		if ( empty( $proposal_event ) || ! is_numeric( $proposal_event ) ) {
			return array();
		}

		$args = wp_parse_args( $args, array(
			'proposal_event'  => $proposal_event,
			'proposal_status' => null,
			'get_headshot'    => false,
		));

		$speakers = wpcampus_speakers()->get_profiles( $args );

		if ( empty( $speakers ) ) {
			return null;
		}

		$headshots = array();

		// Get full size photo.
		foreach ( $speakers as $speaker ) {
			$headshot = get_the_post_thumbnail_url( $speaker->ID, 'full' );
			if ( ! empty( $headshot ) ) {
				$headshots[] = $headshot;
			}
		}

		return $headshots;
	}

	/**
	 * @TODO:
	 * - Only get profiles assigned to proposals who are assigned to schedules?
	 */
	public function get_profiles( $args = array() ) {
		global $wpdb;

		// Merge incoming with defaults.
		$args = wp_parse_args( $args, array(
			'p'               => 0,
			'post__in'        => '',
			'orderby'         => 'name',
			'order'           => 'ASC',
			'by_proposal'     => null,
			'proposal_event'  => null,
			'proposal_status' => null,
			'get_headshot'    => true,
			'get_feedback'    => false,
			'get_proposals'   => false,
			'get_wp_user'     => true,
		));

		$get_row = false;

		$meta_fields = array( 'wordpress_user', 'first_name', 'last_name', 'display_name', 'email', 'phone', 'company', 'company_position', 'company_website', 'website', 'facebook', 'twitter', 'instagram', 'linkedin' );

		$profiles_query = "SELECT DISTINCT posts.ID,
			IF ( display_name.meta_value IS NOT NULL AND display_name.meta_value != '',
				display_name.meta_value,
				IF ( ( first_name.meta_value IS NOT NULL AND first_name.meta_value != '' ) OR ( last_name.meta_value IS NOT NULL AND last_name.meta_value != '' ), TRIM( CONCAT( first_name.meta_value, ' ', last_name.meta_value ) ), posts.post_title )
			) AS title,
			posts.post_name AS slug,
			posts.post_content AS content,
			posts.post_excerpt AS excerpt,
			null AS headshot";

		//proposal_event.meta_value AS event,
		//IF ( proposal_event.meta_value IS NOT NULL, (SELECT name FROM {$wpdb->terms} WHERE term_id = proposal_event.meta_value), null ) AS event_name,

		//posts.post_author,
		//posts.post_status,
		//posts.post_date,
		//posts.post_modified,
		//posts.post_modified_gmt,

		foreach ( $meta_fields as $field ) {
			$profiles_query .= ", {$field}.meta_value AS {$field}";
		}

		// Overwrite info from WP user
		if ( $args['get_wp_user'] ) {
			$profiles_query .= ", users.user_nicename AS nicename, CONCAT( '" . wpcampus_get_network_site_url() . "', 'author/', users.user_nicename, '/' ) AS permalink";
		}

		$profiles_query .= " FROM {$wpdb->posts} posts";

		foreach ( $meta_fields as $field ) {
			$profiles_query .= $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} {$field} ON {$field}.post_id = posts.ID AND {$field}.meta_key = %s", $field );
		}

		// IMPORTANT: Only get profiles that are published and assigned to a proposal.
		$profiles_query .= " INNER JOIN {$wpdb->postmeta} proposalm ON proposalm.meta_value = posts.ID AND proposalm.meta_key REGEXP 'speakers\_[0-9]+\_speaker'";

		if ( ! empty( $args['by_proposal'] ) ) {

			// Make sure it's an array.
			if ( ! is_array( $args['by_proposal'] ) ) {
				$args['by_proposal'] = explode( ',', str_replace( ' ', '', $args['by_proposal'] ) );
			}

			// Make sure they're IDs.
			$args['by_proposal'] = array_filter( $args['by_proposal'], 'is_numeric' );

			$profiles_query .= ' AND proposalm.post_id IN (' . implode( ',', $args['by_proposal'] ) . ')';

		}

		$profiles_query .= " INNER JOIN {$wpdb->posts} proposals ON proposals.ID = proposalm.post_id AND proposals.post_type = 'proposal' AND proposals.post_status = 'publish'";

		$profiles_query .= " LEFT JOIN {$wpdb->postmeta} proposal_status ON proposal_status.post_id = proposals.ID AND proposal_status.meta_key = 'proposal_status'";

		if ( ! empty( $args['proposal_event'] ) ) {

			// Make sure its an array.
			if ( ! is_array( $args['proposal_event'] ) ) {
				$args['proposal_event'] = explode( ',', $args['proposal_event'] );
			}

			$args['proposal_event'] = array_filter( $args['proposal_event'], 'is_numeric' );

			$profiles_query .= " INNER JOIN {$wpdb->postmeta} proposal_event ON proposal_event.post_id = proposals.ID AND proposal_event.meta_key = 'proposal_event' AND proposal_event.meta_value IN (" . implode( ',', $args['proposal_event'] ) . ')';

		}

		// Overwrite info from WP user
		if ( $args['get_wp_user'] ) {
			$profiles_query .= " LEFT JOIN {$wpdb->users} users ON users.ID = wordpress_user.meta_value";
		}

		$profiles_query .= " WHERE posts.post_type = 'profile' AND posts.post_status = 'publish'";

		if ( ! empty( $args['p'] ) && is_numeric( $args['p'] ) ) {

			$get_row = true;
			$profiles_query .= $wpdb->prepare( ' AND posts.ID = %d', (int) $args['p'] );

		} elseif ( ! empty( $args['post__in'] ) ) {

			// Make sure its an array.
			if ( ! is_array( $args['post__in'] ) ) {
				$args['post__in'] = explode( ',', $args['post__in'] );
			}

			// Sanitize the array.
			$args['post__in'] = array_filter( $args['post__in'], 'is_numeric' );

			$profiles_query .= ' AND posts.ID IN (' . implode( ',', $args['post__in'] ) . ')';

		}

		if ( ! empty( $args['proposal_status'] ) ) {

			// Make sure its an array.
			if ( ! is_array( $args['proposal_status'] ) ) {
				$args['proposal_status'] = explode( ',', $args['proposal_status'] );
			}

			// Get "not selected" proposals.
			$not_selected = array_search( 'not_selected', $args['proposal_status'] );
			if ( $not_selected !== false ) {
				unset( $args['proposal_status'][ $not_selected ] );
				$args['proposal_status'] = array_merge( $args['proposal_status'], array( 'submitted', 'no', 'maybe', 'backup' ) );
			}

			if ( in_array( 'submitted', $args['proposal_status'] ) ) {
				$profiles_query .= " AND ( proposal_status.meta_value IS NULL OR proposal_status.meta_value = '' OR proposal_status.meta_value IN ('" . implode( "','", $args['proposal_status'] ) . "'))";
			} else {
				$profiles_query .= " AND proposal_status.meta_value IN ('" . implode( "','", $args['proposal_status'] ) . "')";
			}

			// TODO: have to use this method to remove profiles with emails from selected proposals.
			// Can simplify later when we can use the same profile for multiple proposals?
			if ( $not_selected !== false ) {

				$selected_emails = $this->get_profile_emails( array( 'proposal_status' => 'selected,declined,confirmed' ));
				if ( ! empty( $selected_emails ) ) {
					$profiles_query .= " AND email.meta_value NOT IN ('" . implode( "','", $selected_emails ) . "')";
				}
			}
		}

		// Setup orderby.
		$desc = ( 'DESC' == $args['order'] ? 'DESC' : 'ASC' );
		switch ( $args['orderby'] ) {

			case 'name':
				$profiles_query .= " ORDER BY last_name {$desc}, first_name {$desc}";
				break;

			case 'post_title':
			default:
				$profiles_query .= " ORDER BY title {$desc}";
				break;
		}

		$profiles = $wpdb->get_results( $profiles_query );

		if ( ! empty( $profiles ) ) {

			foreach ( $profiles as &$profile ) {

				$profile->content = array(
					'raw'      => $profile->content,
					'rendered' => wpautop( $profile->content ),
				);

				$profile->excerpt = array(
					'raw'      => $profile->excerpt,
					'rendered' => wpautop( $profile->excerpt ),
				);

				if ( $args['get_wp_user'] && ! empty( $profile->wordpress_user ) ) {
					$profile->avatar = get_avatar_url( $profile->wordpress_user );
				} else {
					$profile->avatar = null;
				}

				if ( ! empty( $args['get_headshot'] ) ) {
					$headshot          = get_the_post_thumbnail_url( $profile->ID, 'thumbnail' );
					$profile->headshot = ! empty( $headshot ) ? $headshot : null;
				}

				if ( ! empty( $args['get_proposals'] ) ) {

					$proposal_args = array(
						'by_profile' => $profile->ID,
					);

					if ( ! empty( $args['proposal_event'] ) ) {
						$proposal_args['proposal_event'] = $args['proposal_event'];
					}

					if ( ! empty( $args['proposal_status'] ) ) {
						$proposal_args['proposal_status'] = $args['proposal_status'];
					}

					if ( ! empty( $args['get_feedback'] ) ) {
						$proposal_args['get_feedback'] = $args['get_feedback'];
					}

					$profile->proposals = $this->get_proposals( $proposal_args );

				}
			}

			if ( $get_row ) {
				$profiles = array_shift( $profiles );
			}
		}

		return $profiles;
	}

	/**
	 *
	 */
	public function load_review_assets() {

		$assets_dir = wpcampus_speakers()->get_plugin_url() . 'assets/build/';

		wp_enqueue_style( 'wpc-admin-speakers', $assets_dir . 'css/wpc-admin-proposals-review.min.css', array(), null );

		wp_register_script( 'handlebars', $assets_dir . 'js/handlebars.min.js', array(), null, true );
		wp_enqueue_script( 'wpc-admin-proposals-review', $assets_dir . 'js/wpc-admin-proposals-review.min.js', array( 'jquery', 'handlebars' ), true );
		wp_localize_script( 'wpc-admin-proposals-review', 'wpc_prop_review', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'proposal_event'  => $this->get_proposal_event(),
			'users_reviewing' => (int) get_option( 'wpc_users_reviewing_proposals_count' ), // @TODO make programmatic
		));
	}

	/**
	 * Print the proposal reviews table.
	 */
	public function print_proposals_review_table() {

		if ( ! current_user_can( 'review_wpc_proposals' ) ) {
			return;
		}

		$is_selection = $this->proposal_status_is_selection();
		$display_speakers = wpcampus_speakers()->proposal_selection_display_speakers();

		?>
		<div class="wpc-proposals-table-wrapper loading"
		     data-template="wpc-template-proposals-review"
		     data-header="Review Proposals"
		     data-orderby="avg_rating"
		     data-order="DESC"
		     data-get-user-viewed="1"
		     data-get-user-rating="1"
		     data-get-avg-rating="1"
		     data-get-profiles="1"
		     data-get-subjects="1">
			<h2>Review Proposals</h2>
		</div>

		<script id="wpc-template-proposals-review" type="text/x-handlebars-template">
			<div class="wpc-proposals-table-header">
				<h2>{{header}} ({{proposals.length}})</h2>
				<button class="button button-primary wpc-proposals-table-update wpc-proposals-table-update-single">Update table</button>
			</div>
			<div class="wpc-proposals-progress">
				<span class="progress-level" style="width:{{user_progress}}%;"></span>
				<span class="progress-label">You have reviewed <strong>{{proposal_rating_count}} ({{user_progress}}%) out of {{proposals.length}}</strong> proposals.</span>
			</div>
			<div class="wpc-proposals-progress everyone">
				<span class="progress-level" style="width:{{total_progress}}%;"></span>
				<span class="progress-label">Everyone has reviewed <strong>{{total_progress}}%</strong> of the proposals.</span>
			</div>
			<div class="wpc-proposals-table wpc-proposals-review">
				<div class="wpc-table-head">
					<div class="wpc-table-row">
						<div class="wpc-table-cell number">#</div>
						<div class="wpc-table-cell title"><?php _e( 'Title', 'wpcampus-speakers' ); ?></div>
						<div class="wpc-table-cell status"><?php _e( 'Status', 'wpcampus-speakers' ); ?></div>
						<div class="wpc-table-cell avg_rating"><?php _e( 'Avg Rating', 'wpcampus-speakers' ); ?></div>
						<div class="wpc-table-cell your_rating"><?php _e( 'Your Rating', 'wpcampus-speakers' ); ?></div>
						<div class="wpc-table-cell comment"><?php _e( 'Comments', 'wpcampus-speakers' ); ?></div>
					</div>
				</div>
				<div class="wpc-table-body">
					{{#if proposals}}
					{{#proposals}}
					<div class="wpc-table-row {{proposal_review_class}}">
						<div class="wpc-table-cell number">{{math @index "+" 1}}</div>
						<div class="wpc-table-cell title">
							<div class="proposal-title">{{#if permalink}}<a href="{{permalink}}" target="_blank">{{/if}}{{title}}{{#if permalink}}</a>{{/if}}</div>
							<?php

							if ( $is_selection && $display_speakers ) :
								?>
								{{#if speakers}}<div class="proposal-speakers">{{#each speakers}}{{#unless @first}}, {{/unless}}{{title}} ({{company}}){{/each}}</div>{{/if}}
								<?php
							endif;

							?>
							{{#if subjects}}<div class="proposal-subjects">{{#each subjects}}{{#unless @first}}, {{/unless}}{{name}}{{/each}}</div>{{/if}}
						</div>
						<div class="wpc-table-cell status">{{proposal_status_label}}</div>
						<div class="wpc-table-cell avg_rating"><?php echo $is_selection ? '{{avg_rating}}' : '-'; ?></div>
						<div class="wpc-table-cell your_rating"><span>{{#if rating}}{{rating}}{{else}}-{{/if}}</span></div>
						<div class="wpc-table-cell comment"><span>{{comment_count}}</span></div>
					</div>
					{{/proposals}}
					{{else}}
					<div class="wpc-table-row no-proposals">
						<div class="wpc-table-cell"><?php _e( 'There are no proposals available.', 'wpcampus-speakers' ); ?></div>
					</div>
					{{/if}}
				</div>
			</div>
		</script>
		<?php
	}

	/**
	 *
	 */
	public function print_proposals_select_table() {

		if ( ! current_user_can( 'review_wpc_proposals' ) ) {
			return;
		}

		$is_selection = $this->proposal_status_is_selection();
		$display_speakers = wpcampus_speakers()->proposal_selection_display_speakers();

		?>
		<div class="wpc-proposals-table-wrapper loading"
		     data-header="Confirmed Proposals"
		     data-template="wpc-template-proposals-select"
		     data-orderby="avg_rating"
		     data-order="DESC"
		     data-proposal-status="confirmed"
		     data-get-user-rating="1"
		     data-get-avg-rating="1"
		     data-get-profiles="1"
		     data-get-subjects="1">
			<h2>Confirmed Proposals</h2>
		</div>

		<div class="wpc-proposals-table-wrapper loading"
		     data-header="Declined Proposals"
		     data-template="wpc-template-proposals-select"
		     data-orderby="avg_rating"
		     data-order="DESC"
		     data-proposal-status="declined"
		     data-get-user-rating="1"
		     data-get-avg-rating="1"
		     data-get-profiles="1"
		     data-get-subjects="1">
			<h2>Declined Proposals</h2>
		</div>

		<div class="wpc-proposals-table-wrapper loading"
		    data-header="Selected Proposals"
		    data-template="wpc-template-proposals-select"
		    data-orderby="avg_rating"
		    data-order="DESC"
		    data-proposal-status="selected"
		    data-get-user-rating="1"
		    data-get-avg-rating="1"
			data-get-profiles="1"
			data-get-subjects="1">
			<h2>Selected Proposals</h2>
		</div>

		<div class="wpc-proposals-table-wrapper loading"
		     data-header="Backup Proposals"
		     data-template="wpc-template-proposals-select"
		     data-orderby="avg_rating"
		     data-order="DESC"
		     data-proposal-status="backup"
		     data-get-user-rating="1"
		     data-get-avg-rating="1"
		     data-get-profiles="1"
		     data-get-subjects="1">
			<h2>Backup Proposals</h2>
		</div>

		<div class="wpc-proposals-table-wrapper loading"
		     data-header="Maybe Proposals"
		     data-template="wpc-template-proposals-select"
		     data-orderby="avg_rating"
		     data-order="DESC"
		     data-proposal-status="maybe"
		     data-get-user-rating="1"
		     data-get-avg-rating="1"
		     data-get-profiles="1"
		     data-get-subjects="1">
			<h2>Maybe Proposals</h2>
		</div>

		<div class="wpc-proposals-table-wrapper loading"
		     data-header="No Proposals"
		     data-template="wpc-template-proposals-select"
		     data-orderby="avg_rating"
		     data-order="DESC"
		     data-proposal-status="no"
		     data-get-user-rating="1"
		     data-get-avg-rating="1"
		     data-get-profiles="1"
		     data-get-subjects="1">
			<h2>No Proposals</h2>
		</div>

		<div class="wpc-proposals-table-wrapper loading"
		     data-header="Submitted Proposals"
		     data-template="wpc-template-proposals-select"
		     data-orderby="avg_rating"
		     data-order="DESC"
		     data-proposal-status="submitted"
		     data-get-user-rating="1"
		     data-get-avg-rating="1"
		     data-get-profiles="1"
		     data-get-subjects="1">
			<h2>Submitted Proposals</h2>
		</div>

		<script id="wpc-template-proposals-select" type="text/x-handlebars-template">
			<div class="wpc-proposals-table-header">
				<h2>{{header}} ({{proposals.length}})</h2>
				<button class="button button-primary wpc-proposals-table-update wpc-proposals-table-update-single">Update table</button>
			</div>
			<div class="wpc-proposals-stats">
				{{print_proposal_stats}}
			</div>
			{{proposal_filters}}
			<div class="wpc-proposals-table wpc-proposals-select">
				<div class="wpc-table-head">
					<div class="wpc-table-row">
						<div class="wpc-table-cell number">#</div>
						<div class="wpc-table-cell title"><?php _e( 'Title', 'wpcampus-speakers' ); ?></div>
						<div class="wpc-table-cell avg_rating"><?php _e( 'Avg Rating', 'wpcampus-speakers' ); ?></div>
						<div class="wpc-table-cell your_rating"><?php _e( 'Your Rating', 'wpcampus-speakers' ); ?></div>
						<div class="wpc-table-cell format">Format</div>
					</div>
				</div>
				<div class="wpc-table-body">
					{{#if proposals}}
					{{#proposals}}
					<div class="wpc-table-row {{proposal_select_class}}">
						<div class="wpc-table-cell number">{{math @index "+" 1}}</div>
						<div class="wpc-table-cell title">
							<div class="proposal-title">{{#if permalink}}<a href="{{permalink}}" target="_blank">{{/if}}{{title}}{{#if permalink}}</a>{{/if}}</div>
							<?php

							if ( $is_selection && $display_speakers ) :
								?>
								{{#if speakers}}<div class="proposal-speakers">{{#each speakers}}{{#unless @first}}, {{/unless}}{{title}} ({{company}}){{/each}}</div>{{/if}}
								{{has_speaker_dup}}
								<?php
							endif;

							?>
							{{#if subjects}}<div class="proposal-subjects">{{#each subjects}}{{#unless @first}}, {{/unless}}{{name}}{{/each}}</div>{{/if}}
						</div>
						<div class="wpc-table-cell avg_rating"><?php echo $is_selection ? '{{avg_rating}}' : '-'; ?></div>
						<div class="wpc-table-cell your_rating"><span>{{#if rating}}{{rating}}{{else}}-{{/if}}</span></div>
						<div class="wpc-table-cell format">{{print_format}}</div>
					</div>
					{{/proposals}}
					{{else}}
					<div class="wpc-table-row no-proposals">
						<div class="wpc-table-cell"><?php _e( 'There are no proposals available.', 'wpcampus-speakers' ); ?></div>
					</div>
					{{/if}}
				</div>
			</div>
		</script>
		<?php
	}

	/**
	 * Get all sessions. Used for for data feed.
	 */
	public function get_sessions( $args = array() ) {

		// Merge incoming with defaults.
		$args = wp_parse_args( $args, array(
			'orderby'         => 'post_title',
			'order'           => 'ASC',
			'proposal_status' => 'confirmed',
			'get_profiles'    => true,
			'get_wp_user'     => true,
			'assets'          => null,
			//'user_id'          => null,
			//'get_user_viewed'  => false,
			//'get_user_rating'  => false,
			//'get_avg_rating'   => false,
			//'by_profile'       => null,
			//'proposal_event'   => '',
			//'subject'          => '',
			//'get_headshot'     => false,
			//'get_feedback'     => false,
			//'get_subjects'     => false,
		));

		return $this->get_proposals( $args );

		// Used to group them by event.
		global $wpdb;

		// Will hold sessions.
		$sessions = array();

		// Do we have any filters?
		$filters = array();
		$allowed_filters = array( 'e' );
		if ( ! empty( $_GET ) ) {
			foreach ( $_GET as $get_filter_key => $get_filter_value ) {
				if ( ! in_array( $get_filter_key, $allowed_filters ) ) {
					continue;
				}
				$filters[ $get_filter_key ] = explode( ',', sanitize_text_field( $get_filter_value ) );
			}
		}

		// Store info for event sites.
		$event_sites = array(
			array(
				'site_id' => 6,
				'title'   => 'WPCampus Online 2018',
				'slug'    => 'wpcampus-online-2018',
				'date'    => '2018-01-30',
			),
			array(
				'site_id' => 7,
				'title'   => 'WPCampus 2017',
				'slug'    => 'wpcampus-2017',
				'date'    => "2017-07-15','2017-07-14",
			),
			array(
				'site_id' => 6,
				'title'   => 'WPCampus Online 2017',
				'slug'    => 'wpcampus-online-2017',
				'date'    => '2017-01-23',
			),
			array(
				'site_id' => 4,
				'title'   => 'WPCampus 2016',
				'slug'    => 'wpcampus-2016',
				'date'    => "2016-07-16','2016-07-16",
			),
		);

		$main_site_prefix = $wpdb->prefix;

		foreach ( $event_sites as $event ) {

			// If filtering by event, remove those not in the filter.
			if ( ! empty( $filters['e'] ) && ! in_array( $event['slug'], $filters['e'] ) ) {
				continue;
			}

			if ( empty( $event['slug'] ) ) {
				continue;
			}

			// Set the ID and title
			$event_site_id = $event['site_id'];

			// Get the site's DB prefix.
			$event_site_prefix = $wpdb->get_blog_prefix( $event_site_id );

			// Get the schedule URL for the site.
			$event_site_schedule_url = get_site_url( $event_site_id, '/schedule/' );

			$event_slug = $event['slug'];

			// Get the sessions.
			$site_sessions = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT proposal.meta_value AS ID,
					%d AS blog_id,
					%s AS event,
					%s AS event_slug,
					event_date.meta_value AS event_date,
					the_proposal.post_title,
					the_proposal.post_content,
					posts.post_parent,
					slides.meta_value AS slides_url,
					posts.post_name AS slug,
					CONCAT( %s, posts.post_name, '/') AS permalink,
					posts.guid
					FROM {$event_site_prefix}posts posts
					INNER JOIN {$event_site_prefix}postmeta event_type ON event_type.post_id = posts.ID AND event_type.meta_key = 'event_type' AND event_type.meta_value = 'session'
					INNER JOIN {$event_site_prefix}postmeta proposal ON proposal.post_id = posts.ID AND proposal.meta_key = 'proposal' AND proposal.meta_value != ''
					INNER JOIN {$event_site_prefix}postmeta event_date ON event_date.post_id = posts.ID AND event_date.meta_key = 'conf_sch_event_date' AND event_date.meta_value IN ('" . $event['date'] . "')
					INNER JOIN {$main_site_prefix}posts the_proposal ON the_proposal.ID = proposal.meta_value AND the_proposal.post_type = 'proposal' AND the_proposal.post_status = 'publish'
					LEFT JOIN {$main_site_prefix}postmeta slides ON slides.post_id = the_proposal.ID AND slides.meta_key = 'session_slides_url'
					WHERE posts.post_type = 'schedule' AND posts.post_status = 'publish'",
					$event_site_id, $event['title'], $event_slug, $event_site_schedule_url
				)
			);

			// Sort by title.
			usort( $site_sessions, function( $a, $b ) {
				if ( $a->post_title == $b->post_title ) {
					return 0;
				}
				return ( $a->post_title < $b->post_title ) ? -1 : 1;
			});

			// Add to complete list.
			$sessions[ $event_slug ] = array(
				'title'    => $event['title'],
				'slug'     => $event_slug,
				'sessions' => $site_sessions
			);

		}

		return $sessions;
	}

	/**
	 * Get all sessions subjects. Used for for data feed.
	 */
	public function get_sessions_subjects( $args = array() ) {
		global $wpdb;

		// Merge incoming with defaults.
		$defaults = array(
			'orderby'         => 'title',
			'order'           => 'ASC',
			'proposal_status' => 'confirmed',
		);
		$args = wp_parse_args( $args, $defaults );

		$taxonomy = 'subjects';

		$query = $wpdb->prepare( "SELECT DISTINCT terms.term_id, terms.name, terms.slug FROM {$wpdb->terms} terms
			INNER JOIN {$wpdb->term_taxonomy} term_tax ON term_tax.term_id = terms.term_id AND term_tax.taxonomy = %s
			INNER JOIN {$wpdb->term_relationships} term_rel ON term_rel.term_taxonomy_id = term_tax.term_taxonomy_id
			INNER JOIN {$wpdb->posts} posts ON posts.ID = term_rel.object_id AND posts.post_type = 'proposal' AND posts.post_status = 'publish'",
			$taxonomy
		);

		if ( ! empty( $args['proposal_status'] ) ) {
			$proposal_status = strtolower( $args['proposal_status'] );
			if ( in_array( $proposal_status, array( 'confirmed' ) ) ) {
				$query .= $wpdb->prepare( " INNER JOIN {$wpdb->postmeta} proposal_status ON proposal_status.post_id = posts.ID AND proposal_status.meta_key = 'proposal_status' AND proposal_status.meta_value = %s", $proposal_status );
			}
		}

		if ( empty( $args['orderby'] ) ) {
			$args['orderby'] = $defaults['orderby'];
		}
		if ( empty( $args['order'] ) ) {
			$args['order'] = $defaults['order'];
		}

		// Make sure order is valid.
		$args['order'] = strtoupper( $args['order'] );
		if ( ! in_array( $args['order'], array( 'ASC', 'DESC' ) ) ) {
			$args['order'] = $defaults['order'];
		}

		switch( $args['orderby'] ) {

			case 'title':
				$query .= ' ORDER BY terms.name ' . $args['order'];
				break;
		}

		return $wpdb->get_results( $query );
	}
}

/**
 * Returns the instance of our WPCampus_Speakers class.
 *
 * Use this function and class methods
 * to retrieve plugin data.
 *
 * @return  WPCampus_Speakers
 */
function wpcampus_speakers() {
	return WPCampus_Speakers::instance();
}

function wpcampus_get_sessions( $args = array() ) {
	return wpcampus_speakers()->get_sessions( $args );
}

function wpcampus_get_sessions_subjects( $args = array() ) {
	return wpcampus_speakers()->get_sessions_subjects( $args );
}
