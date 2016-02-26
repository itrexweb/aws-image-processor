<?php

	class AWS_Simple_Upload_DB extends aws_simple_db
	{

		public function get_rows($select_condition = null)
		{
			$rows = $this->get_db_rows($this->db_abstracts['UPLOAD'], $select_condition);
			return $rows;
		}

	}