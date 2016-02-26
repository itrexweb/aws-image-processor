<?php
	/* Begin Bootstrap */
	require '/home/capturetheevent/vendor/autoload.php';

	if(!defined('AWS_ACCESS_KEY_ID'))
		define('AWS_ACCESS_KEY_ID',  'AKIAJPC6SYP34RJSPMLA');

	if(!defined('AWS_SECRET_ACCESS_KEY'))
		define('AWS_SECRET_ACCESS_KEY', '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');


	define('SQS_ENDPOINT', 'http://queue.amazonaws.com');

	/* there can be only one! */
	define('CTE_DOMAIN', 'capturedomain');

	// define('IMAGE_DOMAIN','imagedomain');
	// define('GALLERY_DOMAIN', 'gallerydomain');
	// define('UPLOAD_DOMAIN', 'uploaddomain');
	// define('LOG_DOMAIN', 'logdomain');

	define('PROCESSING_QUEUE', 'image-processing-queue');
	define('GALLERY_QUEUE','gallery-create-queue');
	define('PROCESS_COMPLETE_QUEUE', 'image-process-complete-queue');


	// log states
	define('AWS_LOG_ACTIVE','active');
	define('AWS_LOG_SUCCESS','success');
	define('AWS_LOG_INFO','info');
	define('AWS_LOG_WARNING','warning');
	define('AWS_LOG_ERROR','danger');

	define('S3_UNPROTECTED_BASE', 'https://s3-eu-west-1.amazonaws.com/cte-lowres-unprotected');
	define('S3_PROTECTED_BASE', 'https://s3-eu-west-1.amazonaws.com/cte-highres-protected');

	use Aws\Common\Aws;

	require '/home/capturetheevent/vendor/itrex/common/config.php';

	require 'common/aws_plus.php';
	require 'common/aws_simple_db.php';
	require 'common/aws_simple_gallery_db.php';
	require 'common/aws_simple_upload_db.php';
	require 'common/aws_simple_log_db.php';
	require 'common/aws_simple_image_db.php';

	$aws = Aws::factory(array(
		'key'    => AWS_ACCESS_KEY_ID,
		'secret' => AWS_SECRET_ACCESS_KEY,
		'region' => 'eu-west-1',
	));

	// Get client instances from the service locator by name
	$s3client = $aws->get('s3');
	$ec2client = $aws->get('ec2');
	$dbclient = $aws->get('SimpleDb');
	$sqsclient = $aws->get('Sqs');

	/* push server bucket */
	$bucket = 'cte-highres-protected';
	$public_bucket = 'cte-lowres-unprotected';

	$aws_processor = new AWS_Plus($aws, $bucket, $public_bucket);
	$aws_image_db = new AWS_Simple_Image_DB($aws) ;
	$aws_log_db = new AWS_Simple_Log_DB($aws);
	$aws_upload_db = new AWS_Simple_Upload_DB($aws);
	$aws_gallery_db = new AWS_Simple_Gallery_DB($aws);

/* End Bootstrap */

?>
