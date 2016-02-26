<?php

	class AWS_Simple_DB
	{
		private $dbclient;
		protected $db_abstracts;
	
		function __construct($aws)
		{
			$this->dbclient = $aws->get('SimpleDb');
			$this->db_abstracts = array( 
				'LOG' => 'log',
				'IMAGE' => 'image',
				'GALLERY' => 'gallery',
				'UPLOAD' => 'upload',
				);
		}

		public function get_db_rows($abstract_db_name, $select_condition = null)
		{

			// $select_contition shoul be in the following format;
			//  "attribute_name = 'value' and attribute_name2 = 'value2'" 
			if($select_condition)
			{
				$select_expression = array(
					'SelectExpression' => "select * from " . 
						CTE_DOMAIN . " where itr_abstract_db_table = '" . $abstract_db_name . "' and " . 
						$select_condition	
				);
			}
			else
			{
				$select_expression = array(
					'SelectExpression' => "select * from " . 
						CTE_DOMAIN . " where itr_abstract_db_table = '" . $abstract_db_name . "'"
				);
			}

			//var_export($select_expression);

			try
			{
				$iterator = $this->dbclient->getIterator('Select', $select_expression);
			}
			catch(Exception $e)
			{
				echo $e->getMessage();
			}


			//var_export($iterator);

			$rows = $iterator->toArray();

			//var_export($rows);
			return $rows;
		}

		public function get_db_row_by_id($row_id, $consistent = true)
		{
			try
			{				
				$result = $this->dbclient->getAttributes(array(
				    'DomainName' => CTE_DOMAIN,
				    'ItemName'   =>  $row_id,
				    'ConsistentRead' => $consistent
				));
			}
			catch(Exception $e)
			{
				dump('error in get_db_row_by_id() in AWS_Simple_DB');
				echo $e->getMessage();
			}
			
			$result_array = $result->toArray();

			$temp_attributes_array = array();
			foreach($result_array['Attributes'] as $key => $value)
			{
				$temp_attributes_array[$value['Name']] = $value['Value'];
			}

			$attributes_array['Name'] = $row_id;
			$attributes_array['meta_data'] = $temp_attributes_array;

			return $attributes_array;
		}

		public function edit_db_row_by_id($row_id, $additional_attributes, $replace = true)
		{

			$attributes = array();
			
			foreach ($additional_attributes as $key => $value) 
			{
				$attributes[] = array(
					'Name' => $value['Name'], 
					'Value' => (string)$value['Value'], 
					'Replace' => $replace); 
			}

			try
			{
			
				$this->dbclient->putAttributes(array(
				    'DomainName' => CTE_DOMAIN,
				    'ItemName'   =>  $row_id,
				    'Attributes' => $attributes,
				));
			}
			catch(Exception $e)
			{
				dump('error in edit_db_row_by_id() in AWS_Simple_DB');
				echo $e->getMessage();
			}

			return true;
		}

		public function put_row($abstract_db_name, $additional_attributes)
		{

			$attributes  = array();

			$attributes[] = array('Name' => 'itr_abstract_db_table', 'Value' => (string)$abstract_db_name); 

			foreach ($additional_attributes as $key => $value) 
			{
				$attributes[] = array('Name' => $value['Name'], 'Value' => (string)$value['Value'], 'Replace' => (bool)$value['Replace']); 
			}

			$unique_id = str_replace( ' ', '', microtime() . md5(uniqid(rand(), true)));
			$unique_id = str_replace('.', '', $unique_id);
			
			try
			{

				$this->dbclient->putAttributes(array(
					'DomainName' => CTE_DOMAIN,
					'ItemName'   => $unique_id,
					'Attributes' => $attributes,
				));
			}
			catch(Exception $e)
			{
				dump('error in put_row() in AWS_Simple_DB');
				echo $e->getMessage();
			}
			
			return $unique_id;

		}

	}