<?php

	class AWS_Simple_Gallery_DB extends aws_simple_db
	{

		public function get_rows($select_condition = null)
		{
			$rows = $this->get_db_rows($this->db_abstracts['GALLERY'], $select_condition);
			return $rows;
		}

	}