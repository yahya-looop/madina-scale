<?php

it('serves staff login only under the backend namespace and preserves the legacy GET alias', function (): void {
    $this->get('/backend/login')->assertOk();
    $this->get('/login')->assertRedirect('/backend/login');
});

it('redirects guests from backend pages to the backend login page', function (): void {
    $this->get('/backend/dashboard')->assertRedirect('/backend/login');
    $this->get('/backend/products')->assertRedirect('/backend/login');
});

it('redirects non-conflicting legacy staff page URLs into the backend namespace', function (): void {
    $this->get('/dashboard')->assertRedirect('/backend/dashboard');
    $this->get('/inventory')->assertRedirect('/backend/inventory');
    $this->get('/orders')->assertRedirect('/backend/orders');
});

it('does not redirect the public products URL to backend product management', function (): void {
    $this->get('/products')->assertOk();
    $this->get('/products/create')->assertRedirect('/backend/products/create');
});
