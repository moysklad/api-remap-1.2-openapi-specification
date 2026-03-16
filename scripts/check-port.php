<?php
$f = @fsockopen('127.0.0.1', 4010, $errno, $errstr, 10);
if ($f) {
  fclose($f);
  exit(0);
}
exit(1);
