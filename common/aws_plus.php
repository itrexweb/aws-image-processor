<?php

/* Begin */

class AWS_Plus
{
	private $s3client;
	private $ec2client;
	private $dbclient;
	private $sqsclient;

	private $s3_folder;
	private $image_name;
	private $image_date;
	private $private_bucket;
	private $public_bucket;

	function __construct($aws, $bucket, $public_bucket)
	{
		$this->s3client = $aws->get('s3');
		$this->ec2client = $aws->get('ec2');
		$this->dbclient = $aws->get('SimpleDb');
		$this->sqsclient = $aws->get('Sqs');
		$this->private_bucket = $bucket;
		$this->public_bucket = $public_bucket;
	}

	public function get_queue_url($queue)
	{
		$result = $this->sqsclient->createQueue(array('QueueName' => $queue));
		$QueueUrl = $result->get('QueueUrl');
		return $QueueUrl;
	}

	public function get_image_from_protected_s3($what_to_get, $where_to_put)
	{
		try
		{
			$result = $this->s3client->getObject(array(
				'Bucket' => $this->private_bucket,
				'Key' => $what_to_get,
				'SaveAs' => $where_to_put,
				));
		}

		catch (Exception $e)
		{
			dump($e->getMessage());
			return false;
		}

		return true;
	}

	public function get_image_by_id($image_id)
	{
		$image_details = json_decode(urldecode($image_id));
		$this->s3_folder = $image_details->{'folder'};
		$this->image_name = $image_details->{'imagename'};
		$this->image_date = $image_details->{'imagedate'};

		//var_dump($this->s3_folder . '/' . $this->image_name);

		$result = $this->s3client->getObject(array(
				'Bucket' => $this->private_bucket,
				'Key' => $this->s3_folder . '/' . $this->image_name,
				'SaveAs' => PROCESSED_UPLOAD_DIR . $this->image_name
				));

		return true;
	}

	public function put_image_by_path($source, $destination)
	{
		try
		{
			$file_stream = fopen($source, 'r+');
			$result = $this->s3client->putObject(array(
				'Bucket'	 => $this->public_bucket,
				'Key'		=> $destination,
				'Body' 			=> $file_stream
			));
		}
		catch(Exception $e)
		{
			$aws_log_db->add(AWS_LOG_ERROR, 'S3 upload failed: ' . $e->getMessage());
			return false;
		}

		return true;
	}

	public function delete_message($queue, $handle)
	{
		dump($this->get_queue_url($queue));

		$result = $this->sqsclient->deleteMessage(array(
			// QueueUrl is required
			'QueueUrl' => $this->get_queue_url($queue),
			// ReceiptHandle is required
			'ReceiptHandle' => $handle,
		));
		dump($result, 107);
		return $result;
	}

	public function update_image_staus($image_id)
	{
		$status = $this->check_image_status($image_id);
		//echo (string)$status;
		if(!$status)
		{
			//echo 'h2';
			return false;
		}

		if($status == 'upload_complete' &&
			$status != 'processing' &&
			$status != 'processed'
		)
		{
			//echo 'h3';
			$result = $this->dbclient->putAttributes(array(
				'DomainName' => UPLOAD_DOMAIN,
				'ItemName'   => $image_id,
				'Attributes' => array(
					array('Name' => 'status', 'Value' => 'processing', 'Replace' => true),
				)
			));
			//var_dump($image_id);

			return $image_id;
		}
		else
		{
			return false;
		}
	}

	public function get_domain_entry_status($domain, $item_name)
	{
		$result = $this->dbclient->getAttributes(array(
			'DomainName' => $domain,
			'ItemName'   => $item_name,
			'Attributes' => array(
				'status'
			),
			'ConsistentRead' => true
		));

		foreach ($result["Attributes"] as $key => $attribute)
		{
			if($attribute['Name'] == 'status')
			{
				return $attribute['Value'];
			}
		}

		return false;
	}


	public function check_domain_entry_status($domain, $item_name, $status)
	{
		$domain_entry_status = $this->get_domain_entry_status($domain, $item_name);

		if($domain_entry_status == $status)
		{
			return true;
		}

		return false;
	}

	public function set_domain_entry_status($domain, $item_name, $status)
	{
		$result = $this->dbclient->putAttributes(array(
			'DomainName' => $domain,
			'ItemName'   => $item_name,
			'Attributes' => array(
				array('Name' => 'status', 'Value' => $status, 'Replace' => true),
			)
		));

		return $result;
	}


	public function check_image_status($image_id)
	{
		$result = $this->dbclient->getAttributes(array(
			'DomainName' => UPLOAD_DOMAIN,
			'ItemName'   => $image_id,
			'Attributes' => array(
				'status'
			),
			'ConsistentRead' => true
		));

		return $result['Attributes'][0]['Value'];
	}

	/**
	 * This gets the detials and contents of a domain so they
	 * can be easily sent to a view page.
	 *
	 * details
	 * echo $result['ItemCount'] . "\n"
	 * echo $result['ItemNamesSizeBytes'] . "\n";
	 * echo $result['AttributeNameCount'] . "\n";
	 * echo $result['AttributeNamesSizeBytes'] . "\n";
	 * echo $result['AttributeValueCount'] . "\n";
	 * echo $result['AttributeValuesSizeBytes'] . "\n";
	 * echo $result['Timestamp'] . "\n";
	 *
	 * items
	 *
	 * foreach ($iterator as $item) {
	 *	 echo $item['Name'] . "\n";
	 *	 var_export($item['Attributes']);
	 * }
	 *
	 * @param  string $domain domain name
	 * @return array		 items and descriptions
	 */
	public function get_domain_for_view($domain)
	{
		try
		{
			$details = $this->dbclient->domainMetadata(array('DomainName' => $domain));
			$items = $this->dbclient->getIterator('select', array(
				'SelectExpression' => "select * from " . $domain
			));
		}

		catch (Exception $e)
		{
			//echo 'AWS Error : ' . $e->getMessage();
			return false;
		}

		return array('items' => $items, 'details' => $details);
	}

	public function get_images_from_buket_dir($bucket, $gallery)
	{
		try
		{
			$iterator = $this->s3client->getIterator('ListObjects', array(
				'Bucket' => $bucket,
				"Prefix" => $gallery . "/"
			));
		}

		catch(Exception $e)
		{
			//echo $e->getMessage();
		}

		$images =  array();
		foreach ($iterator as $object)
		{
			var_dump($object['Key']);

			$name_array = explode('/', $object['Key']);
			$num = count($name_array);

			$images[] = $name_array[$num-1];
		}

		if(isset($images[0]))
		{
			unset($images[0]);
		}

		return $images;
	}

	public function update_log($message, $status)
	{
		$random = (string)microtime() . '|' . (string)rand(10000000,99999999);
		$this->dbclient->putAttributes(array(
			'DomainName' => LOG_DOMAIN,
			'ItemName'   => (string)$random,
			'Attributes' => array(
				array('Name' => 'message', 'Value' => $message),
				array('Name' => 'status', 'Value' => $status),
			)
		));
	}

	public function get_image_metadata($image_id)
	{
		$result = $this->dbclient->getAttributes(array(
			'DomainName' => IMAGE_DOMAIN,
			'ItemName'   => (string)$image_id,
			'ConsistentRead' => true
		));
		return $result;

		/*$iterator = $this->dbclient->getIterator('Select', array(
			'SelectExpression' => "select * from " . IMAGE_DOMAIN . " where ItemName = '" . (string)$image_id . "'"
		));
		foreach ($iterator as $item) {
			echo $item['Name'] . "\n";
			var_export($item['Attributes']);
		}*/
	}

	public function get_imageid_from_s3_image($s3_bucket, $s3_file_and_folder)
	{
		$meta = $s3client->headObject(array(
			"Bucket" => $s3_bucket,
			"Key" => $s3_file_and_folder
		));

		foreach($meta->toArray() as $key => $value)
		{
			if($value == 'image_id')
			{
				return $image_id;
			}
		}

		return null;
	}

	/*public function add_image_to_upload_queue($image_folder, $imagename, $image_meta)
	{

		//echo '<br>'; var_dump($image_folder);echo '<br>'; var_dump($imagename);echo '<br>'; var_dump($image_date_modified);echo '<br>';


		// create uniquie image id
		//
		//

		$aws_image_db->



		$aws_simple_image_db()
		// create image ID
		$image_id = array(	'folder' => utf8_encode_all((string)$image_folder),
							'imagename' => utf8_encode_all((string)$imagename),
							'imagedate' => utf8_encode_all((string)$image_meta['file-filemodifydate']),
		 				);

		$image_json = json_encode($image_id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );

		//var_dump($image_json);
		$image_id = urlencode($image_json);
		// end Create image ID




		$state = $this->check_image_status($image_id);


		if(!$state)
		{
			$this->dbclient->putAttributes(array(
				'DomainName' => UPLOAD_DOMAIN,
				'ItemName'   => (string)$image_id,
				'Attributes' => array(
					array('Name' => 'status', 'Value' => 'uploading'),
				)
			));

			$image_meta_array = array();
			foreach ($image_meta as $name => $value)
			{
				$image_meta_array[] = array(
					'Name' => $name, 'Value' => $value
					);
			}

			$this->dbclient->putAttributes(array(
				'DomainName' => IMAGE_DOMAIN,
				'ItemName' => (string)$image_id,
				'Attributes' => $image_meta_array
			));

			return $image_id;
		}
		elseif($state == 'upload_failed')
		{
			return $image_id;
		}
		else
		{
			return false;
		}
	}*/

	public function add_image_to_process_image_queue($image_id)
	{
		$result = $this->sqsclient->createQueue(array('QueueName' => PROCESSING_QUEUE));
		$image_processing_queueUrl = $result->get('QueueUrl');

		$messageId = $this->sqsclient->SendMessage(array(
			'QueueUrl'	=> $image_processing_queueUrl,
			'MessageBody' => $image_id,
		));

		if($messageId)
		{
			return true;
		}

		return false;
	}

	public function add_image_to_image_shop_queue($image_id)
	{
		$result = $this->sqsclient->createQueue(array('QueueName' => PROCESS_COMPLETE_QUEUE));
		$image_processing_queueUrl = $result->get('QueueUrl');

		$messageId = $this->sqsclient->SendMessage(array(
			'QueueUrl'	=> $image_processing_queueUrl,
			'MessageBody' => $image_id,
		));

		if($messageId)
		{
			return true;
		}

		return false;
	}
}








function upload_file_to_s3($s3client, $bucket, $file_location, $destination, $image_id = null)
{
	$file_stream = fopen(UNPROCESSED_UPLOAD_DIR .  $file_location, 'r+');
	$attributes = array(
		'Bucket'	=> $bucket,
		'Key'	   => $destination . '/' . $file_location,
		'Body' 		=> $file_stream,
	);

	if($image_id)
	{
		$attributes['Metadata'] = array( 'image_id' => $image_id);
	}

	$result = $s3client->putObject($attributes);

	return $result;
}

function check_if_image_is_being_uloaded($dbclient, $image_id)
{
	$uploads = $dbclient->select(array(
		'SelectExpression' => "select * from " . UPLOAD_DOMAIN
	));

	foreach ((array)$uploads['Items'] as $upload)
	{
		echo '<pre>';
		var_dump($upload['Name']);
		echo '</pre>';
		if($upload['Name'] == $image_id && $upload['Value'] == 'uploading')
		{
			//return true;
		}

		if($upload['Name'] == $image_id && $upload['Value'] == 'upload_failed')
		{
			//return 'failed';
		}
	}
	die;

	//var_dump('image is not being uploaded');

	return false;
}

function add_folder_to_folder_create_queue($dbclient, $sqsclient, $destination)
{

	if(!folder_is_queued_for_creation($dbclient, $destination))
	{
		$dbclient->putAttributes(array(
			'DomainName' => GALLERY_DOMAIN,
			'ItemName'   => (string)$destination,
			'Attributes' => array(
				array('Name' => 'folder', 'Value' => $destination),
				array('Name' => 'status', 'Value' => 'not added'),
			)
		));

		if(add_to_gallery_create_queue($sqsclient, $destination))
		{
			return true;
		}

		return false;
	}
	else
	{
		return true;
	}
}

function folder_is_queued_for_creation($dbclient, $destination)
{
	$folders = $dbclient->select(array(
		'SelectExpression' => "select * from " . GALLERY_DOMAIN
	));

	foreach ((array)$folders['Items'] as $folder)
	{
		if($folder['Name'] == $destination)
		{
			return true;
		}
	}

	return false;
}

function print_queue($dbclient, $domain)
{
	$items = $dbclient->select(array(
		'SelectExpression' => "select * from " . $domain
	));
}

function add_to_gallery_create_queue($sqsclient, $gallery_name)
{
	$result = $sqsclient->createQueue(array('QueueName' => GALLERY_QUEUE));
	$gallery_create_queueUrl = $result->get('QueueUrl');

	$messageId = $sqsclient->SendMessage(array(
		'QueueUrl'	=> $gallery_create_queueUrl,
		'MessageBody' => $gallery_name,
	));

	if($messageId)
	{
		return true;
	}

	return false;
}


/* End */

function utf8_encode_all($dat) // -- It returns $dat encoded to UTF8
{
	if (is_string($dat)) return utf8_encode($dat);
	if (!is_array($dat)) return $dat;
	$ret = array();
	foreach($dat as $i=>$d) $ret[$i] = utf8_encode_all($d);
	return $ret;
}
/* ....... */

function utf8_decode_all($dat) // -- It returns $dat decoded from UTF8
{
	if (is_string($dat)) return utf8_decode($dat);
	if (!is_array($dat)) return $dat;
	$ret = array();
	foreach($dat as $i=>$d) $ret[$i] = utf8_decode_all($d);
	return $ret;
}

function delete_image($path_to_file)
{
	if(file_exists($path_to_file))
	{
		exec('rm -f ' . $path_to_file);
	}

	if(!file_exists($path_to_file))
	{
		return true;
	}

	return false;
}

function update_log($dbclient, $message, $status)
{
	$random = (string)microtime() . '|' . (string)rand(10000000,99999999);
	$dbclient->putAttributes(array(
		'DomainName' => LOG_DOMAIN,
		'ItemName'   => (string)$random,
		'Attributes' => array(
			array('Name' => 'message', 'Value' => $message),
			array('Name' => 'status', 'Value' => $status),
		)
	));
}



/////////////// USEFUL FUNCTRITONS

function clean_string($string)
{
	// Replaces all spaces with hyphens.
	$string = str_replace(' ', '-', trim($string));
	// Removes special chars.
	$string = preg_replace('/[^A-Za-z0-9\-]/', '', $string);

	// Replaces multiple hyphens with single one.
	return preg_replace('/-+/', '-', $string);
}

function check_meta_data($custom_metadata, $existing_metadata, $all_metadata = null)
{

	if(!isset($custom_metadata) || !is_array($custom_metadata) || count((array)$custom_metadata) < 5)
	{
		return false;
	}

	if(!isset($existing_metadata) || !is_array($existing_metadata) || count((array)$existing_metadata) < 2)
	{
		return false;
	}

	if(!isset($custom_metadata['SourceFile']))
	{
		return false;
	}

	return true;
}

function build_meta_data_for_s3($custom_metadata, $existing_metadata, $all_metadata = null, $allowed_metadata = null)
{
	$useful_xmp_meta = array_merge($custom_metadata, $existing_metadata);

	$all_metadata['XMP'] = array_merge($all_metadata['XMP'], $useful_xmp_meta);
	$combined_meta = $all_metadata;

	$aws_meta = array();
	foreach ($combined_meta as $pre => $meta_group)
	{
		$clean_pre = strtolower($pre);

		if(!is_array($meta_group))
		{
			$aws_meta[$clean_pre] = strtolower($meta_group);
		}
		else
		{
			foreach ($meta_group as $key => $value)
			{
				if(is_array($value))
				{
					$value = serialize($value);
				}
				$clean_value = strtolower($value);
				if(isset($allowed_metadata))
				{
					if(in_array($clean_pre, $allowed_metadata))
					{
						$aws_meta[$clean_pre . '-' . strtolower($key)] = $clean_value;
					}
				}
				else
				{
					$aws_meta[$clean_pre . '-' . strtolower($key)] = $clean_value;
				}

			}
		}
	}

	if(isset($aws_meta['xmp-fixturename']))
	{
		$aws_meta['xmp-fixturename'] = clean_string($aws_meta['xmp-fixturename']);
	}

	if(isset($aws_meta['xmp-eventname']))
	{
		$aws_meta['xmp-eventname'] = clean_string($aws_meta['xmp-eventname']);
	}

	if(isset($aws_meta['xmp-sportname']))
	{
		$aws_meta['xmp-sportname'] = clean_string($aws_meta['xmp-sportname']);
	}

	if(isset($aws_meta['xmp-sectionname']))
	{
		$aws_meta['xmp-sectionname'] = clean_string($aws_meta['xmp-sectionname']);
	}

	return $aws_meta;
}

function build_path_from_meta($custom_metadata)
{
	if(!isset($custom_metadata['file-filename']))
	{
		return false;
	}
	else
	{
		$source = strtolower($custom_metadata['file-filename']);
	}

	if(isset($custom_metadata['xmp-sportname']))
	{
		$sport = clean_string($custom_metadata['xmp-sportname']) . '/';
	}

	if(isset($custom_metadata['xmp-othersportname']))
	{
		$sport = clean_string($custom_metadata['xmp-othersportname']) . '/';
	}

	if(isset($custom_metadata['xmp-eventname']))
	{
		$event = clean_string($custom_metadata['xmp-eventname']) . '/';
	}

	if(isset($custom_metadata['xmp-fixturename']))
	{
		$fixture = clean_string($custom_metadata['xmp-fixturename']) . '/';
	}

	if(isset($custom_metadata['xmp-sectionname']))
	{
		$section = clean_string($custom_metadata['xmp-sectionname']);
	}

	return array( 'destination' => $sport . $event . $fixture . $section, 'filename' => $source);
}

///////// END

?>