<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

/**
* Name: Thumdb
*
* Author: Victor Michnowicz
*
* Location: http://www.vmichnowicz.com/projects/thumdb/
*
* Created: 2010.09.23
*
* Description: Store images and thumbnails in a MySQL database
*
* Requirements: PHP5 or above
*
*/

class Thumdb {
	
	private $CI;
	public $orig, $thumb;
	
	/**
	 * Constructor
	 *
	 * Set CI super object, loads database library, and then load config file
	 *
	 */
	function __construct()
	{
		// CI super object
		$this->CI =& get_instance();

		// Load database library
		$this->CI->load->database();
		
		// Load thumdb model
		$this->CI->load->model('thumdb_model');
		
		// Load config file
		$this->CI->config->load('thumdb');
		
		// URL helper
		$this->CI->load->helper('url');
	}
	
	/**
	 * __call
	 *
	 * Acts as a simple way to call model methods without loads of stupid alias'
	 *
	 **/
	public function __call($method, $arguments)
	{
		if (!method_exists( $this->CI->thumdb_model, $method) )
		{
			throw new Exception('Undefined method Thumdb::' . $method . '() called');
		}

		return call_user_func_array( array($this->CI->thumdb_model, $method), $arguments);
	}
	
	/**
	 * Get the ID of an image by its slug
	 *
	 * @param string	Original image slug
	 *
	 * @return		mixed
	 */
	function id_from_slug($slug)
	{	
		$this->CI->db
			->select('id')
			->from('images')
			->where('slug', $slug)
			->limit(1);
			
		$query = $this->CI->db->get();
		
		if ($query->num_rows() > 0)
		{
			$row = $query->row();
			
			return $row->id;
		}
		else
		{
			return FALSE;
		}
	}
	
	/**
	 * Show either a thumbnail or original image image (called by either "orig_show()" or "thumb_show()")
	 *
	 * @param string	Image type (original or thumbnail)
	 * @param int		ID of original image
	 * @param string	Thumbnail type (jpg, png, gif)
	 * @param int		Thumbnail width
	 * @param int		Thumbnail height
	 *
	 * @return		bool
	 */
	function _show($case, $id = NULL, $type = NULL, $width = NULL, $height = NULL, $expire = NULL)
	{
		
		// Set expire header
		if ($expire)
		{
			header('Expires: ' . date('r', $expire));
		}
		else
		{
			header('Expires: Fri, 1 Jan 2100 00:00:00 GMT');
		}
		
		// Set image type in header
		switch($type)
		{
			case 'jpg':
				header('Content-Type: image/jpg');
				break;
			case 'gif':
				header('Content-Type: image/gif');
				break;
			case 'png':
				header('Content-Type: image/png');
				break;
		}
		
		// Output the image
		switch($case)
		{
			case 'orig':
				echo $this->orig['image'];
				break;

			case 'thumb':
				echo $this->thumb['image'];
				break;
		}
	}
	
	/**
	 * Show an original image
	 *
	 * @param int		ID of original image
	 *
	 * @return		bool
	 */
	function orig_show($id)
	{
		$this->orig_exist($id);
		
		// Show original image
		$this->_show('orig', $id, $this->orig['type']);
	}
	
	/**
	 * Show a thumbnail image
	 *
	 * @param int		ID of original image
	 * @param string	Thumbnail type (jpg, png, gif)
	 * @param int		Thumbnail width
	 * @param int		Thumbnail height
	 *
	 * @return		bool
	 */
	function thumb_show($id, $type, $width, $height, $expire = NULL)
	{
		// Remove all expired thumbnails
		$this->garbage_collect();
		
		// Create a new thumbnail (will automagically use one that has already been created)
		$this->thumb_create($id, $width, $height, $type, $expire);
		
		// Show thumbnail image
		$this->_show('thumb', $id, $type, $width, $height, $this->thumb['expire']);
	}
	
	/**
	 * Check to see if an original image exists
	 *
	 * @param int		ID of original image
	 *
	 * @return		bool
	 */
	function orig_exist($id)
	{
		$this->CI->db
			->select('id, alt, type, width, height, image')
			->from('images')
			->where('id', $id)
			->limit(1);
			
		$query = $this->CI->db->get();
		
		if ($query->num_rows() > 0)
		{
			$row = $query->row();
			
			// Set original image data
			$this->orig = array(
				'id'				=> $row->id,
				'alt'			=> $row->alt,
				'type'			=> $row->type,
				'width'			=> $row->width,
				'height'			=> $row->height,
				'image'			=> $row->image
			);
			
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
	/**
	 * Create an image thumbnail
	 *
	 * @param int		ID of original image
	 * @param string	Thumbnail type (jpg, png, gif)
	 * @param int		Thumbnail width
	 * @param int		Thumbnail height
	 *
	 * @return		bool
	 */
	function thumb_exist($id, $type, $width, $height, $expire = NULL)
	{
		$this->CI->db
			->select('id, parent, type, expire, width, height, image')
			->from('image_thumbs')
			->where('parent', $id)
			->where('type', $type)
			->where('width', $width)
			->where('height', $height)
			->limit(1);
			
		$query = $this->CI->db->get();
		
		// If this thumbnail image exists
		if ($query->num_rows() > 0)
		{
			$row = $query->row();
			
			// Set thumbnail image data
			$this->thumb = array(
				'id'				=> $row->id,
				'parent'			=> $row->parent,
				'expire'			=> $row->expire,
				'type'			=> $row->type,
				'width'			=> $row->width,
				'height'			=> $row->height,
				'image'			=> $row->image
			);
			
			// If the user provided an expire value
			if ($expire)
			{
				// New expire date
				$expire = time() + $expire;

				$data = array(
					'expire' => $expire
				);
				
				// Update expire date on the database
				$this->CI->db
					->where('id', $row->id)
					->update('image_thumbs', $data);
			}
			
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
	/**
	 * Destroy (delete) an original image
	 *
	 * @param	string	Image slug
	 *
	 * @return		bool
	 */
	orig_destroy($slug)
	{
	
	}
	
	/**
	 * Upload an original image to the database
	 *
	 * @return		bool
	 */
	function orig_create()
	{
		$img_data = getimagesize($_FILES['file']['tmp_name']);
			
		if ($img_data)
		{
			switch ($img_data['mime'])
			{
				case 'image/jpeg':
					$type = 'jpg';
					break;
				case 'image/png':
					$type = 'png';
					break;
				case 'image/gif':
					$type = 'gif';
					break;
				default:
					return FALSE;
			}
		
			$data = array(
				'slug'	=> url_title( $this->CI->input->post('slug') ),
				'alt'	=> $this->CI->input->post('alt'),
				'width'	=> $img_data[0],
				'height'	=> $img_data[1],
				'type'	=> $type,
				'created'	=> time(),
				'image'	=> file_get_contents($_FILES['file']['tmp_name'])
			);
		
			$this->CI->db->insert('images', $data);
			
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * Create an image thumbnail
	 *
	 * @link			http://www.php.net/manual/en/function.imagecopyresampled.php#94251
	 *
	 * @param int		ID of original image
	 * @param int		Thumbnail width
	 * @param int		Thumbnail height
	 * @param string	Thumbnail type (jpg, png, gif)
	 * @param int		Length of time (in seconds) until this thumbnail expires
	 *
	 * @return		void
	 */
	function thumb_create($id, $thumb_width = 150, $thumb_height = 150, $type = 'jpg', $expire = NULL)
	{
		// If an image with this ID exists
		if ( $this->orig_exist($id) )
		{
			
			// If this thumbnail does not yet exist
			if ( ! $this->thumb_exist($id, $type, $thumb_width, $thumb_height, $expire) )
			{
				$img_orig = imagecreatefromstring($this->orig['image']);
				$width_orig = $this->orig['width'];
				$height_orig = $this->orig['height'];
		
				$ratio_orig = $width_orig / $height_orig;

				if ($thumb_width / $thumb_height > $ratio_orig)
				{
					$new_height = $thumb_width / $ratio_orig;
					$new_width = $thumb_width;
				}
			
				else
				{
					$new_width = $thumb_height * $ratio_orig;
					$new_height = $thumb_height;
				}

				$x_mid = $new_width / 2;
				$y_mid = $new_height / 2;

				$process = imagecreatetruecolor(round($new_width), round($new_height)); 
				imagealphablending($process, false);
	    			imagesavealpha($process, true);
				
				imagecopyresampled($process, $img_orig, 0, 0, 0, 0, $new_width, $new_height, $width_orig, $height_orig);
				$img_thumb = imagecreatetruecolor($thumb_width, $thumb_height);
				
	    			imagealphablending($img_thumb, false);
	    			imagesavealpha($img_thumb, true);
	    			
				imagecopyresampled($img_thumb, $process, 0, 0, ( $x_mid - ($thumb_width / 2) ), ( $y_mid - ($thumb_height / 2) ), $thumb_width, $thumb_height, $thumb_width, $thumb_height);

				imagedestroy($process);
				imagedestroy($img_orig);
		
				ob_start();
		
				switch ($type)
				{
					case 'jpg':
						// Get jpg quality from config file
						imagejpeg($img_thumb, NULL, $this->CI->config->item('jpg_quality'));
						break;
					case 'gif':
						imagegif($img_thumb);
						break;
					case 'png':
						imagepng($img_thumb);
						break;
				}
		
				$image = ob_get_clean(); 
			
				// If the user set this thumbnail to expire
				if ($expire)
				{
					$expire = time() + $expire;
				}
			
				$data = array(
					'parent'	=> $id,
					'type'	=> $type,
					'expire'	=> $expire,
					'width'	=> $thumb_width,
					'height'	=> $thumb_height,
					'image'	=> $image
				);
			
				// Insert thumbnail into the database
				$this->CI->db->insert('image_thumbs', $data);
			
				// Set thumbnail image data
				$this->thumb = array(
					'id'				=> $this->CI->db->insert_id(),
					'parent'			=> $id,
					'expire'			=> $expire,
					'type'			=> $type,
					'width'			=> $thumb_width,
					'height'			=> $thumb_height,
					'image'			=> $image
				);
			}
		}
	}
	
	/**
	 * Remove all expired thumbnails
	 *
	 * @return		void
	 */
	function garbage_collect()
	{
		$this->CI->db
			->from('image_thumbs')
			->where('expire < ', time());
			
		$query = $this->CI->db->delete();
	}
	
}

/* End of file Thumdb.php */
/* Location: ./application/libraries/Thumdb.php */
