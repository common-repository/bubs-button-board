<?php
/*
Plugin Name: Bubs' Button Board
Plugin URI: http://bubblessoc.net/archives/bubs-button-board/
Description: A customizable plugboard (<a href="http://plugboard.org/" title="The Original Plugboard">http://plugboard.org/</a>) for your Wordpress 2.5+ blog.
Version: 2.2
Author: Sidney Collins (aka Bubs)
Author URI: http://bubblessoc.net/
*/

### Button Board Version
$bbb_db_version = "2.2";

### Button Board Cache Dir
$bbb_cache_dir = ABSPATH . PLUGINDIR . "/button-board/cache";

function bbb_install() {
	global $wpdb;
	global $bbb_db_version;

	$table_name = $wpdb->prefix . "bbb";
	
	if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
		$sql = "CREATE TABLE " . $table_name . " (
				id bigint(11) NOT NULL AUTO_INCREMENT,
				url text NOT NULL,
				button text NOT NULL,
				title text NOT NULL,
				isApproved tinyint(1) DEFAULT '0' NOT NULL,
				UNIQUE KEY id (id)
			);";

		require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
		dbDelta($sql);
		
		// Insert initial plug
		$plug_url = "http://bubblessoc.net/";
		$plug_button = "http://bubblessoc.net/images/buttons/bubbles.gif";
		$plug_title = "BubblesSOC";

		$insert = "INSERT INTO " . $table_name .
				" (url, button, title) " .
				"VALUES ('" . $wpdb->escape($plug_url) . "', '" . $wpdb->escape($plug_button) . "', '" . $wpdb->escape($plug_title) . "')";

		$results = $wpdb->query( $insert );
		
		// Add Default Options
		add_option("bbb_db_version", $bbb_db_version);
		add_option("bbb_number", 10);
		add_option("bbb_width", 88);
		add_option("bbb_height", 31);
		add_option("bbb_moderate", 0);
		add_option("bbb_cache", 0);
		
		// NEW in 2.1
		add_option("bbb_email", 0);
		
		// NEW in 2.2
		add_option("bbb_target", 0);
		
		// Resource Page: http://codex.wordpress.org/Creating_Tables_with_Plugins
	}
}

### My Clean Up Function
function bbb_clean($string) {
	$string = strip_tags($string);
	$string = stripslashes($string);
	$string = htmlentities($string, ENT_QUOTES, get_option('blog_charset'));
	$string = trim($string);
	return $string;
}

### Get Cached Button Filename
function bbb_get_filename($id) {
	global $bbb_cache_dir;
	
	if (is_dir($bbb_cache_dir) && is_readable($bbb_cache_dir)) {
		if ($dh = opendir($bbb_cache_dir)) {
			while (($file = readdir($dh)) !== false) {
				$path_parts = pathinfo($file);
				if (basename($file, $path_parts['extension']) == $id) {
					return $file;
				}
			}
			closedir($dh);
		}
	}
	return false;
}

## Random Letter/Digit String
function randLetDig($length) {
	// Taken from PHP.Net
	$key_chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	$rand_max  = strlen($key_chars) - 1;

	for ($i = 0; $i < $length; $i++)
	{
	   $rand_pos  = rand(0, $rand_max);
	   $rand_key[] = $key_chars{$rand_pos};
	}
	
	$confirmCode = implode('', $rand_key);
	return $confirmCode;
}

function bbb_page() {
	global $wpdb;
	global $bbb_cache_dir;
	
	$table_name = $wpdb->prefix . "bbb";
	
	$content = "";
	
	// Is the directory writable?
	if (!is_writable($bbb_cache_dir)) {
		$content .= "<p>You must make the cache directory writable before using the button board.</p>\n";
		return $content;
	}
	
	// Is cURL enabled?
	if (!function_exists('curl_init')) {
		$content .= "<p>You must have <a href=\"http://us.php.net/manual/en/ref.curl.php\" title=\"PHP cURL\">cURL</a> enabled on your server in order to use Bubs' Button Board 1.1. (Try <a href=\"http://bubblessoc.net/goodies/scripts/bubs-button-board.zip\" title=\"Bubs' Button Board 1.0\">Version 1.0</a>?)</p>\n";
		return $content;
	}
	
	// Retrieve current buttons query
	$query = "SELECT * FROM $table_name";
	
	if (get_option('bbb_moderate') == 1)
		$query .= " WHERE isApproved = '1'";
		
	$query .= " ORDER BY id DESC LIMIT " . get_option('bbb_number');
	
	if (isset($_POST['bbb_submit'])) {
		$url = bbb_clean($_POST['url']);
		$button = bbb_clean($_POST['button']);
		$title = bbb_clean($_POST['title']);
		
		// Default Flags
		$empty_fields = false;
		$broken_img = false;
		$wrong_dim = false;
		$wrong_type = false;
		$on_board = false;
		
		// Copy Temporary Image - Thanks Billy!
		$ch = curl_init();
		
		curl_setopt($ch, CURLOPT_URL, $button);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		
		// get the contents of the image into a string var
		$fileContents = curl_exec($ch);
		curl_close($ch);
		
		// give it a unique name
		$str = substr(md5(randLetDig(10)), 0, 10);
		$temp = $bbb_cache_dir."/".$str;
		
		// create a temp file and write the contents of the image to it
		$fp = fopen($temp, "wb+");
		fwrite($fp, $fileContents);
		fclose($fp);
		
		// http://wiki.dreamhost.com/CURL
		// http://us.php.net/manual/en/ref.curl.php
		// http://www.slowerbetter.com/2006/09/19/alternative-to-doing-file-operations-on-a-url-using-curl-in-php/
		
		if ($url == "" || $url == "http://" || $button == "" || $button == "http://" || $title == "")
			$empty_fields = true;
			
		elseif (!$info = getimagesize($temp))
			$broken_img = true;
			
		elseif ($info[0] != get_option('bbb_width') || $info[1] != get_option('bbb_height'))
			$wrong_dim = true;
			
		elseif ($info[2] != 1 && $info[2] != 2 && $info[2] != 3)
			$wrong_type = true;
			
		elseif ($wpdb->query("SELECT url, button FROM $table_name WHERE url = '".$wpdb->escape($url)."' OR button = '".$wpdb->escape($button)."'"))
			$on_board = true;
			
		else {
			// Add to database
			$wpdb->query("INSERT INTO $table_name SET url = '".$wpdb->escape($url)."', button = '".$wpdb->escape($button)."', title = '".$wpdb->escape($title)."'");
			$id = mysql_insert_id();
			
			if (get_option('bbb_moderate') == 1)
				$content .= "<p class=\"success\">Your button is awaiting moderation.</p>";
				
			// Cache
			if (is_writable($bbb_cache_dir) && get_option('bbb_cache') == 1) {
				// Get File Type
				switch ($info[2])
				{
					case 2:
						$ext = ".jpg";
						break;
					case 3:
						$ext = ".png";
						break;
					default:
						$ext = ".gif";
						break;
				}
				copy($temp, $bbb_cache_dir."/".$id.$ext);
			}
			
			// Email Admin (if applicable) - NEW in 2.1
			if (get_option('bbb_email') == 1) {
				$blogname = get_option('blogname');
				$admin_email = get_option('admin_email');
				
				$email_message = "A new button has been added to your plugboard!\r\n\r\n";
				$email_message .= "Title: $title\r\n";
				$email_message .= "URL: $url\r\n";
				$email_message .= "Button: $button\r\n\r\n";

				if (get_option('bbb_moderate') == 1)
					$email_message .= "Approve It: ".get_option('siteurl')."/wp-admin/options-general.php?page=button-board.php&action=approve&id=".$id."\r\n";
				
				$email_message .= "Delete It: ".get_option('siteurl')."/wp-admin/options-general.php?page=button-board.php&action=delete&id=".$id."\r\n";
				
				$email_subject = "[$blogname] New Plugboard Button";
				@wp_mail($admin_email, $email_subject, $email_message);
			}
			
			// Get the current buttons
			$curr_buttons = $wpdb->get_results($query);
			
			// Delete the excess buttons -- needs work.
			if ($curr_buttons) {
				foreach ($curr_buttons as $button)
					$list[] = $button->id;
					
				$index = get_option('bbb_number') - 1;
				
				// Until I can think of something better...
				$select_query = "SELECT id FROM $table_name WHERE id < '".$list[$index]."'";
				
				if (get_option('bbb_moderate') == 1)
					$select_query .= " AND isApproved = '1'";
				
				$delete_buttons = $wpdb->get_results($select_query);
				
				foreach ($delete_buttons as $button) {
					if (is_writable($bbb_cache_dir)) {
						if ($filename = bbb_get_filename($button->id))
							unlink($bbb_cache_dir."/".$filename);
					}
				}
				
				// This is Ok... Idea from Dodo :)
				$delete_query = "DELETE FROM $table_name WHERE id < '".$list[$index]."'";
				
				if (get_option('bbb_moderate') == 1)
					$delete_query .= " AND isApproved = '1'";
				
				$wpdb->query($delete_query);
			}
			
			// Reset form data
			$_POST['url'] = $_POST['button'] = $_POST['title'] = "";
		}
		
		// Get rid of temporary image
		unlink($temp);
	}
	
	// Get the current buttons
	$curr_buttons = $wpdb->get_results($query);
	
	// Print the buttons
	if ($curr_buttons) {
		$content .= "<ul id=\"plugboard\">\n";
		
		foreach ($curr_buttons as $button) {
			$content .= "<li><a href=\"$button->url\" title=\"$button->title\"";
			
			if (get_option('bbb_target') == 1)
				$content .= " target=\"_blank\"";
			
			$content .= "><img src=\"";
			
			if (get_option('bbb_cache') == 1 && $filename = bbb_get_filename($button->id))
				$content .= get_option('siteurl')."/".PLUGINDIR."/button-board/cache/".$filename;
			else
				$content .= $button->button;
			
			$content .= "\" alt=\"$button->title\" width=\"".get_option('bbb_width')."\" height=\"".get_option('bbb_height')."\" /></a></li>\n";
		}
		
		$content .= "</ul>\n";
	}
		
	$content .= "<div id=\"plugboard-form\">\n";
	$content .= "<h2>Add Your Button</h2>\n";
	
	// Print error message, if applicable
	if ($empty_fields == true)
		$content .= "<p class=\"error\">You forgot to complete all the required form fields.</p>\n";
		
	if ($broken_img == true)
		$content .= "<p class=\"error\">Your button is broken.</p>\n";
		
	if ($wrong_dim == true)
		$content .= "<p class=\"error\">The dimensions of the button you submitted are <strong>".$info[0]."x".$info[1]."</strong>.  Only ".get_option('bbb_width')."x".get_option('bbb_height')." buttons are allowed.</p>\n";
		
	if ($wrong_type == true)
		$content .= "<p class=\"error\">Only .gif, .jpg, and .png buttons are allowed.</p>\n";
		
	if ($on_board == true)
		$content .= "<p class=\"error\">Please wait until your button clears the board before you submit again.</p>\n";
		
	$content .= "<form action=\"\" method=\"post\">\n";
	$content .= "<p><input type=\"text\" name=\"url\" id=\"bbb_url\" tabindex=\"1\" value=\"" . ($_POST['url'] ? $_POST['url'] : "http://") . "\" />";
	$content .= " <label for=\"bbb_url\"><acronym title=\"Uniform Resource Locator\">URL</acronym></label></p>\n";
	$content .= "<p><input type=\"text\" name=\"button\" id=\"bbb_button\" tabindex=\"2\" value=\"" . ($_POST['button'] ? $_POST['button'] : "http://") . "\" />";
	$content .= " <label for=\"bbb_button\">Button <acronym title=\"Uniform Resource Locator\">URL</acronym></label></p>\n";
	$content .= "<p><input type=\"text\" name=\"title\" id=\"bbb_title\" tabindex=\"3\" value=\"" . $_POST['title'] . "\" />";
	$content .= " <label for=\"bbb_title\">Site Title</label></p>\n";
	$content .= "<input type=\"submit\" name=\"bbb_submit\" id=\"bbb_submit\" tabindex=\"4\" value=\"Submit Button\" />\n";
	$content .= "</form>\n";
	$content .= "</div>\n";
	
	return $content;
}

function bbb_page_print($content) {
	$content = preg_replace( "/\[bbb_page_print\]/ise", "bbb_page()", $content); 
	return $content;
}

function bbb_add_page() {
	add_options_page('Button Board', 'Button Board', 9, basename(__FILE__), 'bbb_options_page');
}

function bbb_options_page() {
	global $wpdb;
	global $bbb_cache_dir;
	
	$table_name = $wpdb->prefix . "bbb";
	
	// Update Options
	if (isset($_POST['update_bb_options'])) {
		// Defaults
		$option_number = 10;
		$option_width = 88;
		$option_height = 31;
		$option_moderate = 0;
		$option_cache = 0;
		$option_email = 0;
		$option_target = 0;
		
		// Check to see that number, width, and height are ints > 0
		if (is_numeric($_POST['number']) && $_POST['number'] > 0)
			$option_number = $_POST['number'];
			
		if (is_numeric($_POST['width']) && $_POST['width'] > 0)
			$option_width = $_POST['width'];
			
		if (is_numeric($_POST['height']) && $_POST['height'] > 0)
			$option_height = $_POST['height'];
		
		// Check to see that moderate and cache are either 1 or 0
		if ($_POST['moderate'] == 1)
			$option_moderate = $_POST['moderate'];
			
		if ($_POST['cache'] == 1 && is_writable($bbb_cache_dir))
			$option_cache = $_POST['cache'];
			
		if ($_POST['email'] == 1)
			$option_email = $_POST['email'];
			
		if ($_POST['target'] == 1)
			$option_target = $_POST['target'];
			
		// Update Options
		update_option('bbb_number', $option_number);
		update_option('bbb_width', $option_width);
		update_option('bbb_height', $option_height);
		update_option('bbb_moderate', $option_moderate);
		update_option('bbb_cache', $option_cache);
		update_option('bbb_email', $option_email);
		update_option('bbb_target', $option_target);
		
		//if ($_POST['cache'] == 1 && !is_writable($bbb_cache_dir))
		//	echo "<div id=\"message\" class=\"error fade\"><p>You must make the directory <code>/".PLUGINDIR."/button-board/cache/</code> writable before the buttons can be cached.</p></div>\n";
		
		echo "<div id=\"message\" class=\"updated fade\"><p>Button Board Options Updated!</p></div>\n";
	}
	
	// Delete Button
	elseif ($_GET['action'] == "delete") {
		$result = $wpdb->query("DELETE FROM `$table_name` WHERE id = '".$wpdb->escape($_GET['id'])."'");
		
		// Delete Cached Button
		if (is_writable($bbb_cache_dir)) {
			if ($filename = bbb_get_filename($_GET['id']))
				unlink($bbb_cache_dir."/".$filename);
		}
		
		echo "<div id=\"message\" class=\"updated fade\"><p>Button Deleted!</p></div>\n";
	}
	
	// Approve Button
	elseif ($_GET['action'] == "approve") {
		$result = $wpdb->query("UPDATE `$table_name` SET isApproved = '1' WHERE id = '".$wpdb->escape($_GET['id'])."'");
		echo "<div id=\"message\" class=\"updated fade\"><p>Button Approved!</p></div>\n";
	}
	
	// Unapprove Button
	elseif ($_GET['action'] == "unapprove") {
		$result = $wpdb->query("UPDATE `$table_name` SET isApproved = '0' WHERE id = '".$wpdb->escape($_GET['id'])."'");
		echo "<div id=\"message\" class=\"updated fade\"><p>Button Unapproved!</p></div>\n";
	}
	
	// Edit Button - NEW in 2.1
	elseif ($_GET['action'] == "edit") {
		$edit_button = $wpdb->get_results("SELECT * FROM `$table_name` WHERE `id` = '".$wpdb->escape($_GET['id'])."' LIMIT 1");
		
		// Process Form
		if ($edit_button && isset($_POST['edit_button'])) {
			$url = bbb_clean($_POST['url']);
			$button = bbb_clean($_POST['button']);
			$title = bbb_clean($_POST['title']);
		
			$result = $wpdb->query("UPDATE `$table_name` SET `url` = '".$wpdb->escape($url)."', `button` = '".$wpdb->escape($button)."', `title` = '".$wpdb->escape($title)."' WHERE `id` = '".$wpdb->escape($_GET['id'])."'");
			
			// Update Cached Button
			if (get_option('bbb_cache') == 1 && is_writable($bbb_cache_dir)) {
				
				// Copy Temporary Image - Thanks Billy!
				$ch = curl_init();
				
				curl_setopt($ch, CURLOPT_URL, $button);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
				
				// get the contents of the image into a string var
				$fileContents = curl_exec($ch);
				curl_close($ch);
				
				// give it a unique name
				$str = substr(md5(randLetDig(10)), 0, 10);
				$temp = $bbb_cache_dir."/".$str;
				
				// create a temp file and write the contents of the image to it
				$fp = fopen($temp, "wb+");
				fwrite($fp, $fileContents);
				fclose($fp);
				
				$info = getimagesize($temp);
				
				if ($info && $info[0] == get_option('bbb_width') && $info[1] == get_option('bbb_height') && ($info[2] == 1 || $info[2] == 2 || $info[2] == 3)) {
					
					// Get File Type
					switch ($info[2])
					{
						case 2:
							$ext = ".jpg";
							break;
						case 3:
							$ext = ".png";
							break;
						default:
							$ext = ".gif";
							break;
					}
					
					// Delete Old Button
					if ($filename = bbb_get_filename($_GET['id']))
						unlink($bbb_cache_dir."/".$filename);
					
					copy($temp, $bbb_cache_dir."/".$_GET['id'].$ext);
				}
				
				unlink($temp);
			}
			echo "<div id=\"message\" class=\"updated fade\"><p>Button Updated!</p></div>\n";
		}
		
		// Display Form
		elseif ($edit_button) {
			foreach ($edit_button as $button) {
?>
		<div class="wrap">
			<h2>Edit Button</h2>
			<form method="post" action="">
				<table class="form-table"> 
					<tr valign="top"> 
						<th scope="row" style="width: 300px;">Title:</th> 
						<td><input name="title" type="text" id="title" value="<?php echo $button->title ?>" size="40" /></td> 
					</tr>
					<tr valign="top"> 
						<th scope="row">URL:</th> 
						<td><input name="url" type="text" id="url" value="<?php echo $button->url ?>" size="40" /></td> 
					</tr>
					<tr valign="top"> 
						<th scope="row">Button URL:</th> 
						<td><input name="button" type="text" id="button" value="<?php echo $button->button ?>" size="40" /></td> 
					</tr>
				</table>
				<p class="submit"><input type="submit" name="edit_button" value="Edit Button"/></p>
			</form>
		</div>
<?php
			}
		}
		else {
			echo "<div id=\"message\" class=\"error fade\"><p>Button not found.</p></div>\n";
		}
	}
	
	// Get current buttons, if applicable
	$curr_buttons = $wpdb->get_results("SELECT * FROM `$table_name` ORDER BY id DESC");
	
	// Is the directory writable?
	if (!is_writable($bbb_cache_dir))
		echo "<div id=\"message\" class=\"error fade\"><p>You must make the directory <code>/".PLUGINDIR."/button-board/cache/</code> writable!</p></div>\n";
?>
		<div class="wrap">
			<h2>Current Buttons</h2>
<?php
	if ($curr_buttons) {
?>
			<table class="widefat">
				<thead>
					<tr>
						<th scope="col" style="text-align: center">ID</th>
						<th scope="col" style="text-align: center">Link</th>
						<th scope="col" style="text-align: center">Button</th>
						<th scope="col" style="text-align: center">Cached Button</th>
						<th scope="col" style="text-align: center" colspan="3">Action</th>
					
					</tr>
				</thead>
				
				<tbody id="the-list">
<?php
		$i = 0;
		foreach ($curr_buttons as $button) {
			echo "\t\t\t\t\t<tr".($i % 2 == 0 ? ' class="alternate"' : '').">\n";
			
			echo "\t\t\t\t\t\t<th scope=\"row\" style=\"text-align: center\">$button->id</th>\n";
			echo "\t\t\t\t\t\t<td style=\"text-align: center\"><a href=\"$button->url\" title=\"$button->title\">$button->title</a></td>\n";
			echo "\t\t\t\t\t\t<td style=\"text-align: center\"><img src=\"$button->button\" alt=\"$button->title\" width=\"".get_option('bbb_width')."\" height=\"".get_option('bbb_height')."\" /></td>\n";
			
			echo "\t\t\t\t\t\t<td style=\"text-align: center\">";
			
			if ($filename = bbb_get_filename($button->id))
				echo "<img src=\"".get_option('siteurl')."/".PLUGINDIR."/button-board/cache/$filename\" alt=\"$button->title\" width=\"".get_option('bbb_width')."\" height=\"".get_option('bbb_height')."\" />";
			else
				echo "N/A";
			
			echo "</td>\n";
			
			// Edit Link - NEW in 2.1
			echo "\t\t\t\t\t\t<td><a href=\"".$_SERVER['PHP_SELF']."?page=".basename(__FILE__)."&amp;action=edit&amp;id=$button->id\" title=\"Edit\" class=\"edit\">Edit</a></td>\n";
			
			echo "\t\t\t\t\t\t<td>";
			
			if ($button->isApproved == "0")
				echo "<a href=\"".$_SERVER['PHP_SELF']."?page=".basename(__FILE__)."&amp;action=approve&amp;id=$button->id\" title=\"Approve\" class=\"edit\">Approve</a>";
			else
				echo "<a href=\"".$_SERVER['PHP_SELF']."?page=".basename(__FILE__)."&amp;action=unapprove&amp;id=$button->id\" title=\"Unapprove\" class=\"edit\">Unapprove</a>";
			
			echo "</td>\n";
			
			echo "\t\t\t\t\t\t<td><a href=\"".$_SERVER['PHP_SELF']."?page=".basename(__FILE__)."&amp;action=delete&amp;id=$button->id\" title=\"Delete\" class=\"delete\" onclick=\"return confirm('Are you sure you want to delete this button?');\">Delete</a></td>\n";
			
			echo "\t\t\t\t\t</tr>\n";
			$i++;
		}
?>
				</tbody>
			</table>
<?php
	}
	else {
		echo "<p>There are no current buttons to display.</p>\n";
	}
?>
		</div>

		<div class="wrap">
			<h2>Button Board Options</h2>
			<form method="post" action="">
				<table class="form-table"> 
					<tr valign="top"> 
						<th scope="row" style="width: 300px;">Number of Buttons Displayed:</th> 
						<td><input name="number" type="text" id="number" value="<?php echo get_option('bbb_number'); ?>" size="3" /></td> 
					</tr>
					<tr valign="top"> 
						<th scope="row">Width of Buttons Allowed (in pixels):</th> 
						<td><input name="width" type="text" id="width" value="<?php echo get_option('bbb_width'); ?>" size="3" /></td> 
					</tr>
					<tr valign="top"> 
						<th scope="row">Height of Buttons Allowed (in pixels):</th> 
						<td><input name="height" type="text" id="height" value="<?php echo get_option('bbb_height'); ?>" size="3" /></td> 
					</tr>
					<tr valign="top">
						<th scope="row">Must Approve Buttons?</th>
						<td>
							<select name="moderate" id="moderate">
							<option value="1"<? if (get_option('bbb_moderate') == '1') { echo " selected"; } ?>>Yes</option>
							<option value="0"<? if (get_option('bbb_moderate') == '0') { echo " selected"; } ?>>No</option>
							</select>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Cache Buttons?</th>
						<td>
							<select name="cache" id="cache">
								<option value="1"<? if (get_option('bbb_cache') == '1') { echo " selected"; } ?>>Yes</option>
								<option value="0"<? if (get_option('bbb_cache') == '0') { echo " selected"; } ?>>No</option>
							</select>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Receive email when new button is added?</th>
						<td>
							<select name="email" id="email">
								<option value="1"<? if (get_option('bbb_email') == '1') { echo " selected"; } ?>>Yes</option>
								<option value="0"<? if (get_option('bbb_email') == '0') { echo " selected"; } ?>>No</option>
							</select>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Open links in a new window?</th>
						<td>
							<select name="target" id="target">
								<option value="1"<? if (get_option('bbb_target') == '1') { echo " selected"; } ?>>Yes</option>
								<option value="0"<? if (get_option('bbb_target') == '0') { echo " selected"; } ?>>No</option>
							</select>
							<br />
							<span class="setting-description">Adds <code>target="_blank"</code> to your links.  This attribute is not allowed in the XHTML 1.0 Strict doctype.</span>
						</td>
					</tr>
				</table>
				<p class="submit"><input type="submit" name="update_bb_options" value="Update Button Board Options"/></p>
			</form>
		</div>
<?php
}

register_activation_hook(__FILE__,'bbb_install');
add_action('admin_menu', 'bbb_add_page');
add_filter('the_content', 'bbb_page_print', 7);

function bbb_include($number = "", $before = "", $after = "") {
	global $wpdb;
	global $bbb_cache_dir;
	
	$table_name = $wpdb->prefix . "bbb";
	
	$option_number = get_option('bbb_number');
	$option_before = "<li>";
	$option_after = "</li>";
	
	if (is_numeric($number) && $number > 0)
		$option_number = $number;
		
	if ($before != "")
		$option_before = $before;
		
	if ($after != "")
		$option_after = $after;
	
	// Current buttons query
	$query = "SELECT * FROM $table_name";
	
	if (get_option('bbb_moderate') == 1)
		$query .= " WHERE isApproved = '1'";
		
	$query .= " ORDER BY id DESC LIMIT $option_number";
	
	// Get the current buttons
	$curr_buttons = $wpdb->get_results($query);
	
	// Print the buttons
	if ($curr_buttons) {
		foreach ($curr_buttons as $button) {
			echo "$option_before<a href=\"$button->url\" title=\"$button->title\"";
			
			if (get_option('bbb_target') == 1)
				echo " target=\"_blank\"";
			
			echo "><img src=\"";
			
			if (get_option('bbb_cache') == 1 && $filename = bbb_get_filename($button->id))
				echo get_option('siteurl')."/".PLUGINDIR."/button-board/cache/".$filename;
			else
				echo $button->button;
			
			echo "\" alt=\"$button->title\" width=\"".get_option('bbb_width')."\" height=\"".get_option('bbb_height')."\" /></a>$option_after\n";
		}
	}
}

function widget_bbb_init() {
	if (!function_exists('register_sidebar_widget')) return;

	function widget_bbb($args) {
		extract($args);

		$options = get_option('widget_bbb');
		$title = $options['title'];
		$number = $options['number'];
		$before_board = $options['before_board'];
		$after_board = $options['after_board'];
		$before_button = $options['before_button'];
		$after_button = $options['after_button'];
		
		echo $before_widget . $before_title . $title . $after_title . $before_board;
		bbb_include($number, $before_button, $after_button);
		echo $after_board . $after_widget;
	}

	function widget_bbb_control() {
		$options = get_option('widget_bbb');
		
		if ($_POST['bbb-submit']) {
			$options['title'] = bbb_clean($_POST['bbb-title']);
			$options['number'] = bbb_clean($_POST['bbb-number']);
			$options['before_board'] = $_POST['bbb-before_board'];
			$options['after_board'] = $_POST['bbb-after_board'];
			$options['before_button'] = $_POST['bbb-before_button'];
			$options['after_button'] = $_POST['bbb-after_button'];
			update_option('widget_bbb', $options);
		}

		$title = htmlspecialchars($options['title'], ENT_QUOTES);
		$number = htmlspecialchars($options['number'], ENT_QUOTES);
		$before_board = htmlspecialchars($options['before_board'], ENT_QUOTES);
		$after_board = htmlspecialchars($options['after_board'], ENT_QUOTES);
		$before_button = htmlspecialchars($options['before_button'], ENT_QUOTES);
		$after_button = htmlspecialchars($options['after_button'], ENT_QUOTES);
		
		echo '<p><label for="bbb-title">Title: <input class="widefat" id="bbb-title" name="bbb-title" type="text" value="'.$title.'" /></label></p>';
		echo '<p><label for="bbb-number">Number of Buttons: <input class="widefat" id="bbb-number" name="bbb-number" type="text" value="'.$number.'" /></label></p>';
		echo '<p><label for="bbb-before_board">Before Plugboard: <input class="widefat" id="bbb-before_board" name="bbb-before_board" type="text" value="'.$before_board.'" /></label></p>';
		echo '<p><label for="bbb-after_board">After Plugboard: <input class="widefat" id="bbb-after_board" name="bbb-after_board" type="text" value="'.$after_board.'" /></label></p>';
		echo '<p><label for="bbb-before_button">Before Button: <input class="widefat" id="bbb-before_button" name="bbb-before_button" type="text" value="'.$before_button.'" /></label></p>';
		echo '<p><label for="bbb-after_button">After Button: <input class="widefat" id="bbb-after_button" name="bbb-after_button" type="text" value="'.$after_button.'" /></label></p>';
		echo '<input type="hidden" id="bbb-submit" name="bbb-submit" value="1" />';
	}		

	register_sidebar_widget('Bubs\' Button Board', 'widget_bbb');
	register_widget_control('Bubs\' Button Board', 'widget_bbb_control', 200, 100);
}


add_action('plugins_loaded', 'widget_bbb_init');
?>
