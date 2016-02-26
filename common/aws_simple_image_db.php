<?php	

	class AWS_Simple_Image_DB extends aws_simple_db
	{

		public function get_images($select_condition = null)
		{
			//var_export($select_condition);
			$rows = $this->get_db_rows($this->db_abstracts['IMAGE'], $select_condition);
			return $rows;
		}

		public function get_image_by_id($image_id)
		{
			$select_condition = "itr_db_image_id = '" . $image_id . "'";
			$image_data = $this->get_images($select_condition);

			if(count($image_data) < 1)
			{
				return null;
			}

			$image_data_array = array();
			foreach ($image_data[0]['Attributes'] as $key => $value) 
			{
				$image_data_array[$value['Name']] = $value['Value'];
			}

			$image['Name'] = $image_data[0]['Name'];
			$image['meta_data'] = $image_data_array;

			return $image;
		}

		public function encode_image_id($image_url, $image_date)
		{
			$image_id = array(
				'image_url' => utf8_encode_all((string)$image_url),
				'image_date' => utf8_encode_all((string)$image_date),
 			);

			$image_id_json = json_encode($image_id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
			$image_id = urlencode($image_id_json);

			return $image_id;
		}

		public function decode_image_id($image_id)
		{
			$image_data = json_decode(urldecode($image_id));
			return (array)$image_data;
		}

		private function set_image_attribute($image_unique_id, $attribute, $value)
		{
			$params = array(
						array(
							'Name' => $attribute,
							'Value' => $value,
							'Replace' => true
							)
					);

			$this->edit_db_row_by_id($image_unique_id, $params);
		}

		public function set_image_in_flight($image_id, $in_flight = true)
		{
			// anti dyslexasdic pain!
			if($in_flight == 'flase')
			{
				$in_flight = false;
			}

			$in_flight = (bool)$in_flight;

			if($in_flight)
			{
				$in_flight = '1';
			}
			else
			{
				$in_flight = '0';
			}
			// get image
			$image_data = $this->get_image_by_id($image_id);
			$image_unique_id = $image_data['Name'];
			// set value
			// 
			if(!$image_unique_id)
			{
				return null;
			}

			$this->set_image_attribute($image_unique_id, 'itr_image_in_flight', (string)$in_flight);
			return $in_flight;
		}

		public function add_image($image_url, $image_meta, $in_flight)
		{
			$additional_attributes = array();

			$image_id = $this->encode_image_id($image_url, $image_meta['file-filemodifydate']);

			$additional_attributes[] = array('Name' => 'itr_db_image_id', 
				'Value' => $image_id, 'Replace' => true );

			$additional_attributes[] = array('Name' => 'itr_db_image_status', 
				'Value' => 'added', 'Replace' => true );

			$additional_attributes[] = array('Name' => 'itr_image_in_flight', 
				'Value' => (bool)$in_flight, 'Replace' => true );

			foreach ($image_meta as $key => $value) 
			{
				$additional_attributes[] = array(
					'Name' => $key, 
					'Value' => $value, 
					'Replace' => true );	
			}

			//var_export($additional_attributes);

			$row_id = $this->put_row($this->db_abstracts['IMAGE'], $additional_attributes);

			return $this->get_db_row_by_id($row_id);
		}

		public function set_status($image_id, $status)
		{
			$image_data = $this->get_image_by_id($image_id);
			$image_unique_id = $image_data['Name'];

			$this->set_image_attribute($image_unique_id, 'itr_db_image_status', $status);
		}

		public function get_image($image_url, $image_meta)
		{
			$image_id = $this->encode_image_id($image_url, $image_meta['file-filemodifydate']);
			$select_condition = "itr_db_image_id = '" . $image_id . "'";
			
			$row = $this->get_images($select_condition);

			// assume there is only one!
			// 
			if(count($row) == 0)
			{
				return null;
			}

			$row = $row[0];

			$image_data['unique_id'] = $row['Name'];		

			foreach ($row['Attributes'] as $key => $value) 
			{
				$image_data['meta_data'][$value['Name']] = $value['Value'];
			}

			return $image_data;
		}

		public function get_status($image_id)
		{
			$image_data = $this->get_image_by_id($image_id);
		
			if(isset($image_data['meta_data']))
			{
				return $image_data['meta_data']['itr_db_image_status'];
			}

			return null;
		}

	}