<?php

class Image extends MY_Controller {

	function Image()
	{
		parent::MY_Controller();
		
		// Images model!
		$this->load->model('Images_model');
		
		// Thumdb library!
		$this->load->library('thumdb');
	}
	
	/**
	 * Test this thang out
	 */
	function index()
	{
		echo '<html><head><title>Thumdb</title></head><body style="background: #efefef url(' . site_url() . '/image/orig/thumdb_icon_small); margin: 0px; border: 0px;"></body></html>';
	}
	
	/**
	 * Show an image thumbnail
	 *
	 * @param string	Image slug
	 *
	 */
	function thumb($slug)
	{
		$id = $this->thumdb->id_from_slug($slug);
		$type = 'png';
		$width = 755;
		$height = 150;
		$expire = 1; // Set to NULL for image to stay in DB foreva, foreva eva
		$this->thumdb->thumb_show($id, $type, $width, $height, $expire);
	}
	
	/**
	 * Show an original image
	 *
	 * @param string	Image slug
	 *
	 */
	function orig($slug)
	{
		$id = $this->thumdb->id_from_slug($slug);
		$this->thumdb->orig_show($id);
	}
	
	/**
	 * Upload a new image to the database
	 */
	function upload()
	{
		// If this page was POSTed to
		if ($_POST)
		{

			if ( $this->thumdb->orig_create() )
			{
				echo 'image uploaded successfully';
			}
			else
			{
				echo 'image upload failed';
			}
			
		}
		else
		{
			$this->load->view('upload');
		}
	}
	
}

/* End of file image.php */
/* Location: ./application/controllers/image.php */
