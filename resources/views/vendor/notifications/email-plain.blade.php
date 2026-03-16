<?php

$companyName = config('mail.from.name') ?: config('app.name');

echo $companyName, " - Official Email", "\n\n";

if (! empty($greeting)) {
    echo $greeting, "\n\n";
} else {
    echo $level == 'error' ? 'Attention required,' : 'Hello,', "\n\n";
}

if (! empty($introLines)) {
    echo implode("\n", $introLines), "\n\n";
}

if (isset($actionText)) {
    echo "{$actionText}: {$actionUrl}", "\n\n";
}

if (! empty($outroLines)) {
    echo implode("\n", $outroLines), "\n\n";
}

echo 'Regards,', "\n";
echo $companyName, "\n";
