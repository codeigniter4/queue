<?php

declare(strict_types=1);

return [
    'generator' => [
        'className' => [
            'job' => 'Job class name',
        ],
    ],
    'incorrectHandler'        => 'This queue handler is incorrect.',
    'incorrectQueueFormat'    => 'The queue name should consists only lowercase letters or numbers.',
    'tooLongQueueName'        => 'The queue name is too long. It should be no longer than 64 letters.',
    'incorrectJobHandler'     => 'This job name is not defined in the $jobHandlers array.',
    'incorrectPriorityFormat' => 'The priority name should consists only lowercase letters.',
    'tooLongPriorityName'     => 'The priority name is too long. It should be no longer than 64 letters.',
    'incorrectQueuePriority'  => 'This queue has incorrectly defined priority: "{0}" for the queue: "{1}".',
];
