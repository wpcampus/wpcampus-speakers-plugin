(function($) {
	'use strict';

	$(document).ready(function() {

		wpcampus_load_all_proposal_tables();

		$('.wpc-proposals-table-update-all').on('click',function(){
			wpcampus_load_all_proposal_tables();
		});

		$('.wpc-proposals-table-wrapper').on('click','.wpc-proposals-table-update-single',function(){
			$(this).closest('.wpc-proposals-table-wrapper').wpcampus_load_proposals_table();
		});

		$('.wpc-proposals-table-wrapper').on('change','.wpc-proposals-filter-select',function(){
			var key = $(this).attr('name');
			if ( key != '' ) {
				$(this).closest('.wpc-proposals-table-wrapper').data(key,$(this).val()).wpcampus_load_proposals_table();
			}
		});
	});

	function wpcampus_load_all_proposal_tables() {
		$('.wpc-proposals-table-wrapper').addClass('loading').each(function() {
			$(this).wpcampus_load_proposals_table();
    	});
	}

	$.fn.wpcampus_load_proposals_table = function() {
		var $wpc_prop_table = $(this).addClass('loading'),
			table_data = $wpc_prop_table.data(),
			template_id = table_data.template || '',
			proposals_info = {
				header: table_data.header || '',
				proposals: [],
				subject_ids: [],
				selected_speakers: [],
				selected_subjects: [],
				subjects: [],
				speaker_ids: [],
				speakers: [],
				speaker_dup: []
			};

		// Make sure the template exists.
		if ( ! $( '#' + template_id ).length ) {
			return;
		}

		// Store selected speakers for filters.
		if ( table_data.byProfile != undefined && table_data.byProfile != '' ) {
			proposals_info.selected_speakers = String(table_data.byProfile);
			if ( ! $.isArray( proposals_info.selected_speakers ) ) {
				proposals_info.selected_speakers = proposals_info.selected_speakers.split(',');
			}
		}

		// Store selected subjects for filters.
		if ( table_data.subjects != undefined && table_data.subjects != '' ) {
			proposals_info.selected_subjects = String(table_data.subjects);
			if ( ! $.isArray( proposals_info.selected_subjects ) ) {
				proposals_info.selected_subjects = proposals_info.selected_subjects.split(',');
			}
		}

		// Mix table data with AJAX data.
		table_data.action = 'wpc_get_proposals';
		table_data.proposalEvent = wpc_prop_review.proposal_event;

		// Get the proposals data.
		$.ajax({
			url: wpc_prop_review.ajax_url,
			type: 'GET',
			dataType: 'json',
			cache: false,
			async: false,
			data: table_data,
			success: function( the_proposals ) {
				proposals_info.proposals = the_proposals;
			},
			complete: function() {

				if ( proposals_info.proposals ) {
					$.each(proposals_info.proposals,function(index,proposal){

						// Process subjects.
						$.each(proposal.subjects,function(index,subject){
							var subjectID = subject.term_id;
							if ( $.inArray( subjectID, proposals_info.subject_ids ) == -1 ) {
								proposals_info.subject_ids.push( subjectID );
								proposals_info.subjects.push(subject);
							}
						});

						// Process speakers.
						$.each(proposal.speakers,function(index,speaker){
							var speakerTitle = speaker.title;

							// TODO: Have to use title since using multiple profiles right now.
							if ( $.inArray( speakerTitle, proposals_info.speaker_ids ) > -1 ) {
								proposals_info.speaker_dup.push( speakerTitle );
							} else {
								proposals_info.speaker_ids.push( speakerTitle );
								proposals_info.speakers.push(speaker);
							}
						});
					});
				}

				/*
				 * Get the template HTML.
				 *
				 * It is up to each theme to
				 * provide this template.
				 */
				var template_html = $( '#' + template_id ).html();
				var template = Handlebars.compile(template_html);

				// Render the template.
				var rendered = template( proposals_info );

				// Add the result to the page.
				setTimeout(function(){
					$wpc_prop_table.html(rendered).removeClass('loading');
				}, 1000);
			}
		});
	};

	function convert_obj_to_array(object) {
		var array = [];
		$.each(object,function(key,value){
			array.push([key,value]);
		});
		return array;
	}

	function sort_desc_by_key(a, b) {
		if (a[0] == b[0]) {
			return 0;
		}
		return a[0] < b[0] ? 1 : -1;
	}

	function sort_desc_by_value(a, b) {
		if (a[1] == b[1]) {
			return 0;
		}
        return a[1] < b[1] ? 1 : -1;
	}

	function has_speaker_dup(speakers,proposals_info) {
		var dups = [];
		if ( proposals_info.speaker_dup.length > 0 ) {
			$.each(speakers,function(index,speaker){
				var speakerTitle = speaker.title;
				if ( $.inArray( speakerTitle, proposals_info.speaker_dup ) > -1 ) {
					dups.push(speakerTitle);
				}
			});
		}
        return dups;
	}

	Handlebars.registerHelper( 'print_proposal_stats', function(options) {
		var proposals_info = options.data.root || {},
			formats_obj = {},
			formats = [],
			subjects_obj = {},
			subjects = [];

		// Process proposals.
		$.each(this.proposals,function(index,proposal){

			// Store format.
			if ( proposal.format_name ) {
				if ( formats_obj[proposal.format_name] !== undefined ) {
					formats_obj[proposal.format_name]++;
				} else {
					formats_obj[proposal.format_name] = 1;
				}
			}

			if ( proposal.subjects ) {
				$.each(proposal.subjects,function(index,subject){
					if ( subjects_obj[subject.name] !== undefined ) {
						subjects_obj[subject.name]++;
					} else {
						subjects_obj[subject.name] = 1;
					}
				});
			}
		});

		// Convert formats to array so we can sort desc.
        formats = convert_obj_to_array(formats_obj);
        formats.sort(sort_desc_by_value);

		// Convert subjects to array so we can sort desc.
		subjects_obj = convert_obj_to_array(subjects_obj);
		subjects_obj.sort(sort_desc_by_value);

		var subject_count = {};
		$.each(subjects_obj,function(index,subject){
			var count = subject[1], name = subject[0];
			if ( subject_count[count] !== undefined ) {
				subject_count[count].push(name);
			} else {
				subject_count[count] = [name];
			}
		});

		var result = '';

		// Add formats.
		var formats_str = [];
		$.each(formats,function(key,value){
			formats_str.push( value.join(': ') );
		});
		if ( formats_str.length > 0 ) {
			result += '<div class="wpc-proposals-stat formats"><h3>Formats</h3><ul><li>' + formats_str.join('</li><li>') + '</li></ul></div>';
		}

		// Add speakers.
		var speakers_str = [];
		speakers_str.push('Speaker count: ' + proposals_info.speaker_ids.length );

		if ( proposals_info.speaker_dup.length > 0 ) {
        	speakers_str.push('<span class="wpc-proposals-error-message">The following speakers are listed twice: ' + proposals_info.speaker_dup.join( ', ' ) + '</span>' );
        }

		if ( speakers_str.length > 0 ) {
        	result += '<div class="wpc-proposals-stat speakers"><h3>Speakers</h3><ul><li>' + speakers_str.join('</li><li>') + '</li></ul></div>';
        }

        // Add subjects.
		var subjects_str = [];
		$.each(subject_count,function(count,subjects){
			subjects_str.push( count + ': ' + subjects.join(', ') );
		});
		if ( subjects_str.length > 0 ) {
			result += '<div class="wpc-proposals-stat subjects"><h3>Subjects by number of times used</h3><ul><li>' + subjects_str.join('</li><li>') + '</li></ul></div>';
		}

		if ( result != '' ) {
			return new Handlebars.SafeString( result );
		}
		return null;
	});

	Handlebars.registerHelper( 'print_format', function() {
		if ( ! this.format_slug || this.format_slug == '' ) {
			return null;
		}
		if ( ! this.format_preferred_slug || this.format_preferred_slug == '' ) {
			return new Handlebars.SafeString( this.format_slug );
		} else if ( this.format_preferred_slug != this.format_slug ) {
			return new Handlebars.SafeString( this.format_slug + ' <span class="wpc-proposals-error-message">(Preferred ' + this.format_preferred_slug + ')</span>' );
		}
		return new Handlebars.SafeString( this.format_slug );
	});

	Handlebars.registerHelper( 'proposal_rating_count', function() {
		var proposal_rating_count = 0;

		$.each(this.proposals,function(index,proposal){
			if ( proposal.rating > 0 ) {
				proposal_rating_count++;
			}
    	});

    	return proposal_rating_count;
    });

	Handlebars.registerHelper( 'user_progress', function() {
		var proposal_count = this.proposals.length,
			proposal_rating_count = 0;

		$.each(this.proposals,function(index,proposal){
			if ( proposal.rating > 0 ) {
				proposal_rating_count++;
			}
		});

		var user_progress = proposal_count > 0 ? Math.ceil( ( proposal_rating_count * 100 ) / proposal_count ) : 0;
		if ( user_progress > 100 ) {
			user_progress = 100;
		} else if ( user_progress < 0 ) {
			user_progress = 0;
		}

		return user_progress;
	});

	Handlebars.registerHelper( 'total_progress', function() {
		var proposal_count = this.proposals.length,
			users_reviewing = wpc_prop_review.users_reviewing,
			total_proposal_count = proposal_count * users_reviewing,
			total_rating_count = 0;

		$.each(this.proposals,function(index,proposal){
			if ( proposal.rating_count > 0 ) {
				total_rating_count += parseInt( proposal.rating_count );
			}
		});

		var total_progress = total_proposal_count > 0 ? Math.ceil( ( total_rating_count * 100 ) / total_proposal_count ) : 0;

		if ( total_progress > 100 ) {
			total_progress = 100;
		} else if ( total_progress < 0 ) {
			total_progress = 0;
		}

		return total_progress;
	});

	Handlebars.registerHelper( 'header', function() {
		return this.header;
	});

	Handlebars.registerHelper( 'proposal_status_label', function() {
		if ( !this.proposal_status || '' == this.proposal_status ) {
			return 'Submitted';
		}
		return this.proposal_status.charAt(0).toUpperCase() + this.proposal_status.slice(1);
	});

	Handlebars.registerHelper( 'proposal_review_class', function() {
		var classes = [];

		if ( this.viewed ) {
			classes.push( 'user_viewed' );
		}

		if ( this.rating ) {
			classes.push( 'user_rated' );
		}

		if ( this.avg_rating ) {
			classes.push( 'rated' );
		}

		if ( this.comment_count ) {
			classes.push( 'has-comments' );
		}

		if ( this.proposal_status ) {
			if ( '' != this.proposal_status && 'submitted' != this.proposal_status ) {
				classes.push( 'selected' );
        	}
			classes.push( 'selected-' + this.proposal_status );
		}

		return classes.join( ' ' );
	});

	Handlebars.registerHelper( 'proposal_select_class', function(options) {
		var classes = [],
			proposals_info = options.data.root || {},
			dups = has_speaker_dup(this.speakers,proposals_info);

		if ( this.format_slug && this.format_slug != '' ) {
			if ( this.format_preferred_slug && this.format_preferred_slug != '' ) {
        		if ( this.format_preferred_slug != this.format_slug ) {
        			classes.push( 'format-diff' );
        		}
        	}
        }

		if ( dups.length > 0 ) {
			classes.push( 'speaker-dup' );
		}

		return classes.join( ' ' );
	});

	Handlebars.registerHelper( 'proposal_filters', function(options) {
		var proposals_info = options.data.root || {};
		if ( ! proposals_info.speakers || ! proposals_info.subjects ) {
			return null;
		}

		var filters_str = '';

		if ( proposals_info.speakers ) {
			var speakers_str = '';
			$.each(proposals_info.speakers,function(index,speaker){
				var selected = $.inArray(String(speaker.ID), proposals_info.selected_speakers ) > -1 ? ' selected="selected"' : '';
				speakers_str += '<option value="' + speaker.ID + '"' + selected + '>' + speaker.title + '</option>';
			});
			if ( speakers_str != '' ) {
				speakers_str = '<option value="">Show all speakers</option>' + speakers_str;
				filters_str += '<div class="wpc-proposals-filter"><select class="wpc-proposals-filter-select" name="byProfile">' + speakers_str + '</select></div>';
			}
		}

		if ( proposals_info.subjects ) {
			var subjects_str = '';
			$.each(proposals_info.subjects,function(index,subject){
				var selected = $.inArray(String(subject.term_id), proposals_info.selected_subjects ) > -1 ? ' selected="selected"' : '';
				subjects_str += '<option value="' + subject.term_id + '"' + selected + '>' + subject.name + '</option>';
			});
			if ( subjects_str != '' ) {
				subjects_str = '<option value="">Show all subjects</option>' + subjects_str;
				filters_str += '<div class="wpc-proposals-filter"><select class="wpc-proposals-filter-select" name="subjects">' + subjects_str + '</select></div>';
			}
		}

		if ( filters_str != '' ) {
			return new Handlebars.SafeString( '<div class="wpc-proposals-filters"><h3>Filters:</h3>' + filters_str + '</div>' );
		}
		return null;
	});

	Handlebars.registerHelper( 'has_speaker_dup', function(options) {
		var proposals_info = options.data.root || {},
			dups = has_speaker_dup(this.speakers,proposals_info);
		if ( dups.length > 0 ) {
			return new Handlebars.SafeString( '<span class="wpc-proposals-error-message">The following speakers are listed twice: ' + dups.join( ', ' ) + '</span>' );
		}
		return null;
	});

	Handlebars.registerHelper( 'math', function( lvalue, operator, rvalue, options ) {
        lvalue = parseFloat(lvalue);
        rvalue = parseFloat(rvalue);
        return {
            "+": lvalue + rvalue
        }[operator];
    });
})(jQuery);
