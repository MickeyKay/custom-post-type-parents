<?php
/**
 * Custom Simple Section Navigation widget.
 *
 * Based on Simple Section Navigation Widget plugin.
 * @link     https://wordpress.org/plugins/simple-section-navigation/
 *
 * @since    1.0.0
 *
 * @package  Custom Post Type Parents
 */	

class CustomSimpleSectionNav extends WP_Widget
{
	function __construct() {
		$widget_ops = array('classname' => 'simple-section-nav', 'description' => __( "A custom version of the Simple Section Navigation widget that is integrated with the Custom Post Type Parents plugin.") );
		parent::__construct('simple-section-nav', __('Simple Section Navigation [Custom Post Type Parents]'), $widget_ops);
	}

    function widget($args, $instance) {
		extract($args);
		global $post;
		
		/**
		 * Custom Post Type Parents
		 *
		 * Filter $top_page to allow for custom output
		 *
		 * This also switches to $current_post so we don't corrupt
		 * the actual global $post object.
		 */
		$current_post = apply_filters( 'simple_section_nav_filter_post', $post );

		if ( is_search() || is_404() ) return false; //doesn't apply to search or 404 page
		if ( is_front_page() && !$instance['show_on_home'] ) return false;	//if we're on the front page and we haven't chosen to show this anyways, leave
		
		if ( 'page' == get_post_type( $current_post->ID ) ) {
			if ( isset($current_post) && is_object($current_post) ) get_post_ancestors($current_post);   //workaround for occassional problems
		} else {
			if ($post_page = get_option("page_for_posts")) $current_post = get_page($post_page); //treat the posts page as the current page if applicable
			elseif ($instance['show_on_home']) $sub_front_page = true;	//if want to show on home, and home is the posts page
			else return false;
		}
		
		if ( is_front_page() || isset($sub_front_page )) {
			echo $before_widget.$before_title.get_bloginfo('name').$after_title."<ul>";
			$children = wp_list_pages(array( 'title_li' => '', 'depth' => 1, 'sort_column' => $instance['sort_by'], 'exclude' => $instance['exclude'], 'echo' => false ));
			echo apply_filters('simple_section_page_list',$children);
			echo "</ul>".$after_widget;
			return true; 
	  	}
		
		$exclude_list = $instance['exclude'];
		$excluded = explode(',', $exclude_list); //convert list of excluded pages to array 
		if ( in_array($current_post->ID,$excluded) && $instance['hide_on_excluded'] ) return false; //if on excluded page, and setup to hide on excluded pages 
		
		$post_ancestors = ( isset($current_post->ancestors) ) ? $current_post->ancestors : get_post_ancestors($current_post); //get the current page's ancestors either from existing value or by executing function
		$top_page = $post_ancestors ? end($post_ancestors) : $current_post->ID; //get the top page id
		
		$thedepth = 0; //initialize default variables
		
		if( !$instance['show_all'] ) 
		{	
			$ancestors_me = implode( ',', $post_ancestors ) . ',' . $current_post->ID;
			
			//exclude pages not in direct hierarchy
			foreach ($post_ancestors as $anc_id) 
			{
				if ( in_array($anc_id,$excluded) && $instance['hide_on_excluded'] ) return false; //if ancestor excluded, and hide on excluded, leave
				
				$pageset = get_pages(array( 'child_of' => $anc_id, 'parent' => $anc_id, 'exclude' => $ancestors_me ));
				foreach ($pageset as $page) {
					$excludeset = get_pages(array( 'child_of' => $page->ID, 'parent' => $page->ID ));
					foreach ($excludeset as $expage) { $exclude_list .= ',' . $expage->ID; }
				}
			}
			
			$thedepth = count($post_ancestors)+1; //prevents improper grandchildren from showing
		}		
		
		$children = wp_list_pages(array( 'title_li' => '', 'echo' => 0, 'depth' => $thedepth, 'child_of' => $top_page, 'sort_column' => $instance['sort_by'], 'exclude' => $exclude_list ));	//get the list of pages, including only those in our page list
		if( !$children && !$instance['show_empty'] ) return false; 	//if there are no pages in this section, and use hasnt chosen to display widget anyways, leave the function
		
		$sect_title = ( $instance['title'] ) ? apply_filters( 'the_title', $instance['title'] ) : apply_filters( 'the_title', get_the_title($top_page), $top_page );
		$sect_title = apply_filters( 'simple_section_nav_title', $sect_title );
		if ($instance['a_heading']) {
			$headclass = ( $current_post->ID == $top_page ) ? "current_page_item" : "current_page_ancestor";
			if ( $current_post->post_parent == $top_page ) $headclass .= " current_page_parent";
			$sect_title = '<a href="' . get_page_link($top_page) . '" id="toppage-' . $top_page . '" class="' . $headclass . '">' . $sect_title . '</a>';	
		}
	  	
		echo $before_widget.$before_title.$sect_title.$after_title."<ul>";
		echo apply_filters( 'simple_section_page_list', $children );
		echo "</ul>".$after_widget;
	}

	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = trim( strip_tags( $new_instance['title'] ) );
		$instance['show_all'] = ( $new_instance['show_all'] ) ? true : false;
		$instance['exclude'] = str_replace( " ", "", strip_tags($new_instance['exclude']) ); //remove spaces from list
		$instance['hide_on_excluded'] = ( $new_instance['hide_on_excluded'] ) ? true : false;
		$instance['show_on_home'] = ( $new_instance['show_on_home'] ) ? true : false;
		$instance['show_empty'] = ( $new_instance['show_empty'] ) ? true : false;
		$instance['sort_by'] = ( in_array( $new_instance['sort_by'], array( 'post_title', 'menu_order', 'ID' ) ) ) ? $new_instance['sort_by'] : 'menu_order';
		$instance['a_heading'] = ( $new_instance['a_heading'] ) ? true : false;
		return $instance;
	}

	function form($instance){
		//Defaults
		$instance = wp_parse_args( (array) $instance, array( 'show_all' => false, 'exclude' => '', 'hide_on_excluded' => true, 'show_on_home' => false, 'show_empty' => false, 'sort_by' => 'menu_order', 'a_heading' => false, 'title' => '' ));
	?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Override Title:'); ?></label> 
			<input type="text" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo esc_attr($instance['title']); ?>" size="7" class="widefat" /><br />
			<small>Leave blank to use top level page title.</small>			
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('sort_by'); ?>"><?php _e('Sort pages by:'); ?></label>
			<select name="<?php echo $this->get_field_name('sort_by'); ?>" id="<?php echo $this->get_field_id('sort_by'); ?>" class="widefat">
				<option value="post_title"<?php selected( $instance['sort_by'], 'post_title' ); ?>><?php _e('Page title'); ?></option>
				<option value="menu_order"<?php selected( $instance['sort_by'], 'menu_order' ); ?>><?php _e('Page order'); ?></option>
				<option value="ID"<?php selected( $instance['sort_by'], 'ID' ); ?>><?php _e( 'Page ID' ); ?></option>
			</select>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('exclude'); ?>"><?php _e('Exclude:'); ?></label> 
			<input type="text" id="<?php echo $this->get_field_id('exclude'); ?>" name="<?php echo $this->get_field_name('exclude'); ?>" value="<?php echo esc_attr($instance['exclude']); ?>" size="7" class="widefat" /><br />
			<small>Page IDs, separated by commas.</small>			
		</p>
		<p> 
			<input class="checkbox" type="checkbox" <?php checked($instance['show_on_home']); ?> id="<?php echo $this->get_field_id('show_on_home'); ?>" name="<?php echo $this->get_field_name('show_on_home'); ?>" />
			<label for="<?php echo $this->get_field_id('show_on_home'); ?>"><?php _e('Show on home page'); ?></label><br /> 
			<input class="checkbox" type="checkbox" <?php checked($instance['a_heading']); ?> id="<?php echo $this->get_field_id('a_heading'); ?>" name="<?php echo $this->get_field_name('a_heading'); ?>" />
			<label for="<?php echo $this->get_field_id('a_heading'); ?>"><?php _e('Link heading (top level page)'); ?></label><br />
			<input class="checkbox" type="checkbox" <?php checked($instance['show_all']); ?> id="<?php echo $this->get_field_id('show_all'); ?>" name="<?php echo $this->get_field_name('show_all'); ?>" />
			<label for="<?php echo $this->get_field_id('show_all'); ?>"><?php _e('Show all pages in section'); ?></label><br />
			<input class="checkbox" type="checkbox" <?php checked($instance['show_empty']); ?> id="<?php echo $this->get_field_id('show_empty'); ?>" name="<?php echo $this->get_field_name('show_empty'); ?>" />
			<label for="<?php echo $this->get_field_id('show_empty'); ?>"><?php _e('Output even if empty section'); ?></label><br />
			<input class="checkbox" type="checkbox" <?php checked($instance['hide_on_excluded']); ?> id="<?php echo $this->get_field_id('hide_on_excluded'); ?>" name="<?php echo $this->get_field_name('hide_on_excluded'); ?>" />
			<label for="<?php echo $this->get_field_id('hide_on_excluded'); ?>"><?php _e('No nav on excluded pages'); ?></label> 			
		</p>
		<p><small><a href="http://www.get10up.com/plugins/simple-section-navigation/" target="_blank">Help &amp; Support</a></small></p>
	<?php
	}
}

/**
 * Display section based navigation
 * 
 * Arguments include: 'show_all' (boolean), 'exclude' (comma delimited list of page IDs),
 * 'show_on_home' (boolean), 'show_empty' (boolean), sort_by (any valid page sort string),
 * 'a_heading' (boolean), 'before_widget' (string), 'after_widget' (strong)
 *
 * @param array|string $args Optional. Override default arguments.
 * @param NULL deprecated - so pre 2.0 implementations don't break site 
 * @return string HTML content, if not displaying.
 */
function custom_simple_section_nav($args='',$deprecated=NULL) {
	if ( !is_null($deprecated) ) {
		echo 'The section navigation has been upgrade from 1.x to 2.0; this template needs to be updated to reflect major changes to the plug-in.';
		return false;
	}
	$args = wp_parse_args($args, array(
		'show_all' => false, 
		'exclude' => '', 
		'hide_on_excluded' => true, 
		'show_on_home' => false, 
		'show_empty' => false, 
		'sort_by' => 'menu_order', 
		'a_heading' => false, 
		'before_widget'=>'<div>',
		'after_widget'=>'</div>', 
		'before_title'=>'<h2 class="widgettitle">', 
		'after_title'=>'</h2>', 
		'title' => ''
	)); //defaults
	the_widget('CustomSimpleSectionNav',$args,array('before_widget'=>$args['before_widget'],'after_widget'=>$args['after_widget'],'before_title'=>$args['before_title'],'after_title'=>$args['after_title']));
}

//********************//
//upgrade from pre 2.0//
//********************//

function custom_simple_section_nav_activate() 
{
	if (get_option('ssn_sortby') === false) return false;	//if not upgrading, leave
	
	$show_all = (get_option('ssn_show_all')) ? 1 : 0;
	$exclude =  str_replace(" ","",get_option('ssn_exclude'));
	$hide_on_excluded = (get_option('ssn_hide_on_excluded')) ? 1 : 0;
	$show_on_home = (get_option('ssn_show_on_home')) ? 1 : 0;
	$show_empty = (get_option('ssn_show_empty')) ? 1 : 0;
	$a_heading = (get_option('ssn_a_heading')) ? 1 : 0;
	
	$settings = array('show_all'=>$show_all, 'exclude'=>$exclude, 'hide_on_excluded'=>$hide_on_excluded,'show_on_home'=>$show_on_home,'show_empty'=>$show_empty,'sort_by'=>get_option('ssn_sortby'),'a_heading'=>$a_heading);
	wp_convert_widget_settings('simple-section-nav','widget_simple-section-nav',$settings);
		
	//delete old settings ... done supporting 1.x
	delete_option('ssn_show_all');
	delete_option('ssn_exclude');
	delete_option('ssn_hide_on_excluded');
	delete_option('ssn_show_on_home');
	delete_option('ssn_show_empty');
	delete_option('ssn_sortby');
	delete_option('ssn_a_heading');
}
register_activation_hook(__FILE__, 'simple_section_nav_activate');
?>