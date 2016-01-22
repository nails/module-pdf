<?php

return array(
    'services' => array(
        'Pdf' => function () {
            if (class_exists('\App\Pdf\Library\Pdf')) {
                return new \App\Pdf\Library\Pdf();
            } else {
                return new \Nails\Pdf\Library\Pdf();
            }
        }
    )
);
