<?php

test('private and public media storage roots remain separated', function () {
    expect(config('filesystems.disks.local.root'))
        ->toBe(storage_path('app/private'))
        ->and(config('filesystems.disks.public.root'))
        ->toBe(storage_path('app/public'))
        ->and(config('filesystems.links')[public_path('storage')])
        ->toBe(storage_path('app/public'));
});
