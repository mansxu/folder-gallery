<?php
/*
Plugin Name: Folder Gallery
Version: 1.8a7
Plugin URI: http://www.jalby.org/wordpress/
Author: Vincent Jalby
Author URI: http://www.jalby.org
Description: This plugin creates picture (or document) galleries from a folder. The gallery is automatically generated in a post or page with a shortcode. Usage: [foldergallery folder="local_path_to_folder" title="Gallery title"]. For each gallery, a subfolder cache_[width]x[height] is created inside the pictures folder when the page is accessed for the first time. The picture folder must be writable (chmod 777).
Tags: gallery, folder, lightbox, lightview
Requires: 3.5
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: foldergallery
Domain Path: /languages
*/

/*  Copyright 2014  Vincent Jalby  (wordpress /at/ jalby /dot/ org)

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
		add_action('plugins_loaded', array( $this, 'fg_init' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'fg_styles' ) );
		add_shortcode( 'foldergallery', array( $this, 'fg_gallery' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'fg_plugin_action_links' ) );
	}

	function fg_init() {
		load_plugin_textdomain( 'foldergallery', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		$fg_options = get_option( 'FolderGallery' );
		if ( empty( $fg_options ) ) {
			update_option( 'FolderGallery', $this->fg_settings_default() );
			return;
		}
		if ( ! isset( $fg_options['engine'] ) ) {
			$fg_options['engine'] = 'none';
			update_option( 'FolderGallery', $fg_options );
		}
		if ( 'lightview' == $fg_options['engine'] ) {
			if ( ! is_dir( WP_CONTENT_DIR . '/lightview' ) ) {
				$fg_options['engine'] = 'none';
				update_option( 'FolderGallery', $fg_options );
			}
		}		
		if ( 'fancybox2' == $fg_options['engine'] ) {
			if ( ! is_dir( WP_CONTENT_DIR . '/fancybox' ) ) {
				$fg_options['engine'] = 'none';
				update_option( 'FolderGallery', $fg_options );
			}
		}
		if ( 'lightbox2' == $fg_options['engine'] ) {
			if ( ! is_dir( WP_CONTENT_DIR . '/lightbox' ) ) {
				$fg_options['engine'] = 'none';
				update_option( 'FolderGallery', $fg_options );
			}
		}
		if ( ! isset( $fg_options['fb_speed'] ) ) { // 1.1 + 1.2 + 1.3 update
			$fg_options['thumbnails'] = 'all';
			$fg_options['fb_title'] = 'float';
			$fg_options['caption'] = 'default';
			$fg_options['fb_speed'] = 0;
			update_option( 'FolderGallery', $fg_options );
		}
		if ( ! isset( $fg_options['fb_effect'] ) ) { // 1.4 + 1.4.1 update
			$fg_options['show_thumbnail_captions'] = 0;
			$fg_options['caption'] = 'default';
			$fg_options['sort'] = 'filename';
			$fg_options['fb_effect'] = 'elastic';
			update_option( 'FolderGallery', $fg_options );
		}
		if ( ! isset( $fg_options['orientation'] ) ) { // 1.7 update
			$fg_options['orientation'] = 0;
			update_option( 'FolderGallery', $fg_options );
		}
		if ( ! isset( $fg_options['gridlayout'] ) ) { // 1.8 update
			$fg_options['filetypes'] = 'doc docx xls xlsx ppt pptx pdf';
			$fg_options['gridlayout'] = 0;
			update_option( 'FolderGallery', $fg_options );
		}
	}

	function fg_styles(){
		$fg_options = get_option( 'FolderGallery' );
		wp_enqueue_style( 'fg-style', plugins_url( '/style.css', __FILE__ ) );
		switch ( $fg_options['engine'] ) {
			case 'lightbox2' :
				wp_enqueue_style( 'fg-lightbox-style', content_url( '/lightbox/css/lightbox.css', __FILE__ ) );
			break;
			case 'fancybox2' :
				wp_enqueue_style( 'fancybox-style', content_url( '/fancybox/source/jquery.fancybox.css', __FILE__ ) );
			break;
			case 'lightview' :
				wp_enqueue_style( 'lightview-style', content_url( '/lightview/css/lightview/lightview.css', __FILE__ ) );		
			break;
			case 'photoswipe' :
			case 'responsive-lightbox' :
			case 'easy-fancybox' :
			case 'slenderbox-plugin' :
			case 'none' :
				// do nothing for now
			break;
		}
	}

	function fg_scripts(){
		static $firstcall = 1;
		$fg_options = get_option( 'FolderGallery' );	
		switch ( $fg_options['engine'] ) {
			case 'lightbox2' :
				wp_enqueue_script( 'lightbox-script', content_url( '/lightbox/js/lightbox.min.js', __FILE__ ), array( 'jquery' ) );
			break;
			case 'fancybox2' :
				wp_enqueue_script( 'fancybox-script', content_url( '/fancybox/source/jquery.fancybox.pack.js', __FILE__ ), array( 'jquery' ) );
				wp_enqueue_script( 'fg-fancybox-script', plugins_url( '/js/fg-fancybox.js', __FILE__ ), array( 'jquery' ) );
				if ( $firstcall ) {
					wp_localize_script( 'fg-fancybox-script', 'FancyBoxGalleryOptions', array(
						'title' => $fg_options['fb_title'],
						'speed' => $fg_options['fb_speed'],
						'effect' => $fg_options['fb_effect'],						
						)
					);
					$firstcall = 0;
				}
			break;
			case 'lightview' :
				global $is_IE;
				if ( $is_IE ) {
					wp_enqueue_script( 'excanvas', content_url( '/lightview/js/excanvas/excanvas.js', __FILE__  ), array( 'jquery' ) );
				}
				wp_enqueue_script( 'lightview_spinners', content_url( '/lightview/js/spinners/spinners.min.js', __FILE__ ), array( 'jquery' ) );
				wp_enqueue_script( 'lightview-script', content_url( '/lightview/js/lightview/lightview.js', __FILE__ ) );   		
			break;
			case 'photoswipe' :
			case 'responsive-lightbox' :
			case 'easy-fancybox' :
			case 'slenderbox-plugin' :
			case 'none' :
				// Do nothing for now
			break;
		}	
	}

	/* --------- Folder Gallery Main Functions --------- */

	function save_thumbnail( $path, $savepath, $th_width, $th_height, $gridlayout ) {
		$fg_options = get_option( 'FolderGallery' );
		// Get picture
		$image = wp_get_image_editor( $path );
		if ( is_wp_error( $image ) ) {
			echo '<p style="color:red;"><strong>' . __( 'Folder Gallery Error:', 'foldergallery' ) . '</strong> ' . $image->get_error_code() . ' (' . $path . ')</p>';
			return;
		}
		// Correct EXIF orientation	(of main picture)
		if ( function_exists( 'exif_read_data' ) && $fg_options['orientation'] == 1 ) {	
			$exif = @ exif_read_data( $path );
			if ( $exif !== FALSE ) {
				$orientation = @ $exif['Orientation'];
				if ( $orientation && $orientation != 1 ) {
					switch ( $orientation ) {
						case 2:
							$image->flip( FALSE, TRUE );
							$image->save( $path ); 	
							break;							
						case 3:
							$image->rotate( 180 );
							$image->save( $path ); 
							break;
						case 4:
							$image->flip( TRUE, FALSE );
							$image->save( $path ); 	
							break;
						case 5:
							$image->flip( FALSE, TRUE );
							$image->rotate( 90 );
							$image->save( $path ); 	
							break;	
						case 6:
							$image->rotate( -90 );
							$image->save( $path ); 
							break;
						case 7:
							$image->flip( FALSE, TRUE );
							$image->rotate( -90 );
							$image->save( $path ); 	
							break;	
						case 8:
							$image->rotate( 90 );
							$image->save( $path ); 
							break;			
					}
				}
			}
		}
		// Create thumbnail
		if ( 0 == $th_height ) { // 0 height => auto
			$size = $image->get_size();
			$width = $size['width'];
			$height = $size['height'];
			$th_height = floor( $height * ( $th_width / $width ) );
		} elseif ( 1 == $gridlayout ) { // Grid Layout
			$size = $image->get_size();
			$width = $size['width'];
			$height = $size['height'];
			if ( $width > $height ) { // Landscape: set width, calculate height
				$th_height = floor( $height * ( $th_width / $width ) );
			} else { //Portrait: set height, calculate width		
				$th_width = floor( $width * ( $th_height / $height ) );
			}
		}
		$image->resize( $th_width, $th_height, true );
		$image->save( $savepath );
	}

	function myglob( $directory, $extensions ) {
		$files = array();
		if( $handle = opendir( $directory ) ) {
			while ( false !== ( $file = readdir( $handle ) ) ) {
				$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
				if ( in_array( $ext, $extensions ) ) {
					$files[] = $file;
				}
			}
			closedir( $handle );
		}
		return $files;
	}
	
	function file_array( $directory , $sort, $filetypes = 'jpg gif png jpeg bmp') { // List all image/documents files in $directory
		$extensions = explode(" ", $filetypes);
		$extensions = array_merge( array_map( 'strtolower', $extensions ) , array_map( 'strtoupper', $extensions ) );		
		$cwd = getcwd();
		chdir( $directory );
		$files = glob( '*.{' . implode( ",", $extensions ) .'}' , GLOB_BRACE );
		// Free.fr doesn't accept glob function. Use a workaround		
		if ( 0 == count( $files ) ||  $files === FALSE ) {
			chdir( $cwd ); // Back to root
			$files = $this->myglob( $directory, $extensions );
			chdir( $directory );
		}
		// Verify there's something to sort
		if ( 0 == count($files) || $files === FALSE ) {
			chdir( $cwd );	
			return array();		
		}
		// Remove first file if its name is !!!
		sort( $files ); // Sort by name
		$firstfile = $files[0];
		if ( $this->filename_without_extension( $firstfile ) == '!!!' ) {
			unset( $files[0] );
		} else {
			$firstfile = false;
		}
		// Sort files
		switch ( $sort ) {
			case 'random' :
				shuffle( $files );		
			break;
			case 'date' :
				array_multisort( array_map( 'filemtime' , $files ) , SORT_ASC, $files );			
			break;
			case 'date_desc' :
				array_multisort( array_map( 'filemtime' , $files ) , SORT_DESC, $files );			
			break;
			case 'filename_desc' :
				rsort( $files );
			break;
			default:
				//sort( $files ); already done above
		}
		// Set back !!! file, if any
		if ( $firstfile ) {
			array_unshift( $files, $firstfile );
		}
		chdir( $cwd );	
		return $files;
	}

	function filename_without_extension ( $filename ) {
		$info = pathinfo($filename);
		return basename($filename,'.'.$info['extension']);
	}

	function file_size($size_in_bytes ) {
		if ($size_in_bytes < 1000) {
			return "$size_in_bytes B";
		} elseif ($size_in_bytes < 1000*1000) {
			$size_in_kb = (int) ($size_in_bytes/1000);
			return "$size_in_kb KB";	
		} else {
			$size_in_mb = (int) ($size_in_bytes/1000/1000);
			return "$size_in_mb MB";
		}
	}
	
	function at_query_string( $at ) {
        if ($_SERVER['QUERY_STRING'] == "") return 'at=' . urlencode($at);
        return preg_replace('/at=([^&]*)/', "", $_SERVER['QUERY_STRING']) . '&at=' . urlencode($at);
    }
					
	function fg_gallery( $atts ) { // Generate gallery
		$fg_options = get_option( 'FolderGallery' );
		extract( shortcode_atts( array(
			'folder'  => 'wp-content/uploads/',
			'title'   => 'My Gallery',
			'width'   => $fg_options['thumbnails_width'],
			'height'  => $fg_options['thumbnails_height'],
			'columns' => $fg_options['columns'],
			'margin'  => $fg_options['margin'],
			'padding' => $fg_options['padding'],
			'border'  => $fg_options['border'],
			'thumbnails' => $fg_options['thumbnails'],
			'options' => $fg_options['lw_options'],
			'caption' => $fg_options['caption'],
			'subtitle'=> false, // 1.3 compatibility
			'show_thumbnail_captions'=> $fg_options['show_thumbnail_captions'],
			'sort'	  => $fg_options['sort'],
			'filetypes'   => $fg_options['filetypes'],
			'engine' => $fg_options['engine'],
			'gridlayout' => $fg_options['gridlayout'],
			'cachedir' => $fg_options['cachedir']
		), $atts ) );
		
		// 1.3 Compatibility
		if ( $subtitle ) $caption = $subtitle;

		$folder = rtrim( $folder, '/' ); // Remove trailing / from path
		$subf = $_GET['at'] ?? null;
		if ($subf) {
            // replace backslashes with forward slashes
            $subf = str_replace("\\", '/', $subf);
            if (!in_array("..", explode("/", $subf))) {
                $folder = $folder . '/' . $subf;
            }
        }

		if ( !is_dir( $folder ) ) {
			return '<p style="color:red;"><strong>' . __( 'Folder Gallery Error:', 'foldergallery' ) . '</strong> ' .
				sprintf( __( 'Unable to find the directory %s.', 'foldergallery' ), $folder ) . '</p>';	
		}
		// Scan subdirectory
		$gallery_code ='';
		$cwd = getcwd();
		chdir( $folder );
		$subfolder = glob( '*' , GLOB_ONLYDIR );
		chdir( $cwd ); // Back to root
		
		if ( $engine == 'documentgallery' || $engine == 'documentlist') {
			$pictures = $this->file_array( $folder, $sort, $filetypes );
		} else {
			$pictures = $this->file_array( $folder, $sort );
		}

		$NoP = count( $pictures );		
		if ( 0 == $NoP && 0 == count($subfolder)) {
			return '<p style="color:red;"><strong>' . __( 'Folder Gallery Error:', 'foldergallery' ) . '</strong> ' .
				sprintf( __( 'No picture available inside %s.', 'foldergallery' ), $folder ) . '</p>';
		}	
		// Cleanup parameters
		$width=intval($width);
		$height=intval($height);
		$margin=intval($margin);
		$border=intval($border);
		$padding=intval($padding);
		// Cache folder
		if ( $engine != 'documentgallery' && $engine != 'documentlist') {
            $destfolder = $folder;
            if ( $cachedir != '') {
                if ( substr($cachedir, -1) != '/') {
                    $cachedir = $cachedir . '/';
                }
                $i = 0;
                for (; $i < min(strlen($folder), strlen($cachedir)); $i++) {
                    if ( $folder[$i] != $cachedir[$i] ) break;
                }
                if ( $i > 0 ) {
                    $destfolder = $cachedir . substr($folder, $i);
                } else {
                    $destfolder = $cachedir . $folder;
                }
            }
			if ( $gridlayout && $height > 0) { // Grid Layout
				$cache_folder = $destfolder . '/cache_' . $width . '+' . $height;
			} else {
				$cache_folder = $destfolder . '/cache_' . $width . 'x' . $height;
			}
			if ( ! is_dir( $cache_folder ) ) {
					@mkdir( $cache_folder, 0777, true );
			}
			if ( ! is_dir( $cache_folder ) ) {
				return '<p style="color:red;"><strong>' . __( 'Folder Gallery Error:', 'foldergallery' ) . '</strong> ' .
					sprintf( __( 'Unable to create the thumbnails directory inside %s.', 'foldergallery' ), $folder ) . ' ' .
					__( 'Verify that this directory is writable (chmod 777).', 'foldergallery' ) . '</p>';
			}
		
			if ( 1 == $fg_options['permissions'] ) @chmod( $cache_folder, 0777);
		}		
		// Image and Thumbnail style
		if ( 'none' == $thumbnails ) {
			$thmbdivstyle = '';
			$imgstyle = "display: none;";
		} else {
			if ( $engine == 'documentlist' ) {
				$imgstyle = "float:left;width:{$width}px;";
				$imgstyle .= "margin:0 {$margin}px 0 0;";
				$imgstyle .= "padding:{$padding}px;";
				$imgstyle .= "border-width:{$border}px;";		
				$thmbdivstyle = ' style="width:100%;';
				$thmbdivstyle .= "margin:0px 0px {$margin}px 0px;text-align:left;\"";			
			} else {
				$thmbdivstyle = ' style="width:' . ($width + 2*$border + 2*$padding) . 'px;';
				$thmbdivstyle .= "margin:0px {$margin}px {$margin}px 0px;\"";
				$imgstyle = "width:{$width}px;";
				$imgstyle .= 'margin:0;';
				$imgstyle .= "padding:{$padding}px;";
				$imgstyle .= "border-width:{$border}px;";
			}
		}
		

		//echo $folder . '|' ;
		if ( $engine != 'documentgallery' && $engine != 'documentlist' ) {
			$this->fg_scripts();			
			$lightbox_id = uniqid(); //md5( $folder . );
		}
		if ( $subf ) {
            $up = implode('/', array_slice(explode('/', $subf), 0 , -1));
            $gallery_code .= "\n<div class=\"fg_thumbnail\"$thmbdivstyle>\n";
            $gallery_code .= '<a title="Up" href="?' . $this->at_query_string($up) .'"><img src="' . plugins_url() . '/folder-gallery/img/up.svg" style="width:40px;margin:0;padding:2px;border-width:1px;" alt="Up" /></a>';
            $gallery_code .= "<div class='fg_caption'>Up</div></div>\n";	
            $gallery_code .= "\n<div class=\"fg_thumbnail\"$thmbdivstyle><div style='font-size:200%;'>" . end(explode('/', $subf)) . "</div></div>\n";	
        }
		// Main Div
		if ( 'photoswipe' == $engine ) {
			$gallery_code .= '<div class="fg_gallery gallery-icon">';
		} elseif ( 1 == $gridlayout ) {
			$gallery_code .= '<div class="fg_gallery gallery">';
		} else {
			$gallery_code .= '<div class="fg_gallery">';
		}		
		// Default single thumbnail
		$thumbnail_idx = 0;
		// If first picture == !!! then skip it (but use it as 'single' thumbnail).
		if ( $this->filename_without_extension( $pictures[ 0 ] ) == '!!!' ) {
			$start_idx = 1 ;		
		} else {
			$start_idx = 0 ;
		}
		// (single) thumbnail idx set as thumbnails=-n shortcode attribute		
		if ( intval($thumbnails) < 0 ) {
			$thumbnail_idx = - intval($thumbnails) -1;
			$thumbnails = 'single';
		}
		// Trick to display only the first thumbnails.		
		if ( intval($thumbnails) > 1 ) { // 1 = single should not be used
			$max_thumbnails_idx = intval($thumbnails) - 1 + $start_idx;
			$thumbnails = 'all';
		} else {
			$max_thumbnails_idx = $NoP - 1 + $start_idx;
		}
		if ( count( $subfolder ) > 0 ) {
			$NoF = count( $subfolder );
			$base = "";
			if ( $subf ) {
                $base = $subf . "/";
            }
			for ( $idf = 0 ; $idf < $NoF ; $idf++ ) {
				if ( strpos( $subfolder[$idf], 'cache_' ) === false ) {
					// $gallery_code .= $this->fg_gallery( array_replace( $atts, array ( 'folder' => $folder . '/' . $subfolder[$idf] ) ) ) ;
                    if ( 1 == $gridlayout ) {
                        $gallery_code .= "\n<div class=\"fg_thumbnail gallery-item\"$thmbdivstyle>\n";
                    } else {
                        $gallery_code .= "\n<div class=\"fg_thumbnail\"$thmbdivstyle>\n";
                    }
					$gallery_code .= '<a title="' . $subfolder[$idf] . '" href="?' . $this->at_query_string($base . $subfolder[$idf]) . '">';
                    $gallery_code .= '<img src="' . plugins_url() . '/folder-gallery/img/folder.svg" style="' . $imgstyle . '" alt="' . $subfolder[$idf] . '" /></a><div class="fg_caption">' . $subfolder[$idf] . "</div></div>\n";
				}
			}
			
		}
		// Main Loop
		for ( $idx = $start_idx ; $idx < $NoP ; $idx++ ) {
			// Set the thumbnail to use, depending of thumbnails option.
			if ( 'all' == $thumbnails ) {
				$thumbnail_idx = $idx;	
			}
			// Thumbnail picture
			if ( $engine == 'documentgallery' || $engine == 'documentlist' ) {			
				$filenamewithoutextension = $this->filename_without_extension( $pictures[ $thumbnail_idx ] )	;		
				if ( file_exists( $folder . '/' . $filenamewithoutextension . '.jpg' ) ) {
					$thumbnail = $folder . '/' . $filenamewithoutextension . '.jpg';
				} else if ( file_exists( $folder . '/' . $filenamewithoutextension . '.png' ) ) {
					$thumbnail = $folder . '/' . $filenamewithoutextension . '.png';
				} else if ( file_exists( $folder . '/' . $filenamewithoutextension . '.JPG' ) ) {
					$thumbnail = $folder . '/' . $filenamewithoutextension . '.JPG';
				} else if ( file_exists( $folder . '/' . $filenamewithoutextension . '.PNG' ) ) {
					$thumbnail = $folder . '/' . $filenamewithoutextension . '.PNG';
				} else if ( file_exists( $folder . '/thumbnail.jpg' ) ) {
					$thumbnail = $folder . '/thumbnail.jpg';
				} else if ( file_exists( $folder . '/thumbnail.png' ) ) {
					$thumbnail = $folder . '/thumbnail.png';
				} else if ( file_exists( $folder . '/thumbnail.JPG' ) ) {
					$thumbnail = $folder . '/thumbnail.JPG';
				} else if ( file_exists( $folder . '/thumbnail.PNG' ) ) {
					$thumbnail = $folder . '/thumbnail.PNG';
				} else {
					$thumbnail = 'wp-includes/images/crystal/document.png';
				}
			} else {
				$thumbnail = $cache_folder . '/' . strtolower($pictures[ $thumbnail_idx ]);
				// Generate thumbnail
				if ( ! file_exists( $thumbnail ) ) {
					$this->save_thumbnail( $folder . '/' . $pictures[ $thumbnail_idx ], $thumbnail, $width, $height, $gridlayout );
				}		
			}
			
			if ( ( $idx > $start_idx && 'all' != $thumbnails ) || $idx > $max_thumbnails_idx ) {
				$thmbdivstyle = ' style="display:none;"';
				$columns = 0;
			}
			// Set the Picture Caption
			switch ( $caption ) {
				case 'none' :
					$thecaption = '';
				break;
				case 'filename' :
					$thecaption = $pictures[ $idx ];
				break;
				case 'filenamewithoutextension' :
					$thecaption = $this->filename_without_extension( $pictures[ $idx ] );
				break;
				case 'smartfilename' :
					$thecaption = $this->filename_without_extension( $pictures[ $idx ] );
					$thecaption = preg_replace ( '/^\d+/' , '' , $thecaption );
					$thecaption = str_replace( '_', ' ', $thecaption );
				break;
				case 'modificationdater' :
					$moddate = filemtime( $folder . '/' . $pictures[ $idx ] ) + get_option( 'gmt_offset' ) * 3600;
					$gmtoffset = get_option( 'gmt_offset' );
					$tmznstr = sprintf( "%+03d%02d", $gmtoffset, (abs($gmtoffset) - intval(abs($gmtoffset)))*60 );
					$thecaption = str_replace( '+0000', $tmznstr, date( 'r', $moddate));
				break;
				case 'modificationdatec' :
					$moddate = filemtime( $folder . '/' . $pictures[ $idx ] ) + get_option( 'gmt_offset' ) * 3600;				
					$gmtoffset = get_option( 'gmt_offset' );
					$tmznstr = sprintf( "%+03d:%02d", $gmtoffset, (abs($gmtoffset) - intval(abs($gmtoffset)))*60 );
					$thecaption = str_replace( '+00:00', $tmznstr, date( 'c', $moddate)) ;
				break;
				case 'modificationdate' :
					$moddate = filemtime( $folder . '/' . $pictures[ $idx ] ) + get_option( 'gmt_offset' ) * 3600;
					$thecaption = date_i18n( get_option( 'date_format' ), $moddate);					
				break;
				case 'modificationdateandtime' :
					$moddate = filemtime( $folder . '/' . $pictures[ $idx ] ) + get_option( 'gmt_offset' ) * 3600;
					$thecaption = date_i18n( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ) , $moddate);					
				break;
				case 'iptctitle' :
					$tmp = getimagesize( $folder . '/' . $pictures[ $idx ], $iptcinfo);
					if(isset($iptcinfo['APP13'])) {
    					$iptc = iptcparse( $iptcinfo[ 'APP13' ] );
    					$thecaption = $iptc[ '2#005' ][ 0 ];
					} else {
						$thecaption = 'Unable to read IPTC info';
					}
				break;
				case 'iptccaption' :
					$tmp = getimagesize( $folder . '/' . $pictures[ $idx ], $iptcinfo);
					if(isset($iptcinfo['APP13'])) {
    					$iptc = iptcparse( $iptcinfo[ 'APP13' ] );
    					$thecaption = $iptc[ '2#120' ][ 0 ];
					} else {
						$thecaption = 'Unable to read IPTC info';
					}
				break;
				default :
					$thecaption = $title ;
					if ( 'lightbox2' != $fg_options['engine'] ) $thecaption .= ' (' . ($idx+1-$start_idx) . '/' . ($NoP-$start_idx) . ')' ;
			}		
			// Let's start
			if ( 1 == $gridlayout ) {
				$gallery_code .= "\n<div class=\"fg_thumbnail gallery-item\"$thmbdivstyle>\n";
			} else {
				$gallery_code .= "\n<div class=\"fg_thumbnail\"$thmbdivstyle>\n";
			}
			// Set the link
			switch ( $engine ) {
				case 'lightbox2' :
					$gallery_code.= '<a title="' . $thecaption . '" href="' . home_url( '/' . $folder . '/' . $pictures[ $idx ] ) . '" data-lightbox="' . $lightbox_id . '">';
				break;
				case 'fancybox2' :				
					$gallery_code.= '<a class="fancybox-gallery" title="' . $thecaption . '" href="' . home_url( '/' . $folder . '/' . $pictures[ $idx ] ) . '" data-fancybox-group="' . $lightbox_id . '">';
				break;
				case 'lightview' :
					if ( $options ) $options = " data-lightview-group-options=\"$options\"";
					$gallery_code .= '<a title="' . $thecaption . '" href="' . home_url( '/'  . $folder . '/' . $pictures[ $idx ] ) . '" class="lightview" data-lightview-group="' . $lightbox_id . '"' . $options . '>';
					$options = ''; // group-options required only once per group.
				break;
				case 'responsive-lightbox' :
					$gallery_code .= '<a rel="lightbox[' . $lightbox_id . ']" data-lightbox-gallery="' . $lightbox_id . '" title="' . $thecaption . '" href="' . home_url( '/' . $folder . '/' . $pictures[ $idx ] ) . '">';
				break;
				case 'easy-fancybox' :
					$gallery_code .= '<a class="fancybox" rel="' . $lightbox_id . '" title="' . $thecaption . '" href="' . home_url( '/' . $folder . '/' . $pictures[ $idx ] ) . '">';
				break;
				case 'slenderbox-plugin' :
					$gallery_code .= '<a data-sbox="' . $lightbox_id . '" title="' . $thecaption . '" href="' . home_url( '/' . $folder . '/' . $pictures[ $idx ] ) . '">';
				break;				
				case 'documentlist' :
				case 'documentgallery' :
					$gallery_code .= '<a title="' . $thecaption . '" href="' . home_url( '/' . $folder . '/' . $pictures[ $idx ] ) . '" target="_blank">';
				break;
				case 'photoswipe' :
				case 'none' :
					$gallery_code .= '<a title="' . $thecaption . '" href="' . home_url( '/' . $folder . '/' . $pictures[ $idx ] ) . '">';
				break;
			}
			// Show image (possibly hidden, but required for alt tag)
			$gallery_code .= '<img src="' . home_url( '/' . $thumbnail ) . '" style="' . $imgstyle . '" alt="' . $thecaption . '" />';
			// If no thumbnail, show link instead
			if ( 'none' == $thumbnails && $idx == $start_idx ) {
					$gallery_code .= '<span class="fg_title_link">' . $title . '</span>';
			}
			// Close link
			$gallery_code .= '</a>';
			// Display caption
			if ( $engine == 'documentlist' ) {
				$gallery_code .= '<div class="fg_caption">';
				$gallery_code .= '<a title="' . $thecaption . '" href="' . home_url( '/' . $folder . '/' . $pictures[ $idx ] ) . '" target="_blank">';
				$gallery_code .= $thecaption . '</a></div>';
				$gallery_code .= '<div class="fg_fileinfo">' . strtoupper( pathinfo( $pictures[ $idx ], PATHINFO_EXTENSION ) ) . ' - ' ;
				$gallery_code .= $this->file_size( filesize( $folder . '/' . $pictures[ $idx ] ) ) . ' - ' ;
				$moddate = filemtime( $folder . '/' . $pictures[ $idx ] ) + get_option( 'gmt_offset' ) * 3600;
				$gallery_code .= date_i18n( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ) , $moddate);					
				$gallery_code .= '</div>';
			} else {			
				if ( $show_thumbnail_captions && 'all' == $thumbnails ) $gallery_code .= '<div class="fg_caption">' . $thecaption . '</div>';	
				// Display title
				if ( 'single' == $thumbnails && $idx == $start_idx && $title != '' ) {
					$gallery_code .= '<div class="fg_title">' . $title . '</div>';
				}
			}
			$gallery_code .= '</div>';

			if ( $columns > 0 && $idx < $NoP-1 ) {
				if ( ( $idx + 1 - $start_idx) % $columns == 0 ) $gallery_code .= "\n" . '<br style="clear: both" />';
			}
		}
		if ( 'all' == $thumbnails ) {
			$gallery_code .= '<br style="clear: both" />';
		}
		$gallery_code .= "\n</div>\n";

		return $gallery_code;
	}

	/* --------- Folder Gallery Settings --------- */

	function fg_menu() {
		add_options_page( 'Folder Gallery Settings', 'Folder Gallery', 'manage_options', 'folder-gallery', array( $this, 'fg_settings' ) );
	}

	function fg_settings_init() {
		register_setting( 'FolderGallery', 'FolderGallery', array( $this, 'fg_settings_validate' ) );
		$fg_options = get_option( 'FolderGallery' );
		if ( 'photoswipe' == $fg_options['engine'] ) {
			if ( ! is_plugin_active('photoswipe/photoswipe.php') ) {
				$fg_options['engine'] = 'none';
				update_option( 'FolderGallery', $fg_options );
			}
		}
		if ( 'responsive-lightbox' == $fg_options['engine'] ) {
			if ( ! is_plugin_active('responsive-lightbox/responsive-lightbox.php') ) {
				$fg_options['engine'] = 'none';
				update_option( 'FolderGallery', $fg_options );
			}
		}
		if ( 'easy-fancybox' == $fg_options['engine'] ) {
			if ( ! is_plugin_active('easy-fancybox/easy-fancybox.php') ) {
				$fg_options['engine'] = 'none';
				update_option( 'FolderGallery', $fg_options );
			}
		}
		if ( 'slenderbox-plugin' == $fg_options['engine'] ) {
			if ( ! is_plugin_active('slenderbox/slenderbox.php') ) {
				$fg_options['engine'] = 'none';
				update_option( 'FolderGallery', $fg_options );
			}
		}
	}

	function fg_plugin_action_links( $links ) { 
 		// Add a link to this plugin's settings page
 		$settings_link = '<a href="' . admin_url( 'options-general.php?page=folder-gallery' ) . '">' . __('Settings') . '</a>';
 		array_unshift( $links, $settings_link ); 
 		return $links; 
	}

	function fg_settings_validate( $input ) {
		$input['columns']    = intval( $input['columns'] );
		$input['thumbnails_width']  = intval( $input['thumbnails_width'] );
			if ( 0 == $input['thumbnails_width'] ) $input['thumbnails_width'] = 150;
		$input['thumbnails_height'] = intval( $input['thumbnails_height'] );
		$input['border']            = intval( $input['border'] );
		$input['padding']           = intval( $input['padding'] );
		$input['margin']            = intval( $input['margin'] );
		if ( ! in_array( $input['sort'], array( 'filename','filename_desc','date','date_desc','random' ) ) ) $input['sort'] = 'filename';
		if ( ! in_array( $input['thumbnails'], array( 'all','none','single' ) ) ) $input['thumbnails'] = 'all';
		if ( ! in_array( $input['fb_title'], array( 'inside','outside','float','over','null' ) ) ) $input['fb_title'] = 'all';
		if ( ! in_array( $input['fb_effect'], array( 'elastic','fade' ) ) ) $input['fb_effect'] = 'elastic';
		if ( ! in_array( $input['caption'], array( 'default','none','filename','filenamewithoutextension','smartfilename','modificationdater','modificationdatec','modificationdate','modificationdateandtime','iptctitle','iptccaption' ) ) ) $input['caption'] = 'default';
		$input['show_thumbnail_captions']     = intval( $input['show_thumbnail_captions'] );
		$input['fb_speed']          = intval( $input['fb_speed'] );
		$input['gridlayout']          = intval( $input['gridlayout'] );
		$input['permissions']          = intval( $input['permissions'] );
		$input['orientation']          = intval( $input['orientation'] );
		if (! is_dir(get_home_path() . $input['cachedir']) ) $input['cachedir'] = '';
		return $input;
	}

	function fg_settings_default() {
		$defaults = array(
			'engine'			=> 'none',
			'cachedir'          => '',
			'sort'				=> 'filename',
			'border' 			=> 1,
			'padding' 			=> 2,
			'margin' 			=> 5,
			'columns' 	        => 0,
			'thumbnails_width' 	=> 160,
			'thumbnails_height' => 0,
			'lw_options'        => '',
			'thumbnails'		=> 'all',
			'caption'			=> 'default',
			'show_thumbnail_captions'		=> 0,
			'fb_title'			=> 'float',
			'fb_effect'			=> 'elastic',
			'fb_speed'			=> 0,
			'permissions'		=> 0,
			'orientation'		=> 0,
			'filetypes'			=> 'doc docx xls xlsx ppt pptx pdf',
			'gridlayout'		=> 0
		);
		return $defaults;
	}

	function fg_option_field( $field, $label, $extra = 'px' ) {
		$fg_options = get_option( 'FolderGallery' );
		echo '<tr valign="top">' . "\n";
		echo '<th scope="row"><label for="' . $field . '">' . $label . "</label></th>\n";
		echo '<td><input id="' . $field . '" name="FolderGallery[' . $field . ']" type="text" value="' . $fg_options["$field"] . '" class="small-text"> ' . $extra . "</td>\n";
		echo "</tr>\n";
	}

	function fg_settings()
	{
		$fg_options = get_option( 'FolderGallery' );
		echo '<div class="wrap">' . "\n";
		screen_icon();
		echo '<h2>' . __( 'Folder Gallery Settings', 'foldergallery' ) . "</h2>\n";
		echo '<form method="post" action="options.php">' . "\n";
		settings_fields( 'FolderGallery' );
		echo "\n" . '<table class="form-table"><tbody>' . "\n";
		
		echo '<tr valign="top">' . "\n";
		echo '<th scope="row"><label for="engine">' . __( 'Gallery Engine', 'foldergallery' ) . '</label></th>' . "\n";
		echo '<td><select name="FolderGallery[engine]" id="FolderGallery[engine]">' . "\n";	
			if ( is_dir( WP_CONTENT_DIR . '/lightbox' ) ) {
				echo "\t" .	'<option value="lightbox2"';
				if ( 'lightbox2' == $fg_options['engine'] ) echo ' selected="selected"';
				echo '>Lightbox 2</option>' . "\n";	
			}		
			if ( is_dir( WP_CONTENT_DIR . '/fancybox' ) ) {
				echo "\t" .	'<option value="fancybox2"';
				if ( 'fancybox2' == $fg_options['engine'] ) echo ' selected="selected"';
				echo '>Fancybox 2</option>' . "\n";
			}
			if ( is_dir( WP_CONTENT_DIR . '/lightview' ) ) {
				echo "\t" .	'<option value="lightview"';
				if ( 'lightview' == $fg_options['engine'] ) echo ' selected="selected"';
				echo '>Lightview 3</option>' . "\n";
			}
			if ( is_plugin_active('easy-fancybox/easy-fancybox.php') ) {
				echo "\t" .	'<option value="easy-fancybox"';
				if ( 'easy-fancybox' == $fg_options['engine'] ) echo ' selected="selected"';
				echo '>Easy Fancybox (Plugin)</option>' . "\n";			
			}
			if ( is_plugin_active('responsive-lightbox/responsive-lightbox.php') ) {
				echo "\t" .	'<option value="responsive-lightbox"';
				if ( 'responsive-lightbox' == $fg_options['engine'] ) echo ' selected="selected"';
				echo '>Responsive Lightbox (Plugin)</option>' . "\n";			
			}
			if ( is_plugin_active('photoswipe/photoswipe.php') ) {
				echo "\t" .	'<option value="photoswipe"';
				if ( 'photoswipe' == $fg_options['engine'] ) echo ' selected="selected"';
				echo '>Photo Swipe (Plugin)</option>' . "\n";			
			}	
			if ( is_plugin_active('slenderbox/slenderbox.php') ) {
				echo "\t" .	'<option value="slenderbox-plugin"';
				if ( 'slenderbox-plugin' == $fg_options['engine'] ) echo ' selected="selected"';
				echo '>Slenderbox (Plugin)</option>' . "\n";			
			}	
			echo "\t" .	'<option value="documentlist"';
				if ( 'documentlist' == $fg_options['engine'] ) echo ' selected="selected"';
			echo '>' . __( 'Document List', 'foldergallery') . '</option>' . "\n";
			echo "\t" .	'<option value="documentgallery"';
				if ( 'documentgallery' == $fg_options['engine'] ) echo ' selected="selected"';
			echo '>' . __( 'Document Gallery', 'foldergallery') . '</option>' . "\n";
			echo "\t" .	'<option value="none"';
				if ( 'none' == $fg_options['engine'] ) echo ' selected="selected"';
			echo '>' . __( 'None', 'foldergallery') . '</option>' . "\n";
		echo "</select>\n";
		echo "</td>\n</tr>\n";
		
		if ( 'documentgallery' == $fg_options['engine'] || 'documentlist' == $fg_options['engine'] ) {
			echo '<tr valign="top">' . "\n";
			echo '<th scope="row"><label for="filetypes">' . __( 'Filetypes', 'foldergallery' ) . '</label></th>' . "\n";
			echo '<td><input id="filetypes"  name="FolderGallery[filetypes]" class="regular-text" value="' . $fg_options['filetypes'] . '">' ;
			echo "</td>\n</tr>\n";
		} else {
			echo '<input type="hidden" name="FolderGallery[filetypes]" id="filetypes" value="' . $fg_options['filetypes'] . '" />' ;
		}
		
		echo "</tbody></table>\n";
		echo '<h3 class="title">' . __('Thumbnail Settings','foldergallery') . "</h3>\n";
		echo '<table class="form-table"><tbody>' . "\n";

		echo '<tr valign="top">' . "\n";
		echo '<th scope="row"><label for="thumbnails">' . __( 'Display Thumbnails', 'foldergallery' ) . '</label></th>' . "\n";
		echo '<td><select name="FolderGallery[thumbnails]" id="FolderGallery[thumbnails]">' . "\n";	
			echo "\t" .	'<option value="all"';
				if ( 'all' == $fg_options['thumbnails'] ) echo ' selected="selected"';
				echo '>' . __( 'All', 'foldergallery' ) . '</option>' . "\n";		
			echo "\t" .	'<option value="single"';
				if ( 'single' == $fg_options['thumbnails'] ) echo ' selected="selected"';
				echo '>' . __( 'Single', 'foldergallery' ) . '</option>' . "\n";
			echo "\t" .	'<option value="none"';
				if ( 'none' == $fg_options['thumbnails'] ) echo ' selected="selected"';
				echo '>' . __( 'None', 'foldergallery' ) . '</option>' . "\n";
		echo "</select>\n";
		echo "</td>\n</tr>\n";

		echo '<tr valign="top">' . "\n";
		echo '<th scope="row"><label for="sort">' . __( 'Sort Pictures by', 'foldergallery' ) . '</label></th>' . "\n";
		echo '<td><select name="FolderGallery[sort]" id="FolderGallery[sort]">' . "\n";	
			echo "\t" .	'<option value="filename"';
				if ( 'filename' == $fg_options['sort'] ) echo ' selected="selected"';
				echo '>' . __( 'Filename', 'foldergallery' ) . '</option>' . "\n";		
			echo "\t" .	'<option value="filename_desc"';
				if ( 'filename_desc' == $fg_options['sort'] ) echo ' selected="selected"';
				echo '>' . __( 'Filename (descending)', 'foldergallery' ) . '</option>' . "\n";
			echo "\t" .	'<option value="date"';
				if ( 'date' == $fg_options['sort'] ) echo ' selected="selected"';
				echo '>' . __( 'Date', 'foldergallery' ) . '</option>' . "\n";		
			echo "\t" .	'<option value="date_desc"';
				if ( 'date_desc' == $fg_options['sort'] ) echo ' selected="selected"';
				echo '>' . __( 'Date (descending)', 'foldergallery' ) . '</option>' . "\n";
			echo "\t" .	'<option value="random"';
				if ( 'random' == $fg_options['sort'] ) echo ' selected="selected"';
				echo '>' . __( 'Random', 'foldergallery' ) . '</option>' . "\n";
		echo "</select>\n";
		echo "</td>\n</tr>\n";

		$this->fg_option_field( 'columns', __( 'Columns', 'foldergallery' ), __( '(0 = auto)', 'foldergallery' ) );
		$this->fg_option_field( 'thumbnails_width', __( 'Thumbnails Width', 'foldergallery' ) );
		$this->fg_option_field( 'thumbnails_height', __( 'Thumbnails Height', 'foldergallery' ), ' px ' . __( '(0 = auto)', 'foldergallery' ) );
		$this->fg_option_field( 'border', __( 'Picture Border', 'foldergallery' ) );
		$this->fg_option_field( 'padding', __( 'Padding', 'foldergallery' ) );
		$this->fg_option_field( 'margin', __( 'Margin', 'foldergallery' ) );

		// show_thumbnail_captions
		echo '<tr valign="top">' . "\n";
		echo '<th scope="row"><label for="show_thumbnail_captions">' . __( 'Show Thumbnail Captions', 'foldergallery' ) . '</label></th>' . "\n";
		echo '<td><select name="FolderGallery[show_thumbnail_captions]" id="FolderGallery[show_thumbnail_captions]">' . "\n";		
			echo "\t" .	'<option value="0"';
				if ( '0' == $fg_options['show_thumbnail_captions'] ) echo ' selected="selected"';
				echo '>'. __('No', 'foldergallery') . '</option>' . "\n";
			echo "\t" .	'<option value="1"';
				if ( '1' == $fg_options['show_thumbnail_captions'] ) echo ' selected="selected"';
				echo '>' . __('Yes', 'foldergallery') . '</option>' . "\n";
		echo "</select>\n";
		echo "</td>\n</tr>\n";

		echo "</tbody></table>\n";
		echo '<h3 class="title">' . __('Lightbox Settings','foldergallery') . "</h3>\n";
		echo '<table class="form-table"><tbody>' . "\n";

		// Caption
		echo '<tr valign="top">' . "\n";
		echo '<th scope="row"><label for="caption">' . __( 'Caption Format', 'foldergallery' ) . '</label></th>' . "\n";
		echo '<td><select name="FolderGallery[caption]" id="FolderGallery[caption]">' . "\n";		
			echo "\t" .	'<option value="default"';
				if ( 'default' == $fg_options['caption'] ) echo ' selected="selected"';
				echo '>'. __('Default (Title + Picture Number)', 'foldergallery') . '</option>' . "\n";
			echo "\t" .	'<option value="filename"';
				if ( 'filename' == $fg_options['caption'] ) echo ' selected="selected"';
				echo '>' . __('Filename', 'foldergallery') . '</option>' . "\n";
			echo "\t" .	'<option value="filenamewithoutextension"';
				if ( 'filenamewithoutextension' == $fg_options['caption'] ) echo ' selected="selected"';
				echo '>' . __('Filename without extension', 'foldergallery') . '</option>' . "\n";	
			echo "\t" .	'<option value="smartfilename"';
				if ( 'smartfilename' == $fg_options['caption'] ) echo ' selected="selected"';
				echo '>' . __('Smart Filename', 'foldergallery') . '</option>' . "\n";	
			echo "\t" .	'<option value="modificationdate"';
				if ( 'modificationdate' == $fg_options['caption'] ) echo ' selected="selected"';
				echo '>' . __('Modification date', 'foldergallery') . '</option>' . "\n";				
			echo "\t" .	'<option value="modificationdateandtime"';
				if ( 'modificationdateandtime' == $fg_options['caption'] ) echo ' selected="selected"';
				echo '>' . __('Modification date and time', 'foldergallery') . '</option>' . "\n";
			echo "\t" .	'<option value="modificationdater"';
				if ( 'modificationdater' == $fg_options['caption'] ) echo ' selected="selected"';
				echo '>' . __('Modification date (RFC 2822)', 'foldergallery') . '</option>' . "\n";	
			echo "\t" .	'<option value="modificationdatec"';
				if ( 'modificationdatec' == $fg_options['caption'] ) echo ' selected="selected"';
				echo '>' . __('Modification date (ISO 8601)', 'foldergallery') . '</option>' . "\n";	
			echo "\t" .	'<option value="iptctitle"';
				if ( 'iptctitle' == $fg_options['caption'] ) echo ' selected="selected"';
				echo '>' . __('IPTC Title Metatag', 'foldergallery') . '</option>' . "\n";	
			echo "\t" .	'<option value="iptccaption"';
				if ( 'iptccaption' == $fg_options['caption'] ) echo ' selected="selected"';
				echo '>' . __('IPTC Caption Metatag', 'foldergallery') . '</option>' . "\n";	
			echo "\t" .	'<option value="none"';
				if ( 'none' == $fg_options['caption'] ) echo ' selected="selected"';
			echo '>' . __( 'None', 'foldergallery') . '</option>' . "\n";
		echo "</select>\n";
		echo "</td>\n</tr>\n";


		// Lightview		
		if ( 'lightview' == $fg_options['engine'] ) {			
			echo '<tr valign="top">' . "\n";
			echo '<th scope="row"><label for="lw_options">' . __( 'Lightview Options', 'foldergallery' ) . '</label></th>' . "\n";
			echo '<td><textarea id="lw_options" rows="5" cols="50" name="FolderGallery[lw_options]" class="large-text code">' . $fg_options['lw_options'] . "</textarea>\n";
			echo '<p class="description">' . __( 'Lightview default options, comma-separated.', 'foldergallery' );
			echo " E.g., <code>controls: { slider: false }, skin: 'mac'</code>. ";
			echo __( 'For details, see:', 'foldergallery' );
			echo ' <a href="http://projects.nickstakenburg.com/lightview/documentation/options" target="_blank">http://projects.nickstakenburg.com/lightview</a>.</p>' . "\n";
			echo "</td>\n";
			echo "</tr>\n";
		} else {
			echo '<input type="hidden" name="FolderGallery[lw_options]" id="lw_options" value="' . $fg_options['lw_options'] . '" />';
		}		
		// Fancybox 2 options
		if ( 'fancybox2' == $fg_options['engine'] ) {
			echo '<tr valign="top">' . "\n";
			echo '<th scope="row"><label for="fb_title">' . __( 'Fancybox Caption Style', 'foldergallery' ) . '</label></th>' . "\n";
			echo '<td><select name="FolderGallery[fb_title]" id="FolderGallery[fb_title]">' . "\n";		
				echo "\t" .	'<option value="inside"';
					if ( 'inside' == $fg_options['fb_title'] ) echo ' selected="selected"';
					echo '>' . __( 'Inside', 'foldergallery' ) . '</option>' . "\n";	
				echo "\t" .	'<option value="outside"';
					if ( 'outside' == $fg_options['fb_title'] ) echo ' selected="selected"';
					echo '>' . __( 'Outside', 'foldergallery' ) . '</option>' . "\n";			
				echo "\t" .	'<option value="over"';
					if ( 'over' == $fg_options['fb_title'] ) echo ' selected="selected"';
					echo '>' . __( 'Over', 'foldergallery' ) . '</option>' . "\n";			
				echo "\t" .	'<option value="float"';
					if ( 'float' == $fg_options['fb_title'] ) echo ' selected="selected"';
					echo '>' . __( 'Float', 'foldergallery' ) . '</option>' . "\n";
				echo "\t" .	'<option value="null"';
					if ( 'null' == $fg_options['fb_title'] ) echo ' selected="selected"';
					echo '>' . __( 'None', 'foldergallery' ) . '</option>' . "\n";
			echo "</select>\n";
			echo "</td>\n</tr>\n";
			
			echo '<tr valign="top">' . "\n";
			echo '<th scope="row"><label for="fb_effect">' . __( 'Fancybox Transition', 'foldergallery' ) . '</label></th>' . "\n";
			echo '<td><select name="FolderGallery[fb_effect]" id="FolderGallery[fb_effect]">' . "\n";		
				echo "\t" .	'<option value="elastic"';
					if ( 'elastic' == $fg_options['fb_effect'] ) echo ' selected="selected"';
					echo '>' . 'Elastic' . '</option>' . "\n";	
				echo "\t" .	'<option value="fade"';
					if ( 'fade' == $fg_options['fb_effect'] ) echo ' selected="selected"';
					echo '>' . 'Fade' . '</option>' . "\n";			
			echo "</select>\n";
			echo "</td>\n</tr>\n";
			
			$this->fg_option_field( 'fb_speed', __( 'Autoplay Speed', 'foldergallery' ), ' seconds ' . __( '(0 = off)', 'foldergallery' ) );
			
		} else {
			echo '<input type="hidden" name="FolderGallery[fb_title]" id="fb_title" value="' . $fg_options['fb_title'] . '" />';
			echo '<input type="hidden" name="FolderGallery[fb_speed]" id="fb_speed" value="' . $fg_options['fb_speed'] . '" />';
		}
		// Misc Settings
		echo "</tbody></table>\n";
		echo '<h3 class="title">' . __('Misc Settings','foldergallery') . "</h3>\n";
		echo '<table class="form-table"><tbody>' . "\n";
		echo '<tr valign="top"><th scope="row"><label for="cachedir">Cache Directory</label></th><td><input id="cachedir" name="FolderGallery[cachedir]" type="text" value="' . $fg_options["cachedir"] . '" size="60"></td></tr>';

		echo '<tr><th>' . __('Layout', 'foldergallery')  . '</th><td><label for="gridlayout"><input name="FolderGallery[gridlayout]" type="checkbox" id="gridlayout" value="1"';
		if ( 1 == $fg_options['gridlayout'] ) {
			echo ' checked="checked">';
		} else {
			echo '>';
		}
		echo __('Use Grid Layout (required Masonry Plugin)','foldergallery') . '</label></td></tr>';
		
		echo '<tr><th>' . __('Permissions', 'foldergallery')  . '</th><td><label for="permissions"><input name="FolderGallery[permissions]" type="checkbox" id="permissions" value="1"';
		if ( 1 == $fg_options['permissions'] ) {
			echo ' checked="checked">';
		} else {
			echo '>';
		}
		echo __('Force 777 permissions on cache folders','foldergallery') . '</label></td></tr>';

		if ( function_exists( 'exif_read_data' ) ) {
			echo '<tr><th>' . __('Orientation', 'foldergallery')  . '</th><td><label for="orientation"><input name="FolderGallery[orientation]" type="checkbox" id="orientation" value="1"';
			if ( 1 == $fg_options['orientation'] ) {
				echo ' checked="checked">';
			} else {
				echo '>';
			}
			echo __('Correct picture orientation according to EXIF tag. (Pictures will be overwritten.)','foldergallery') . '</label></td></tr>';
		}

		echo "</tbody></table>\n";
		submit_button();
		echo "</form></div>\n";
	}
		
} //End Of Class

?>
