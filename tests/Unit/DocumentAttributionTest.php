<?php

it('keeps the shared attribution on every printable document output', function () {
    $projectRoot = dirname(__DIR__, 2);
    $footer = file_get_contents($projectRoot.'/resources/js/components/ui/document-footer.tsx');

    expect($footer)->toContain('Developed by stortechltd.com');

    foreach ([
        'resources/js/pages/billing/receipts/show.tsx',
        'resources/js/pages/nursing/recovery/show.tsx',
    ] as $documentOutput) {
        $source = file_get_contents($projectRoot.'/'.$documentOutput);

        expect($source)
            ->toContain("import { DocumentFooter } from '@/components/ui/document-footer';")
            ->toContain('<DocumentFooter');
    }
});
