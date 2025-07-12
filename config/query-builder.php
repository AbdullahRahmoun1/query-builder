<?php

return [
    /**
     * A map of simple aliases to Spatie's internal filter methods.
     * This allows developers to use strings like 'exact' and 'like'
     * in their model configuration.
     */
    'built_in_aliases' => [
        'exact' => 'exact',
        'like' => 'partial',
        'scope' => 'scope',
        'beginsWithStrict' => 'beginsWithStrict',
    ],
];
