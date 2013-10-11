<?php
/*
Plugin Name: PrettyGallery
Plugin URI: http://wordpress.org/plugins/prettygallery
Description: Integrate Wordpress default gallery shortcode with jquery modal popup.
Author: webnoetic
Author URI: http://www.webnoetic.com
Version: 1.0
*/

/**
 * Modify wordpress default gallery
 * Step 1: Remove old Shotcode
 * Step 2: Add new Shortcode 
 * Step 3: Add required scripts for modal popup
 */
remove_shortcode('gallery');
add_shortcode('gallery', array('prettygallery','prettygallery_colorbox'));
add_action( 'wp_enqueue_scripts', array('prettygallery','prettygallery_scripts'));




/**
 * Pretty Gallery Main Class. Define all function related to plugin here. 
 * @author Arjun Jain < arjunjain08 >
 * @since 1.0
 * @version 1.0
 *
 */
class prettygallery{
	
	/**
	 * script for modal popup of images
	 */
	public function prettygallery_scripts(){
		wp_enqueue_style(  'prettygallery_style', plugins_url('css/colorbox.css',__FILE__));
		wp_enqueue_script( 'prettygallery_js', 	  plugins_url('js/jquery.colorbox-min.js',__FILE__), array('jquery'), '1.0.0',false);
	}

	/** Updated shortcode function 
	 * 
	 * [gallery ids="" order="ASC" orderby="" itemtag="dl" icontag="dt" captiontag="dd" "columns"=3 size="thumbnail" include="" exclude=""]
	 * 
	 * For more information about shortcode visi : http://codex.wordpress.org/Gallery_Shortcode
	 * 
	 */
	public function prettygallery_colorbox($attr){
		$post = get_post();
		
		static $instance = 0;
		$instance++;
		
		if ( ! empty( $attr['ids'] ) ) {
			// 'ids' is explicitly ordered, unless you specify otherwise.
			if ( empty( $attr['orderby'] ) )
				$attr['orderby'] = 'post__in';
			$attr['include'] = $attr['ids'];
		}
		
		// Allow plugins/themes to override the default gallery template.
		$output = apply_filters('post_gallery', '', $attr);
		if ( $output != '' )
			return $output;
		
		// We're trusting author input, so let's at least make sure it looks like a valid orderby statement
		if ( isset( $attr['orderby'] ) ) {
			$attr['orderby'] = sanitize_sql_orderby( $attr['orderby'] );
			if ( !$attr['orderby'] )
				unset( $attr['orderby'] );
		}
		
		extract(shortcode_atts(array(
				'order'      => 'ASC',
				'orderby'    => 'menu_order ID',
				'id'         => $post ? $post->ID : 0,
				'itemtag'    => 'dl',
				'icontag'    => 'dt',
				'captiontag' => 'dd',
				'columns'    => 3,
				'size'       => 'thumbnail',
				'include'    => '',
				'exclude'    => ''
		), $attr, 'gallery'));
		
		$id = intval($id);
		if ( 'RAND' == $order )
			$orderby = 'none';
		
		add_filter('wp_get_attachment_link',array('prettygallery','update_prettygallery_rel'),10,6);
		
		if ( !empty($include) ) {
			$_attachments = get_posts( array('include' => $include, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );
		
			$attachments = array();
			foreach ( $_attachments as $key => $val ) {
				$attachments[$val->ID] = $_attachments[$key];
			}
		} elseif ( !empty($exclude) ) {
			$attachments = get_children( array('post_parent' => $id, 'exclude' => $exclude, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );
		} else {
			$attachments = get_children( array('post_parent' => $id, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );
		}
		
		if ( empty($attachments) )
			return '';
		
		if ( is_feed() ) {
			$output = "\n";
			foreach ( $attachments as $att_id => $attachment )
				$output .= wp_get_attachment_link($att_id, $size, true) . "\n";
			return $output;
		}
		
		$itemtag = tag_escape($itemtag);
		$captiontag = tag_escape($captiontag);
		$icontag = tag_escape($icontag);
		$valid_tags = wp_kses_allowed_html( 'post' );
		if ( ! isset( $valid_tags[ $itemtag ] ) )
			$itemtag = 'dl';
		if ( ! isset( $valid_tags[ $captiontag ] ) )
			$captiontag = 'dd';
		if ( ! isset( $valid_tags[ $icontag ] ) )
			$icontag = 'dt';
		
		$columns = intval($columns);
		$itemwidth = $columns > 0 ? floor(100/$columns) : 100;
		$float = is_rtl() ? 'right' : 'left';
		
		$selector = "gallery-{$instance}";
		
		$gallery_style = $gallery_div = '';
		if ( apply_filters( 'use_default_gallery_style', true ) )
			$gallery_style = "<style type='text/css'>
								#{$selector} {
									margin: auto;
								}
								#{$selector} .gallery-item {
									float: {$float};
									margin-top: 10px;
									text-align: center;
									width: {$itemwidth}%;
								}
								#{$selector} img {
									border: 2px solid #cfcfcf;
								}
								#{$selector} .gallery-caption {
									margin-left: 0;
								}
								/* see gallery_shortcode() in wp-includes/media.php */
							</style>";
		
		$size_class = sanitize_html_class( $size );
		$gallery_div = "<div id='$selector' class='gallery galleryid-{$id} gallery-columns-{$columns} gallery-size-{$size_class}'>";
		$output = apply_filters( 'gallery_style', $gallery_style . "\n\t\t" . $gallery_div );
		
		$i = 0;
		foreach ( $attachments as $id => $attachment ) {
		if ( ! empty( $attr['link'] ) && 'file' === $attr['link'] )
			$image_output = wp_get_attachment_link( $id, $size, false, false );
			elseif ( ! empty( $attr['link'] ) && 'none' === $attr['link'] )
			$image_output = wp_get_attachment_image( $id, $size, false );
			else
			$image_output = wp_get_attachment_link( $id, $size, true, false );
		
			$image_meta  = wp_get_attachment_metadata( $id );
		
			$orientation = '';
		if ( isset( $image_meta['height'], $image_meta['width'] ) )
			$orientation = ( $image_meta['height'] > $image_meta['width'] ) ? 'portrait' : 'landscape';
		
		$output .= "<{$itemtag} class='gallery-item'>";
		$output .= "<{$icontag} class='gallery-icon {$orientation}'>$image_output</{$icontag}>";
		if ( $captiontag && trim($attachment->post_excerpt) ) {
			$output .= "<{$captiontag} class='wp-caption-text gallery-caption'>" . wptexturize($attachment->post_excerpt) . "</{$captiontag}>";
		}
		$output .= "</{$itemtag}>";
		if ( $columns > 0 && ++$i % $columns == 0 )
			$output .= '<br style="clear: both" />';
		}	
		$output .= "<br style='clear: both;' />
					</div>\n";
		
		$output .='<script type="text/javascript">
					jQuery("[rel=wp-prettygallery]").colorbox();
				   </script>';
		return $output;
	}
	
	/**
	 * Filter 'wp_get_attachment_link'
	 * Relace default page URL with Large image url
	 * 
	 */
	function update_prettygallery_rel($link,$id,$size,$permalink, $icon, $text){
		$id = intval( $id );
		$_post = get_post( $id );
		
		if ( empty( $_post ) || ( 'attachment' != $_post->post_type ) || ! $url = wp_get_attachment_url( $_post->ID ) )
			return __( 'Missing Attachment' );
		
		if ( $permalink )
			$url = get_attachment_link( $_post->ID );
		
		$post_title = esc_attr( $_post->post_title );
		
		if ( $text )
			$link_text = $text;
		elseif ( $size && 'none' != $size )
			$link_text = wp_get_attachment_image( $id, $size, $icon );
		else
			$link_text = '';
		
		if ( trim( $link_text ) == '' )
			$link_text = $_post->post_title;
		
		// Always display large image 
		$imagesrc= wp_get_attachment_image_src($id,'large',false);

		$link="<a href='".$imagesrc[0]."' rel='wp-prettygallery' title='$post_title'>$link_text</a>";
		return $link;
	}	
}
?>
