<?php
global $CFG;
define('SUCCESS_URL', $CFG->wwwroot . '/payment/gateway/unisbg/checkout.php?status=1');
define('ERROR_URL', $CFG->wwwroot . '/payment/gateway/unisbg/checkout.php?status=1');
define('FEEDBACK_URL', $CFG->wwwroot . '/webservice/rest/server.php');
