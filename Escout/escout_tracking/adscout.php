<?php
/**
 * landing page for impression tracking
 */
header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT');    // Date in the past
header ('Cache-Control: no-cache, must-revalidate');  // HTTP/1.1
header ('Pragma: no-cache');                          // HTTP/1.0
header ('P3P: CP="P3PSTR"');
header ('Content-Type: image/gif');
exit( "\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00\x21\xf9\x04\x01\x00\x00\x00\x00\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3b" );
