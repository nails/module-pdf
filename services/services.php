<?php

return [
    'services' => [
        'Pdf' => function () {
            if (class_exists('\App\Pdf\Service\Pdf')) {
                return new \App\Pdf\Service\Pdf();
            } else {
                return new \Nails\Pdf\Service\Pdf();
            }
        },
    ],
];
