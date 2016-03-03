<?php
	/* Begin Bootstrap */
	require '/home/capturetheevent/vendor/autoload.php';

	/**
	* 
	*/
	class awsConfig 
	{
		
		function __construct($config)
		{
	
			if(!defined('AWS_ACCESS_KEY_ID'))
				define('AWS_ACCESS_KEY_ID',  $config['AWS_ACCESS_KEY_ID']);

			if(!defined('AWS_SECRET_ACCESS_KEY'))
				define('AWS_SECRET_ACCESS_KEY', $config['AWS_SECRET_ACCESS_KEY']);

			// e.g. 'http://queue.amazonaws.com'
			define('SQS_ENDPOINT', $config['SQS_ENDPOINT']);

			/* there can be only one! */
			// e.g. 'capturedomain'
			define('CTE_DOMAIN', $config['CTE_DOMAIN']);

			// define('IMAGE_DOMAIN','imagedomain');
			// define('GALLERY_DOMAIN', 'gallerydomain');
			// define('UPLOAD_DOMAIN', 'uploaddomain');
			// define('LOG_DOMAIN', 'logdomain');

			// e.g. 'image-processing-queue'
			define('PROCESSING_QUEUE', $config['PROCESSING_QUEUE']);
			// e.g. 'gallery-create-queue'
			define('GALLERY_QUEUE', $config['GALLERY_QUEUE']);
			// e.g. 'image-process-complete-queue'
			define('PROCESS_COMPLETE_QUEUE', $config['PROCESS_COMPLETE_QUEUE']);

			// log states
			define('AWS_LOG_ACTIVE','active');
			define('AWS_LOG_SUCCESS','success');
			define('AWS_LOG_INFO','info');
			define('AWS_LOG_WARNING','warning');
			define('AWS_LOG_ERROR','danger');

			'https://s3-eu-west-1.amazonaws.com/cte-lowres-unprotected'
			define('S3_UNPROTECTED_BASE', $config['S3_UNPROTECTED_BASE']);
			'https://s3-eu-west-1.amazonaws.com/cte-highres-protected'
			define('S3_PROTECTED_BASE', $config['S3_PROTECTED_BASE']);

			use Aws\Common\Aws;

			require '/home/capturetheevent/vendor/itrex/common/config.php';

			require 'common/aws_plus.php';
			require 'common/aws_simple_db.php';
			require 'common/aws_simple_gallery_db.php';
			require 'common/aws_simple_upload_db.php';
			require 'common/aws_simple_log_db.php';
			require 'common/aws_simple_image_db.php';

			// e.g. region 'eu-west-1'
			$aws = Aws::factory(array(
				'key'    => AWS_ACCESS_KEY_ID,
				'secret' => AWS_SECRET_ACCESS_KEY,
				'region' => $config['region'],
			));

			// Get client instances from the service locator by name
			$s3client = $aws->get('s3');
			$ec2client = $aws->get('ec2');
			$dbclient = $aws->get('SimpleDb');
			$sqsclient = $aws->get('Sqs');

			$aws_processor = new AWS_Plus($aws, S3_PROTECTED_BASE, S3_UNPROTECTED_BASE);
			$aws_image_db = new AWS_Simple_Image_DB($aws) ;
			$aws_log_db = new AWS_Simple_Log_DB($aws);
			$aws_upload_db = new AWS_Simple_Upload_DB($aws);
			$aws_gallery_db = new AWS_Simple_Gallery_DB($aws);

		/* End Bootstrap */
		}
	}

?>
