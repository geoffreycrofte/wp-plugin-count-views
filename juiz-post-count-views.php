<?php
/**
* Plugin Name: Juiz Post Count views
* Plugin URI: 
* Description: Count views for post and pages, shortcode [juiz_post_view] available
* Version: 1.0
* Author: Geoffrey Crofte
* Author URI: http://www.crofte.fr
* License: GPLv2
**/


$timings = array( 'all'=>'', 'month'=>'Ym', 'year'=>'Y' );

// Make plugin translatable
if ( !function_exists('juiz_count_views_l10n')) {
	add_action( 'init', 'juiz_count_views_l10n' );
	function juiz_count_views_l10n() {
		load_plugin_textdomain( 'jpcv_lang', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}
}

// when plugin is activated
function juiz_count_views_activation() {
	// if need an update between two versions
	global $wpdb;
	$wpdb->update( $wpdb->postmeta, array( 'meta_key' => "juiz_count_views_all" ), array( 'meta_key' => "juiz_count_views" ) );
}
register_activation_hook( __FILE__, 'juiz_count_views_activation' );


function juiz_count_views() {
	if( is_singular() ) {
	global $post, $timings;
		$IP = substr(md5( getenv( 'HTTP_X_FORWARDED_FOR' ) ? getenv( 'HTTP_X_FORWARDED_FOR' ) : getenv( 'REMOTE_ADDR' ) ), 0, 16 );
		if ( ! get_transient( 'juiz_count_views-' . $IP ) ) {
			foreach( $timings as $time=>$date ) {
				if( $date != '' ) 
					$date = '-' . date( $date );
				
				$count = (int)get_post_meta( $post->ID, 'juiz_count_views_' . $time . $date, true );
				$count++;
				update_post_meta( $post->ID, 'juiz_count_views_' . $time . $date, $count );
			}
			set_transient( 'juiz_count_views-' . $IP, $IP, 60 ); // 1 min
		}
	}
}

add_action( 'wp_head', 'juiz_count_views' );


// shortcode
function juiz_count_views_sc( $atts, $content = null ) {
	global $post;
	extract(shortcode_atts(array(
		"id" => isset( $post->ID ) ? (int)$post->ID : 0,
		"time" => 'all',
		"date" => '' // yyyymmdd format
	), $atts));
	
	if ( $id > 0 ) {
		global $timings;
		
		$date = $date != '' ? $date : date($timings[$time]);
		$date = $time == 'all' ? '' : '-'.$date;
		
		$count = (int)get_post_meta( $id, 'juiz_count_views_' . $time . $date, true );
		
		/* if( $count > 1000 )
			$count = $count / 1000 . 'K'; */
			
		return number_format ( $count, 0, ',', ' ');
	}
	
	return '';
}

add_shortcode( 'juiz_post_view', 'juiz_count_views_sc' );

/* function juiz_post_view_in_content() {
	echo '<p>Lu ' .do_shortcode( '[juiz_post_view]' ). ' fois.</p>';
}
add_action( 'the_content', 'juiz_post_view_in_content' ); */



// uninstaller
function juiz_count_views_uninstaller() {
	global $wpdb;
	$wpdb->query( 'DELETE FROM ' . $wpdb->postmeta . ' WHERE meta_key LIKE "juiz_count_views%"' );
}
register_uninstall_hook( __FILE__, 'juiz_count_views_uninstaller' );




// new widget named WP_Widget_Most_Viewed_Posts
class WP_Widget_Most_Viewed_Posts extends WP_Widget {
 
	function __construct() {
	
		$widget_ops = array(
			'classname'		=> 'widget_most_viewed_entries',
			'description'	=> __( 'The most viewed posts on your site', 'jpcv_lang')
		);

		parent::__construct(
			'most-viewed-posts',
			__('Most Viewed Posts', 'jpcv_lang'),
			$widget_ops
		);

		$this->alt_option_name = 'widget_most_viewed_entries';
		 
		add_action( 'save_post', array( &$this, 'flush_widget_cache' ) );
		add_action( 'deleted_post', array( &$this, 'flush_widget_cache' ) );
		add_action( 'switch_theme', array( &$this, 'flush_widget_cache' ) );
	}
	 
	function widget( $args, $instance ) {
		$cache = wp_cache_get('widget_most_viewed_entries', 'widget');
		if ( ! is_array( $cache ) ) {
			$cache = array();
		}
	 
		if ( ! isset( $args['widget_id'] ) ) {
			$args['widget_id'] = $this->id;
		}
	 
		if ( isset( $cache[ $args['widget_id'] ] ) ) {
			echo $cache[ $args['widget_id'] ];
			return;
		}
	 
		ob_start();
		extract( $args );
		 
		$title = apply_filters('widget_title', empty($instance['title']) ? __('Most Viewed Posts', "jpcv_lang") : $instance['title'], $instance, $this->id_base);
		$subtitle = empty( $instance['subtitle'] ) ? '' : $instance['subtitle'];
		
		if ( empty( $instance['number'] ) || ! $number = absint( $instance['number'] ) ) {
			$number = 10;
		}
			
		global $timings;
		
		$date = $instance['date'] != '' ? $instance['date'] : date( $timings[$instance['time']] );
		$date = $instance['time'] == 'all' ? '' : '-' . $date;
		$time = $instance['time'];
		 
		$r = new WP_Query(
			array(
				'posts_per_page'		=> $number,
				'no_found_rows'			=> true,
				'post_status'			=> 'publish',
				'ignore_sticky_posts'	=> true,
				'meta_key'				=> 'juiz_count_views_' . $time . $date,
				'meta_value_num'		=> '0',
				'meta_compare'			=> '>',
				'orderby'				=>'meta_value_num',
				'order'					=>'DESC'
			)
		);
		
		if ( $r->have_posts() ) :
			echo $before_widget;
			
			if ( $title ) {
				echo $before_title . $title . $after_title;
			}

			if ( $subtitle ) {
				echo '<p class="widget-subtitle">' . $subtitle . '</p>';
			}
		?>
		<ul>
		<?php 
			while ( $r->have_posts() ) : $r->the_post(); 
			
			$count = $instance['show'] ? ' <span class="count">(' . sprintf( __( '%s views', "jpcv_lang" ), (int)get_post_meta( get_the_ID(), 'juiz_count_views_' . $time . $date, true ) ) . ')</span>' : ''; 
		?>
		<li>
			<a href="<?php the_permalink() ?>" title="<?php echo esc_attr(get_the_title() ? get_the_title() : get_the_ID()); ?>">
				<?php if ( get_the_title() ) the_title(); else the_ID(); echo $count; ?>
			</a>
		</li>
		<?php 
			endwhile; 
		?>
		</ul>
		<?php 
			echo $after_widget; 
			wp_reset_postdata();
		endif;

		$cache[$args['widget_id']] = ob_get_flush();
		wp_cache_set('widget_most_viewed_entries', $cache, 'widget');
	}
	 
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['subtitle'] = $new_instance['subtitle'];
		$instance['time'] = $new_instance['time'];
		$instance['date'] = $new_instance['date'];
		$instance['number'] = (int) $new_instance['number'];
		$instance['show'] = (bool) $new_instance['show'];
	 
		$this->flush_widget_cache();
	 
		$alloptions = wp_cache_get( 'alloptions', 'options' );
		if ( isset($alloptions['widget_most_viewed_entries']) )
		delete_option('widget_most_viewed_entries');
	 
		return $instance;
	}
	 
	function flush_widget_cache() {
		wp_cache_delete('widget_most_viewed_entries', 'widget'); // oubli de Julio
	}
	 
	function form( $instance ) {
		$title = isset($instance['title']) ? esc_attr($instance['title']) : '';
		$subtitle = isset( $instance['subtitle'] ) ? esc_attr( $instance['subtitle'] ) : '';
		$number = isset($instance['number']) ? absint($instance['number']) : 5;
		$time = isset($instance['time']) ? ($instance['time']) : 'all';
		$date = isset($instance['date']) ? ($instance['date']) : '';
		$show = isset($instance['show']) ? $instance['show'] == 'on' : true;
	 
	?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', "jpcv_lang"); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></p>

		<p><label for="<?php echo $this->get_field_id('subtitle'); ?>"><?php _e('Subtitle:', "jpcv_lang"); ?></label>
		<textarea class="widefat" id="<?php echo $this->get_field_id('subtitle'); ?>" name="<?php echo $this->get_field_name('subtitle'); ?>"><?php echo $subtitle; ?></textarea></p>
		 
		<p><label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Number of posts to show:', "jpcv_lang"); ?></label>
		<input id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" type="text" value="<?php echo $number; ?>" size="3" /></p>
		 
		<p><label for="<?php echo $this->get_field_id('time'); ?>"><?php _e('What top do you want:', "jpcv_lang"); ?></label>
		<select id="<?php echo $this->get_field_id('time'); ?>" name="<?php echo $this->get_field_name('time'); ?>">
		<?php global $timings;
		foreach( $timings as $timing=>$dummy ) { ?>
		<option value="<?php echo esc_attr( $timing ); ?>" <?php selected( $timing, $time ); ?>><?php echo ucwords( esc_html( $timing ) ); ?></option>
		<?php } ?>
		</select>
		 
		<p><label for="<?php echo $this->get_field_id('date'); ?>"><?php _e('Date format', "jpcv_lang"); ?> <code>YYYYMMAA</code></label>
		<input id="<?php echo $this->get_field_id('date'); ?>" name="<?php echo $this->get_field_name('date'); ?>" type="text" value="<?php echo esc_attr( $date ); ?>" size="6" maxlength="8" /><br />
		<code><?php _e( 'If you leave blank the actual time will be used.', "jpcv_lang" ); ?></code></p>
		 
		<p><label for="<?php echo $this->get_field_id('show'); ?>"><?php _e('Show posts count:', "jpcv_lang"); ?></label>
		<input id="<?php echo $this->get_field_id('show'); ?>" name="<?php echo $this->get_field_name('show'); ?>" type="checkbox" <?php checked( $show == true, true ); ?> /></p>
	<?php
	}
}

function juiz_count_views_widgets_init() {
	register_widget( 'WP_Widget_Most_Viewed_Posts' );
}
add_action( 'widgets_init', 'juiz_count_views_widgets_init' );

?>