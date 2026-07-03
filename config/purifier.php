<?php

return [
    'encoding' => 'UTF-8',
    'finalize' => true,
    'ignoreNonStrings' => false,
    'settings' => [
        'default' => [
            'HTML.Allowed' => 'p,br,strong,em,u,s,h1,h2,h3,ul,ol,li,blockquote,a[href|title],code,pre',
            'AutoFormat.RemoveEmpty' => true,
            'Cache.DefinitionImpl' => null,
        ],
        'lesson' => [
            'HTML.Allowed' => 'p,br,strong,em,u,s,h1,h2,h3,ul,ol,li,blockquote,a[href|title],code,pre',
            'HTML.TargetBlank' => true,
            'AutoFormat.RemoveEmpty' => true,
            'Cache.DefinitionImpl' => null,
        ],
    ],
];
