<?php  
/*
Plugin Name: Advanced Twitter
Plugin URI: http://www.dragonsearchmarketing.com/dragon-twitter/
Description: Easily create a custom tweet button on your posts with a custom tweet description.
Version: 1.0
Author: DragonSearch
Author URI: http://dragonsearchmarketing.com/
License: GPL 2
*/

/**
* This is a custom Wordpress addon that will display a simple docked GUI for the user to input custom tweet description.
**/

// Installation which will occur upon Activation
register_activation_hook(__FILE__,'dragon_social_install');
function dragon_social_install() {
	// Table creation
	global $wpdb;
	$table_name = $wpdb->prefix . "dragon_tweet";
	  
	$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL,
		ds_customtext text NOT NULL,
		ds_customvia text NOT NULL,
		ds_customrec text NOT NULL,
		ds_customurl text NOT NULL,
		UNIQUE KEY id (id)
		);";
	
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	
	dbDelta($sql);

	// Added some welcome data to ensure proper functionality.
	$rows_affected = $wpdb->insert( $table_name, array( 'ds_customtext' => 'Welcome', 'id' => 0, 'ds_customvia' => 'DragonSearch', 'ds_customrec' => 'DragonSearch', 'ds_customurl' => ' ' ));
	
	// Default some options if they have not been added already (previous installations)
	$display_top = get_option('ds_display_top');
	if(empty($display_top)) {
		update_option('ds_display_top','on');
	}
	$display_bottom = get_option('ds_display_bottom');
	if(empty($display_bottom)) {
		update_option('ds_display_bottom','on');
	}
	$ds_counttype = get_option('ds_counttype');
	if(empty($counttype)) {
		update_option('ds_counttype','horizontal');
	}
	$ds_related = get_option('ds_related');
	if(empty($ds_related	)) { 
		update_option('ds_related','DragonSearch');
	}
}

// Display a custom meta Edit Post 
add_action( 'add_meta_boxes', 'dragon_social_box_add' );
function dragon_social_box_add() {
	add_meta_box( 'dragon-social-box-id', 'DragonTweet - Customize Your Tweet', 'dragon_social_meta_box_cd', 'post', 'normal', 'high' );
}
function dragon_social_meta_box_cd( $post ) {
	// Get the table data
	global $wpdb;
	global $post;
	$table_name = $wpdb->prefix . "dragon_tweet";
	$this_id = $post->ID;
	$values = $wpdb->get_row( "SELECT * FROM $table_name WHERE id = $this_id" , ARRAY_A);
	$customtext = $values['ds_customtext'];
	$customvia = $values['ds_customvia'];
	$customrec = $values['ds_customrec'];
	$customurl = $values['ds_customurl'];
	
	// Now display the meta box
	wp_nonce_field( 'dragon_social_my_meta_box_nonce', 'dragon_social_meta_box_nonce' );
	?>
	<p>
		<label for="ds_customtext">Enter your description here</label><br/>

		<textarea onblur="textCounter(this,this.form.counter,140);" onkeyup="textCounter(this,this.form.counter,140);" style="width:100%;height:6em;" name="ds_customtext" id="ds_customtext" ><?php echo $customtext; ?></textarea>
		Characters Left: <input onblur="textCounter(this.form.recipients,this,140);" disabled  onfocus="this.blur();" tabindex="999" maxlength="3" size="1" value="140" name="counter"><span style="margin-left:25px" class="howto">Note: This does not include the URL characters or Via</span>

		<script type="text/javascript">
			function textCounter( field, countfield, maxlimit ) {
				if ( field.value.length > maxlimit ) {
						field.value = field.value.substring( 0, maxlimit );
						field.blur();
						field.focus();
						return false;
					} else {
						countfield.value = maxlimit - field.value.length;
				}
			}
		</script>
	</p>
	<p>
		<label for="ds_customvia">Enter your preferred Via Username here</label> <span style="margin-left:25px" class="howto">For "via @JohnSmith", simply enter "JohnSmith".  For no username or to fallback to the default (see Settings > Advanced Twitter to modify), simply leave it blank.  You do not need to include the '@' symbol.</span><br/>
		<input style="width:100%;height:1em;" name="ds_customvia" id="ds_customvia"  value="<?php echo $customvia; ?>"/>
	</p>
	<p>
		<label for="ds_customrec">Enter your Recommendation here</label> <span style="margin-left:25px" class="howto">To Recommend a user to follow after the tweet, enter numerous usernames separated by commas.  For no recommendation or to fallback to the default (see Settings > Advanced Twitter to modify), simply leave it blank. You do not need to include the '@' symbol.</span><br/>
		<textarea style="width:100%;height:6em;" name="ds_customrec" id="ds_customrec" /><?php echo $customrec; ?></textarea>
	</p>
	<p>
		<label for="ds_customurl">Enter your custom url here</label> <span style="margin-left:25px" class="howto"><a href="http://bit.ly/" target="_blank">Use a shortener</a></span><br/>
		<input style="width:100%;height:1em;" name="ds_customurl" id="ds_customurl"  value="<?php echo $customurl; ?>"/>
	</p>


	<?php	
}

// This will record all of our changes to the custom meta data
add_action( 'save_post', 'dragon_social_meta_box_save' );
function dragon_social_meta_box_save( $post_id ) {
	global $wpdb;
	global $post;
	// Bail if we're doing an auto save
	if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	
	// if our nonce isn't there, or we can't verify it, bail
	if( !isset( $_POST['dragon_social_meta_box_nonce'] ) || !wp_verify_nonce( $_POST['dragon_social_meta_box_nonce'], 'dragon_social_my_meta_box_nonce' ) ) return;
	
	// if our current user can't edit this post, bail
	if( !current_user_can( 'edit_post' ) ) return;
	
	// now we can actually save the data
	$allowed = array( 
		'a' => array( // on allow a tags
			'href' => array() // and those anchords can only have href attribute
		)
	);
	
	// Save the current data to the table
	$table_name = $wpdb->prefix . 'dragon_tweet';
	$id = $post->ID;
	$ds_customtext = stripslashes($_POST['ds_customtext']);
	$ds_customvia = stripslashes($_POST['ds_customvia']);
	$ds_customrec = stripslashes($_POST['ds_customrec']);
	$ds_customurl = stripslashes($_POST['ds_customurl']);
	$found = $wpdb->update( 
		$table_name, //FROM table
		array('ds_customtext' => $ds_customtext,
				'ds_customvia' => $ds_customvia,
				'ds_customrec' => $ds_customrec, // UPDATE data
				'ds_customurl' => $ds_customurl
			),
		array('id' => $id) //WHERE id is this post id
		);
	if($found == false)	{
		$wpdb->insert( 
			$table_name, //FROM table
			array('ds_customtext' => $ds_customtext,
				'ds_customvia' => $ds_customvia,
				'ds_customrec' => $ds_customrec,
				'ds_customurl' => $ds_customurl, // INSERT data
				'id' => $id)
			);
	}

}

// Displays a link to the options pane in the Settings submenu.
add_action('admin_menu', 'dragon_social_options');
function dragon_social_options() {  
    add_options_page("Advanced Twitter Settings", "Advanced Twitter", 'manage_options', "dragon-social-option", "dragon_social_display_options");  
	//call register settings function
	add_action( 'admin_init', 'register_dragon_social_settings' );
}
function register_dragon_social_settings() {
	//register our settings
	register_setting( 'dragon-social-group', 'ds_display_top' );
	register_setting( 'dragon-social-group', 'ds_display_bottom' );
	register_setting( 'dragon-social-group', 'ds_large' );
	register_setting( 'dragon-social-group', 'ds_via' );
	register_setting( 'dragon-social-group', 'ds_related' );
	register_setting( 'dragon-social-group', 'ds_counttype' );
}
// Displays the Options Pane
function dragon_social_display_options() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	?>
	<div class="wrap">
		<form method="post" action="options.php">
			<?php 
				settings_fields( 'dragon-social-group' ); 
				$display_top = get_option('ds_display_top');
				$display_bottom = get_option('ds_display_bottom');
				$ds_large = get_option('ds_large');
				$ds_via = get_option('ds_via');
				$ds_related = get_option('ds_related');
				$ds_counttype = get_option('ds_counttype');
			?>
			
			<a href="http://www.dragonsearchmarketing.com/" target="_blank">
				<img src="<?php echo plugins_url(); ?>/advanced-twitter/ds-logo.png" />
			</a>
			<h2>DragonTweet-Advanced Twitter Settings</h2>
			
			<p>
				Create and customize your Twitter button: location, size, recommendation, counter, and username on your post's content. 
			</p>
			<style type="text/css">
				.one-set {
					width: 100%;
					height: 5em;
				}
				.one-label {
					width: 20%;
					float: left;
				}
				.one-option {
					width: 80%;
					float: right;
				}
			</style>
			<div class="one-set">
				<div class="one-label">
					<span>Display a Twitter button...</span>
				</div>
				<div class="one-option">
					<input type="checkbox" name="ds_display_top" id="ds_display_top" <?php checked( $display_top, 'on' ); ?> />
					<label for="ds_display_top">...on the top of a post's content</label><br />
					<input type="checkbox" name="ds_display_bottom" id="ds_display_bottom" <?php checked( $display_bottom, 'on' ); ?> />
					<label for="ds_display_bottom">...on the bottom of a post's content</label>
				</div>
			</div>
			<br />
			<div class="one-set">
				<label class="one-label" for="ds_via">Default Via Username?</label>
				<div class="one-option">
					<span class="description">If no username is specified on your post, the username will fall back to this default.  For "via @JohnSmith", simply enter "JohnSmith".  For no default username, simply leave it blank.  You do not need to include the '@' symbol.</span><br />
					<input type="text" name="ds_via" id="ds_via" value="<?php echo $ds_via; ?>" />
				</div>
			</div>
			<br />
			<div class="one-set">
				<label class="one-label" for="ds_related">Default Recommendation?</label>
				<div class="one-option">
					<span class="description">If no recommendation is specified on your post, the recommendation will fall back to this default.  To Recommend a user to follow after the tweet, enter numerous usernames separated by commas.  For no recommendation, simply leave it blank. You do not need to include the '@' symbol.</span><br />
					<input type="text" name="ds_related" id="ds_related" value="<?php echo $ds_related; ?>" />
				</div>
			</div>
			<br />
			<div class="one-set">
				<span class="one-label">Size of the Twitter button?</span>
				<div class="one-option">
					<input type="checkbox" name="ds_large" id="ds_large" <?php checked( $ds_large, 'on' ); ?> />
					<label for="ds_large">Large</label>
				</div>
			</div>
			<br />
			<div class="one-set">
				<label class="one-label" for="ds_counttype">Type of Twitter Counter</label>
				<div class="one-option">
					<span class="description">Vertical cannot be used with Large size</span><br />
					<input type="radio" name="ds_counttype" value="horizontal" <?php if($ds_counttype == "horizontal"){echo "checked";} ?> /> Default<br />
					<input type="radio" name="ds_counttype" value="vertical" <?php if($ds_counttype == "vertical"){echo "checked";} ?> /> Vertical<br />
					<input type="radio" name="ds_counttype" value="none" <?php if($ds_counttype == "none"){echo "checked";} ?> /> None<br />
					<input type="radio" name="ds_counttype" value="nocounterortweet" <?php if($ds_counttype == "nocounterortweet"){echo "checked";} ?> /> No Counter Or "Tweet"<br />
				</div>
			</div>
			<br />
			<p>
				<span class="description">Want this customized button elsewhere on your page?  Just call this php function on your template: get_advanced_tweet();</span>
			</p>
			We'd love to hear from you! 
			Contact us on <a href="https://twitter.com/dragonsearch" target='_blank'>Twitter</a>, <a href="http://www.facebook.com/DragonSearch" target='_blank'>Facebook</a> or visit us at <a href="http://www.dragonsearchmarketing.com/" target='_blank'>Dragonsearchmarketing.com</a>

			<a href="https://twitter.com/dragonsearch" target='_blank'></a>
			<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
			</p>
		</form>
	</div>
	<?php
}

// This will return a string containing the tag necessary to display the tweet button.
function get_advanced_tweet() {
	$tweet_tag = "";
	
	// Get the post's custom value: "ds_customtext"
	global $wpdb;
	global $post;
	$table_name = $wpdb->prefix . "dragon_tweet";
	$this_id = $post->ID;
	$values = $wpdb->get_row( "SELECT * FROM $table_name WHERE id = $this_id" , ARRAY_A);
	// ...Set the custom meta data (first occurance only).
	$ds_customtext = $values['ds_customtext'];
	$ds_customrec = $values['ds_customrec'];
	$ds_customvia = $values['ds_customvia'];
	$ds_customurl = $values['ds_customurl'];
	// If our current post's custom twitter description is empty...
	if(empty($ds_customtext)){
		// ...Rather than leave it blank, add the post's title.
		$ds_customtext = $post->post_title;
	}
	$ds_customtext = htmlspecialchars($ds_customtext);
	
	// If our customvia and default via are both empty, display nothing.  If custom is empty, fallback on default.
	if(empty($ds_customvia)){
		$ds_via = get_option('ds_via');
		if(!empty($ds_via)) {
			$ds_customvia = $ds_via;
		}
	}
	// This should not be an else because than the ds_via won't receive the same treatment.
	if(!empty($ds_customvia)){
		$ds_customvia = ' data-via="' . htmlspecialchars($ds_customvia) . '" ';
	}

	// If our customrecommendation and default related are both empty, display nothing.  If custom is empty, fallback on default.
	if(empty($ds_customrec)){
		$ds_related = get_option('ds_related');
		if(!empty($ds_related)) {
			$ds_customrec = $ds_related;
		}
	}
	// This should not be an else because than the ds_via won't receive the same treatment.
	if(!empty($ds_customrec)) {
		$ds_customrec = ' data-related ="' . htmlspecialchars($ds_customrec) . '" ';
	}


	if(empty($ds_customurl)){
		if(!empty($ds_permalink)) {
			$ds_customurl = $ds_permalink;
		}
	}
	if(!empty($ds_customurl)) {
		$ds_customurl = ' data-url ="' . htmlspecialchars($ds_customurl) . '" ';
	}
	
	// Only display a larger icon if it is not blank.
	$ds_large = get_option('ds_large');
	if(!empty($ds_large)) {
		$ds_large = ' data-size="large" ';
	}
		
	// Only display count type if it is not blank.
	$ds_counttype = get_option('ds_counttype');
	if(!empty($ds_counttype)) {
		// Special style to remove both the Tweet text and the counter
		if($ds_counttype == "nocounterortweet") {
			// ..which is dependant on the size of the tweet button
			if(empty($ds_large)) {
				$tweet_tag .= '<style type="text/css"> iframe.twitter-share-button { width: 21px!important; } </style>';
			}
			else {
				$tweet_tag .= '<style type="text/css"> iframe.twitter-share-button { width: 31px!important; } </style>';
			}
		}
		else {
			$ds_counttype = ' data-count="' . $ds_counttype . '" ';
		}
	}
	
	// Now let's merge our work together
	// Create the base of the anchor
	$tweet_tag .= '<a href="https://twitter.com/share" class="twitter-share-button" data-text="' . $ds_customtext . '"';
	// Add our attributes
	$tweet_tag .= $ds_customurl . $ds_customvia . $ds_customrec . $ds_large . $ds_counttype;
	// Add the child text node 
	$tweet_tag .= '>Tweet</a>';
	// ...to top it off: the Javascript
	$tweet_tag .= '<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>';
	return $tweet_tag;

}

//Within a single post, this method will add html based on custom value pairs.
add_action('the_content', 'dragon_social_display_tag');
function dragon_social_display_tag($content) {
	// Ensure we are on an ACTUAL post page.
	if(is_single() ) {
		// Get the post's custom meta
		$tweet_tag = get_advanced_tweet();
		$ds_display_top = get_option('ds_display_top');
		$ds_display_bottom = get_option('ds_display_bottom');
		if(!empty($ds_display_top) && $ds_display_top == 'on') {
			$content = $tweet_tag . $content;
		}
		if(!empty($ds_display_bottom) && $ds_display_bottom == 'on') {
			$content .= $tweet_tag;
		}
	}
	return $content;
}
?>