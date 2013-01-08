<?php
/*
Plugin Name: Folder Gallery
Version: 0.93
Plugin URI: http://www.jalby.org/wordpress/
Author: Vincent Jalby
Author URI: http://www.jalby.org
Text Domain: foldergallery
Description: This plugin creates picture galleries from a folder. The gallery is automatically generated in a post or page with a shortcode. Usage: [foldergallery folder="path_to_folder" title="Gallery title"]. For each gallery, a subfolder cache_[width]x[height] is created inside the pictures folder when the page is accessed for the first time. The picture folder must be writable (chmod 777).
Tags: gallery, folder, lightbox
Requires: 3.5
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: foldergallery
Domain Path: /languages
*/

/*  Copyright 2013  Vincent Jalby  (wordpress /at/ jalby /dot/ org)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

new foldergallery();

class foldergallery{

function foldergallery() {		
		add_action( 'admin_menu', array( $this, 'fg_menu' ) );
		add_action( 'admin_init', array( $this, 'fg_settings_init' ) );				
		add_shortcode( 'foldergallery', array( $this, 'fg_gallery' ) );		
		
		load_plugin_textdomain( 'foldergallery', false, basename( dirname( __FILE__ ) ) . '/languages' );
		
		add_action( 'wp_enqueue_scripts', array( $this, 'fg_styles_and_scripts' ) );
		
		
		$options = get_option( 'FolderGallery' );
		if ( empty( $options ) ) {	
			update_option( 'FolderGallery', $this->fg_settings_default() );
		}
}

function fg_styles_and_scripts(){
    wp_enqueue_style( 'fg_style', plugins_url( '/css/style.css', __FILE__ ) );
	wp_enqueue_style( 'fg_lightbox_style', plugins_url( '/css/lightbox.css', __FILE__ ) );
	wp_enqueue_script( 'fg_lightbox_script', plugins_url( '/js/lightbox.js', __FILE__ ), array( 'jquery' ) );
	wp_localize_script( 'fg_lightbox_script', 'FGtrans', array(
		'labelImage' => __( 'Image', 'foldergallery' ),
		'labelOf'    => __( 'of', 'foldergallery' ),
		)
	);
}

/* --------- Folder Gallery Main Functions --------- */

function save_thumbnail( $path, $savepath, $th_width, $th_height ) { // Save thumbnail
	$image = wp_get_image_editor( $path );
	if ( ! is_wp_error( $image ) ) {
		if ( 0 == $th_height ) { // 0 height => auto
			$size = $image->get_size();
			$width = $size['width'];
			$height = $size['height'];
			$th_height = floor( $height * ( $th_width / $width ) ); 
		}
    	$image->resize( $th_width, $th_height, true );
    	$image->save( $savepath );
	}
}

function file_array( $directory ) { // List all JPG & PNG files in $directory
	$files = array();
	if( $handle = opendir( $directory ) ) {
		while ( false !== ( $file = readdir( $handle ) ) ) {
			$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
			if ( 'jpg' == $ext || 'png' == $ext ) {
				$files[] = $file;
			}
		}
		closedir( $handle );
	}
	return $files;
}
				
function fg_gallery( $atts ) { // Generate gallery
	$options = get_option( 'FolderGallery' );
	extract( shortcode_atts( array(
		'folder'  => 'wp-content/uploads/',
		'title'   => 'My Gallery',
		'width'   => $options['thumbnails_width'],
		'height'  => $options['thumbnails_height'],
		'cols'    => $options['images_per_row'],
		'margin'  => $options['margin'],
		'padding' => $options['padding'],
		'border'  => $options['border']
	), $atts ) );
   
	$folder = rtrim( $folder, '/' ); // Remove trailing / from path

	if ( !is_dir( $folder ) ) {
		echo '<p style="color:red;"><strong>' . __( 'Folder Gallery Error:', 'foldergallery' ) . '</strong> ';
		printf( __( 'Unable to find the directory %s.', 'foldergallery' ), $folder );
		echo '</p>';	
		return;
	}
	
	$cache_folder = $folder . '/cache_' . $width . 'x' . $height;
	if ( ! is_dir( $cache_folder ) ) {
			@mkdir( $cache_folder, 0777 );
	}
	if ( ! is_dir( $cache_folder ) ) {
		echo '<p style="color:red;"><strong>' . __( 'Folder Gallery Error:', 'foldergallery' ) . '</strong> ';
		printf( __( 'Unable to create the thumbnails directory inside %s.', 'foldergallery' ), $folder ); 
		_e( 'Verify that this directory is writable (chmod 777).', 'foldergallery' );
		echo '</p>';
		return;
	}
	
	$lightbox_id = md5( $folder );
	
	$pictures = $this->file_array( $folder );

	$imgstyle = "margin:0px {$margin}px {$margin}px 0px;";
	$imgstyle .= "padding:{$padding}px;";
	$imgstyle .= "border-width:{$border}px;";

	$gallery_code = '<div class="fg_gallery">';

	$NoP = count( $pictures );
	for ( $idx = 0 ; $idx < $NoP ; $idx++ ) {
		$thumbnail = $cache_folder . '/' . $pictures[ $idx ];
		if ( ! file_exists( $thumbnail ) ) {
			$this->save_thumbnail( $folder . '/' . $pictures[ $idx ], $thumbnail, $width, $height );
		}
		$gallery_code .= "\n";
		$gallery_code.= '<a title="' . $title . '" href="' . home_url( '/' ) . $folder . '/' . $pictures[ $idx ] . '" rel="lightbox[' . $lightbox_id . ']">';
		$gallery_code.= '<img src="' . home_url( '/' ) . $thumbnail . '" style="' . $imgstyle . '" alt="' . $title . ' [' . ( $idx + 1 ) . ']' . '" /></a>';
		if ( $cols>0 ) {
			if ( ( $idx + 1 ) % $cols == 0 ) $gallery_code .= "\n" . '<div class="fg_clear"></div>'; 
		}
	}
	if ( 0 == $NoP ) {
		echo '<p style="color:red;"><strong>' . __( 'Folder Gallery Error:', 'foldergallery' ) . '</strong> ';
		printf( __( 'No picture available inside %s.', 'foldergallery' ), $folder );
		echo '</p>';
	}	
	$gallery_code .= "\n</div>";
	$gallery_code .= "\n" . '<div class="fg_clear"></div>';
	return $gallery_code;
}

/* --------- Folder Gallery Settings --------- */

function fg_menu() {
	add_options_page( 'Folder Gallery Settings', 'Folder Gallery', 'manage_options', 'foldergallery-settings', array( $this, 'fg_settings' ) );
}

function fg_settings_init() {
	register_setting( 'FolderGallery', 'FolderGallery', array( $this, 'fg_settings_validate' ) );
}

function fg_settings_validate( $input ) {
    $input['images_per_row']    = intval( $input['images_per_row'] );
    $input['thumbnails_width']  = intval( $input['thumbnails_width'] );
    	if ( 0 == $input['thumbnails_width'] ) $input['thumbnails_width'] = 150;
    $input['thumbnails_height'] = intval( $input['thumbnails_height'] );
    $input['border']            = intval( $input['border'] );   
	$input['padding']           = intval( $input['padding'] );
	$input['margin']            = intval( $input['margin'] );
    return $input;
}

function fg_settings_default() {
		$defaults = array( 
			'border' 			=> 1,
			'padding' 			=> 2,
			'margin' 			=> 5,
			'images_per_row' 	=> 0, 
			'thumbnails_width' 	=> 160,
			'thumbnails_height' => 0,
		);
		return $defaults;
}

function fg_option_field( $field, $label, $extra = 'px' ) {
	$options = get_option( 'FolderGallery' );
	echo '<tr valign="top">';
	echo '<th scope="row"><label for="' . $field . '">' . $label . '</label></th>';
	echo '<td><input id="' . $field . '" name="FolderGallery[' . $field . ']" type="text" value="' . $options[$field] . '" class="small-text"> ' . $extra . '</td>';
	echo '</tr>';
}

function fg_settings()
{
	echo '<div class="wrap">';
	screen_icon();
	echo '<h2>' . __( 'Folder Gallery Settings', 'foldergallery' ) . '</h2>';
	echo '<form method="post" action="options.php">';
	settings_fields( 'FolderGallery' );
	echo '<table class="form-table"><tbody>';
	$this->fg_option_field( 'images_per_row', __( 'Images Per Row', 'foldergallery' ), __( '(0 = auto)', 'foldergallery' ) );
	$this->fg_option_field( 'thumbnails_width', __( 'Thumbnails Width', 'foldergallery' ) );
	$this->fg_option_field( 'thumbnails_height', __( 'Thumbnails Height', 'foldergallery' ), ' px ' . __( '(0 = auto)', 'foldergallery' ) );
	$this->fg_option_field( 'border', __( 'Picture Border', 'foldergallery' ) );
	$this->fg_option_field( 'padding', __( 'Padding', 'foldergallery' ) );
	$this->fg_option_field( 'margin', __( 'Margin', 'foldergallery' ) );	
	echo '</tbody></table>';
	submit_button();
	echo '</form></div>';
}
	
} //End Of Class

?>