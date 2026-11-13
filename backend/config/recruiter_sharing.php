<?php

return [
    'shared_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('RECRUITER_SHARED_EMAILS', 'hire@gmail.com,morgan@gmail.com')),
    ))),
];
