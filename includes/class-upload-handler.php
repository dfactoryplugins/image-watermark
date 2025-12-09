<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Image_Watermark_Upload_Handler {

	/**
	 * Plugin instance.
	 *
	 * @var Image_Watermark
	 */
	private $plugin;

	/**
	 * Tracks whether the current request originates from the admin.
	 *
	 * @var bool
	 */
	private $is_admin = true;

	/**
	 * Upload handler constructor.
	 *
	 * @param Image_Watermark $plugin
	 */
	public function __construct( Image_Watermark $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Handles uploads and registers metadata generation filter when needed.
	 *
	 * @param array $file
	 *
	 * @return array
	 */
	public function handle_upload_files( $file ) {
		if ( ! $this->plugin->get_extension() ) {
			return $file;
		}

		$script_filename = isset( $_SERVER['SCRIPT_FILENAME'] ) ? $_SERVER['SCRIPT_FILENAME'] : '';

		if ( wp_doing_ajax() ) {
			$ref = '';

			if ( ! empty( $_REQUEST['_wp_http_referer'] ) ) {
				$ref = wp_unslash( $_REQUEST['_wp_http_referer'] );
			} elseif ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
				$ref = wp_unslash( $_SERVER['HTTP_REFERER'] );
			}

			if ( ( strpos( $ref, admin_url() ) === false ) && ( basename( $script_filename ) === 'admin-ajax.php' ) ) {
				$this->is_admin = false;
			} else {
				$this->is_admin = true;
			}
		} else {
			$this->is_admin = is_admin();
		}

		$options = $this->plugin->options;
		$allowed_mime = $this->plugin->get_allowed_mime_types();

		if ( $this->is_admin === true ) {
			if ( $options['watermark_image']['plugin_off'] == 1 && wp_attachment_is_image( $options['watermark_image']['url'] ) && in_array( $file['type'], $allowed_mime, true ) ) {
				add_filter( 'wp_generate_attachment_metadata', [ $this, 'apply_watermark' ], 10, 2 );
			}
		} else {
			if ( $options['watermark_image']['frontend_active'] == 1 && wp_attachment_is_image( $options['watermark_image']['url'] ) && in_array( $file['type'], $allowed_mime, true ) ) {
				add_filter( 'wp_generate_attachment_metadata', [ $this, 'apply_watermark' ], 10, 2 );
			}
		}

		return $file;
	}

	/**
	 * Applies watermark to attachment sizes.
	 *
	 * @param array $data
	 * @param int|string $attachment_id
	 * @param string $method
	 *
	 * @return array
	 */
	public function apply_watermark( $data, $attachment_id, $method = '' ) {
		$attachment_id = (int) $attachment_id;
		$post = get_post( $attachment_id );
		$post_id = ( ! empty( $post ) ? (int) $post->post_parent : 0 );

		$options = apply_filters( 'iw_watermark_options', $this->plugin->options );

		if ( $attachment_id === (int) $options['watermark_image']['url'] ) {
			return [ 'error' => __( 'Watermark not applied because this is your selected watermark image.', 'image-watermark' ) ];
		}

		if ( $method !== 'manual' && ( $this->is_admin === true && ! ( ( isset( $options['watermark_cpt_on'][0] ) && $options['watermark_cpt_on'][0] === 'everywhere' ) || ( $post_id > 0 && in_array( get_post_type( $post_id ), array_keys( $options['watermark_cpt_on'] ), true ) === true ) ) ) ) {
			return $data;
		}

		if ( apply_filters( 'iw_watermark_display', $attachment_id ) === false ) {
			return $data;
		}

		$upload_dir = wp_upload_dir();
		$original_file = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $data['file'];

		if ( getimagesize( $original_file, $original_image_info ) !== false ) {
			$metadata = $this->get_image_metadata( $original_image_info );

			if ( (int) get_post_meta( $attachment_id, $this->plugin->get_watermarked_meta_key(), true ) === 1 ) {
				$this->remove_watermark( $data, $attachment_id, 'manual' );
			}

			if ( $options['backup']['backup_image'] ) {
				$this->do_backup( $data, $upload_dir, $attachment_id );
			}

			foreach ( $options['watermark_on'] as $image_size => $active_size ) {
				if ( $active_size === 1 ) {
					switch ( $image_size ) {
						case 'full':
							$filepath = $original_file;
							break;

						default:
							if ( ! empty( $data['sizes'] ) && array_key_exists( $image_size, $data['sizes'] ) ) {
								$filepath = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . dirname( $data['file'] ) . DIRECTORY_SEPARATOR . $data['sizes'][ $image_size ]['file'];
							} else {
								continue 2;
							}
				}

				do_action( 'iw_before_apply_watermark', $attachment_id, $image_size );

				$this->do_watermark( $attachment_id, $filepath, $image_size, $upload_dir, $metadata );

				$this->save_image_metadata( $metadata, $filepath );

				do_action( 'iw_after_apply_watermark', $attachment_id, $image_size );
				}
			}

			update_post_meta( $attachment_id, $this->plugin->get_watermarked_meta_key(), 1 );
		}

		return $data;
	}

	/**
	 * Removes a watermark from an image.
	 *
	 * @param array $data
	 * @param int|string $attachment_id
	 * @param string $method
	 *
	 * @return array|false
	 */
	public function remove_watermark( $data, $attachment_id, $method = '' ) {
		if ( $method !== 'manual' ) {
			return $data;
		}

		$upload_dir = wp_upload_dir();

		if ( getimagesize( $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $data['file'] ) !== false ) {
			$filepath = get_attached_file( $attachment_id );
			$backup_filepath = $this->get_image_backup_filepath( get_post_meta( $attachment_id, '_wp_attached_file', true ) );

			if ( file_exists( $backup_filepath ) ) {
				copy( $backup_filepath, $filepath );
			}

			$metadata = wp_generate_attachment_metadata( $attachment_id, $filepath );
			wp_update_attachment_metadata( $attachment_id, $metadata );
			update_post_meta( $attachment_id, $this->plugin->get_watermarked_meta_key(), 0 );

			return wp_get_attachment_metadata( $attachment_id );
		}

		return false;
	}

	/**
	 * Returns image metadata.
	 *
	 * @param array $imageinfo
	 *
	 * @return array
	 */
	private function get_image_metadata( $imageinfo ) {
		$metadata = [
			'exif' => null,
			'iptc' => null,
		];

		if ( is_array( $imageinfo ) ) {
			$exifdata = key_exists( 'APP1', $imageinfo ) ? $imageinfo['APP1'] : null;

			if ( $exifdata ) {
				$exiflength = strlen( $exifdata ) + 2;

				if ( $exiflength > 0xFFFF ) {
					return $metadata;
				}

				$metadata['exif'] = chr( 0xFF ) . chr( 0xE1 ) . chr( ( $exiflength >> 8 ) & 0xFF ) . chr( $exiflength & 0xFF ) . $exifdata;
			}

			$iptcdata = key_exists( 'APP13', $imageinfo ) ? $imageinfo['APP13'] : null;

			if ( $iptcdata ) {
				$iptclength = strlen( $iptcdata ) + 2;

				if ( $iptclength > 0xFFFF ) {
					return $metadata;
				}

				$metadata['iptc'] = chr( 0xFF ) . chr( 0xED ) . chr( ( $iptclength >> 8 ) & 0xFF ) . chr( $iptclength & 0xFF ) . $iptcdata;
			}
		}

		return $metadata;
	}

	/**
	 * Saves EXIF/IPTC metadata into the destination file.
	 *
	 * @param array $metadata
	 * @param string $file
	 *
	 * @return bool|int
	 */
	private function save_image_metadata( $metadata, $file ) {
		$mime = wp_check_filetype( $file );

		if ( file_exists( $file ) && $mime['type'] !== 'image/webp' && $mime['type'] !== 'image/png' ) {
			$exifdata = $metadata['exif'];
			$iptcdata = $metadata['iptc'];

			$destfilecontent = @file_get_contents( $file );

			if ( ! $destfilecontent ) {
				return false;
			}

			if ( strlen( $destfilecontent ) > 0 ) {
				$destfilecontent = substr( $destfilecontent, 2 );
				$portiontoadd = chr( 0xFF ) . chr( 0xD8 );
				$exifadded = ! $exifdata;
				$iptcadded = ! $iptcdata;

				while ( ( $this->get_safe_chunk( substr( $destfilecontent, 0, 2 ) ) & 0xFFF0 ) === 0xFFE0 ) {
					$segmentlen = ( $this->get_safe_chunk( substr( $destfilecontent, 2, 2 ) ) & 0xFFFF );
					$iptcsegmentnumber = ( $this->get_safe_chunk( substr( $destfilecontent, 1, 1 ) ) & 0x0F );

					if ( $segmentlen <= 2 ) {
						return false;
					}

					$thisexistingsegment = substr( $destfilecontent, 0, $segmentlen + 2 );

					if ( ( $iptcsegmentnumber >= 1 ) && ( ! $exifadded ) ) {
						$portiontoadd .= $exifdata;
						$exifadded = true;

						if ( $iptcsegmentnumber === 1 ) {
							$thisexistingsegment = '';
						}
					}

					if ( ( $iptcsegmentnumber >= 13 ) && ( ! $iptcadded ) ) {
						$portiontoadd .= $iptcdata;
						$iptcadded = true;

						if ( $iptcsegmentnumber === 13 ) {
							$thisexistingsegment = '';
						}
					}

					$portiontoadd .= $thisexistingsegment;
					$destfilecontent = substr( $destfilecontent, $segmentlen + 2 );
				}

				if ( ! $exifadded ) {
					$portiontoadd .= $exifdata;
				}

				if ( ! $iptcadded ) {
					$portiontoadd .= $iptcdata;
				}

				$outputfile = fopen( $file, 'w' );

				if ( $outputfile ) {
					return fwrite( $outputfile, $portiontoadd . $destfilecontent );
				}
			}
		}

		return false;
	}

	/**
	 * Helper to interpret binary segments safely.
	 *
	 * @param string|int $value
	 *
	 * @return int
	 */
	private function get_safe_chunk( $value ) {
		if ( is_numeric( $value ) ) {
			return (int) $value;
		}

		return 0;
	}

	/**
	 * Applies the watermark to a single image path.
	 *
	 * @param int $attachment_id
	 * @param string $image_path
	 * @param string $image_size
	 * @param array $upload_dir
	 * @param array $metadata
	 */
	private function do_watermark( $attachment_id, $image_path, $image_size, $upload_dir, $metadata = [] ) {
		$options = apply_filters( 'iw_watermark_options', $this->plugin->options );
		$mime = wp_check_filetype( $image_path );

		if ( ! wp_attachment_is_image( $options['watermark_image']['url'] ) ) {
			return;
		}

		$watermark_file = wp_get_attachment_metadata( $options['watermark_image']['url'], true );
		$watermark_path = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $watermark_file['file'];

		if ( $this->plugin->get_extension() === 'imagick' ) {
			$image = new Imagick( $image_path );
			$watermark = new Imagick( $watermark_path );

			if ( $watermark->getImageAlphaChannel() > 0 ) {
				$watermark->evaluateImage( Imagick::EVALUATE_MULTIPLY, round( (float) ( $options['watermark_image']['transparent'] / 100 ), 2 ), Imagick::CHANNEL_ALPHA );
			} else {
				$watermark->setImageOpacity( round( (float) ( $options['watermark_image']['transparent'] / 100 ), 2 ) );
			}

			if ( $mime['type'] === 'image/jpeg' ) {
				$image->setImageCompressionQuality( $options['watermark_image']['quality'] );
				$image->setImageCompression( imagick::COMPRESSION_JPEG );
			} else {
				$image->setImageCompressionQuality( $options['watermark_image']['quality'] );
			}

			if ( $options['watermark_image']['jpeg_format'] === 'progressive' ) {
				$image->setImageInterlaceScheme( Imagick::INTERLACE_PLANE );
			}

			$image_dim = $image->getImageGeometry();
			$watermark_dim = $watermark->getImageGeometry();

			list( $width, $height ) = $this->calculate_watermark_dimensions( $image_dim['width'], $image_dim['height'], $watermark_dim['width'], $watermark_dim['height'], $options );

			$watermark->resizeImage( $width, $height, imagick::FILTER_CATROM, 1 );

			list( $dest_x, $dest_y ) = $this->calculate_image_coordinates( $image_dim['width'], $image_dim['height'], $width, $height, $options );

			$image->compositeImage( $watermark, Imagick::COMPOSITE_DEFAULT, $dest_x, $dest_y, Imagick::CHANNEL_ALL );

			$image->writeImage( $image_path );
			$image->clear();
			$image->destroy();
			$image = null;
			$watermark->clear();
			$watermark->destroy();
			$watermark = null;
		} else {
			$image = $this->get_image_resource( $image_path, $mime['type'] );

			if ( $image !== false ) {
				$image = $this->add_watermark_image( $image, $options, $upload_dir );

				if ( $image !== false ) {
					$this->save_image_file( $image, $mime['type'], $image_path, $options['watermark_image']['quality'] );
					imagedestroy( $image );
					$image = null;
				}
			}
		}
	}

	/**
	 * Creates a backup of the original image.
	 *
	 * @param array $data
	 * @param array $upload_dir
	 * @param int $attachment_id
	 */
	private function do_backup( $data, $upload_dir, $attachment_id ) {
		$backup_filepath = $this->get_image_backup_filepath( $data['file'] );

		if ( file_exists( $backup_filepath ) ) {
			return;
		}

		$filepath = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $data['file'];
		$mime = wp_check_filetype( $filepath );
		$image = $this->get_image_resource( $filepath, $mime['type'] );

		if ( $image !== false ) {
			wp_mkdir_p( $this->get_image_backup_folder_location( $data['file'] ) );
			$path = pathinfo( $backup_filepath );
			wp_mkdir_p( $path['dirname'] );
			$this->save_image_file( $image, $mime['type'], $backup_filepath, $this->plugin->options['backup']['backup_quality'] );
			imagedestroy( $image );
			$image = null;
		}
	}

	/**
	 * Returns image resource based on mime type.
	 *
	 * @param string $filepath
	 * @param string $mime_type
	 *
	 * @return resource|false
	 */
	private function get_image_resource( $filepath, $mime_type ) {
		switch ( $mime_type ) {
			case 'image/jpeg':
			case 'image/pjpeg':
				$image = imagecreatefromjpeg( $filepath );
				break;

			case 'image/png':
				$image = imagecreatefrompng( $filepath );

				if ( is_resource( $image ) ) {
					imagefilledrectangle( $image, 0, 0, imagesx( $image ), imagesy( $image ), imagecolorallocatealpha( $image, 255, 255, 255, 127 ) );
				}

				break;

			case 'image/webp':
				$image = imagecreatefromwebp( $filepath );

				if ( is_resource( $image ) ) {
					imagefilledrectangle( $image, 0, 0, imagesx( $image ), imagesy( $image ), imagecolorallocatealpha( $image, 255, 255, 255, 127 ) );
				}

				break;

			default:
				$image = false;
		}

		if ( is_resource( $image ) ) {
			imagealphablending( $image, false );
			imagesavealpha( $image, true );
		}

		return $image;
	}

	/**
	 * Returns filename without directory structure.
	 *
	 * @param string $filepath
	 *
	 * @return string
	 */
	private function get_image_filename( $filepath ) {
		return basename( $filepath );
	}

	/**
	 * Returns backup folder path for an attachment.
	 *
	 * @param string $filepath
	 *
	 * @return string
	 */
	private function get_image_backup_folder_location( $filepath ) {
		$path = explode( DIRECTORY_SEPARATOR, $filepath );
		array_pop( $path );
		$path = implode( DIRECTORY_SEPARATOR, $path );

		return IMAGE_WATERMARK_BACKUP_DIR . DIRECTORY_SEPARATOR . $path;
	}

	/**
	 * Returns backup file path for an attachment.
	 *
	 * @param string $filepath
	 *
	 * @return string
	 */
	private function get_image_backup_filepath( $filepath ) {
		return IMAGE_WATERMARK_BACKUP_DIR . DIRECTORY_SEPARATOR . $filepath;
	}

	/**
	 * Adds watermark image using GD.
	 *
	 * @param resource $image
	 * @param array $options
	 * @param array $upload_dir
	 *
	 * @return bool|resource
	 */
	private function add_watermark_image( $image, $options, $upload_dir ) {
		if ( ! wp_attachment_is_image( $options['watermark_image']['url'] ) ) {
			return false;
		}

		$watermark_file = wp_get_attachment_metadata( $options['watermark_image']['url'], true );
		$url = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $watermark_file['file'];
		$watermark_file_info = getimagesize( $url );

		switch ( $watermark_file_info['mime'] ) {
			case 'image/jpeg':
			case 'image/pjpeg':
				$watermark = imagecreatefromjpeg( $url );
				break;

			case 'image/gif':
				$watermark = imagecreatefromgif( $url );
				break;

			case 'image/png':
				$watermark = imagecreatefrompng( $url );
				break;

			case 'image/webp':
				$watermark = imagecreatefromwebp( $url );
				break;

			default:
				return false;
		}

		$image_width = imagesx( $image );
		$image_height = imagesy( $image );

		list( $w, $h ) = $this->calculate_watermark_dimensions( $image_width, $image_height, imagesx( $watermark ), imagesy( $watermark ), $options );

		list( $dest_x, $dest_y ) = $this->calculate_image_coordinates( $image_width, $image_height, $w, $h, $options );

		$this->imagecopymerge_alpha( $image, $this->resize( $watermark, $w, $h, $watermark_file_info ), $dest_x, $dest_y, 0, 0, $w, $h, $options['watermark_image']['transparent'] );

		if ( $options['watermark_image']['jpeg_format'] === 'progressive' ) {
			imageinterlace( $image, true );
		}

		return $image;
	}

	/**
	 * Copies image with transparency when merging.
	 */
	private function imagecopymerge_alpha( $dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct ) {
		$cut = imagecreatetruecolor( $src_w, $src_h );
		imagecopy( $cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h );
		imagecopy( $cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h );
		imagecopymerge( $dst_im, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h, $pct );
	}

	/**
	 * Resizes a watermark resource.
	 */
	private function resize( $image, $width, $height, $info ) {
		$new_image = imagecreatetruecolor( $width, $height );

		if ( $info[2] === 3 ) {
			imagealphablending( $new_image, false );
			imagesavealpha( $new_image, true );
			imagefilledrectangle( $new_image, 0, 0, $width, $height, imagecolorallocatealpha( $new_image, 255, 255, 255, 127 ) );
		}

		imagecopyresampled( $new_image, $image, 0, 0, 0, 0, $width, $height, $info[0], $info[1] );

		return $new_image;
	}

	/**
	 * Writes an image resource to a file.
	 */
	private function save_image_file( $image, $mime_type, $filepath, $quality ) {
		switch ( $mime_type ) {
			case 'image/jpeg':
			case 'image/pjpeg':
				imagejpeg( $image, $filepath, $quality );
				break;

			case 'image/png':
				imagepng( $image, $filepath, (int) round( 9 - ( 9 * $quality / 100 ), 0 ) );
				break;

			case 'image/webp':
				imagewebp( $image, $filepath, $quality );
				break;
		}
	}

	/**
	 * Calculates watermark dimensions based on settings.
	 */
	private function calculate_watermark_dimensions( $image_width, $image_height, $watermark_width, $watermark_height, $options ) {
		if ( $options['watermark_image']['watermark_size_type'] === 1 ) {
			$width = $options['watermark_image']['absolute_width'];
			$height = $options['watermark_image']['absolute_height'];
		} elseif ( $options['watermark_image']['watermark_size_type'] === 2 ) {
			$ratio = $image_width * $options['watermark_image']['width'] / 100 / $watermark_width;
			$width = (int) ( $watermark_width * $ratio );
			$height = (int) ( $watermark_height * $ratio );

			if ( $height > $image_height ) {
				$width = (int) ( $image_height * $width / $height );
				$height = $image_height;
			}
		} else {
			$width = $watermark_width;
			$height = $watermark_height;
		}

		return [ $width, $height ];
	}

	/**
	 * Calculates watermark coordinates.
	 */
	private function calculate_image_coordinates( $image_width, $image_height, $watermark_width, $watermark_height, $options ) {
		switch ( $options['watermark_image']['position'] ) {
			case 'top_left':
				$dest_x = $dest_y = 0;
				break;

			case 'top_center':
				$dest_x = (int) round( ( $image_width / 2 ) - ( $watermark_width / 2 ), 0 );
				$dest_y = 0;
				break;

			case 'top_right':
				$dest_x = $image_width - $watermark_width;
				$dest_y = 0;
				break;

			case 'middle_left':
				$dest_x = 0;
				$dest_y = (int) round( ( $image_height / 2 ) - ( $watermark_height / 2 ), 0 );
				break;

			case 'middle_right':
				$dest_x = $image_width - $watermark_width;
				$dest_y = (int) round( ( $image_height / 2 ) - ( $watermark_height / 2 ), 0 );
				break;

			case 'bottom_left':
				$dest_x = 0;
				$dest_y = $image_height - $watermark_height;
				break;

			case 'bottom_center':
				$dest_x = (int) round( ( $image_width / 2 ) - ( $watermark_width / 2 ), 0 );
				$dest_y = $image_height - $watermark_height;
				break;

			case 'bottom_right':
				$dest_x = $image_width - $watermark_width;
				$dest_y = $image_height - $watermark_height;
				break;

			case 'middle_center':
			default:
				$dest_x = (int) round( ( $image_width / 2 ) - ( $watermark_width / 2 ), 0 );
				$dest_y = (int) round( ( $image_height / 2 ) - ( $watermark_height / 2 ), 0 );
		}

		if ( $options['watermark_image']['offset_unit'] === 'pixels' ) {
			$dest_x += $options['watermark_image']['offset_width'];
			$dest_y += $options['watermark_image']['offset_height'];
		} else {
			$dest_x += (int) round( $image_width * $options['watermark_image']['offset_width'] / 100, 0 );
			$dest_y += (int) round( $image_height * $options['watermark_image']['offset_height'] / 100, 0 );
		}

		return [ (int) $dest_x, (int) $dest_y ];
	}

	/**
	 * Removes stored backup when an attachment is deleted.
	 *
	 * @param int $attachment_id
	 *
	 * @return void
	 */
	public function delete_attachment( $attachment_id ) {
		$filepath = get_post_meta( $attachment_id, '_wp_attached_file', true );

		if ( ! $filepath ) {
			return;
		}

		$backup_filepath = $this->get_image_backup_filepath( $filepath );

		if ( $backup_filepath && file_exists( $backup_filepath ) ) {
			unlink( $backup_filepath );
		}
	}
}
