<?php
/*
	The uninstall file
	(to make your blog always clean when you need to test plugins :p)
*/

global $wpdb;

if( !defined( 'ABSPATH') &&  !defined('WP_UNINSTALL_PLUGIN') )
	    exit();
	
	$allposts = get_posts('numberposts=-1&post_type=post&post_status=any');

	foreach( $allposts as $postinfo)
		delete_post_meta($postinfo->ID, 'juiz_count_views');

