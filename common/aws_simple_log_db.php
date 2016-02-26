<?php

	class AWS_Simple_Log_DB extends aws_simple_db
	{

		public function get_rows($select_condition = null)
		{
			$rows = $this->get_db_rows($this->db_abstracts['LOG'], $select_condition);
			return $rows;
		}

		public function add($state, $message)
		{
			$additional_attributes  = array(
				array('Name' => 'state', 'Value' => $state, 'Replace' => false),
				array('Name' => 'message', 'Value' => $message, 'Replace' => false),
				);

			$this->put_row($this->db_abstracts['LOG'], $additional_attributes);
		}

		public function get_rows_for_view($number, $state = null)
		{
			if($state)
			{
				$select_condition = "state = '" . $state . "'";
			}
			else
			{
				$select_condition = null;
			}

			$rows = $this->get_rows($select_condition);

			$formatted_rows = array();
			
			foreach ($rows as $key => $row)
			{
				$formatted_rows[$key] = array(
							'id' => $row['Attributes'][0]['Value'],
							'message' => $row['Attributes'][1]['Value'],
							'state' => $row['Attributes'][2]['Value'],
					);
				
				if($number != 0)
				{
					if($number == (int)$key)
					{
						break;
					}
				}
			}

			return $formatted_rows;
		}

	}